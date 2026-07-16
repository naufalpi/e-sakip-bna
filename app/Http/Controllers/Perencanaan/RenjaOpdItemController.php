<?php

namespace App\Http\Controllers\Perencanaan;

use App\Http\Controllers\Controller;
use App\Http\Requests\Perencanaan\StoreRenjaOpdItemRequest;
use App\Http\Requests\Perencanaan\UpdateRenjaOpdItemRequest;
use App\Models\RenjaOpd;
use App\Models\RenjaOpdItem;
use App\Models\SubKegiatanPemerintahan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class RenjaOpdItemController extends Controller
{
    public function store(StoreRenjaOpdItemRequest $request, RenjaOpd $renjaOpd): RedirectResponse
    {
        $subKegiatan = $this->subKegiatan((int) $request->validated('sub_kegiatan_pemerintahan_id'));

        $renjaOpd->items()->create($this->payload($request->validated(), $subKegiatan, [
            'status' => $request->validated('status') ?: 'draft',
            'urutan' => $request->validated('urutan') ?: ((int) $renjaOpd->items()->max('urutan')) + 1,
        ]));

        return back()->with('success', 'Baris Renja OPD berhasil disimpan.');
    }

    public function update(UpdateRenjaOpdItemRequest $request, RenjaOpd $renjaOpd, RenjaOpdItem $item): RedirectResponse
    {
        abort_unless((int) $item->renja_opd_id === (int) $renjaOpd->id, 404);

        $subKegiatan = $this->subKegiatan((int) $request->validated('sub_kegiatan_pemerintahan_id'));

        $item->update($this->payload($request->validated(), $subKegiatan, [
            'status' => $request->validated('status') ?: $item->status,
            'urutan' => $request->validated('urutan') ?: $item->urutan,
        ]));

        return back()->with('success', 'Baris Renja OPD berhasil diperbarui.');
    }

    public function destroy(Request $request, RenjaOpd $renjaOpd, RenjaOpdItem $item): RedirectResponse
    {
        abort_unless($request->user()->can('update', $renjaOpd), 403);
        abort_unless((int) $item->renja_opd_id === (int) $renjaOpd->id, 404);

        $item->delete();

        return back()->with('success', 'Baris Renja OPD berhasil dihapus.');
    }

    private function subKegiatan(int $id): SubKegiatanPemerintahan
    {
        return SubKegiatanPemerintahan::query()
            ->with('kegiatanPemerintahan.programPemerintahan')
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

        return [
            'program_pemerintahan_id' => $program?->id,
            'kegiatan_pemerintahan_id' => $kegiatan?->id,
            'sub_kegiatan_pemerintahan_id' => $subKegiatan->id,
            'indikator_sub_kegiatan_id' => $data['indikator_sub_kegiatan_id'] ?? null,
            'kode' => $subKegiatan->kode,
            'nama_sub_kegiatan' => $subKegiatan->nama,
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
            ...$overrides,
        ];
    }
}
