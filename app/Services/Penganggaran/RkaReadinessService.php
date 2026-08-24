<?php

namespace App\Services\Penganggaran;

use App\Models\RkaOpd;
use App\Models\RkaOpdItem;
use Illuminate\Validation\ValidationException;

class RkaReadinessService
{
    /** @return array{ready: bool, issues: array<int, string>, incomplete_items: int} */
    public function inspect(RkaOpd $rka): array
    {
        $issues = [];
        $headerFields = [
            'nomor_kua' => 'Nomor KUA',
            'tanggal_kua' => 'Tanggal KUA',
            'nomor_ppas' => 'Nomor PPAS',
            'tanggal_ppas' => 'Tanggal PPAS',
        ];

        foreach ($headerFields as $field => $label) {
            if (blank($rka->getAttribute($field))) {
                $issues[] = "{$label} belum diisi";
            }
        }

        $rka->loadMissing('items');
        if ($rka->items->isEmpty()) {
            $issues[] = 'Rincian sub kegiatan belum tersedia';
        }

        $requiredItemFields = [
            'tolok_ukur_kinerja' => 'tolok ukur kinerja',
            'target_kinerja' => 'target kinerja',
            'sumber_pendanaan' => 'sumber pendanaan',
            'lokasi' => 'lokasi',
            'kelompok_sasaran' => 'kelompok sasaran',
        ];

        $incompleteItems = $rka->items->filter(function (RkaOpdItem $item) use ($requiredItemFields): bool {
            foreach (array_keys($requiredItemFields) as $field) {
                if (blank($item->getAttribute($field))) {
                    return true;
                }
            }

            return ! $this->hasConsistentBudgetBreakdown($item);
        });

        if ($incompleteItems->isNotEmpty()) {
            $missingLabels = collect($requiredItemFields)
                ->filter(fn (string $label, string $field) => $incompleteItems->contains(fn (RkaOpdItem $item) => blank($item->getAttribute($field))))
                ->values();
            if ($incompleteItems->contains(fn (RkaOpdItem $item) => ! $this->hasConsistentBudgetBreakdown($item))) {
                $missingLabels->push('rincian jenis belanja');
            }
            $issues[] = "{$incompleteItems->count()} sub kegiatan belum lengkap ({$missingLabels->implode(', ')})";
        }

        return [
            'ready' => $issues === [],
            'issues' => $issues,
            'incomplete_items' => $incompleteItems->count(),
        ];
    }

    public function ensureReady(RkaOpd $rka): void
    {
        $readiness = $this->inspect($rka);
        if (! $readiness['ready']) {
            throw ValidationException::withMessages([
                'action' => 'RKA belum dapat diajukan. '.implode('; ', $readiness['issues']).'.',
            ]);
        }
    }

    private function hasConsistentBudgetBreakdown(RkaOpdItem $item): bool
    {
        $paguRka = collect(['operasi', 'modal', 'tidak_terduga', 'transfer'])
            ->sum(fn (string $type) => (float) $item->getAttribute("pagu_belanja_{$type}"));

        return abs($paguRka - (float) $item->pagu_rka) <= 0.01;
    }
}
