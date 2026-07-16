<?php

namespace App\Http\Controllers\Perencanaan;

use App\Http\Controllers\Controller;
use App\Http\Requests\Perencanaan\StoreRkpdItemRequest;
use App\Http\Requests\Perencanaan\UpdateRkpdItemRequest;
use App\Models\Rkpd;
use App\Models\RkpdItem;
use App\Models\SubKegiatanPemerintahan;
use App\Services\Perencanaan\RkpdCompilationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class RkpdItemController extends Controller
{
    public function store(StoreRkpdItemRequest $request, Rkpd $rkpd): RedirectResponse
    {
        $subKegiatan = $this->subKegiatan((int) $request->validated('sub_kegiatan_pemerintahan_id'));

        $rkpd->items()->create($this->payload($request->validated(), $subKegiatan, [
            'status' => $request->validated('status') ?: 'draft',
            'urutan' => $request->validated('urutan') ?: ((int) $rkpd->items()->max('urutan')) + 1,
        ]));

        return back()->with('success', 'Baris RKPD berhasil disimpan.');
    }

    public function update(UpdateRkpdItemRequest $request, Rkpd $rkpd, RkpdItem $item): RedirectResponse
    {
        abort_unless((int) $item->rkpd_id === (int) $rkpd->id, 404);

        $subKegiatan = $this->subKegiatan((int) $request->validated('sub_kegiatan_pemerintahan_id'));

        $item->update($this->payload($request->validated(), $subKegiatan, [
            'status' => $request->validated('status') ?: $item->status,
            'urutan' => $request->validated('urutan') ?: $item->urutan,
        ]));

        return back()->with('success', 'Baris RKPD berhasil diperbarui.');
    }

    public function destroy(Request $request, Rkpd $rkpd, RkpdItem $item): RedirectResponse
    {
        abort_unless($request->user()->can('update', $rkpd), 403);
        abort_unless((int) $item->rkpd_id === (int) $rkpd->id, 404);

        $item->delete();

        return back()->with('success', 'Baris RKPD berhasil dihapus.');
    }

    public function pullRenja(Request $request, Rkpd $rkpd, RkpdCompilationService $service): RedirectResponse
    {
        abort_unless($request->user()->can('update', $rkpd), 403);

        $count = $service->pullApprovedRenja($rkpd);

        return back()->with('success', "{$count} baris Renja OPD berhasil ditarik ke RKPD.");
    }

    private function subKegiatan(int $id): SubKegiatanPemerintahan
    {
        return SubKegiatanPemerintahan::query()
            ->with('kegiatanPemerintahan.programPemerintahan.bidangUrusan.urusanPemerintahan')
            ->findOrFail($id);
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $data, SubKegiatanPemerintahan $subKegiatan, array $overrides = []): array
    {
        $kegiatan = $subKegiatan->kegiatanPemerintahan;
        $program = $kegiatan?->programPemerintahan;
        $bidang = $program?->bidangUrusan;
        $urusan = $bidang?->urusanPemerintahan;

        return [
            'opd_id' => $data['opd_id'],
            'opd_unit_id' => $data['opd_unit_id'] ?? null,
            'urusan_pemerintahan_id' => $urusan?->id,
            'bidang_urusan_id' => $bidang?->id,
            'program_pemerintahan_id' => $program?->id,
            'kegiatan_pemerintahan_id' => $kegiatan?->id,
            'sub_kegiatan_pemerintahan_id' => $subKegiatan->id,
            'program_rpjmd_id' => $data['program_rpjmd_id'] ?? null,
            'kode' => $subKegiatan->kode,
            'nama_urusan_bidang_program_kegiatan_sub' => $subKegiatan->nama,
            'indikator' => $data['indikator'] ?? null,
            'target_akhir_renstra' => $data['target_akhir_renstra'] ?? null,
            'realisasi_capaian_renja_tahun_lalu' => $data['realisasi_capaian_renja_tahun_lalu'] ?? null,
            'prakiraan_capaian_target_renja_tahun_berjalan' => $data['prakiraan_capaian_target_renja_tahun_berjalan'] ?? null,
            'target' => $data['target'] ?? null,
            'pagu_indikatif' => $data['pagu_indikatif'] ?? null,
            'lokasi' => $data['lokasi'] ?? null,
            'sumber_dana' => $data['sumber_dana'] ?? null,
            'prioritas_nasional' => $data['prioritas_nasional'] ?? null,
            'prioritas_daerah' => $data['prioritas_daerah'] ?? null,
            'kelompok_sasaran' => $data['kelompok_sasaran'] ?? null,
            'prakiraan_maju_target' => $data['prakiraan_maju_target'] ?? null,
            'prakiraan_maju_pagu_indikatif' => $data['prakiraan_maju_pagu_indikatif'] ?? null,
            'perangkat_daerah_penanggung_jawab' => $data['perangkat_daerah_penanggung_jawab'] ?? null,
            ...$overrides,
        ];
    }
}
