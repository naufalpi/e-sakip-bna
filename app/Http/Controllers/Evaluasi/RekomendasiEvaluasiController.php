<?php

namespace App\Http\Controllers\Evaluasi;

use App\Http\Controllers\Controller;
use App\Http\Requests\Evaluasi\StoreRekomendasiEvaluasiRequest;
use App\Models\EvaluasiSakip;
use App\Models\EvaluasiSakipItem;
use App\Models\RekomendasiEvaluasi;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;

class RekomendasiEvaluasiController extends Controller
{
    public function store(StoreRekomendasiEvaluasiRequest $request, EvaluasiSakip $evaluasiSakip): RedirectResponse
    {
        $data = $request->validated();
        $this->assertItemBelongsToEvaluasi($data['evaluasi_sakip_item_id'] ?? null, $evaluasiSakip);

        $evaluasiSakip->rekomendasi()->create([
            ...$data,
            'opd_id' => $evaluasiSakip->opd_id,
            'status_tindak_lanjut' => $data['status_tindak_lanjut'] ?? 'belum',
            'created_by' => $request->user()->id,
        ]);

        return back()->with('success', 'Rekomendasi evaluasi berhasil ditambahkan.');
    }

    public function update(StoreRekomendasiEvaluasiRequest $request, EvaluasiSakip $evaluasiSakip, RekomendasiEvaluasi $rekomendasi): RedirectResponse
    {
        $this->authorize('update', $evaluasiSakip);
        abort_unless((int) $rekomendasi->evaluasi_sakip_id === (int) $evaluasiSakip->id, 404);

        $data = $request->validated();
        $this->assertItemBelongsToEvaluasi($data['evaluasi_sakip_item_id'] ?? null, $evaluasiSakip);

        $rekomendasi->update($data);

        return back()->with('success', 'Rekomendasi evaluasi berhasil diperbarui.');
    }

    public function destroy(EvaluasiSakip $evaluasiSakip, RekomendasiEvaluasi $rekomendasi): RedirectResponse
    {
        $this->authorize('update', $evaluasiSakip);
        abort_unless((int) $rekomendasi->evaluasi_sakip_id === (int) $evaluasiSakip->id, 404);

        $rekomendasi->delete();

        return back()->with('success', 'Rekomendasi evaluasi berhasil dihapus.');
    }

    private function assertItemBelongsToEvaluasi(mixed $itemId, EvaluasiSakip $evaluasiSakip): void
    {
        if (! $itemId) {
            return;
        }

        if (! EvaluasiSakipItem::query()->whereKey($itemId)->where('evaluasi_sakip_id', $evaluasiSakip->id)->exists()) {
            throw ValidationException::withMessages(['evaluasi_sakip_item_id' => 'Item evaluasi tidak sesuai dengan evaluasi ini.']);
        }
    }
}
