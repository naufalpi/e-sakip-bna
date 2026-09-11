<?php

namespace App\Services\Kinerja;

use App\Models\PerjanjianKinerja;
use Illuminate\Validation\ValidationException;

class PerjanjianKinerjaReadinessService
{
    public function ensureReady(PerjanjianKinerja $pk): void
    {
        // Dokumen lama hasil migrasi dapat belum memiliki relasi pemilik/snapshot identitas.
        if (blank($pk->pegawai_id) && blank($pk->nama_pegawai_snapshot)) {
            return;
        }

        if (blank($pk->nama_pegawai_snapshot) || blank($pk->jabatan_snapshot)) {
            $this->fail('Identitas pemilik dan jabatan penandatangan PK belum lengkap. Edit PK lalu pilih kembali pemilik serta jabatannya.');
        }

        if ($pk->level_pk !== 'bupati'
            && (blank($pk->nama_atasan_snapshot) || blank($pk->jabatan_atasan_snapshot))) {
            $this->fail('Identitas Pihak Kedua / Atasan PK belum lengkap. Edit PK lalu pilih kembali atasan langsung.');
        }

        if ($pk->level_pk === 'individu' && $pk->tipe_pk === 'individual' && blank($pk->unit_kerja_snapshot)) {
            $this->fail('Unit Kerja PK manual belum diisi. Edit PK lalu lengkapi nama Bidang/Bagian.');
        }

        $items = $pk->items()
            ->where(fn ($query) => $query->whereNull('jenis_item')->orWhere('jenis_item', '!=', 'program_opd'))
            ->get(['sasaran', 'indikator', 'target', 'target_text']);

        if ($items->isEmpty()) {
            $this->fail('PK belum memiliki sasaran dan indikator kinerja. Lengkapi matriks PK sebelum diajukan.');
        }

        if ($items->contains(fn ($item) => blank($item->sasaran) || blank($item->indikator))) {
            $this->fail('Masih ada sasaran atau indikator kinerja PK yang kosong. Lengkapi matriks PK sebelum diajukan.');
        }

        if ($items->contains(fn ($item) => $item->target === null && blank($item->target_text))) {
            $this->fail('Masih ada target kinerja PK yang kosong. Lengkapi seluruh target sebelum diajukan.');
        }

        if (in_array($pk->level_pk, ['bupati', 'kepala_opd'], true) && ! $pk->programs()->exists()) {
            $this->fail('Daftar program dan anggaran PK belum tersedia. Periksa kembali dokumen sumber PK.');
        }
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['action' => $message]);
    }
}
