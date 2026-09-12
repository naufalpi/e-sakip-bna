<?php

namespace App\Http\Controllers\Kinerja;

use App\Http\Controllers\Controller;
use App\Http\Requests\Kinerja\StorePerjanjianKinerjaItemRequest;
use App\Http\Requests\Kinerja\UpdatePerjanjianKinerjaItemTargetRequest;
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

        if ($this->usesReadonlySnapshot($perjanjianKinerja)) {
            throw ValidationException::withMessages([
                'sasaran' => 'Matriks PK Cascading dibentuk dari lingkup kinerja pada dokumen PK dan tidak dapat ditambah manual.',
            ]);
        }

        $data = $request->validated();
        $data = $this->normalizeItemSource($perjanjianKinerja, $data);
        if ($perjanjianKinerja->tipe_pk === 'individual') {
            $data['urutan'] = ((int) $perjanjianKinerja->items()->max('urutan')) + 1;
        }
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

        if ($this->usesReadonlySnapshot($perjanjianKinerja) || $item->is_readonly) {
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

        if ($this->usesReadonlySnapshot($perjanjianKinerja) || $item->is_readonly) {
            return back()->with('error', 'Item snapshot dari dokumen sumber resmi tidak dapat dihapus.');
        }

        $item->delete();

        return back()->with('success', 'Item Perjanjian Kinerja berhasil dihapus.');
    }

    public function updateTarget(
        UpdatePerjanjianKinerjaItemTargetRequest $request,
        PerjanjianKinerja $perjanjianKinerja,
        PerjanjianKinerjaItem $item,
    ): RedirectResponse {
        $this->authorize('update', $perjanjianKinerja);
        abort_unless((int) $item->perjanjian_kinerja_id === (int) $perjanjianKinerja->id, 404);

        if ($perjanjianKinerja->tipe_pk !== 'cascading'
            || $item->sumber_item !== 'snapshot'
            || ! $item->is_readonly
            || blank($item->cascading_source_type)
            || ! $item->cascading_source_id) {
            throw ValidationException::withMessages([
                'target_text' => 'Hanya target item snapshot cascading yang dapat disesuaikan melalui tindakan ini.',
            ]);
        }

        $data = $request->validated();
        if ($data['restore_source'] ?? false) {
            if ($item->target_sumber === null && blank($item->target_sumber_text)) {
                throw ValidationException::withMessages([
                    'target_text' => 'Target sumber tidak tersedia sehingga belum dapat dipulihkan.',
                ]);
            }

            $item->update([
                'target' => $item->target_sumber,
                'target_text' => $item->target_sumber_text,
                'target_disesuaikan' => false,
            ]);

            return back()->with('success', 'Target PK berhasil dikembalikan ke target dokumen sumber.');
        }

        $targetText = trim((string) $data['target_text']);
        $item->update([
            'target' => $this->numericTarget($targetText),
            'target_text' => $targetText,
            'target_disesuaikan' => ! $this->targetMatchesSource($item, $targetText),
        ]);

        return back()->with('success', 'Target PK berhasil disesuaikan tanpa mengubah dokumen sumber.');
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
                'sumber_item' => 'manual',
                'jenis_item' => 'manual',
                'level_cascading' => null,
                'cascading_source_type' => null,
                'cascading_source_id' => null,
                'sasaran_opd_id' => null,
                'indikator_sasaran_opd_id' => null,
                'opd_program_id' => null,
                'satuan_indikator_id' => null,
                'satuan_snapshot' => null,
                'kode' => null,
                'sasaran' => $data['sasaran'],
                'indikator' => $data['indikator'],
                'target' => null,
                'target_text' => $data['target_text'] ?? null,
                'urutan' => $data['urutan'] ?? 1,
            ];
        }

        return [...$data, 'sumber_item' => 'cascading', 'jenis_item' => $data['level_cascading'] ?? 'manual'];
    }

    private function usesReadonlySnapshot(PerjanjianKinerja $pk): bool
    {
        return $pk->tipe_pk === 'cascading'
            && in_array($pk->sumber_data, ['rkpd', 'dpa', 'renstra_cascading'], true);
    }

    private function targetMatchesSource(PerjanjianKinerjaItem $item, string $targetText): bool
    {
        $source = filled($item->target_sumber_text)
            ? (string) $item->target_sumber_text
            : ($item->target_sumber !== null ? (string) $item->target_sumber : '');
        $sourceNumeric = $this->numericTarget($source);
        $targetNumeric = $this->numericTarget($targetText);

        if ($sourceNumeric !== null && $targetNumeric !== null) {
            return abs($sourceNumeric - $targetNumeric) < 0.0001;
        }

        return mb_strtolower(preg_replace('/\s+/', ' ', trim($source)) ?? '')
            === mb_strtolower(preg_replace('/\s+/', ' ', $targetText) ?? '');
    }

    private function numericTarget(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        $normalized = str_replace(' ', '', trim((string) $value));
        if (! preg_match('/^-?\d+(?:[.,]\d+)?$/', $normalized)) {
            return null;
        }

        return (float) str_replace(',', '.', $normalized);
    }
}
