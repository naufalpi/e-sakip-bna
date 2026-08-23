<?php

namespace App\Services\Penganggaran;

use App\Models\RkaOpd;
use App\Models\RkaOpdItem;
use Illuminate\Support\Collection;

class RkaPreviewTableService
{
    /**
     * @return array{rows: array<int, array<string, mixed>>, total: array<string, float>, uses_verified_budget: bool}
     */
    public function build(RkaOpd $rka): array
    {
        $rka->loadMissing(['opd:id,kode,nama,singkatan', 'opdUnit:id,kode,nama']);
        $items = $rka->items()->get()->sort($this->compareItems(...))->values();
        $usesVerifiedBudget = in_array($rka->status, ['verified', 'approved', 'locked'], true);
        $rows = [];

        if ($items->isNotEmpty()) {
            $rows[] = $this->row(
                'opd',
                'opd-'.$rka->id,
                (string) ($rka->opd?->kode ?? ''),
                (string) ($rka->opd?->nama ?? 'Perangkat Daerah'),
                $items,
                $usesVerifiedBudget,
            );
        }

        $levels = [
            ['level' => 'urusan', 'code' => 'kode_urusan', 'name' => 'nama_urusan', 'fallback' => 'Urusan belum terpetakan'],
            ['level' => 'bidang', 'code' => 'kode_bidang', 'name' => 'nama_bidang', 'fallback' => 'Bidang urusan belum terpetakan'],
            ['level' => 'program', 'code' => 'kode_program', 'name' => 'nama_program', 'fallback' => 'Program belum terpetakan'],
            ['level' => 'kegiatan', 'code' => 'kode_kegiatan', 'name' => 'nama_kegiatan', 'fallback' => 'Kegiatan belum terpetakan'],
        ];

        $this->appendLevelRows($rows, $items, $levels, 0, $usesVerifiedBudget, '');

        return [
            'rows' => $rows,
            'total' => $this->budget($items, $usesVerifiedBudget),
            'uses_verified_budget' => $usesVerifiedBudget,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @param  Collection<int, RkaOpdItem>  $items
     * @param  array<int, array{level: string, code: string, name: string, fallback: string}>  $levels
     */
    private function appendLevelRows(
        array &$rows,
        Collection $items,
        array $levels,
        int $index,
        bool $usesVerifiedBudget,
        string $parentKey,
    ): void {
        if ($index >= count($levels)) {
            $items->each(function (RkaOpdItem $item) use (&$rows, $usesVerifiedBudget): void {
                $rows[] = [
                    ...$this->row(
                        'sub_kegiatan',
                        'sub-kegiatan-'.$item->id,
                        (string) ($item->kode_sub_kegiatan ?: '-'),
                        (string) ($item->nama_sub_kegiatan ?: 'Sub kegiatan belum terpetakan'),
                        collect([$item]),
                        $usesVerifiedBudget,
                    ),
                    'source' => (string) ($item->sumber_pendanaan ?: '-'),
                    'location' => (string) ($item->lokasi ?: '-'),
                ];
            });

            return;
        }

        $level = $levels[$index];
        $items
            ->groupBy(fn (RkaOpdItem $item) => (string) $item->{$level['code']}.'|'.(string) $item->{$level['name']})
            ->each(function (Collection $group, string $groupKey) use (&$rows, $levels, $index, $usesVerifiedBudget, $parentKey, $level): void {
                /** @var RkaOpdItem $first */
                $first = $group->first();
                $key = trim($parentKey.'-'.$level['level'].'-'.$groupKey, '-');
                $rows[] = $this->row(
                    $level['level'],
                    $key,
                    (string) ($first->{$level['code']} ?: '-'),
                    (string) ($first->{$level['name']} ?: $level['fallback']),
                    $group,
                    $usesVerifiedBudget,
                );
                $this->appendLevelRows($rows, $group, $levels, $index + 1, $usesVerifiedBudget, $key);
            });
    }

    /**
     * @param  Collection<int, RkaOpdItem>  $items
     * @return array<string, mixed>
     */
    private function row(
        string $level,
        string $key,
        string $code,
        string $description,
        Collection $items,
        bool $usesVerifiedBudget,
    ): array {
        return [
            'key' => $key,
            'level' => $level,
            'code' => $code,
            'description' => $description,
            'source' => '',
            'location' => '',
            'budget' => $this->budget($items, $usesVerifiedBudget),
        ];
    }

    /**
     * @param  Collection<int, RkaOpdItem>  $items
     * @return array{previous: float, operational: float, capital: float, unexpected: float, transfer: float, total: float, next: float}
     */
    private function budget(Collection $items, bool $usesVerifiedBudget): array
    {
        $budget = [
            'previous' => 0.0,
            'operational' => 0.0,
            'capital' => 0.0,
            'unexpected' => 0.0,
            'transfer' => 0.0,
            'total' => 0.0,
            'next' => 0.0,
        ];

        $items->each(function (RkaOpdItem $item) use (&$budget, $usesVerifiedBudget): void {
            $current = (float) ($usesVerifiedBudget ? $item->pagu_hasil_verifikasi : $item->pagu_usulan);
            $suffix = $usesVerifiedBudget ? 'hasil_verifikasi' : 'usulan';
            $budget['previous'] += (float) $item->alokasi_tahun_sebelumnya;
            $budget['total'] += $current;
            $budget['next'] += (float) $item->alokasi_tahun_berikutnya;

            $budget['operational'] += (float) $item->getAttribute("pagu_belanja_operasi_{$suffix}");
            $budget['capital'] += (float) $item->getAttribute("pagu_belanja_modal_{$suffix}");
            $budget['unexpected'] += (float) $item->getAttribute("pagu_belanja_tidak_terduga_{$suffix}");
            $budget['transfer'] += (float) $item->getAttribute("pagu_belanja_transfer_{$suffix}");
        });

        return $budget;
    }

    private function compareItems(RkaOpdItem $left, RkaOpdItem $right): int
    {
        foreach (['kode_urusan', 'kode_bidang', 'kode_program', 'kode_kegiatan', 'kode_sub_kegiatan'] as $field) {
            $comparison = strnatcasecmp((string) $left->{$field}, (string) $right->{$field});
            if ($comparison !== 0) {
                return $comparison;
            }
        }

        return ((int) $left->urutan <=> (int) $right->urutan) ?: ((int) $left->id <=> (int) $right->id);
    }
}
