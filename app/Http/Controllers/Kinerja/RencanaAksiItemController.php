<?php

namespace App\Http\Controllers\Kinerja;

use App\Http\Controllers\Controller;
use App\Http\Requests\Kinerja\StoreRencanaAksiItemRequest;
use App\Models\OpdKegiatan;
use App\Models\OpdProgram;
use App\Models\OpdSubKegiatan;
use App\Models\PerjanjianKinerjaItem;
use App\Models\RencanaAksi;
use App\Models\RencanaAksiItem;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;

class RencanaAksiItemController extends Controller
{
    public function store(StoreRencanaAksiItemRequest $request, RencanaAksi $rencanaAksi): RedirectResponse
    {
        $data = $request->validated();
        $this->assertRelationsBelongToOpd($data, (int) $rencanaAksi->opd_id);

        $rencanaAksi->items()->create($data);

        return back()->with('success', 'Item Rencana Aksi berhasil ditambahkan.');
    }

    public function destroy(RencanaAksi $rencanaAksi, RencanaAksiItem $item): RedirectResponse
    {
        $this->authorize('update', $rencanaAksi);
        abort_unless((int) $item->rencana_aksi_id === (int) $rencanaAksi->id, 404);

        $item->delete();

        return back()->with('success', 'Item Rencana Aksi berhasil dihapus.');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function assertRelationsBelongToOpd(array $data, int $opdId): void
    {
        if (($data['perjanjian_kinerja_item_id'] ?? null)
            && ! PerjanjianKinerjaItem::query()
                ->whereKey($data['perjanjian_kinerja_item_id'])
                ->whereHas('perjanjianKinerja', fn (Builder $query) => $query->where('opd_id', $opdId))
                ->exists()) {
            throw ValidationException::withMessages(['perjanjian_kinerja_item_id' => 'Item Perjanjian Kinerja tidak sesuai OPD Rencana Aksi.']);
        }

        if (($data['opd_program_id'] ?? null)
            && ! OpdProgram::query()
                ->whereKey($data['opd_program_id'])
                ->whereHas('renstra', fn (Builder $query) => $query->where('opd_id', $opdId))
                ->exists()) {
            throw ValidationException::withMessages(['opd_program_id' => 'Program OPD tidak sesuai OPD Rencana Aksi.']);
        }

        if (($data['opd_kegiatan_id'] ?? null)
            && ! OpdKegiatan::query()
                ->whereKey($data['opd_kegiatan_id'])
                ->whereHas('program.renstra', fn (Builder $query) => $query->where('opd_id', $opdId))
                ->exists()) {
            throw ValidationException::withMessages(['opd_kegiatan_id' => 'Kegiatan OPD tidak sesuai OPD Rencana Aksi.']);
        }

        if (($data['opd_sub_kegiatan_id'] ?? null)
            && ! OpdSubKegiatan::query()
                ->whereKey($data['opd_sub_kegiatan_id'])
                ->whereHas('kegiatan.program.renstra', fn (Builder $query) => $query->where('opd_id', $opdId))
                ->exists()) {
            throw ValidationException::withMessages(['opd_sub_kegiatan_id' => 'Sub kegiatan OPD tidak sesuai OPD Rencana Aksi.']);
        }
    }
}
