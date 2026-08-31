<?php

namespace App\Http\Controllers\Perencanaan;

use App\Http\Controllers\Controller;
use App\Http\Requests\Perencanaan\StoreRenjaOpdItemRequest;
use App\Http\Requests\Perencanaan\UpdateRenjaOpdItemRequest;
use App\Models\RenjaOpd;
use App\Models\RenjaOpdItem;
use App\Models\SubKegiatanPemerintahan;
use App\Services\Perencanaan\RenjaProgramScopeService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class RenjaOpdItemController extends Controller
{
    public function __construct(private readonly RenjaProgramScopeService $renjaProgramScopeService) {}

    public function store(StoreRenjaOpdItemRequest $request, RenjaOpd $renjaOpd): RedirectResponse
    {
        $this->ensureSubKegiatanAvailable($renjaOpd, (int) $request->validated('sub_kegiatan_pemerintahan_id'));
        $subKegiatan = $this->subKegiatan((int) $request->validated('sub_kegiatan_pemerintahan_id'), $renjaOpd);

        try {
            $renjaOpd->items()->create($this->payload($request->validated(), $subKegiatan, [
                'opd_sub_kegiatan_id' => null,
                'sumber_item' => 'manual',
                'status' => $request->validated('status') ?: 'draft',
                'urutan' => $request->validated('urutan') ?: ((int) $renjaOpd->items()->max('urutan')) + 1,
            ]));
        } catch (QueryException $exception) {
            if ($this->isActiveSubKegiatanDuplicate($exception)) {
                throw ValidationException::withMessages([
                    'sub_kegiatan_pemerintahan_id' => 'Sub kegiatan tersebut sudah diinput pada Renja OPD ini.',
                ]);
            }

            throw $exception;
        }

        return back()->with('success', 'Sub kegiatan RENJA berhasil disimpan.');
    }

    public function update(UpdateRenjaOpdItemRequest $request, RenjaOpd $renjaOpd, RenjaOpdItem $item): RedirectResponse
    {
        abort_unless((int) $item->renja_opd_id === (int) $renjaOpd->id, 404);

        $requestedSubKegiatanId = (int) $request->validated('sub_kegiatan_pemerintahan_id');

        if ($item->isFromRenstra() && $requestedSubKegiatanId !== (int) $item->sub_kegiatan_pemerintahan_id) {
            throw ValidationException::withMessages([
                'sub_kegiatan_pemerintahan_id' => 'Struktur Program, Kegiatan, dan Sub Kegiatan hasil salinan RENSTRA tidak dapat diubah. Hapus baris ini jika tidak digunakan pada tahun RENJA.',
            ]);
        }

        $this->ensureSubKegiatanAvailable($renjaOpd, $requestedSubKegiatanId, $item->id);

        $subKegiatan = $item->isFromRenstra()
            ? $item->subKegiatanPemerintahan()->with('kegiatanPemerintahan.programPemerintahan')->firstOrFail()
            : $this->subKegiatan($requestedSubKegiatanId, $renjaOpd);

        $overrides = [
            'status' => $request->validated('status') ?: $item->status,
            'urutan' => $request->validated('urutan') ?: $item->urutan,
        ];

        if ($item->isFromRenstra()) {
            $overrides = [
                ...$overrides,
                'indikator_sub_kegiatan_id' => $item->indikator_sub_kegiatan_id,
                'indikator' => $item->indikator,
                'target_akhir_renstra' => $item->target_akhir_renstra,
            ];
        }

        $item->update($this->payload($request->validated(), $subKegiatan, $overrides));

        return back()->with('success', 'Sub kegiatan RENJA berhasil diperbarui.');
    }

    public function destroy(Request $request, RenjaOpd $renjaOpd, RenjaOpdItem $item): RedirectResponse
    {
        abort_unless($request->user()->can('update', $renjaOpd), 403);
        abort_unless((int) $item->renja_opd_id === (int) $renjaOpd->id, 404);

        $item->delete();

        return back()->with('success', 'Sub kegiatan RENJA berhasil dihapus.');
    }

    private function subKegiatan(int $id, RenjaOpd $renjaOpd): SubKegiatanPemerintahan
    {
        $programPemerintahanIds = $this->renjaProgramScopeService->programPemerintahanIds($renjaOpd);

        abort_if($programPemerintahanIds === [], 422, 'Program Renja OPD belum tersedia. Lengkapi program Renstra/RPJMD terkait terlebih dahulu.');

        return SubKegiatanPemerintahan::query()
            ->with('kegiatanPemerintahan.programPemerintahan')
            ->where('periode_tahun_id', $renjaOpd->periode_tahun_id)
            ->whereHas('kegiatanPemerintahan', fn (Builder $query) => $query->whereIn('program_pemerintahan_id', $programPemerintahanIds))
            ->findOrFail($id);
    }

    private function ensureSubKegiatanAvailable(RenjaOpd $renjaOpd, int $subKegiatanId, ?int $exceptItemId = null): void
    {
        $exists = $renjaOpd->items()
            ->where('sub_kegiatan_pemerintahan_id', $subKegiatanId)
            ->when($exceptItemId, fn ($query) => $query->where('id', '!=', $exceptItemId))
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'sub_kegiatan_pemerintahan_id' => 'Sub kegiatan tersebut sudah diinput pada Renja OPD ini.',
            ]);
        }
    }

    private function isActiveSubKegiatanDuplicate(QueryException $exception): bool
    {
        $message = strtolower($exception->getMessage());

        return str_contains($message, 'renja_items_active_sub_kegiatan_unique')
            || (str_contains($message, 'unique constraint failed')
                && str_contains($message, 'renja_opd_items.renja_opd_id')
                && str_contains($message, 'renja_opd_items.sub_kegiatan_pemerintahan_id'));
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
