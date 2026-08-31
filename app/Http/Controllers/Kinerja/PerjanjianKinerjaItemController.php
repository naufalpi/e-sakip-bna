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

        if (in_array($perjanjianKinerja->level_pk, ['bupati', 'kepala_opd'], true)) {
            throw ValidationException::withMessages([
                'sasaran' => 'Matriks PK Bupati dan PK Kepala OPD dibuat otomatis dari dokumen sumber resmi dan tidak dapat ditambah manual.',
            ]);
        }

        $data = $request->validated();
        $data = $this->normalizeItemSource($perjanjianKinerja, $data);
        $this->assertRelationsBelongToOpd($data, (int) $perjanjianKinerja->opd_id);
        if ($perjanjianKinerja->tipe_pk === 'cascading') {
            $data = $hierarchyValidation->applyApprovedPerjanjianKinerjaTarget($perjanjianKinerja, $data);
        }

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

        if ($item->is_readonly) {
            throw ValidationException::withMessages([
                'sasaran' => 'Item snapshot dari dokumen sumber resmi tidak dapat diubah. Perbaiki dokumen sumber lalu buat ulang snapshot PK.',
            ]);
        }

        $data = $request->validated();
        $data = $this->normalizeItemSource($perjanjianKinerja, $data);
        $this->assertRelationsBelongToOpd($data, (int) $perjanjianKinerja->opd_id);
        if ($perjanjianKinerja->tipe_pk === 'cascading') {
            $data = $hierarchyValidation->applyApprovedPerjanjianKinerjaTarget($perjanjianKinerja, $data);
        }

        $item->update($data);

        return back()->with('success', 'Item Perjanjian Kinerja berhasil diperbarui.');
    }

    public function destroy(PerjanjianKinerja $perjanjianKinerja, PerjanjianKinerjaItem $item): RedirectResponse
    {
        $this->authorize('update', $perjanjianKinerja);
        abort_unless((int) $item->perjanjian_kinerja_id === (int) $perjanjianKinerja->id, 404);

        if ($item->is_readonly) {
            return back()->with('error', 'Item snapshot dari dokumen sumber resmi tidak dapat dihapus.');
        }

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

    private function normalizeItemSource(PerjanjianKinerja $pk, array $data): array
    {
        if ($pk->tipe_pk === 'individual') {
            return [
                ...$data,
                'sumber_item' => 'manual',
                'level_cascading' => null,
                'cascading_source_type' => null,
                'cascading_source_id' => null,
                'sasaran_opd_id' => null,
                'indikator_sasaran_opd_id' => null,
                'opd_program_id' => null,
            ];
        }

        return [...$data, 'sumber_item' => 'cascading', 'jenis_item' => $data['level_cascading'] ?? 'manual'];
    }
}
