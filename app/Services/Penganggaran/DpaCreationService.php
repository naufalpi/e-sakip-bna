<?php

namespace App\Services\Penganggaran;

use App\Models\DpaOpd;
use App\Models\RkaOpd;
use App\Models\RkaOpdItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DpaCreationService
{
    /**
     * Membuat snapshot DPA-BELANJA SKPD dari RKA yang sudah resmi.
     *
     * @param  array<string, mixed>  $header
     */
    public function createFromRka(RkaOpd $rka, array $header): DpaOpd
    {
        $this->ensureEligible($rka);

        return DB::transaction(function () use ($rka, $header): DpaOpd {
            $lockedRka = RkaOpd::query()->lockForUpdate()->findOrFail($rka->id);
            $this->ensureEligible($lockedRka);

            if (DpaOpd::query()->where('rka_opd_id', $lockedRka->id)->exists()) {
                throw ValidationException::withMessages([
                    'rka_opd_id' => 'DPA untuk RKA tersebut sudah tersedia.',
                ]);
            }

            $lockedRka->loadMissing(['opd:id,kode,nama,singkatan', 'items']);
            if ($lockedRka->items->isEmpty()) {
                throw ValidationException::withMessages([
                    'rka_opd_id' => 'RKA belum memiliki rincian sub kegiatan yang dapat disalin ke DPA.',
                ]);
            }

            $dpa = DpaOpd::create([
                'rka_opd_id' => $lockedRka->id,
                'renja_opd_id' => $lockedRka->renja_opd_id,
                'rkpd_id' => $lockedRka->rkpd_id,
                'opd_id' => $lockedRka->opd_id,
                'opd_unit_id' => $lockedRka->opd_unit_id,
                'periode_tahun_id' => $lockedRka->periode_tahun_id,
                'tahun' => $lockedRka->tahun,
                'jenis_anggaran' => $lockedRka->jenis_anggaran,
                'judul' => $header['judul'] ?: $this->defaultTitle($lockedRka),
                'nomor_dpa' => $header['nomor_dpa'] ?? null,
                'tanggal_pengesahan' => $header['tanggal_pengesahan'] ?? null,
                'nomor_perda_apbd' => $header['nomor_perda_apbd'] ?? null,
                'tanggal_perda_apbd' => $header['tanggal_perda_apbd'] ?? null,
                'nomor_perkada_penjabaran' => $header['nomor_perkada_penjabaran'] ?? null,
                'tanggal_perkada_penjabaran' => $header['tanggal_perkada_penjabaran'] ?? null,
                'nama_pengguna_anggaran' => $header['nama_pengguna_anggaran'] ?? null,
                'nip_pengguna_anggaran' => $header['nip_pengguna_anggaran'] ?? null,
                'status' => 'draft',
                'catatan' => $header['catatan'] ?? null,
            ]);

            foreach ($lockedRka->items as $index => $rkaItem) {
                $dpa->items()->create($this->itemPayload($rkaItem, $index + 1));
            }

            return $dpa->load(['opd', 'opdUnit', 'rkaOpd', 'items']);
        });
    }

    public function ensureEligible(RkaOpd $rka): void
    {
        if (! in_array($rka->status, ['approved', 'locked'], true)) {
            throw ValidationException::withMessages([
                'rka_opd_id' => 'DPA hanya dapat dibuat dari RKA yang sudah disetujui atau dikunci.',
            ]);
        }
    }

    private function defaultTitle(RkaOpd $rka): string
    {
        $opd = $rka->opd?->singkatan ?: $rka->opd?->nama ?: 'OPD';
        $prefix = $rka->jenis_anggaran === 'perubahan' ? 'DPPA' : 'DPA';

        return str("{$prefix} {$opd} TAHUN ANGGARAN {$rka->tahun}")->upper()->toString();
    }

    /** @return array<string, mixed> */
    private function itemPayload(RkaOpdItem $item, int $urutan): array
    {
        return [
            'rka_opd_item_id' => $item->id,
            'urusan_pemerintahan_id' => $item->urusan_pemerintahan_id,
            'bidang_urusan_id' => $item->bidang_urusan_id,
            'program_pemerintahan_id' => $item->program_pemerintahan_id,
            'kegiatan_pemerintahan_id' => $item->kegiatan_pemerintahan_id,
            'sub_kegiatan_pemerintahan_id' => $item->sub_kegiatan_pemerintahan_id,
            'kode_urusan' => $item->kode_urusan,
            'nama_urusan' => $item->nama_urusan,
            'kode_bidang' => $item->kode_bidang,
            'nama_bidang' => $item->nama_bidang,
            'kode_program' => $item->kode_program,
            'nama_program' => $item->nama_program,
            'kode_kegiatan' => $item->kode_kegiatan,
            'nama_kegiatan' => $item->nama_kegiatan,
            'kode_sub_kegiatan' => $item->kode_sub_kegiatan,
            'nama_sub_kegiatan' => $item->nama_sub_kegiatan,
            'tolok_ukur_kinerja' => $item->tolok_ukur_kinerja,
            'target_kinerja' => $item->target_kinerja,
            'satuan_kinerja' => $item->satuan_kinerja,
            'sumber_pendanaan' => $item->sumber_pendanaan,
            'lokasi' => $item->lokasi,
            'kelompok_sasaran' => $item->kelompok_sasaran,
            'bulan_mulai' => $item->bulan_mulai,
            'bulan_selesai' => $item->bulan_selesai,
            'jenis_belanja' => $item->jenis_belanja,
            'alokasi_tahun_sebelumnya' => $item->alokasi_tahun_sebelumnya,
            'pagu_rka' => $item->pagu_rka,
            'pagu_dpa' => $item->pagu_rka,
            'alokasi_tahun_berikutnya' => $item->alokasi_tahun_berikutnya,
            'alasan_penyesuaian' => null,
            'catatan' => null,
            'urutan' => $item->urutan ?: $urutan,
        ];
    }
}
