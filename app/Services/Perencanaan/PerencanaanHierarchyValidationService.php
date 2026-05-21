<?php

namespace App\Services\Perencanaan;

use App\Models\PerjanjianKinerja;
use App\Models\RencanaAksi;
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
}
