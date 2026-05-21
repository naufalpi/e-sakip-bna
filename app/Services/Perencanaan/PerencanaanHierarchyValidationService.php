<?php

namespace App\Services\Perencanaan;

use App\Models\PerjanjianKinerja;
use App\Models\RencanaAksi;
use App\Models\TargetIndikatorOpdProgram;
use App\Models\TargetIndikatorSasaranOpd;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class PerencanaanHierarchyValidationService
{
    private const APPROVED_STATUSES = ['approved', 'locked'];

    public function ensureParentExists(?Model $parent, string $field, string $message): void
    {
        if (! $parent) {
            throw ValidationException::withMessages([$field => $message]);
        }
    }

    public function ensureTargetCanBeCreated(?Model $indicator, string $field = 'parent_id'): void
    {
        $this->ensureParentExists($indicator, $field, 'Target tidak dapat dibuat karena indikator belum tersedia.');
    }

    public function ensureIndicatorCanBeCreated(?Model $parent, string $field = 'parent_id'): void
    {
        $this->ensureParentExists($parent, $field, 'Indikator tidak dapat dibuat karena level induknya belum tersedia.');
    }

    public function ensurePerjanjianKinerjaCanBeCreated(bool $hasApprovedTarget): void
    {
        if (! $hasApprovedTarget) {
            throw ValidationException::withMessages([
                'perjanjian_kinerja_id' => 'Perjanjian Kinerja tidak dapat dibuat sebelum target tahunan tersedia dan disetujui.',
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function applyApprovedPerjanjianKinerjaTarget(PerjanjianKinerja $perjanjianKinerja, array $data): array
    {
        if (! $perjanjianKinerja->periode_tahun_id) {
            throw ValidationException::withMessages([
                'periode_tahun_id' => 'Perjanjian Kinerja harus memiliki periode sebelum item target dapat dibuat.',
            ]);
        }

        if (empty($data['indikator_sasaran_opd_id']) && empty($data['opd_program_id'])) {
            throw ValidationException::withMessages([
                'indikator_sasaran_opd_id' => 'Item Perjanjian Kinerja harus memilih indikator sasaran OPD atau program OPD dari Renstra yang sudah disetujui.',
            ]);
        }

        if (! empty($data['indikator_sasaran_opd_id'])) {
            $target = TargetIndikatorSasaranOpd::query()
                ->where('indikator_sasaran_opd_id', $data['indikator_sasaran_opd_id'])
                ->where('periode_tahun_id', $perjanjianKinerja->periode_tahun_id)
                ->where(function (Builder $query) {
                    $query->whereNotNull('target')
                        ->orWhereNotNull('target_text');
                })
                ->whereHas('indikator.sasaran.tujuan.renstra', function (Builder $query) use ($perjanjianKinerja) {
                    $query->where('opd_id', $perjanjianKinerja->opd_id)
                        ->whereIn('status', self::APPROVED_STATUSES);
                })
                ->first();

            if (! $target) {
                throw ValidationException::withMessages([
                    'indikator_sasaran_opd_id' => 'Target tahunan indikator sasaran OPD belum tersedia pada Renstra yang sudah disetujui atau terkunci.',
                ]);
            }

            return $this->applyApprovedTargetValue($data, $target, 'target');
        }

        $target = TargetIndikatorOpdProgram::query()
            ->where('periode_tahun_id', $perjanjianKinerja->periode_tahun_id)
            ->where(function (Builder $query) {
                $query->whereNotNull('target')
                    ->orWhereNotNull('target_text');
            })
            ->whereHas('indikator.program', function (Builder $query) use ($data, $perjanjianKinerja) {
                $query->whereKey($data['opd_program_id'])
                    ->whereHas('renstra', function (Builder $query) use ($perjanjianKinerja) {
                        $query->where('opd_id', $perjanjianKinerja->opd_id)
                            ->whereIn('status', self::APPROVED_STATUSES);
                    });
            })
            ->first();

        if (! $target) {
            throw ValidationException::withMessages([
                'opd_program_id' => 'Target tahunan program OPD belum tersedia pada Renstra yang sudah disetujui atau terkunci.',
            ]);
        }

        return $this->applyApprovedTargetValue($data, $target, 'target');
    }

    public function ensureRencanaAksiCanBeCreated(?PerjanjianKinerja $perjanjianKinerja): void
    {
        if (! $perjanjianKinerja) {
            throw ValidationException::withMessages([
                'perjanjian_kinerja_id' => 'Rencana Aksi tidak dapat dibuat sebelum Perjanjian Kinerja tersedia.',
            ]);
        }

        if (! in_array($perjanjianKinerja->status, self::APPROVED_STATUSES, true)) {
            throw ValidationException::withMessages([
                'perjanjian_kinerja_id' => 'Rencana Aksi hanya dapat dibuat dari Perjanjian Kinerja yang sudah disetujui atau terkunci.',
            ]);
        }

        if (! $perjanjianKinerja->items()->exists()) {
            throw ValidationException::withMessages([
                'perjanjian_kinerja_id' => 'Rencana Aksi tidak dapat dibuat sebelum item target Perjanjian Kinerja tersedia.',
            ]);
        }
    }

    public function ensureRealisasiCanBeCreated(?PerjanjianKinerja $perjanjianKinerja, ?RencanaAksi $rencanaAksi = null): void
    {
        if (! $perjanjianKinerja || ! in_array($perjanjianKinerja->status, self::APPROVED_STATUSES, true)) {
            throw ValidationException::withMessages([
                'perjanjian_kinerja_id' => 'Realisasi tidak dapat dibuat sebelum target Perjanjian Kinerja disetujui.',
            ]);
        }

        if ($rencanaAksi && (int) $rencanaAksi->perjanjian_kinerja_id !== (int) $perjanjianKinerja->id) {
            throw ValidationException::withMessages([
                'rencana_aksi_id' => 'Rencana Aksi tidak sesuai dengan Perjanjian Kinerja Realisasi.',
            ]);
        }

        if ($rencanaAksi && ! in_array($rencanaAksi->status, self::APPROVED_STATUSES, true)) {
            throw ValidationException::withMessages([
                'rencana_aksi_id' => 'Realisasi tidak dapat dibuat sebelum Rencana Aksi disetujui atau terkunci.',
            ]);
        }

        if ($rencanaAksi && ! $rencanaAksi->items()->exists()) {
            throw ValidationException::withMessages([
                'rencana_aksi_id' => 'Realisasi tidak dapat dibuat sebelum item Rencana Aksi tersedia.',
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function applyApprovedTargetValue(array $data, Model $target, string $field): array
    {
        $approvedTarget = $target->getAttribute('target');

        if ($approvedTarget !== null) {
            if (($data[$field] ?? null) !== null
                && $data[$field] !== ''
                && abs((float) $data[$field] - (float) $approvedTarget) > 0.0001) {
                throw ValidationException::withMessages([
                    $field => 'Target Perjanjian Kinerja harus sama dengan target tahunan Renstra yang sudah disetujui.',
                ]);
            }

            $data[$field] = $approvedTarget;
        }

        if (blank($data['target_text'] ?? null) && filled($target->getAttribute('target_text'))) {
            $data['target_text'] = $target->getAttribute('target_text');
        }

        return $data;
    }
}
