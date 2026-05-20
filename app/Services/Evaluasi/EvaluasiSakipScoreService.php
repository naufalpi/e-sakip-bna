<?php

namespace App\Services\Evaluasi;

use App\Models\EvaluasiSakip;
use App\Models\EvaluasiSakipItem;
use App\Models\PredikatEvaluasi;

class EvaluasiSakipScoreService
{
    public function skorItem(float $nilai, float $nilaiMaksimal, float $bobot): float
    {
        if ($nilaiMaksimal <= 0) {
            return 0;
        }

        return round(min($nilai, $nilaiMaksimal) / $nilaiMaksimal * $bobot, 2);
    }

    public function recalculate(EvaluasiSakip $evaluasiSakip): void
    {
        $nilaiAkhir = (float) EvaluasiSakipItem::query()
            ->where('evaluasi_sakip_id', $evaluasiSakip->id)
            ->sum('skor');

        $predikat = $this->predikatModel($nilaiAkhir);

        $evaluasiSakip->forceFill([
            'nilai_akhir' => round($nilaiAkhir, 2),
            'predikat' => $predikat?->kode ?? $this->predikat($nilaiAkhir),
            'predikat_evaluasi_id' => $predikat?->id,
        ])->save();

        if ($evaluasiSakip->lhe) {
            $evaluasiSakip->lhe->update([
                'nilai_akhir' => round($nilaiAkhir, 2),
                'predikat' => $predikat?->kode ?? $this->predikat($nilaiAkhir),
                'predikat_evaluasi_id' => $predikat?->id,
            ]);
        }
    }

    public function predikat(float $nilai): string
    {
        $predikat = $this->predikatModel($nilai);

        if ($predikat) {
            return $predikat->kode;
        }

        return match (true) {
            $nilai >= 90 => 'AA',
            $nilai >= 80 => 'A',
            $nilai >= 70 => 'BB',
            $nilai >= 60 => 'B',
            $nilai >= 50 => 'CC',
            $nilai >= 30 => 'C',
            default => 'D',
        };
    }

    public function predikatModel(float $nilai): ?PredikatEvaluasi
    {
        return PredikatEvaluasi::query()
            ->where('is_active', true)
            ->where('nilai_min', '<=', $nilai)
            ->where('nilai_max', '>=', $nilai)
            ->orderByDesc('nilai_min')
            ->first();
    }
}
