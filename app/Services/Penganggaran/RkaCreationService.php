<?php

namespace App\Services\Penganggaran;

use App\Models\RenjaOpd;
use App\Models\RenjaOpdItem;
use App\Models\RkaOpd;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RkaCreationService
{
    /**
     * Membuat snapshot RKA-BELANJA SKPD dari RENJA resmi.
     *
     * @param  array<string, mixed>  $header
     */
    public function createFromRenja(RenjaOpd $renja, array $header): RkaOpd
    {
        $this->ensureEligible($renja);

        return DB::transaction(function () use ($renja, $header): RkaOpd {
            $lockedRenja = RenjaOpd::query()->lockForUpdate()->findOrFail($renja->id);
            $this->ensureEligible($lockedRenja);

            if (RkaOpd::query()->where('renja_opd_id', $lockedRenja->id)->exists()) {
                throw ValidationException::withMessages([
                    'renja_opd_id' => 'RKA untuk versi RENJA tersebut sudah tersedia.',
                ]);
            }

            $lockedRenja->loadMissing([
                'opd:id,kode,nama,singkatan',
                'items.programPemerintahan.bidangUrusan.urusanPemerintahan',
                'items.kegiatanPemerintahan',
                'items.subKegiatanPemerintahan.satuanIndikator',
            ]);

            if ($lockedRenja->items->isEmpty()) {
                throw ValidationException::withMessages([
                    'renja_opd_id' => 'RENJA belum memiliki sub kegiatan yang dapat disalin ke RKA.',
                ]);
            }

            $rka = RkaOpd::create([
                'renja_opd_id' => $lockedRenja->id,
                'rkpd_id' => $lockedRenja->rkpd_id,
                'opd_id' => $lockedRenja->opd_id,
                'opd_unit_id' => $lockedRenja->opd_unit_id,
                'periode_tahun_id' => $lockedRenja->periode_tahun_id,
                'tahun' => $lockedRenja->tahun,
                'jenis_anggaran' => $lockedRenja->jenis_versi === 'perubahan' ? 'perubahan' : 'murni',
                'judul' => $header['judul'] ?: $this->defaultTitle($lockedRenja),
                'nomor_dokumen' => $header['nomor_dokumen'] ?? null,
                'tanggal_dokumen' => $header['tanggal_dokumen'] ?? null,
                'nomor_kua' => $header['nomor_kua'] ?? null,
                'tanggal_kua' => $header['tanggal_kua'] ?? null,
                'nomor_ppas' => $header['nomor_ppas'] ?? null,
                'tanggal_ppas' => $header['tanggal_ppas'] ?? null,
                'status' => 'draft',
                'catatan' => $header['catatan'] ?? null,
            ]);

            $now = now();
            $rows = $lockedRenja->items
                ->values()
                ->map(fn (RenjaOpdItem $item, int $index) => $this->itemPayload($rka, $item, $index + 1, $now))
                ->all();

            DB::table('rka_opd_items')->insert($rows);

            return $rka->load(['opd', 'opdUnit', 'renjaOpd', 'items']);
        });
    }

    public function ensureEligible(RenjaOpd $renja): void
    {
        if (! in_array($renja->jenis_versi, ['ditetapkan', 'perubahan'], true)
            || ! in_array($renja->status, ['approved', 'locked'], true)) {
            throw ValidationException::withMessages([
                'renja_opd_id' => 'RKA hanya dapat dibuat dari RENJA Ditetapkan atau RENJA Perubahan yang sudah disetujui.',
            ]);
        }
    }

    private function defaultTitle(RenjaOpd $renja): string
    {
        $opd = $renja->opd?->singkatan ?: $renja->opd?->nama ?: 'OPD';
        $prefix = $renja->jenis_versi === 'perubahan' ? 'PERUBAHAN RKA' : 'RKA';

        return str("{$prefix} {$opd} TAHUN ANGGARAN {$renja->tahun}")->upper()->toString();
    }

    /**
     * @return array<string, mixed>
     */
    private function itemPayload(RkaOpd $rka, RenjaOpdItem $item, int $urutan, mixed $now): array
    {
        $program = $item->programPemerintahan;
        $kegiatan = $item->kegiatanPemerintahan;
        $subKegiatan = $item->subKegiatanPemerintahan;
        $bidang = $program?->bidangUrusan;
        $urusan = $bidang?->urusanPemerintahan;
        $satuan = $subKegiatan?->satuanIndikator;
        $pagu = (float) ($item->pagu_indikatif ?? 0);

        return [
            'rka_opd_id' => $rka->id,
            'renja_opd_item_id' => $item->id,
            'urusan_pemerintahan_id' => $urusan?->id,
            'bidang_urusan_id' => $bidang?->id,
            'program_pemerintahan_id' => $program?->id,
            'kegiatan_pemerintahan_id' => $kegiatan?->id,
            'sub_kegiatan_pemerintahan_id' => $subKegiatan?->id,
            'kode_urusan' => $urusan?->kode,
            'nama_urusan' => $urusan?->nama,
            'kode_bidang' => $bidang?->kode,
            'nama_bidang' => $bidang?->nama,
            'kode_program' => $program?->kode,
            'nama_program' => $program?->nama,
            'kode_kegiatan' => $kegiatan?->kode,
            'nama_kegiatan' => $kegiatan?->nama,
            'kode_sub_kegiatan' => $item->kode ?: $subKegiatan?->kode,
            'nama_sub_kegiatan' => $item->nama_sub_kegiatan ?: $subKegiatan?->nama,
            'tolok_ukur_kinerja' => $item->indikator ?: $subKegiatan?->indikator_sub_kegiatan,
            'target_kinerja' => $item->target,
            'satuan_kinerja' => $satuan?->simbol ?: $satuan?->nama,
            'sumber_pendanaan' => $item->sumber_dana,
            'lokasi' => $item->lokasi,
            'kelompok_sasaran' => $item->kelompok_sasaran,
            'bulan_mulai' => 1,
            'bulan_selesai' => 12,
            'jenis_belanja' => null,
            'alokasi_tahun_sebelumnya' => 0,
            'pagu_renja' => $pagu,
            'pagu_usulan' => $pagu,
            'pagu_belanja_operasi_usulan' => 0,
            'pagu_belanja_modal_usulan' => 0,
            'pagu_belanja_tidak_terduga_usulan' => 0,
            'pagu_belanja_transfer_usulan' => 0,
            'pagu_hasil_verifikasi' => $pagu,
            'pagu_belanja_operasi_hasil_verifikasi' => 0,
            'pagu_belanja_modal_hasil_verifikasi' => 0,
            'pagu_belanja_tidak_terduga_hasil_verifikasi' => 0,
            'pagu_belanja_transfer_hasil_verifikasi' => 0,
            'alokasi_tahun_berikutnya' => (float) ($item->prakiraan_maju_pagu_indikatif ?? 0),
            'alasan_penyesuaian' => null,
            'catatan' => null,
            'urutan' => $item->urutan ?: $urutan,
            'created_at' => $now,
            'updated_at' => $now,
        ];
    }
}
