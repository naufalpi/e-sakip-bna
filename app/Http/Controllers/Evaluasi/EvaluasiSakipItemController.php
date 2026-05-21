<?php

namespace App\Http\Controllers\Evaluasi;

use App\Http\Controllers\Controller;
use App\Http\Requests\Evaluasi\StoreEvaluasiSakipItemRequest;
use App\Models\EvaluasiSakip;
use App\Models\EvaluasiSakipItem;
use App\Models\KriteriaEvaluasi;
use App\Services\Evaluasi\EvaluasiSakipScoreService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;

class EvaluasiSakipItemController extends Controller
{
    public function store(StoreEvaluasiSakipItemRequest $request, EvaluasiSakip $evaluasiSakip, EvaluasiSakipScoreService $scoreService): RedirectResponse
    {
        $data = $request->validated();
        $kriteria = KriteriaEvaluasi::query()->findOrFail($data['kriteria_evaluasi_id']);
        $skor = $scoreService->skorItem((float) $data['nilai'], (float) $kriteria->nilai_maksimal, (float) $kriteria->bobot);

        $evaluasiSakip->items()->updateOrCreate([
            'kriteria_evaluasi_id' => $kriteria->id,
        ], [
            'nilai' => $data['nilai'],
            'skor' => $skor,
            'catatan' => $data['catatan'] ?? null,
            'rekomendasi_text' => $data['rekomendasi_text'] ?? null,
        ]);

        $scoreService->recalculate($evaluasiSakip->fresh('lhe'));

        return back()->with('success', 'Nilai kriteria evaluasi berhasil disimpan.');
    }

    public function update(StoreEvaluasiSakipItemRequest $request, EvaluasiSakip $evaluasiSakip, EvaluasiSakipItem $item, EvaluasiSakipScoreService $scoreService): RedirectResponse
    {
        $this->authorize('update', $evaluasiSakip);
        abort_unless((int) $item->evaluasi_sakip_id === (int) $evaluasiSakip->id, 404);

        $data = $request->validated();
        $kriteria = KriteriaEvaluasi::query()->findOrFail($data['kriteria_evaluasi_id']);

        $duplicate = EvaluasiSakipItem::query()
            ->where('evaluasi_sakip_id', $evaluasiSakip->id)
            ->where('kriteria_evaluasi_id', $kriteria->id)
            ->whereKeyNot($item->id)
            ->exists();

        if ($duplicate) {
            throw ValidationException::withMessages(['kriteria_evaluasi_id' => 'Kriteria ini sudah dinilai pada evaluasi ini.']);
        }

        $item->update([
            'kriteria_evaluasi_id' => $kriteria->id,
            'nilai' => $data['nilai'],
            'skor' => $scoreService->skorItem((float) $data['nilai'], (float) $kriteria->nilai_maksimal, (float) $kriteria->bobot),
            'catatan' => $data['catatan'] ?? null,
            'rekomendasi_text' => $data['rekomendasi_text'] ?? null,
        ]);

        $scoreService->recalculate($evaluasiSakip->fresh('lhe'));

        return back()->with('success', 'Nilai kriteria evaluasi berhasil diperbarui.');
    }

    public function destroy(EvaluasiSakip $evaluasiSakip, EvaluasiSakipItem $item, EvaluasiSakipScoreService $scoreService): RedirectResponse
    {
        $this->authorize('update', $evaluasiSakip);
        abort_unless((int) $item->evaluasi_sakip_id === (int) $evaluasiSakip->id, 404);

        $item->delete();
        $scoreService->recalculate($evaluasiSakip->fresh('lhe'));

        return back()->with('success', 'Nilai kriteria evaluasi berhasil dihapus.');
    }
}
