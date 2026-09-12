<?php

namespace App\Services\Kinerja;

use App\Models\DpaOpdItem;
use App\Models\OpdKegiatan;
use App\Models\OpdProgram;
use App\Models\OpdSubKegiatan;
use App\Models\PerjanjianKinerja;
use App\Models\PerjanjianKinerjaItem;
use App\Models\RencanaAksi;
use App\Models\SasaranOpd;
use App\Models\TujuanOpd;
use App\Services\Perencanaan\RenjaAnnualTargetService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RencanaAksiSnapshotService
{
    private const OFFICIAL_STATUSES = ['approved', 'locked'];

    public function __construct(private readonly RenjaAnnualTargetService $renjaAnnualTargetService) {}

    /**
     * @return array{ready: bool, issues: array<int, string>, warnings: array<int, string>, counts: array<string, int>}
     */
    public function inspect(PerjanjianKinerja $pk): array
    {
        $context = $this->context($pk);

        return [
            'ready' => $context['issues'] === [],
            'issues' => $context['issues'],
            'warnings' => $context['warnings'],
            'counts' => [
                'tujuan' => $context['goals']->count(),
                'sasaran' => $context['objectives']->count(),
                'program' => $context['programs']->count(),
                'kegiatan' => $context['activities']->count(),
                'sub_kegiatan' => $context['subActivities']->count(),
                'baris' => $context['rowCount'],
            ],
        ];
    }

    public function ensureReady(PerjanjianKinerja $pk): void
    {
        $result = $this->inspect($pk);

        if (! $result['ready']) {
            throw ValidationException::withMessages([
                'perjanjian_kinerja_id' => implode(' ', $result['issues']),
            ]);
        }
    }

    public function populate(RencanaAksi $rencanaAksi, PerjanjianKinerja $pk): void
    {
        $context = $this->context($pk);
        if ($context['issues'] !== []) {
            throw ValidationException::withMessages([
                'perjanjian_kinerja_id' => implode(' ', $context['issues']),
            ]);
        }

        DB::transaction(function () use ($rencanaAksi, $pk, $context): void {
            $rencanaAksi->items()->forceDelete();
            $rencanaAksi->forceFill([
                'perjanjian_kinerja_id' => $pk->id,
                'renstra_opd_id' => $pk->renstra_opd_id,
                'dpa_opd_id' => $pk->dpa_opd_id,
                'snapshot_dibuat_pada' => now(),
                'format_version' => 2,
            ])->save();

            $order = 1;
            $goalParents = [];
            $objectiveParents = [];
            $programParents = [];
            $activityParents = [];

            foreach ($context['goals'] as $goal) {
                $goalParents[$goal->id] = $this->storeIndicatorRows(
                    $rencanaAksi,
                    'tujuan_opd',
                    $goal,
                    $goal->tujuan,
                    $goal->indikator,
                    null,
                    $order,
                    $pk,
                    $context,
                );

                foreach ($context['objectives']->where('tujuan_opd_id', $goal->id) as $objective) {
                    $objectiveParents[$objective->id] = $this->storeIndicatorRows(
                        $rencanaAksi,
                        'sasaran_opd',
                        $objective,
                        $objective->sasaran,
                        $objective->indikator,
                        $goalParents[$goal->id],
                        $order,
                        $pk,
                        $context,
                    );

                    foreach ($context['programs']->where('sasaran_opd_id', $objective->id) as $program) {
                        $programParents[$program->id] = $this->storeIndicatorRows(
                            $rencanaAksi,
                            'program_opd',
                            $program,
                            $program->nama,
                            $program->indikator,
                            $objectiveParents[$objective->id],
                            $order,
                            $pk,
                            $context,
                        );

                        foreach ($context['activities']->where('opd_program_id', $program->id) as $activity) {
                            $activityParents[$activity->id] = $this->storeIndicatorRows(
                                $rencanaAksi,
                                'kegiatan_opd',
                                $activity,
                                $activity->nama,
                                $activity->indikator,
                                $programParents[$program->id],
                                $order,
                                $pk,
                                $context,
                            );

                            foreach ($context['subActivities']->where('opd_kegiatan_id', $activity->id) as $subActivity) {
                                $this->storeIndicatorRows(
                                    $rencanaAksi,
                                    'sub_kegiatan_opd',
                                    $subActivity,
                                    $subActivity->nama,
                                    $subActivity->indikator,
                                    $activityParents[$activity->id],
                                    $order,
                                    $pk,
                                    $context,
                                );
                            }
                        }
                    }
                }
            }
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function context(PerjanjianKinerja $pk): array
    {
        $issues = [];
        $warnings = [];
        $empty = collect();

        $pk->loadMissing(['renstraOpd', 'dpaOpd', 'items', 'programs']);
        $renstra = $pk->renstraOpd;
        $dpa = $pk->dpaOpd;

        if ($pk->level_pk !== 'kepala_opd' || $pk->tipe_pk !== 'cascading') {
            $issues[] = 'Gunakan PK Kepala OPD bertipe cascading sebagai sumber Rencana Aksi.';
        }
        if (! in_array($pk->status, self::OFFICIAL_STATUSES, true)) {
            $issues[] = 'PK Kepala OPD harus sudah disetujui atau dikunci.';
        }
        if (! $renstra
            || (int) $renstra->opd_id !== (int) $pk->opd_id
            || ! in_array($renstra->status, self::OFFICIAL_STATUSES, true)
            || (int) $pk->tahun < (int) $renstra->tahun_awal
            || (int) $pk->tahun > (int) $renstra->tahun_akhir) {
            $issues[] = 'PK belum terhubung ke RENSTRA OPD yang sesuai.';
        }
        if (! $dpa
            || (int) $dpa->opd_id !== (int) $pk->opd_id
            || (int) $dpa->tahun !== (int) $pk->tahun
            || (int) $dpa->periode_tahun_id !== (int) $pk->periode_tahun_id
            || ! in_array($dpa->status, self::OFFICIAL_STATUSES, true)) {
            $issues[] = 'PK belum terhubung ke DPA/DPPA resmi pada OPD dan tahun yang sama.';
        }
        if (! $renstra || ! $dpa) {
            return compact('issues', 'warnings') + [
                'goals' => $empty, 'objectives' => $empty, 'programs' => $empty,
                'activities' => $empty, 'subActivities' => $empty, 'dpaGroups' => $empty,
                'pkItems' => $empty, 'pkPrograms' => $empty, 'annualTargets' => $empty,
                'useAnnualTargets' => false, 'rowCount' => 0,
            ];
        }
        if ((int) $dpa->renja_opd_id !== (int) $pk->renja_opd_id) {
            $issues[] = 'DPA/DPPA pada PK tidak berasal dari RENJA yang sama.';
        }
        if (! $dpa->renjaOpd()->where('renstra_opd_id', $renstra->id)->exists()) {
            $issues[] = 'DPA/DPPA pada PK tidak terhubung ke RENSTRA sumber PK.';
        }

        $useAnnualTargets = $this->renjaAnnualTargetService->available();
        $annualTargets = collect();
        $renja = $dpa->renjaOpd;
        if ($useAnnualTargets && $renja) {
            $this->renjaAnnualTargetService->bootstrap(
                $renja,
                $renja->isOfficialVersion() ? 'legacy_backfill' : 'renstra_initial',
            );
            $annualTargets = $renja->annualTargets()
                ->get()
                ->keyBy(fn ($target) => $target->indicator_type.':'.$target->indicator_id);
        }

        $allSubActivities = OpdSubKegiatan::query()
            ->whereHas('kegiatan.program', fn ($query) => $query->where('renstra_opd_id', $renstra->id))
            ->with(['kegiatan.program.sasaran.tujuan', 'opdUnit:id,nama'])
            ->get();
        $byMaster = $allSubActivities->filter(fn ($item) => $item->sub_kegiatan_pemerintahan_id)->groupBy('sub_kegiatan_pemerintahan_id');
        $byCode = $allSubActivities->filter(fn ($item) => filled($item->kode))->groupBy(fn ($item) => $this->normalize($item->kode));

        $dpaRows = DpaOpdItem::query()
            ->where('dpa_opd_id', $dpa->id)
            ->with('rkaOpdItem.renjaOpdItem.subKegiatanRenstra.kegiatan.program')
            ->orderBy('urutan')->orderBy('id')->get();
        if ($dpaRows->isEmpty()) {
            $issues[] = 'DPA/DPPA belum memiliki sub kegiatan.';
        }

        $mapped = collect();
        $unmapped = [];
        foreach ($dpaRows as $dpaRow) {
            $subActivity = $dpaRow->rkaOpdItem?->renjaOpdItem?->subKegiatanRenstra;
            if ($subActivity?->kegiatan?->program?->renstra_opd_id !== $renstra->id) {
                $subActivity = null;
            }
            if (! $subActivity && $dpaRow->sub_kegiatan_pemerintahan_id) {
                $candidates = $byMaster->get($dpaRow->sub_kegiatan_pemerintahan_id, collect());
                $subActivity = $candidates->count() === 1 ? $candidates->first() : null;
            }
            if (! $subActivity && filled($dpaRow->kode_sub_kegiatan)) {
                $candidates = $byCode->get($this->normalize($dpaRow->kode_sub_kegiatan), collect());
                $subActivity = $candidates->count() === 1 ? $candidates->first() : null;
            }
            if (! $subActivity) {
                $unmapped[] = $dpaRow->kode_sub_kegiatan ?: $dpaRow->nama_sub_kegiatan ?: "item #{$dpaRow->id}";

                continue;
            }
            $mapped->push(['sub_activity' => $subActivity, 'dpa_item' => $dpaRow]);
        }
        if ($unmapped !== []) {
            $issues[] = 'Ada sub kegiatan DPA/DPPA yang tidak dapat dipetakan ke RENSTRA: '.implode(', ', array_slice(array_unique($unmapped), 0, 5)).'.';
        }

        $dpaGroups = $mapped->groupBy(fn ($entry) => (int) $entry['sub_activity']->id);
        $subIds = $dpaGroups->keys()->map(fn ($id) => (int) $id);
        $subActivities = $allSubActivities->whereIn('id', $subIds)->sortBy(fn ($item) => $this->hierarchyOrder($item))->values();
        if ($subActivities->contains(fn ($item) => ! $item->kegiatan || ! $item->kegiatan->program || ! $item->kegiatan->program->sasaran || ! $item->kegiatan->program->sasaran->tujuan)) {
            $issues[] = 'Ada sub kegiatan DPA/DPPA yang rantai Kegiatan, Program, Sasaran Strategis, atau Tujuan pada RENSTRA belum lengkap.';
        }
        $activityIds = $subActivities->pluck('opd_kegiatan_id')->unique();
        $activities = OpdKegiatan::query()
            ->whereIn('id', $activityIds)
            ->with(['program.sasaran.tujuan', 'indikator' => fn ($query) => $query->with([
                'satuanIndikator:id,nama',
                'targets' => fn ($targetQuery) => $targetQuery->where('periode_tahun_id', $pk->periode_tahun_id),
            ])])
            ->get()->sortBy(fn ($item) => $this->hierarchyOrder($item))->values();
        $programIds = $activities->pluck('opd_program_id')->unique();
        $programs = OpdProgram::query()
            ->whereIn('id', $programIds)
            ->with(['sasaran.tujuan', 'indikator' => fn ($query) => $query->with([
                'satuanIndikator:id,nama',
                'targets' => fn ($targetQuery) => $targetQuery->where('periode_tahun_id', $pk->periode_tahun_id),
            ])])
            ->get()->sortBy(fn ($item) => $this->hierarchyOrder($item))->values();

        $goals = TujuanOpd::query()->where('renstra_opd_id', $renstra->id)
            ->with(['indikator' => fn ($query) => $query->with('satuanIndikator:id,nama')])
            ->orderBy('urutan')->orderBy('id')->get();
        $objectives = SasaranOpd::query()->whereHas('tujuan', fn ($query) => $query->where('renstra_opd_id', $renstra->id))
            ->with(['tujuan:id,urutan', 'indikator' => fn ($query) => $query->with('satuanIndikator:id,nama')])
            ->get()->sortBy(fn ($item) => sprintf('%06d-%06d-%010d', $item->tujuan?->urutan ?? 0, $item->urutan, $item->id))->values();
        if ($goals->isEmpty() || $objectives->isEmpty()) {
            $issues[] = 'RENSTRA sumber belum memiliki Tujuan dan Sasaran Strategis OPD yang lengkap.';
        }
        $subActivities->load(['indikator' => fn ($query) => $query->with([
            'satuanIndikator:id,nama',
            'targets' => fn ($targetQuery) => $targetQuery->where('periode_tahun_id', $pk->periode_tahun_id),
        ])]);

        $pkItems = $pk->items->keyBy(fn (PerjanjianKinerjaItem $item) => $item->cascading_source_type.':'.$item->cascading_source_id);
        $pkPrograms = $pk->programs->filter(fn ($program) => $program->opd_program_id)->keyBy('opd_program_id');
        $pkProgramsByCode = $pk->programs->filter(fn ($program) => filled($program->kode))->keyBy(fn ($program) => $this->normalize($program->kode));

        foreach ($goals->concat($objectives) as $node) {
            if ($node->indikator->isEmpty()) {
                $issues[] = 'Tujuan/Sasaran Strategis RENSTRA belum memiliki indikator.';
                break;
            }
            foreach ($node->indikator as $indicator) {
                if (! $pkItems->has($indicator->getTable().':'.$indicator->id)) {
                    $issues[] = 'PK Kepala OPD belum memuat seluruh indikator Tujuan dan Sasaran Strategis dari RENSTRA.';
                    break 2;
                }
            }
        }
        if ($programs->contains(fn ($program) => ! $pkPrograms->has($program->id) && ! $pkProgramsByCode->has($this->normalize($program->kode)))) {
            $issues[] = 'PK Kepala OPD belum memuat seluruh program yang terdapat pada DPA/DPPA.';
        }
        foreach ($programs->concat($activities)->concat($subActivities) as $node) {
            if ($node->indikator->isEmpty()) {
                $issues[] = 'Masih ada program, kegiatan, atau sub kegiatan DPA/DPPA yang belum memiliki indikator pada RENSTRA.';
                break;
            }
        }
        foreach ($programs->concat($activities) as $node) {
            foreach ($node->indikator as $indicator) {
                $target = $indicator->targets->first();
                $annualTarget = $annualTargets->get($indicator->getTable().':'.$indicator->id);
                $missing = $useAnnualTargets
                    ? ! $annualTarget || ($annualTarget->target_renja === null && blank($annualTarget->target_renja_text))
                    : ! $target || ($target->target === null && blank($target->target_text));
                if ($missing) {
                    $issues[] = $useAnnualTargets
                        ? 'Target tahunan indikator program/kegiatan pada RENJA sumber PK belum lengkap.'
                        : 'Target tahunan indikator program/kegiatan pada RENSTRA belum lengkap untuk tahun PK.';
                    break 2;
                }
            }
        }
        foreach ($subActivities as $subActivity) {
            $entries = $dpaGroups->get($subActivity->id, collect());
            foreach ($subActivity->indikator as $indicator) {
                $directDpaTarget = $entries
                    ->filter(fn ($entry) => (int) ($entry['dpa_item']->rkaOpdItem?->renjaOpdItem?->indikator_sub_kegiatan_id ?? 0) === (int) $indicator->id)
                    ->pluck('dpa_item.target_kinerja')
                    ->first(fn ($value) => filled($value));
                $fallbackDpaTarget = $subActivity->indikator->count() === 1
                    ? $entries->pluck('dpa_item.target_kinerja')->first(fn ($value) => filled($value))
                    : null;
                if (blank($directDpaTarget) && blank($fallbackDpaTarget)) {
                    $target = $indicator->targets->first();
                    if (! $target || ($target->target === null && blank($target->target_text))) {
                        $issues[] = "Target indikator sub kegiatan {$subActivity->kode} belum tersedia pada DPA/DPPA maupun RENSTRA.";
                        break 2;
                    }
                }
            }
        }

        $allIndicators = $goals->pluck('indikator')->flatten()
            ->concat($objectives->pluck('indikator')->flatten())
            ->concat($programs->pluck('indikator')->flatten())
            ->concat($activities->pluck('indikator')->flatten())
            ->concat($subActivities->pluck('indikator')->flatten());
        if ($allIndicators->contains(fn ($indicator) => blank($indicator->formulasi_pengukuran) && blank($indicator->formula))) {
            $warnings[] = 'Sebagian formula belum diisi di RENSTRA dan perlu dilengkapi pada matriks Rencana Aksi.';
        }

        return compact(
            'issues', 'warnings', 'goals', 'objectives', 'programs', 'activities',
            'subActivities', 'dpaGroups', 'pkItems', 'pkPrograms', 'pkProgramsByCode',
            'annualTargets', 'useAnnualTargets'
        ) + ['rowCount' => $allIndicators->count()];
    }

    private function storeIndicatorRows(
        RencanaAksi $rencanaAksi,
        string $level,
        Model $node,
        string $description,
        Collection $indicators,
        ?int $parentId,
        int &$order,
        PerjanjianKinerja $pk,
        array $context,
    ): ?int {
        $firstId = null;
        foreach ($indicators as $indicator) {
            [$target, $targetText, $budget] = $this->annualValues($level, $node, $indicator, $context);
            $responsible = $indicator->pd_penanggung_jawab
                ?: ($level === 'sub_kegiatan_opd' ? $node->opdUnit?->nama : null)
                ?: $pk->unit_kerja_snapshot
                ?: $pk->opd?->nama;
            $pkItem = $context['pkItems']->get($indicator->getTable().':'.$indicator->id);

            $sourceFormula = $indicator->formulasi_pengukuran ?: $indicator->formula;
            $row = $rencanaAksi->items()->create([
                'parent_id' => $parentId,
                'source_type' => $indicator->getTable(),
                'source_id' => $indicator->id,
                'level' => $level,
                'kode_snapshot' => $node->kode ?? $indicator->kode,
                'uraian_snapshot' => $description,
                'perjanjian_kinerja_item_id' => $pkItem?->id,
                'opd_program_id' => $level === 'program_opd' ? $node->id : ($node->opd_program_id ?? $node->kegiatan?->opd_program_id),
                'opd_kegiatan_id' => $level === 'kegiatan_opd' ? $node->id : ($node->opd_kegiatan_id ?? null),
                'opd_sub_kegiatan_id' => $level === 'sub_kegiatan_opd' ? $node->id : null,
                'periode_realisasi' => 'triwulan',
                'aksi' => $description,
                'indikator' => $indicator->indikator,
                'formula_snapshot' => $sourceFormula,
                'formula' => $sourceFormula,
                'satuan_snapshot' => $indicator->satuanIndikator?->nama,
                'tipe_perhitungan_snapshot' => $indicator->tipe_perhitungan,
                'is_snapshot' => true,
                'target' => $target,
                'target_text' => $targetText,
                'anggaran' => $budget,
                'penanggung_jawab' => $responsible,
                'status' => 'draft',
                'urutan' => $order++,
            ]);
            foreach (range(1, 4) as $quarter) {
                $row->targetTriwulan()->create(['triwulan' => $quarter]);
            }
            $firstId ??= $row->id;
        }

        return $firstId;
    }

    /** @return array{0: mixed, 1: ?string, 2: float} */
    private function annualValues(string $level, Model $node, Model $indicator, array $context): array
    {
        if (in_array($level, ['tujuan_opd', 'sasaran_opd'], true)) {
            $pkItem = $context['pkItems']->get($indicator->getTable().':'.$indicator->id);

            return [$pkItem?->target, $pkItem?->target_text, 0.0];
        }

        if ($level === 'sub_kegiatan_opd') {
            $entries = $context['dpaGroups']->get($node->id, collect());
            $dpaRows = $entries->pluck('dpa_item');
            $dpaTarget = $entries
                ->filter(fn ($entry) => (int) ($entry['dpa_item']->rkaOpdItem?->renjaOpdItem?->indikator_sub_kegiatan_id ?? 0) === (int) $indicator->id)
                ->pluck('dpa_item.target_kinerja')
                ->first(fn ($value) => filled($value));
            if (blank($dpaTarget) && $node->indikator->count() === 1) {
                $dpaTarget = $dpaRows->pluck('target_kinerja')->first(fn ($value) => filled($value));
            }
            $target = $indicator->targets->first();

            return [
                $this->numericValue($dpaTarget) ?? $target?->target,
                $this->numericValue($dpaTarget) === null && filled($dpaTarget) ? (string) $dpaTarget : $target?->target_text,
                (float) $dpaRows->sum(fn ($row) => (float) ($row->pagu_dpa ?? 0)),
            ];
        }

        $target = $indicator->targets->first();
        $annualTarget = $context['annualTargets']->get($indicator->getTable().':'.$indicator->id);
        $targetNumber = $context['useAnnualTargets'] ? $annualTarget?->target_renja : $target?->target;
        $targetText = $context['useAnnualTargets'] ? $annualTarget?->target_renja_text : $target?->target_text;
        if ($level === 'program_opd') {
            $pkProgram = $context['pkPrograms']->get($node->id)
                ?: $context['pkProgramsByCode']->get($this->normalize($node->kode));
            $budget = (float) ($pkProgram?->anggaran ?? 0);
        } else {
            $subIds = $context['subActivities']->where('opd_kegiatan_id', $node->id)->pluck('id');
            $budget = (float) $subIds->sum(fn ($subId) => $context['dpaGroups']->get($subId, collect())->sum(fn ($entry) => (float) ($entry['dpa_item']->pagu_dpa ?? 0)));
        }

        return [$targetNumber, $targetText, $budget];
    }

    private function hierarchyOrder(Model $model): string
    {
        $program = $model instanceof OpdProgram ? $model : ($model instanceof OpdKegiatan ? $model->program : $model->kegiatan?->program);
        $activity = $model instanceof OpdKegiatan ? $model : ($model instanceof OpdSubKegiatan ? $model->kegiatan : null);
        $objective = $program?->sasaran;
        $goal = $objective?->tujuan;

        return sprintf(
            '%06d-%06d-%06d-%06d-%06d-%010d',
            $goal?->urutan ?? 0,
            $objective?->urutan ?? 0,
            $program?->urutan ?? 0,
            $activity?->urutan ?? 0,
            $model->urutan ?? 0,
            $model->id,
        );
    }

    private function normalize(mixed $value): string
    {
        return mb_strtolower(preg_replace('/\s+/', ' ', trim((string) $value)) ?? '');
    }

    private function numericValue(mixed $value): ?float
    {
        if ($value === null || $value === '' || ! is_numeric($value)) {
            return null;
        }

        return (float) $value;
    }
}
