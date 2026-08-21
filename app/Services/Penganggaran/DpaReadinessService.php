<?php

namespace App\Services\Penganggaran;

use App\Models\DpaOpd;
use App\Models\DpaOpdItem;
use Illuminate\Validation\ValidationException;

class DpaReadinessService
{
    /** @return array{ready: bool, issues: array<int, string>, mismatched_items: int} */
    public function inspect(DpaOpd $dpa, bool $forApproval = false): array
    {
        $issues = [];
        $requiredHeader = [
            'nomor_perda_apbd' => 'Nomor Perda APBD',
            'tanggal_perda_apbd' => 'Tanggal Perda APBD',
            'nomor_perkada_penjabaran' => 'Nomor Perkada Penjabaran APBD',
            'tanggal_perkada_penjabaran' => 'Tanggal Perkada Penjabaran APBD',
            'nama_pengguna_anggaran' => 'Nama Pengguna Anggaran',
            'nip_pengguna_anggaran' => 'NIP Pengguna Anggaran',
        ];

        if ($forApproval) {
            $requiredHeader += [
                'nomor_dpa' => 'Nomor DPA',
                'tanggal_pengesahan' => 'Tanggal pengesahan',
                'nama_ppkd' => 'Nama PPKD',
                'nip_ppkd' => 'NIP PPKD',
                'nama_sekretaris_daerah' => 'Nama Sekretaris Daerah',
                'nip_sekretaris_daerah' => 'NIP Sekretaris Daerah',
            ];
        }

        foreach ($requiredHeader as $field => $label) {
            if (blank($dpa->getAttribute($field))) {
                $issues[] = "{$label} belum diisi";
            }
        }

        $dpa->loadMissing('items.cashPlans');
        if ($dpa->items->isEmpty()) {
            $issues[] = 'Rincian sub kegiatan belum tersedia';
        }

        $mismatched = $dpa->items->filter(function (DpaOpdItem $item): bool {
            return $item->cashPlans->count() !== 12
                || abs((float) $item->cashPlans->sum('jumlah') - (float) $item->pagu_dpa) > 0.01;
        });

        if ($mismatched->isNotEmpty()) {
            $issues[] = $mismatched->count().' sub kegiatan memiliki rencana penarikan yang tidak sama dengan pagu DPA';
        }

        return [
            'ready' => $issues === [],
            'issues' => $issues,
            'mismatched_items' => $mismatched->count(),
        ];
    }

    public function ensureReadyForSubmit(DpaOpd $dpa): void
    {
        $this->ensureReady($dpa, false, 'diajukan');
    }

    public function ensureReadyForApproval(DpaOpd $dpa): void
    {
        $this->ensureReady($dpa, true, 'disetujui');
    }

    private function ensureReady(DpaOpd $dpa, bool $forApproval, string $action): void
    {
        $readiness = $this->inspect($dpa, $forApproval);
        if (! $readiness['ready']) {
            throw ValidationException::withMessages([
                'action' => 'DPA belum dapat '.$action.'. '.implode('; ', $readiness['issues']).'.',
            ]);
        }
    }
}
