<?php

namespace App\Services\Penganggaran;

use App\Models\DpaOpd;
use App\Models\DpaOpdItem;
use Illuminate\Support\Collection;

class DpaPreviewTableService
{
    /**
     * @return array{rows: array<int, array<string, mixed>>, total: array{previous: float, total: float, next: float}}
     */
    public function build(DpaOpd $dpa): array
    {
        $items = $dpa->items()->get()->sort($this->compareItems(...))->values();
        $rows = [];

        if ($items->isNotEmpty()) {
            $rows[] = $this->row(
                'opd',
                'opd-'.$dpa->id,
                (string) ($dpa->opd?->kode ?? ''),
                (string) ($dpa->opd?->nama ?? 'Perangkat Daerah'),
                $items,
            );
        }

        $levels = [
            ['level' => 'urusan', 'code' => 'kode_urusan', 'name' => 'nama_urusan', 'fallback' => 'Urusan belum terpetakan'],
            ['level' => 'bidang', 'code' => 'kode_bidang', 'name' => 'nama_bidang', 'fallback' => 'Bidang urusan belum terpetakan'],
            ['level' => 'program', 'code' => 'kode_program', 'name' => 'nama_program', 'fallback' => 'Program belum terpetakan'],
            ['level' => 'kegiatan', 'code' => 'kode_kegiatan', 'name' => 'nama_kegiatan', 'fallback' => 'Kegiatan belum terpetakan'],
        ];

        $this->appendLevelRows($rows, $items, $levels, 0, '');

        return [
            'rows' => $rows,
            'total' => $this->budget($items),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @param  Collection<int, DpaOpdItem>  $items
     * @param  array<int, array{level: string, code: string, name: string, fallback: string}>  $levels
     */
    private function appendLevelRows(array &$rows, Collection $items, array $levels, int $index, string $parentKey): void
    {
        if ($index >= count($levels)) {
            $items->each(function (DpaOpdItem $item) use (&$rows): void {
                $rows[] = [
                    ...$this->row(
                        'sub_kegiatan',
                        'sub-kegiatan-'.$item->id,
                        (string) ($item->kode_sub_kegiatan ?: '-'),
                        (string) ($item->nama_sub_kegiatan ?: 'Sub kegiatan belum terpetakan'),
                        collect([$item]),
                    ),
                    'source' => (string) ($item->sumber_pendanaan ?: '-'),
                    'location' => (string) ($item->lokasi ?: '-'),
                ];
            });

            return;
        }

        $level = $levels[$index];
        $items
            ->groupBy(fn (DpaOpdItem $item) => (string) $item->{$level['code']}.'|'.(string) $item->{$level['name']})
            ->each(function (Collection $group, string $groupKey) use (&$rows, $levels, $index, $parentKey, $level): void {
                /** @var DpaOpdItem $first */
                $first = $group->first();
                $key = trim($parentKey.'-'.$level['level'].'-'.$groupKey, '-');
                $rows[] = $this->row(
                    $level['level'],
                    $key,
                    (string) ($first->{$level['code']} ?: '-'),
                    (string) ($first->{$level['name']} ?: $level['fallback']),
                    $group,
                );
                $this->appendLevelRows($rows, $group, $levels, $index + 1, $key);
            });
    }

    /** @param Collection<int, DpaOpdItem> $items */
    private function row(string $level, string $key, string $code, string $description, Collection $items): array
    {
        return [
            'key' => $key,
            'level' => $level,
            'code' => $code,
            'description' => $description,
            'source' => '',
            'location' => '',
            'budget' => $this->budget($items),
        ];
    }

    /**
     * @param  Collection<int, DpaOpdItem>  $items
     * @return array{previous: float, total: float, next: float}
     */
    private function budget(Collection $items): array
    {
        return [
            'previous' => $items->sum(fn (DpaOpdItem $item): float => (float) $item->alokasi_tahun_sebelumnya),
            'total' => $items->sum(fn (DpaOpdItem $item): float => (float) $item->pagu_dpa),
            'next' => $items->sum(fn (DpaOpdItem $item): float => (float) $item->alokasi_tahun_berikutnya),
        ];
    }

    private function compareItems(DpaOpdItem $left, DpaOpdItem $right): int
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
