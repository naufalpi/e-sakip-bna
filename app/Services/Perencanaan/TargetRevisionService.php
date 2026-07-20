<?php

namespace App\Services\Perencanaan;

use App\Models\Dokumen;
use App\Models\IndikatorOpdProgram;
use App\Models\IndikatorProgramRpjmd;
use App\Models\IndikatorSasaranDaerah;
use App\Models\IndikatorSasaranOpd;
use App\Models\IndikatorSubKegiatan;
use App\Models\IndikatorTujuanDaerah;
use App\Models\IndikatorTujuanOpd;
use App\Models\RenstraOpd;
use App\Models\Rpjmd;
use App\Models\TargetIndikatorOpdProgram;
use App\Models\TargetIndikatorProgramRpjmd;
use App\Models\TargetIndikatorSasaranDaerah;
use App\Models\TargetIndikatorSasaranOpd;
use App\Models\TargetIndikatorTujuanDaerah;
use App\Models\TargetIndikatorTujuanOpd;
use App\Models\TargetRevision;
use App\Models\TargetTriwulanIndikator;
use App\Models\TujuanDaerah;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TargetRevisionService
{
    /**
     * @return array<int, string>
     */
    public function supportedTargetTables(): array
    {
        return array_keys($this->targetModels());
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function submit(User $user, array $data): TargetRevision
    {
        $target = $this->targetModel($data['target_table'], (int) $data['target_id']);
        $owner = $this->ownerModel($target, $data['target_table']);
        $this->ensureCanRequest($user, $owner);

        $newValues = $this->revisionValues($target, $data['new_values'] ?? []);

        if ($newValues === []) {
            throw ValidationException::withMessages([
                'new_values' => 'Isi minimal satu nilai target baru yang sesuai dengan jenis target.',
            ]);
        }

        if (TargetRevision::query()
            ->where('target_table', $data['target_table'])
            ->where('target_id', $target->getKey())
            ->where('status', 'submitted')
            ->exists()) {
            throw ValidationException::withMessages([
                'target_id' => 'Target ini masih memiliki pengajuan revisi yang belum diputuskan.',
            ]);
        }

        $ownerInfo = $this->ownerInfo($owner);

        return TargetRevision::create([
            'module' => $ownerInfo['module'],
            'target_table' => $data['target_table'],
            'target_id' => $target->getKey(),
            'owner_table' => $owner->getTable(),
            'owner_id' => $owner->getKey(),
            'opd_id' => $ownerInfo['opd_id'],
            'status' => 'submitted',
            'old_values' => Arr::only($target->getAttributes(), array_keys($newValues)),
            'new_values' => $newValues,
            'reason' => $data['reason'],
            'document_number' => $data['document_number'] ?? null,
            'document_date' => $data['document_date'] ?? null,
            'dokumen_id' => $this->dokumenId($data['dokumen_id'] ?? null),
            'requested_by' => $user->id,
        ])->fresh(['requestedBy:id,name', 'reviewedBy:id,name', 'opd:id,nama,singkatan']);
    }

    public function approve(User $reviewer, TargetRevision $revision, ?string $note = null): TargetRevision
    {
        $this->ensureCanReview($reviewer, $revision);
        $this->ensureSubmitted($revision);

        return DB::transaction(function () use ($reviewer, $revision, $note) {
            $target = $this->targetModel($revision->target_table, $revision->target_id);
            $target->forceFill($revision->new_values)->save();

            $revision->update([
                'status' => 'approved',
                'reviewed_by' => $reviewer->id,
                'reviewed_at' => now(),
                'review_note' => $note,
                'applied_at' => now(),
            ]);

            return $revision->fresh(['requestedBy:id,name', 'reviewedBy:id,name', 'opd:id,nama,singkatan']);
        });
    }

    public function reject(User $reviewer, TargetRevision $revision, string $note): TargetRevision
    {
        $this->ensureCanReview($reviewer, $revision);
        $this->ensureSubmitted($revision);

        $revision->update([
            'status' => 'rejected',
            'reviewed_by' => $reviewer->id,
            'reviewed_at' => now(),
            'review_note' => $note,
        ]);

        return $revision->fresh(['requestedBy:id,name', 'reviewedBy:id,name', 'opd:id,nama,singkatan']);
    }

    public function visibleQuery(User $user): Builder
    {
        $query = TargetRevision::query()
            ->with(['requestedBy:id,name', 'reviewedBy:id,name', 'opd:id,nama,singkatan']);

        if ($user->isSuperAdmin() || $user->hasAnyRole(['admin_kabupaten_bagian_organisasi', 'admin_kabupaten_bapperida', 'admin_kabupaten_inspektorat', 'pimpinan'])) {
            return $query;
        }

        if ($user->hasRole('admin_opd') && $user->opd_id) {
            return $query->where('opd_id', $user->opd_id);
        }

        return $query->whereRaw('1 = 0');
    }

    public function canViewIndex(User $user): bool
    {
        return $user->isSuperAdmin()
            || $user->hasAnyRole(['admin_kabupaten_bagian_organisasi', 'admin_kabupaten_bapperida', 'admin_kabupaten_inspektorat', 'admin_opd', 'pimpinan']);
    }

    public function canReview(User $user, ?TargetRevision $revision = null): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        if (! $revision) {
            return $user->hasAnyRole(['admin_kabupaten_bagian_organisasi', 'admin_kabupaten_bapperida']);
        }

        return match ($revision->module) {
            'rpjmd' => $user->hasAnyRole(['admin_kabupaten_bagian_organisasi', 'admin_kabupaten_bapperida']),
            'renstra_opd' => $user->hasRole('admin_kabupaten_bagian_organisasi'),
            default => false,
        };
    }

    private function ensureSubmitted(TargetRevision $revision): void
    {
        if ($revision->status !== 'submitted') {
            throw ValidationException::withMessages([
                'status' => 'Revisi target hanya dapat diproses saat status submitted.',
            ]);
        }
    }

    private function ensureCanRequest(User $user, Model $owner): void
    {
        if ($user->isSuperAdmin()) {
            return;
        }

        if ($owner instanceof Rpjmd && $user->hasRole('admin_kabupaten_bapperida')) {
            return;
        }

        if ($owner instanceof RenstraOpd && $user->hasRole('admin_opd') && (int) $user->opd_id === (int) $owner->opd_id) {
            return;
        }

        throw ValidationException::withMessages([
            'target_id' => 'User tidak berwenang mengajukan revisi target ini.',
        ]);
    }

    private function ensureCanReview(User $reviewer, TargetRevision $revision): void
    {
        if (! $this->canReview($reviewer, $revision)) {
            throw ValidationException::withMessages([
                'reviewer' => 'User tidak berwenang memutuskan revisi target ini.',
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    private function revisionValues(Model $target, array $values): array
    {
        $allowed = array_intersect($this->editableColumns($target), array_keys($values));

        return collect($allowed)
            ->mapWithKeys(function (string $key) use ($values) {
                $value = $values[$key];

                return [$key => is_string($value) ? trim($value) : $value];
            })
            ->filter(fn ($value) => $value !== '')
            ->all();
    }

    /**
     * @return array<int, string>
     */
    private function editableColumns(Model $target): array
    {
        return match (true) {
            $target instanceof TargetIndikatorTujuanDaerah,
            $target instanceof TargetIndikatorSasaranDaerah,
            $target instanceof TargetIndikatorTujuanOpd,
            $target instanceof TargetIndikatorSasaranOpd => ['target', 'target_text'],
            $target instanceof TargetIndikatorProgramRpjmd,
            $target instanceof TargetIndikatorOpdProgram => ['target', 'target_text', 'pagu'],
            $target instanceof TargetTriwulanIndikator => ['target_text', 'target_angka', 'target_anggaran'],
            default => [],
        };
    }

    private function targetModel(string $table, int $id): Model
    {
        $class = $this->targetModels()[$table] ?? null;

        if (! $class) {
            throw ValidationException::withMessages([
                'target_table' => 'Jenis target tidak didukung untuk revisi formal.',
            ]);
        }

        return $class::query()->findOrFail($id);
    }

    /**
     * @return array<string, class-string<Model>>
     */
    private function targetModels(): array
    {
        return [
            'target_indikator_tujuan_daerah' => TargetIndikatorTujuanDaerah::class,
            'target_indikator_sasaran_daerah' => TargetIndikatorSasaranDaerah::class,
            'target_indikator_program_rpjmd' => TargetIndikatorProgramRpjmd::class,
            'target_indikator_tujuan_opd' => TargetIndikatorTujuanOpd::class,
            'target_indikator_sasaran_opd' => TargetIndikatorSasaranOpd::class,
            'target_indikator_opd_program' => TargetIndikatorOpdProgram::class,
            'target_triwulan_indikator' => TargetTriwulanIndikator::class,
        ];
    }

    private function ownerModel(Model $target, string $table): Model
    {
        return match ($table) {
            'target_indikator_tujuan_daerah' => $this->rpjmdFromTujuan($target->indikator->tujuan),
            'target_indikator_sasaran_daerah' => $this->rpjmdFromTujuan($target->indikator->sasaran->tujuan),
            'target_indikator_program_rpjmd' => $this->rpjmdFromTujuan(
                $target->indikator->program->sasaran?->tujuan
                    ?? $target->indikator->program->indikatorSasaran?->sasaran?->tujuan
            ),
            'target_indikator_tujuan_opd' => $target->indikator->tujuan->renstra,
            'target_indikator_sasaran_opd' => $target->indikator->sasaran->tujuan->renstra,
            'target_indikator_opd_program' => $target->indikator->program->renstra,
            'target_triwulan_indikator' => $this->ownerForTriwulanTarget($target),
            default => throw ValidationException::withMessages(['target_table' => 'Jenis target tidak didukung.']),
        };
    }

    private function ownerForTriwulanTarget(TargetTriwulanIndikator $target): Model
    {
        $related = match ($target->related_table) {
            'indikator_tujuan_daerah' => IndikatorTujuanDaerah::findOrFail($target->related_id),
            'indikator_sasaran_daerah' => IndikatorSasaranDaerah::findOrFail($target->related_id),
            'indikator_program_rpjmd' => IndikatorProgramRpjmd::findOrFail($target->related_id),
            'indikator_tujuan_opd' => IndikatorTujuanOpd::findOrFail($target->related_id),
            'indikator_sasaran_opd' => IndikatorSasaranOpd::findOrFail($target->related_id),
            'indikator_opd_program' => IndikatorOpdProgram::findOrFail($target->related_id),
            'indikator_sub_kegiatan' => IndikatorSubKegiatan::findOrFail($target->related_id),
            default => throw ValidationException::withMessages(['target_id' => 'Relasi target triwulan tidak didukung.']),
        };

        return match ($target->related_table) {
            'indikator_tujuan_daerah' => $this->rpjmdFromTujuan($related->tujuan),
            'indikator_sasaran_daerah' => $this->rpjmdFromTujuan($related->sasaran->tujuan),
            'indikator_program_rpjmd' => $this->rpjmdFromTujuan(
                $related->program->sasaran?->tujuan
                    ?? $related->program->indikatorSasaran?->sasaran?->tujuan
            ),
            'indikator_tujuan_opd' => $related->tujuan->renstra,
            'indikator_sasaran_opd' => $related->sasaran->tujuan->renstra,
            'indikator_opd_program' => $related->program->renstra,
            'indikator_sub_kegiatan' => $related->subKegiatan->kegiatan->program->renstra,
        };
    }

    private function rpjmdFromTujuan(?TujuanDaerah $tujuan): Rpjmd
    {
        $rpjmd = $tujuan?->parentRpjmd();

        if (! $rpjmd) {
            throw ValidationException::withMessages([
                'target_id' => 'Target RPJMD tidak memiliki relasi visi/misi yang valid.',
            ]);
        }

        return $rpjmd;
    }

    /**
     * @return array{module: string, opd_id: int|null}
     */
    private function ownerInfo(Model $owner): array
    {
        if ($owner instanceof Rpjmd) {
            return ['module' => 'rpjmd', 'opd_id' => null];
        }

        if ($owner instanceof RenstraOpd) {
            return ['module' => 'renstra_opd', 'opd_id' => $owner->opd_id];
        }

        return ['module' => $owner->getTable(), 'opd_id' => null];
    }

    private function dokumenId(mixed $id): ?int
    {
        if (! $id) {
            return null;
        }

        return Dokumen::query()->whereKey($id)->value('id');
    }
}
