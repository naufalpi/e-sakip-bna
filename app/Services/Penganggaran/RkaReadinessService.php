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

        $incompleteItems = $rka->items->filter(fn (RkaOpdItem $item): bool => collect(array_keys($requiredItemFields))
            ->contains(fn (string $field): bool => blank($item->getAttribute($field))));

        if ($incompleteItems->isNotEmpty()) {
            $missingLabels = collect($requiredItemFields)
                ->filter(fn (string $label, string $field) => $incompleteItems->contains(fn (RkaOpdItem $item) => blank($item->getAttribute($field))))
                ->values();
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
}
