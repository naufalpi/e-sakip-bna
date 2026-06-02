<?php

namespace App\Http\Controllers\Kinerja;

use App\Http\Controllers\Controller;
use App\Http\Requests\Kinerja\StorePerjanjianKinerjaItemRequest;
use App\Models\IndikatorSasaranOpd;
use App\Models\OpdProgram;
use App\Models\PerjanjianKinerja;
use App\Models\PerjanjianKinerjaItem;
use App\Models\SasaranOpd;
use App\Services\Perencanaan\PerencanaanHierarchyValidationService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;

class PerjanjianKinerjaItemController extends Controller
{
    public function store(
        StorePerjanjianKinerjaItemRequest $request,
        PerjanjianKinerja $perjanjianKinerja,
        PerencanaanHierarchyValidationService $hierarchyValidation
    ): RedirectResponse {
        $this->authorize('update', $perjanjianKinerja);

        $data = $request->validated();
        $this->assertRelationsBelongToOpd($data, (int) $perjanjianKinerja->opd_id);
        $data = $hierarchyValidation->applyApprovedPerjanjianKinerjaTarget($perjanjianKinerja, $data);

        $perjanjianKinerja->items()->create($data);

        return back()->with('success', 'Item Perjanjian Kinerja berhasil ditambahkan.');
    }

    public function update(
        StorePerjanjianKinerjaItemRequest $request,
        PerjanjianKinerja $perjanjianKinerja,
        PerjanjianKinerjaItem $item,
        PerencanaanHierarchyValidationService $hierarchyValidation
    ): RedirectResponse {
        $this->authorize('update', $perjanjianKinerja);
        abort_unless((int) $item->perjanjian_kinerja_id === (int) $perjanjianKinerja->id, 404);

        $data = $request->validated();
        $this->assertRelationsBelongToOpd($data, (int) $perjanjianKinerja->opd_id);
        $data = $hierarchyValidation->applyApprovedPerjanjianKinerjaTarget($perjanjianKinerja, $data);

        $item->update($data);

        return back()->with('success', 'Item Perjanjian Kinerja berhasil diperbarui.');
    }

    public function destroy(PerjanjianKinerja $perjanjianKinerja, PerjanjianKinerjaItem $item): RedirectResponse
    {
        $this->authorize('update', $perjanjianKinerja);
        abort_unless((int) $item->perjanjian_kinerja_id === (int) $perjanjianKinerja->id, 404);

        $item->delete();

        return back()->with('success', 'Item Perjanjian Kinerja berhasil dihapus.');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function assertRelationsBelongToOpd(array $data, int $opdId): void
    {
        if (($data['sasaran_opd_id'] ?? null)
            && ! SasaranOpd::query()
                ->whereKey($data['sasaran_opd_id'])
                ->whereHas('tujuan.renstra', fn (Builder $query) => $query->where('opd_id', $opdId))
                ->exists()) {
            throw ValidationException::withMessages(['sasaran_opd_id' => 'Sasaran OPD tidak sesuai OPD Perjanjian Kinerja.']);
        }

        if (($data['indikator_sasaran_opd_id'] ?? null)
            && ! IndikatorSasaranOpd::query()
                ->whereKey($data['indikator_sasaran_opd_id'])
                ->whereHas('sasaran.tujuan.renstra', fn (Builder $query) => $query->where('opd_id', $opdId))
                ->exists()) {
            throw ValidationException::withMessages(['indikator_sasaran_opd_id' => 'Indikator sasaran OPD tidak sesuai OPD Perjanjian Kinerja.']);
        }

        if (($data['opd_program_id'] ?? null)
            && ! OpdProgram::query()
                ->whereKey($data['opd_program_id'])
                ->whereHas('renstra', fn (Builder $query) => $query->where('opd_id', $opdId))
                ->exists()) {
            throw ValidationException::withMessages(['opd_program_id' => 'Program OPD tidak sesuai OPD Perjanjian Kinerja.']);
        }
    }
}
