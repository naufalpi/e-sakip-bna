<?php

namespace App\Services\Perencanaan;

use App\Models\RenjaOpd;
use Illuminate\Validation\ValidationException;

class RenjaAnnualTargetReadinessService
{
    public function __construct(private readonly RenjaAnnualTargetService $service) {}

    public function ensureReady(RenjaOpd $renja): void
    {
        if (! $this->service->available()) {
            return;
        }

        $this->service->bootstrap($renja);

        $firstMissing = $renja->annualTargets()
            ->whereNull('target_renja')
            ->whereNull('target_renja_text')
            ->orderBy('urutan')
            ->first();

        if ($firstMissing) {
            throw ValidationException::withMessages([
                'action' => "Target tahunan indikator “{$firstMissing->indikator_snapshot}” belum diisi. Buka Target Kinerja Tahunan dan isi target atau gunakan NA.",
            ]);
        }

        if (! $renja->annualTargets()->exists()) {
            throw ValidationException::withMessages([
                'action' => 'Target Kinerja Tahunan belum tersedia. Pastikan RENSTRA sumber memiliki indikator Tujuan, Sasaran Strategis, Program, dan Kegiatan.',
            ]);
        }
    }
}
