<?php

namespace App\Services\Workflow;

use App\Models\DpaOpd;
use App\Models\Lkjip;
use App\Models\PerjanjianKinerja;
use App\Models\RealisasiKinerja;
use App\Models\RencanaAksi;
use App\Models\RenjaOpd;
use App\Models\RenstraOpd;
use App\Models\RkaOpd;
use App\Models\Rkpd;
use App\Models\Rpjmd;
use App\Models\User;
use App\Models\WorkflowHistory;
use App\Models\WorkflowSubmission;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class DocumentCorrectionService
{
    private const BLOCKING_STATUSES = ['submitted', 'verified', 'approved', 'locked'];

    private const ADJUSTABLE_STATUSES = ['draft', 'revision', 'rejected'];

    public function ensureCorrectedSourceIsOfficial(Model $model, string $module): void
    {
        $submission = WorkflowSubmission::query()
            ->where('related_table', $model->getTable())
            ->where('related_id', (int) $model->getKey())
            ->where('module', $module)
            ->first();
        $source = $submission?->metadata['source_correction'] ?? null;

        if (! is_array($source) || empty($source['module']) || empty($source['related_id'])) {
            return;
        }

        $sourceClass = match ((string) $source['module']) {
            'rpjmd' => Rpjmd::class,
            'rkpd' => Rkpd::class,
            'renstra_opd' => RenstraOpd::class,
            'renja_opd' => RenjaOpd::class,
            'rka_opd' => RkaOpd::class,
            'dpa_opd' => DpaOpd::class,
            'perjanjian_kinerja' => PerjanjianKinerja::class,
            'rencana_aksi' => RencanaAksi::class,
            'realisasi_kinerja' => RealisasiKinerja::class,
            'lkjip' => Lkjip::class,
            default => null,
        };
        $sourceModel = $sourceClass ? $sourceClass::query()->find((int) $source['related_id']) : null;

        if (! $sourceModel || ! in_array((string) $sourceModel->getAttribute('status'), ['approved', 'locked'], true)) {
            throw ValidationException::withMessages([
                'action' => 'Dokumen belum dapat diajukan karena dokumen acuannya masih dalam proses koreksi. '
                    .'Selesaikan dan setujui kembali dokumen acuan terlebih dahulu.',
            ]);
        }
    }

    /**
     * Validate downstream documents and mark editable descendants for alignment.
     *
     * @return array{correction_reference: string, affected_documents: array<int, array<string, mixed>>}
     */
    public function prepare(Model $source, string $module, User $actor, string $reason, string $reference): array
    {
        $dependents = $this->allDependents($source);
        $blockers = $dependents
            ->filter(fn (Model $model) => in_array((string) $model->getAttribute('status'), self::BLOCKING_STATUSES, true))
            ->values();

        if ($blockers->isNotEmpty()) {
            $documents = $blockers
                ->take(8)
                ->map(fn (Model $model) => $this->description($model))
                ->implode('; ');
            $suffix = $blockers->count() > 8 ? '; dan '.($blockers->count() - 8).' dokumen lainnya' : '';

            throw ValidationException::withMessages([
                'action' => 'Koreksi belum dapat dilakukan karena dokumen turunan masih diproses atau sudah resmi: '
                    .$documents.$suffix
                    .'. Koreksi atau tarik dokumen turunan dari level paling bawah terlebih dahulu.',
            ]);
        }

        $affected = [];

        foreach ($dependents as $dependent) {
            $oldStatus = (string) ($dependent->getAttribute('status') ?? 'draft');
            if (! in_array($oldStatus, self::ADJUSTABLE_STATUSES, true)) {
                continue;
            }

            $dependentModule = $this->moduleFor($dependent);
            if ($dependentModule === null) {
                continue;
            }

            $metadata = [
                'source_correction' => [
                    'module' => $module,
                    'related_table' => $source->getTable(),
                    'related_id' => (int) $source->getKey(),
                    'reason' => $reason,
                    'reference' => $reference,
                ],
            ];

            $dependent->forceFill(['status' => 'revision']);
            if ($dependent->isFillable('submitted_by')) {
                $dependent->forceFill(['submitted_by' => null, 'submitted_at' => null]);
            }
            $dependent->save();

            $existingSubmission = WorkflowSubmission::query()
                ->where('related_table', $dependent->getTable())
                ->where('related_id', (int) $dependent->getKey())
                ->where('module', $dependentModule)
                ->first();

            $submission = WorkflowSubmission::updateOrCreate([
                'related_table' => $dependent->getTable(),
                'related_id' => (int) $dependent->getKey(),
                'module' => $dependentModule,
            ], [
                'status' => 'revision',
                'submitted_by' => $existingSubmission?->submitted_by,
                'current_reviewer_id' => null,
                'submitted_at' => $existingSubmission?->submitted_at,
                'reviewed_at' => now(),
                'note' => 'Perlu disesuaikan karena dokumen acuan sedang dikoreksi: '.$reason,
                'metadata' => array_merge($existingSubmission?->metadata ?? [], $metadata),
            ]);

            WorkflowHistory::create([
                'workflow_submission_id' => $submission->id,
                'related_table' => $dependent->getTable(),
                'related_id' => (int) $dependent->getKey(),
                'module' => $dependentModule,
                'from_status' => $oldStatus,
                'to_status' => 'revision',
                'action' => 'source_correction',
                'actor_id' => $actor->id,
                'reviewer_id' => null,
                'notes' => 'Dokumen perlu disesuaikan dengan koreksi data pada dokumen acuan.',
                'metadata' => $metadata,
            ]);

            $affected[] = [
                'module' => $dependentModule,
                'related_table' => $dependent->getTable(),
                'related_id' => (int) $dependent->getKey(),
                'previous_status' => $oldStatus,
            ];
        }

        return [
            'correction_reference' => $reference,
            'affected_documents' => $affected,
        ];
    }

    /** @return Collection<int, Model> */
    private function allDependents(Model $source): Collection
    {
        $found = collect();
        $queue = $this->directDependents($source)->values();

        while ($queue->isNotEmpty()) {
            /** @var Model $dependent */
            $dependent = $queue->shift();
            $key = $dependent->getTable().':'.$dependent->getKey();

            if ($found->has($key)) {
                continue;
            }

            $children = $this->directDependents($dependent);
            if (method_exists($dependent, 'isArchivedVersion') && $dependent->isArchivedVersion()) {
                $queue = $queue->concat($children)->values();

                continue;
            }

            $found->put($key, $dependent);
            $queue = $queue->concat($children)->values();
        }

        return $found->values();
    }

    /** @return Collection<int, Model> */
    private function directDependents(Model $source): Collection
    {
        return match (true) {
            $source instanceof Rpjmd => collect()
                ->concat(RenstraOpd::query()->where('rpjmd_id', $source->id)->get())
                ->concat(Rkpd::query()->where('rpjmd_id', $source->id)->get()),
            $source instanceof RenstraOpd => collect()
                ->concat(RenjaOpd::query()->where('renstra_opd_id', $source->id)->get())
                ->concat(PerjanjianKinerja::query()->where('renstra_opd_id', $source->id)->get()),
            $source instanceof Rkpd => collect()
                ->concat(RenjaOpd::query()->where('rkpd_id', $source->id)->get())
                ->concat(RkaOpd::query()->where('rkpd_id', $source->id)->get())
                ->concat(DpaOpd::query()->where('rkpd_id', $source->id)->get()),
            $source instanceof RenjaOpd => collect()
                ->concat(RkaOpd::query()->where('renja_opd_id', $source->id)->get())
                ->concat(DpaOpd::query()->where('renja_opd_id', $source->id)->get()),
            $source instanceof RkaOpd => DpaOpd::query()->where('rka_opd_id', $source->id)->get(),
            $source instanceof PerjanjianKinerja => collect()
                ->concat(RencanaAksi::query()->where('perjanjian_kinerja_id', $source->id)->get())
                ->concat(RealisasiKinerja::query()->where('perjanjian_kinerja_id', $source->id)->get())
                ->concat(Lkjip::query()->where('perjanjian_kinerja_id', $source->id)->get()),
            $source instanceof RencanaAksi => RealisasiKinerja::query()->where('rencana_aksi_id', $source->id)->get(),
            $source instanceof RealisasiKinerja => Lkjip::query()->where('realisasi_kinerja_id', $source->id)->get(),
            default => collect(),
        };
    }

    private function moduleFor(Model $model): ?string
    {
        return match (true) {
            $model instanceof Rpjmd => 'rpjmd',
            $model instanceof Rkpd => 'rkpd',
            $model instanceof RenstraOpd => 'renstra_opd',
            $model instanceof RenjaOpd => 'renja_opd',
            $model instanceof RkaOpd => 'rka_opd',
            $model instanceof DpaOpd => 'dpa_opd',
            $model instanceof PerjanjianKinerja => 'perjanjian_kinerja',
            $model instanceof RencanaAksi => 'rencana_aksi',
            $model instanceof RealisasiKinerja => 'realisasi_kinerja',
            $model instanceof Lkjip => 'lkjip',
            default => null,
        };
    }

    private function description(Model $model): string
    {
        $label = match (true) {
            $model instanceof Rpjmd => 'RPJMD',
            $model instanceof Rkpd => 'RKPD',
            $model instanceof RenstraOpd => 'RENSTRA',
            $model instanceof RenjaOpd => 'RENJA',
            $model instanceof RkaOpd => 'RKA',
            $model instanceof DpaOpd => 'DPA',
            $model instanceof PerjanjianKinerja => 'PK',
            $model instanceof RencanaAksi => 'Rencana Aksi',
            $model instanceof RealisasiKinerja => 'Realisasi Kinerja',
            $model instanceof Lkjip => 'LKjIP',
            default => class_basename($model),
        };
        $title = $model->getAttribute('judul') ?: '#'.$model->getKey();
        $status = match ((string) $model->getAttribute('status')) {
            'submitted' => 'Diajukan',
            'verified' => 'Terverifikasi',
            'approved' => 'Disetujui',
            'locked' => 'Terkunci',
            default => (string) $model->getAttribute('status'),
        };

        return $label.' “'.$title.'” ('.$status.')';
    }
}
