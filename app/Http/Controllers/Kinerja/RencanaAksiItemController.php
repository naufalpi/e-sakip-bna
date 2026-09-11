<?php

namespace App\Http\Controllers\Kinerja;

use App\Http\Controllers\Controller;
use App\Http\Requests\Kinerja\StoreRencanaAksiItemRequest;
use App\Http\Requests\Kinerja\UpdateRencanaAksiMatrixRequest;
use App\Models\OpdKegiatan;
use App\Models\OpdProgram;
use App\Models\OpdSubKegiatan;
use App\Models\PerjanjianKinerjaItem;
use App\Models\RencanaAksi;
use App\Models\RencanaAksiItem;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RencanaAksiItemController extends Controller
{
    public function updateMatrix(UpdateRencanaAksiMatrixRequest $request, RencanaAksi $rencanaAksi): RedirectResponse
    {
        $this->authorize('update', $rencanaAksi);
        abort_unless((int) $rencanaAksi->format_version >= 2, 404);
        if (! in_array($rencanaAksi->status, ['draft', 'revision', 'rejected'], true)) {
            throw ValidationException::withMessages([
                'items' => 'Matriks hanya dapat diubah saat Rencana Aksi berstatus Draft, Perlu Perbaikan, atau Ditolak.',
            ]);
        }

        $rows = collect($request->validated('items'));
        $expectedIds = $rencanaAksi->items()->where('is_snapshot', true)->pluck('id')->sort()->values();
        $submittedIds = $rows->pluck('id')->map(fn ($id) => (int) $id)->sort()->values();
        if ($expectedIds->all() !== $submittedIds->all()) {
            throw ValidationException::withMessages([
                'items' => 'Data matriks berubah atau tidak lengkap. Muat ulang halaman sebelum menyimpan.',
            ]);
        }
        if ($rows->contains(fn (array $row) => collect($row['target_triwulan'])->pluck('triwulan')->map(fn ($quarter) => (int) $quarter)->sort()->values()->all() !== [1, 2, 3, 4])) {
            throw ValidationException::withMessages([
                'items' => 'Setiap baris harus memiliki target Triwulan I, II, III, dan IV tepat satu kali.',
            ]);
        }

        DB::transaction(function () use ($rows, $rencanaAksi): void {
            $items = $rencanaAksi->items()->whereIn('id', $rows->pluck('id'))->get()->keyBy('id');
            foreach ($rows as $data) {
                $item = $items->get((int) $data['id']);
                $item->update([
                    'formula' => trim($data['formula']),
                    'penanggung_jawab' => trim($data['penanggung_jawab']),
                ]);

                foreach ($data['target_triwulan'] as $quarter) {
                    $text = trim($quarter['target_text']);
                    $item->targetTriwulan()->updateOrCreate(
                        ['triwulan' => (int) $quarter['triwulan']],
                        ['target_text' => $text, 'target' => is_numeric($text) ? (float) $text : null],
                    );
                }
            }
        });

        return back()->with('success', 'Formula, target triwulan, dan penanggung jawab berhasil disimpan.');
    }

    public function store(StoreRencanaAksiItemRequest $request, RencanaAksi $rencanaAksi): RedirectResponse
    {
        $this->authorize('update', $rencanaAksi);

        $data = $request->validated();
        $this->assertRelationsBelongToOpd($data, (int) $rencanaAksi->opd_id);

        $rencanaAksi->items()->create($data);

        return back()->with('success', 'Item Rencana Aksi berhasil ditambahkan.');
    }

    public function update(StoreRencanaAksiItemRequest $request, RencanaAksi $rencanaAksi, RencanaAksiItem $item): RedirectResponse
    {
        $this->authorize('update', $rencanaAksi);
        abort_unless((int) $item->rencana_aksi_id === (int) $rencanaAksi->id, 404);

        $data = $request->validated();
        $this->assertRelationsBelongToOpd($data, (int) $rencanaAksi->opd_id);

        $item->update($data);

        return back()->with('success', 'Item Rencana Aksi berhasil diperbarui.');
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
