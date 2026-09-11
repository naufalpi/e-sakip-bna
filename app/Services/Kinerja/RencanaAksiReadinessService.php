<?php

namespace App\Services\Kinerja;

use App\Models\RencanaAksi;
use Illuminate\Validation\ValidationException;

class RencanaAksiReadinessService
{
    public function ensureReady(RencanaAksi $rencanaAksi): void
    {
        if ((int) $rencanaAksi->format_version < 2) {
            if (! $rencanaAksi->items()->exists()) {
                $this->fail('Rencana Aksi belum memiliki item.');
            }

            return;
        }

        if (! $rencanaAksi->perjanjian_kinerja_id || ! $rencanaAksi->renstra_opd_id || ! $rencanaAksi->dpa_opd_id) {
            $this->fail('Sumber snapshot PK, RENSTRA, atau DPA/DPPA tidak lengkap. Buat ulang Rencana Aksi dari PK Kepala OPD resmi.');
        }

        $items = $rencanaAksi->items()->with('targetTriwulan')->where('is_snapshot', true)->get();
        if ($items->isEmpty()) {
            $this->fail('Matriks Rencana Aksi belum memiliki cascading kinerja.');
        }

        if ($items->contains(fn ($item) => blank($item->uraian_snapshot) || blank($item->indikator))) {
            $this->fail('Masih ada uraian atau indikator pada matriks yang tidak lengkap.');
        }

        if ($items->contains(fn ($item) => blank($item->formula))) {
            $this->fail('Formula wajib diisi pada seluruh baris Rencana Aksi.');
        }

        if ($items->contains(fn ($item) => blank($item->penanggung_jawab))) {
            $this->fail('Penanggung jawab wajib diisi pada seluruh baris Rencana Aksi.');
        }

        if ($items->contains(fn ($item) => $item->targetTriwulan->count() !== 4
            || $item->targetTriwulan->contains(fn ($target) => blank($target->target_text) && $target->target === null))) {
            $this->fail('Target pelaksanaan Triwulan I sampai IV wajib diisi pada seluruh baris Rencana Aksi.');
        }
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['action' => $message]);
    }
}
