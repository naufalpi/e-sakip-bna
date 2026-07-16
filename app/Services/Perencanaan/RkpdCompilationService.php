<?php

namespace App\Services\Perencanaan;

use App\Models\RenjaOpd;
use App\Models\RenjaOpdItem;
use App\Models\Rkpd;
use App\Models\RkpdItem;
use Illuminate\Support\Facades\DB;

class RkpdCompilationService
{
    public function pullApprovedRenja(Rkpd $rkpd): int
    {
        return DB::transaction(function () use ($rkpd) {
            $count = 0;

            RenjaOpd::query()
                ->with([
                    'opd:id,kode,nama,singkatan',
                    'opdUnit:id,opd_id,kode,nama',
                    'items.subKegiatanPemerintahan.kegiatanPemerintahan.programPemerintahan.bidangUrusan.urusanPemerintahan',
                ])
                ->where(function ($query) use ($rkpd) {
                    $query->where('rkpd_id', $rkpd->id)
                        ->orWhere(function ($query) use ($rkpd) {
                            $query->where('periode_tahun_id', $rkpd->periode_tahun_id)
                                ->where('tahun', $rkpd->tahun);
                        });
                })
                ->whereIn('status', ['verified', 'approved', 'locked'])
                ->chunkById(50, function ($renjas) use ($rkpd, &$count) {
                    foreach ($renjas as $renja) {
                        foreach ($renja->items as $item) {
                            $this->upsertRkpdItem($rkpd, $renja, $item);
                            $count++;
                        }
                    }
                });

            return $count;
        });
    }

    private function upsertRkpdItem(Rkpd $rkpd, RenjaOpd $renja, RenjaOpdItem $item): void
    {
        $subKegiatan = $item->subKegiatanPemerintahan;
        $kegiatan = $subKegiatan?->kegiatanPemerintahan;
        $program = $kegiatan?->programPemerintahan;
        $bidang = $program?->bidangUrusan;
        $urusan = $bidang?->urusanPemerintahan;

        RkpdItem::updateOrCreate(
            [
                'rkpd_id' => $rkpd->id,
                'renja_opd_item_id' => $item->id,
            ],
            [
                'renja_opd_id' => $renja->id,
                'opd_id' => $renja->opd_id,
                'opd_unit_id' => $renja->opd_unit_id,
                'urusan_pemerintahan_id' => $urusan?->id,
                'bidang_urusan_id' => $bidang?->id,
                'program_pemerintahan_id' => $program?->id,
                'kegiatan_pemerintahan_id' => $kegiatan?->id,
                'sub_kegiatan_pemerintahan_id' => $subKegiatan?->id,
                'kode' => $item->kode ?? $subKegiatan?->kode,
                'nama_urusan_bidang_program_kegiatan_sub' => $item->nama_sub_kegiatan ?? $subKegiatan?->nama,
                'indikator' => $item->indikator,
                'target_akhir_renstra' => $item->target_akhir_renstra,
                'realisasi_capaian_renja_tahun_lalu' => $item->realisasi_capaian_renja_tahun_lalu,
                'prakiraan_capaian_target_renja_tahun_berjalan' => $item->prakiraan_capaian_target_renja_tahun_berjalan,
                'target' => $item->target,
                'pagu_indikatif' => $item->pagu_indikatif,
                'lokasi' => $item->lokasi,
                'sumber_dana' => $item->sumber_dana,
                'prioritas_nasional' => $item->prioritas_nasional,
                'prioritas_daerah' => $item->prioritas_daerah,
                'kelompok_sasaran' => $item->kelompok_sasaran,
                'prakiraan_maju_target' => $item->prakiraan_maju_target,
                'prakiraan_maju_pagu_indikatif' => $item->prakiraan_maju_pagu_indikatif,
                'perangkat_daerah_penanggung_jawab' => $renja->opd?->nama,
                'status' => 'draft',
                'urutan' => $item->urutan,
                'metadata' => [
                    'source' => 'renja_opd',
                    'opd_unit' => $renja->opdUnit?->nama,
                ],
            ],
        );
    }
}
