<?php

namespace App\Services\Perencanaan;

use App\Models\PerjanjianKinerja;
use App\Models\RencanaAksi;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class PerencanaanHierarchyValidationService
{
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

    public function ensureRencanaAksiCanBeCreated(?PerjanjianKinerja $perjanjianKinerja): void
    {
        if (! $perjanjianKinerja) {
            throw ValidationException::withMessages([
                'perjanjian_kinerja_id' => 'Rencana Aksi tidak dapat dibuat sebelum Perjanjian Kinerja tersedia.',
            ]);
        }
    }

    public function ensureRealisasiCanBeCreated(?PerjanjianKinerja $perjanjianKinerja, ?RencanaAksi $rencanaAksi = null): void
    {
        if (! $perjanjianKinerja || ! in_array($perjanjianKinerja->status, ['approved', 'locked'], true)) {
            throw ValidationException::withMessages([
                'perjanjian_kinerja_id' => 'Realisasi tidak dapat dibuat sebelum target Perjanjian Kinerja disetujui.',
            ]);
        }

        if ($rencanaAksi && (int) $rencanaAksi->perjanjian_kinerja_id !== (int) $perjanjianKinerja->id) {
            throw ValidationException::withMessages([
                'rencana_aksi_id' => 'Rencana Aksi tidak sesuai dengan Perjanjian Kinerja Realisasi.',
            ]);
        }
    }
}
