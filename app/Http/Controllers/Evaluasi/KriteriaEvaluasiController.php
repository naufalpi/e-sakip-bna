<?php

namespace App\Http\Controllers\Evaluasi;

use App\Http\Controllers\Controller;
use App\Models\KomponenEvaluasi;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class KriteriaEvaluasiController extends Controller
{
    public function __invoke(Request $request): Response
    {
        abort_unless($request->user()->hasAnyPermission(['evaluasi.view', 'evaluasi.manage', 'manage_evaluasi']), 403);

        $komponen = KomponenEvaluasi::query()
            ->with('subKomponen.kriteria')
            ->orderBy('urutan')
            ->get()
            ->map(fn (KomponenEvaluasi $komponen) => [
                'id' => $komponen->id,
                'kode' => $komponen->kode,
                'nama' => $komponen->nama,
                'bobot' => $komponen->bobot,
                'status' => $komponen->status,
                'sub_komponen' => $komponen->subKomponen->map(fn ($sub) => [
                    'id' => $sub->id,
                    'kode' => $sub->kode,
                    'nama' => $sub->nama,
                    'bobot' => $sub->bobot,
                    'status' => $sub->status,
                    'kriteria' => $sub->kriteria->map(fn ($kriteria) => [
                        'id' => $kriteria->id,
                        'kode' => $kriteria->kode,
                        'nama' => $kriteria->nama,
                        'bobot' => $kriteria->bobot,
                        'nilai_maksimal' => $kriteria->nilai_maksimal,
                        'status' => $kriteria->status,
                    ]),
                ]),
            ]);

        return Inertia::render('EvaluasiSakip/Kriteria', [
            'komponen' => $komponen,
        ]);
    }
}
