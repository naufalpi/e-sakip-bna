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
            'jenis_belanja' => 'jenis belanja',
        ];

        $incompleteItems = $rka->items->filter(function (RkaOpdItem $item) use ($requiredItemFields): bool {
            foreach (array_keys($requiredItemFields) as $field) {
                if (blank($item->getAttribute($field))) {
                    return true;
                }
            }

            return false;
        });

        if ($incompleteItems->isNotEmpty()) {
            $missingLabels = collect($requiredItemFields)
                ->filter(fn (string $label, string $field) => $incompleteItems->contains(fn (RkaOpdItem $item) => blank($item->getAttribute($field))))
                ->values()
                ->implode(', ');
            $issues[] = "{$incompleteItems->count()} sub kegiatan belum lengkap ({$missingLabels})";
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
