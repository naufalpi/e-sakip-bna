<?php

namespace App\Services\Kinerja;

use App\Models\PerjanjianKinerja;
use App\Models\RiwayatPejabatJabatan;
use Illuminate\Validation\ValidationException;

class PerjanjianKinerjaReadinessService
{
    public function ensureReady(PerjanjianKinerja $pk): void
    {
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

        $this->ensureVerifiedSignatories($pk);

        $items = $pk->items()->get(['sasaran', 'indikator', 'target', 'target_text']);

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

    private function ensureVerifiedSignatories(PerjanjianKinerja $pk): void
    {
        if (! $pk->pegawai_id || ! $pk->penempatan_pegawai_id) {
            $this->fail('Pemilik dan jabatan penandatangan PK belum terhubung ke Struktur Organisasi. Edit PK lalu pilih kembali data tersebut.');
        }

        $periodStart = "{$pk->tahun}-01-01";
        $periodEnd = "{$pk->tahun}-12-31";
        $placement = $pk->penempatanPegawai()
            ->with('jabatanOrganisasi:id,opd_id,parent_id,nama,level_jabatan,status,verification_status')
            ->first();

        if (! $placement
            || (int) $placement->pegawai_id !== (int) $pk->pegawai_id
            || $placement->tanggal_mulai?->format('Y-m-d') > $periodEnd
            || ($placement->tanggal_selesai && $placement->tanggal_selesai->format('Y-m-d') < $periodStart)) {
            $this->fail('Jabatan penandatangan tidak sesuai dengan pemilik atau tidak berlaku pada tahun PK. Edit PK lalu pilih penempatan yang benar.');
        }

        if (! $placement->jabatanOrganisasi || ! $placement->jabatanOrganisasi->isVerified()) {
            $this->fail('Jabatan penandatangan belum terverifikasi oleh Bagian Organisasi. PK dapat disimpan sebagai Draft, tetapi belum dapat diajukan.');
        }

        if ($placement->jabatanOrganisasi->status !== 'active') {
            $this->fail('Jabatan penandatangan sudah tidak aktif. Edit PK lalu pilih jabatan aktif yang sesuai.');
        }

        $allowedLevels = match ($pk->level_pk) {
            'bupati' => ['kepala_daerah'],
            'kepala_opd' => ['jpt_pratama'],
            'struktural' => ['administrator'],
            'individu' => ['pengawas', 'fungsional', 'pelaksana'],
            default => [],
        };
        if (! in_array($placement->jabatanOrganisasi->level_jabatan, $allowedLevels, true)) {
            $this->fail('Level jabatan penandatangan tidak sesuai dengan jenis PK. Edit PK lalu pilih jabatan yang benar.');
        }

        if ($pk->level_pk !== 'bupati' && (int) $placement->jabatanOrganisasi->opd_id !== (int) $pk->opd_id) {
            $this->fail('Jabatan penandatangan tidak berada pada OPD dokumen PK. Edit PK lalu pilih data yang sesuai.');
        }

        if ($pk->level_pk === 'bupati') {
            return;
        }

        if (! $pk->atasan_pegawai_id) {
            $this->fail('Pihak Kedua / Atasan PK belum dipilih. Edit PK lalu pilih atasan langsung.');
        }

        $parentJabatanId = $placement->jabatanOrganisasi->parent_id;
        if (! $parentJabatanId && $pk->level_pk !== 'kepala_opd') {
            $this->fail('Jabatan penandatangan belum memiliki atasan langsung pada Struktur Organisasi. Lengkapi struktur terlebih dahulu sebelum mengajukan PK.');
        }

        $supervisorPlacement = RiwayatPejabatJabatan::query()
            ->with('jabatanOrganisasi:id,nama,level_jabatan,status,verification_status')
            ->where('pegawai_id', $pk->atasan_pegawai_id)
            ->when($parentJabatanId, fn ($query, int $jabatanId) => $query->where('jabatan_organisasi_id', $jabatanId))
            ->when(! $parentJabatanId && $pk->level_pk === 'kepala_opd', fn ($query) => $query
                ->whereHas('jabatanOrganisasi', fn ($query) => $query->where('level_jabatan', 'kepala_daerah')))
            ->whereDate('tanggal_mulai', '<=', $periodEnd)
            ->where(fn ($query) => $query->whereNull('tanggal_selesai')->orWhereDate('tanggal_selesai', '>=', $periodStart))
            ->latest('tanggal_mulai')
            ->first();

        if (! $supervisorPlacement) {
            $this->fail('Pihak Kedua tidak lagi sesuai dengan jabatan atasan langsung pada tahun PK. Edit PK lalu pilih kembali atasan.');
        }

        if (! $supervisorPlacement->jabatanOrganisasi || ! $supervisorPlacement->jabatanOrganisasi->isVerified()) {
            $this->fail('Jabatan Pihak Kedua / Atasan belum terverifikasi oleh Bagian Organisasi. PK belum dapat diajukan.');
        }

        if ($supervisorPlacement->jabatanOrganisasi->status !== 'active') {
            $this->fail('Jabatan Pihak Kedua / Atasan sudah tidak aktif. Edit PK lalu pilih atasan langsung yang aktif.');
        }
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['action' => $message]);
    }
}
