<?php

namespace App\Http\Controllers\Evaluasi;

use App\Http\Controllers\Controller;
use App\Http\Requests\Evaluasi\StoreTindakLanjutRekomendasiRequest;
use App\Http\Requests\Evaluasi\VerifyTindakLanjutRekomendasiRequest;
use App\Models\RekomendasiEvaluasi;
use App\Models\TindakLanjutRekomendasi;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

class TindakLanjutRekomendasiController extends Controller
{
    public function store(StoreTindakLanjutRekomendasiRequest $request, RekomendasiEvaluasi $rekomendasi): RedirectResponse
    {
        $data = $request->validated();

        DB::transaction(function () use ($request, $rekomendasi, $data) {
            $rekomendasi->tindakLanjut()->create([
                ...$data,
                'opd_id' => $rekomendasi->opd_id,
                'created_by' => $request->user()->id,
            ]);

            $rekomendasi->update([
                'status_tindak_lanjut' => $data['status_tindak_lanjut'],
            ]);
        });

        return back()->with('success', 'Tindak lanjut rekomendasi berhasil dikirim.');
    }

    public function verify(VerifyTindakLanjutRekomendasiRequest $request, TindakLanjutRekomendasi $tindakLanjut): RedirectResponse
    {
        $data = $request->validated();

        DB::transaction(function () use ($request, $tindakLanjut, $data) {
            $tindakLanjut->update([
                'status_tindak_lanjut' => $data['status_tindak_lanjut'],
                'catatan_verifikator' => $data['catatan_verifikator'] ?? null,
                'diverifikasi_oleh' => $request->user()->id,
                'diverifikasi_at' => now(),
            ]);

            $tindakLanjut->rekomendasi()->update([
                'status_tindak_lanjut' => $data['status_tindak_lanjut'],
            ]);
        });

        return back()->with('success', 'Tindak lanjut rekomendasi berhasil diverifikasi.');
    }
}
