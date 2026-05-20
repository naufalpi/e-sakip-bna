<?php

namespace App\Http\Controllers\Evaluasi;

use App\Http\Controllers\Controller;
use App\Http\Requests\Evaluasi\StoreLheRequest;
use App\Models\EvaluasiSakip;
use Illuminate\Http\RedirectResponse;

class LheController extends Controller
{
    public function store(StoreLheRequest $request, EvaluasiSakip $evaluasiSakip): RedirectResponse
    {
        $data = $request->validated();

        $evaluasiSakip->lhe()->updateOrCreate([
            'evaluasi_sakip_id' => $evaluasiSakip->id,
        ], [
            ...$data,
            'nilai_akhir' => $evaluasiSakip->nilai_akhir,
            'predikat' => $evaluasiSakip->predikat,
            'disusun_oleh' => $request->user()->id,
        ]);

        return back()->with('success', 'LHE berhasil disimpan.');
    }
}
