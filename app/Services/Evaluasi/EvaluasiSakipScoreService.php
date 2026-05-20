<?php

namespace App\Services\Evaluasi;

use App\Models\EvaluasiSakip;
use App\Models\EvaluasiSakipItem;

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

        $evaluasiSakip->forceFill([
            'nilai_akhir' => round($nilaiAkhir, 2),
            'predikat' => $this->predikat($nilaiAkhir),
        ])->save();

        if ($evaluasiSakip->lhe) {
            $evaluasiSakip->lhe->update([
                'nilai_akhir' => round($nilaiAkhir, 2),
                'predikat' => $this->predikat($nilaiAkhir),
            ]);
        }
    }

    public function predikat(float $nilai): string
    {
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
}
