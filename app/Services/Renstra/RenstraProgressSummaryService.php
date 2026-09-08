<?php

namespace App\Services\Renstra;

use App\Models\RenstraOpd;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class RenstraProgressSummaryService
{
    /**
     * Calculate progress without loading the complete cascading tree into memory.
     *
     * @param  Collection<int, RenstraOpd>  $renstras
     * @return array<int, array<string, int|string>>
     */
    public function summarize(Collection $renstras): array
    {
        if ($renstras->isEmpty()) {
            return [];
        }

        $renstraIds = $renstras->pluck('id')->map(fn ($id): int => (int) $id)->all();
        $totals = collect($renstraIds)->mapWithKeys(fn (int $id): array => [$id => [
            'stages_filled' => 0,
            'indicator_parents_filled' => 0,
            'indicator_parents_total' => 0,
            'indicators_total' => 0,
            'targets_filled' => 0,
        ]])->all();

        foreach (['tujuan', 'sasaran', 'program', 'kegiatan', 'sub_kegiatan'] as $stage) {
            foreach ($this->stageQuery($stage, $renstraIds)->get() as $row) {
                $renstraId = (int) $row->renstra_id;
                $nodeCount = (int) $row->node_count;

                $totals[$renstraId]['stages_filled'] += $nodeCount > 0 ? 1 : 0;
                $totals[$renstraId]['indicator_parents_filled'] += (int) $row->indicator_parents_filled;
                $totals[$renstraId]['indicator_parents_total'] += $nodeCount;
                $totals[$renstraId]['indicators_total'] += (int) $row->indicators_total;
                $totals[$renstraId]['targets_filled'] += (int) $row->targets_filled;
            }
        }

        return $renstras->mapWithKeys(function (RenstraOpd $renstra) use ($totals): array {
            $summary = $totals[$renstra->id];
            $targetYearCount = max(0, ((int) $renstra->tahun_akhir + 1) - (int) $renstra->tahun_awal + 1);
            $targetsTotal = $summary['indicators_total'] * $targetYearCount;
            $percentage = (int) round(
                (($summary['stages_filled'] / 5) * 40)
                + ($summary['indicator_parents_total'] > 0
                    ? (($summary['indicator_parents_filled'] / $summary['indicator_parents_total']) * 40)
                    : 0)
                + ($targetsTotal > 0 ? (($summary['targets_filled'] / $targetsTotal) * 20) : 0),
            );

            return [$renstra->id => [
                'percentage' => min($percentage, 100),
                'stages_filled' => $summary['stages_filled'],
                'stages_total' => 5,
                'indicators_filled' => $summary['indicator_parents_filled'],
                'indicators_total' => $summary['indicator_parents_total'],
                'targets_filled' => $summary['targets_filled'],
                'targets_total' => $targetsTotal,
                'status' => $percentage === 100 ? 'terisi' : 'belum_lengkap',
            ]];
        })->all();
    }

    /**
     * Return the exact active nodes and annual targets that keep a RENSTRA incomplete.
     * The active-chain rules intentionally mirror stageQuery().
     *
     * @return array<string, mixed>
     */
    public function diagnose(RenstraOpd $renstra): array
    {
        $summary = $this->summarize(collect([$renstra]))[$renstra->id];
        $expectedYears = $this->expectedTargetYears($renstra);
        $missingStages = [];
        $missingIndicators = [];
        $missingTargets = [];

        foreach (['tujuan', 'sasaran', 'program', 'kegiatan', 'sub_kegiatan'] as $stage) {
            $rows = $this->diagnosticStageQuery($stage, $renstra)->get();
            $nodes = [];
            $indicators = [];

            foreach ($rows as $row) {
                $nodeKey = "{$stage}:{$row->node_id}";
                $nodes[$nodeKey] ??= [
                    'id' => (int) $row->node_id,
                    'type' => $stage,
                    'type_label' => $this->stageLabel($stage),
                    'section' => $this->stageSection($stage),
                    'code' => $row->node_code,
                    'name' => $row->node_name,
                    'path' => $this->diagnosticPath($row),
                    'indicator_ids' => [],
                ];

                if ($row->indicator_id === null) {
                    continue;
                }

                $indicatorId = (int) $row->indicator_id;
                $nodes[$nodeKey]['indicator_ids'][$indicatorId] = true;
                $indicatorKey = "{$stage}:{$indicatorId}";
                $indicators[$indicatorKey] ??= [
                    'id' => $indicatorId,
                    'name' => $row->indicator_name,
                    'parent_id' => (int) $row->node_id,
                    'parent_type' => $stage,
                    'parent_type_label' => $this->stageLabel($stage),
                    'parent_code' => $row->node_code,
                    'parent_name' => $row->node_name,
                    'section' => $this->stageSection($stage),
                    'path' => $this->diagnosticPath($row),
                    'filled_years' => [],
                ];

                if ($row->target_year !== null && $this->hasTargetValue($row->target_value, $row->target_text)) {
                    $indicators[$indicatorKey]['filled_years'][(int) $row->target_year] = true;
                }
            }

            if ($nodes === []) {
                $missingStages[] = [
                    'type' => $stage,
                    'label' => $this->stageLabel($stage),
                    'section' => $this->stageSection($stage),
                ];
            }

            foreach ($nodes as $node) {
                if ($node['indicator_ids'] !== []) {
                    continue;
                }

                unset($node['indicator_ids']);
                $missingIndicators[] = $node;
            }

            foreach ($indicators as $indicator) {
                $missingYears = array_values(array_filter(
                    $expectedYears,
                    fn (int $year): bool => ! isset($indicator['filled_years'][$year]),
                ));

                if ($missingYears === []) {
                    continue;
                }

                unset($indicator['filled_years']);
                $indicator['missing_years'] = $missingYears;
                $missingTargets[] = $indicator;
            }
        }

        $missingTargetCount = array_sum(array_map(
            fn (array $item): int => count($item['missing_years']),
            $missingTargets,
        ));
        $anomalies = $this->orphanedActiveNodes($renstra);

        return [
            'renstra' => [
                'id' => $renstra->id,
                'title' => $renstra->judul,
                'opd' => $renstra->opd?->singkatan ?: $renstra->opd?->nama,
                'period' => "{$renstra->tahun_awal}-{$renstra->tahun_akhir}",
            ],
            'summary' => $summary,
            'expected_years' => $expectedYears,
            'counts' => [
                'missing_stages' => count($missingStages),
                'missing_indicators' => count($missingIndicators),
                'missing_targets' => $missingTargetCount,
                'anomalies' => count($anomalies),
            ],
            'missing_stages' => $missingStages,
            'missing_indicators' => $missingIndicators,
            'missing_targets' => $missingTargets,
            'anomalies' => $anomalies,
        ];
    }

    /**
     * @return array<int, int>
     */
    private function expectedTargetYears(RenstraOpd $renstra): array
    {
        $start = (int) $renstra->tahun_awal;
        $end = (int) $renstra->tahun_akhir + 1;

        return $end >= $start ? range($start, $end) : [];
    }

    private function hasTargetValue(mixed $target, mixed $targetText): bool
    {
        return $target !== null || trim((string) ($targetText ?? '')) !== '';
    }

    private function stageLabel(string $stage): string
    {
        return match ($stage) {
            'tujuan' => 'Tujuan OPD',
            'sasaran' => 'Sasaran Strategis OPD',
            'program' => 'Program OPD',
            'kegiatan' => 'Kegiatan OPD',
            'sub_kegiatan' => 'Sub Kegiatan OPD',
        };
    }

    private function stageSection(string $stage): string
    {
        return $stage === 'sub_kegiatan' ? 'sub-kegiatan' : $stage;
    }

    /**
     * @return array<int, array{label: string, value: string}>
     */
    private function diagnosticPath(object $row): array
    {
        return collect([
            ['label' => 'Tujuan', 'value' => $row->tujuan_name],
            ['label' => 'Sasaran Strategis', 'value' => $row->sasaran_name],
            ['label' => 'Program', 'value' => $row->program_name],
            ['label' => 'Kegiatan', 'value' => $row->kegiatan_name],
        ])->filter(fn (array $item): bool => filled($item['value']))
            ->values()
            ->all();
    }

    private function diagnosticStageQuery(string $stage, RenstraOpd $renstra): Builder
    {
        $query = match ($stage) {
            'tujuan' => DB::table('tujuan_opd as node')
                ->join('renstra_opd as renstra', 'renstra.id', '=', 'node.renstra_opd_id'),
            'sasaran' => DB::table('sasaran_opd as node')
                ->join('tujuan_opd as tujuan', function ($join): void {
                    $join->on('tujuan.id', '=', 'node.tujuan_opd_id')->whereNull('tujuan.deleted_at');
                })
                ->join('renstra_opd as renstra', 'renstra.id', '=', 'tujuan.renstra_opd_id'),
            'program' => DB::table('opd_program as node')
                ->join('sasaran_opd as sasaran', function ($join): void {
                    $join->on('sasaran.id', '=', 'node.sasaran_opd_id')->whereNull('sasaran.deleted_at');
                })
                ->join('tujuan_opd as tujuan', function ($join): void {
                    $join->on('tujuan.id', '=', 'sasaran.tujuan_opd_id')->whereNull('tujuan.deleted_at');
                })
                ->join('renstra_opd as renstra', 'renstra.id', '=', 'node.renstra_opd_id'),
            'kegiatan' => DB::table('opd_kegiatan as node')
                ->join('opd_program as program', function ($join): void {
                    $join->on('program.id', '=', 'node.opd_program_id')->whereNull('program.deleted_at');
                })
                ->join('sasaran_opd as sasaran', function ($join): void {
                    $join->on('sasaran.id', '=', 'program.sasaran_opd_id')->whereNull('sasaran.deleted_at');
                })
                ->join('tujuan_opd as tujuan', function ($join): void {
                    $join->on('tujuan.id', '=', 'sasaran.tujuan_opd_id')->whereNull('tujuan.deleted_at');
                })
                ->join('renstra_opd as renstra', 'renstra.id', '=', 'program.renstra_opd_id'),
            'sub_kegiatan' => DB::table('opd_sub_kegiatan as node')
                ->join('opd_kegiatan as kegiatan', function ($join): void {
                    $join->on('kegiatan.id', '=', 'node.opd_kegiatan_id')->whereNull('kegiatan.deleted_at');
                })
                ->join('opd_program as program', function ($join): void {
                    $join->on('program.id', '=', 'kegiatan.opd_program_id')->whereNull('program.deleted_at');
                })
                ->join('sasaran_opd as sasaran', function ($join): void {
                    $join->on('sasaran.id', '=', 'program.sasaran_opd_id')->whereNull('sasaran.deleted_at');
                })
                ->join('tujuan_opd as tujuan', function ($join): void {
                    $join->on('tujuan.id', '=', 'sasaran.tujuan_opd_id')->whereNull('tujuan.deleted_at');
                })
                ->join('renstra_opd as renstra', 'renstra.id', '=', 'program.renstra_opd_id'),
        };

        [$nodeNameColumn, $nodeCodeColumn, $indicatorTable, $indicatorForeignKey, $targetTable, $targetForeignKey] = match ($stage) {
            'tujuan' => ['tujuan', DB::raw('NULL as node_code'), 'indikator_tujuan_opd', 'tujuan_opd_id', 'target_indikator_tujuan_opd', 'indikator_tujuan_opd_id'],
            'sasaran' => ['sasaran', 'node.kode as node_code', 'indikator_sasaran_opd', 'sasaran_opd_id', 'target_indikator_sasaran_opd', 'indikator_sasaran_opd_id'],
            'program' => ['nama', 'node.kode as node_code', 'indikator_opd_program', 'opd_program_id', 'target_indikator_opd_program', 'indikator_opd_program_id'],
            'kegiatan' => ['nama', 'node.kode as node_code', 'indikator_opd_kegiatan', 'opd_kegiatan_id', 'target_indikator_opd_kegiatan', 'indikator_opd_kegiatan_id'],
            'sub_kegiatan' => ['nama', 'node.kode as node_code', 'indikator_sub_kegiatan', 'opd_sub_kegiatan_id', 'target_indikator_sub_kegiatan', 'indikator_sub_kegiatan_id'],
        };

        $contextColumns = match ($stage) {
            'tujuan' => [DB::raw('NULL as tujuan_name'), DB::raw('NULL as sasaran_name'), DB::raw('NULL as program_name'), DB::raw('NULL as kegiatan_name')],
            'sasaran' => ['tujuan.tujuan as tujuan_name', DB::raw('NULL as sasaran_name'), DB::raw('NULL as program_name'), DB::raw('NULL as kegiatan_name')],
            'program' => ['tujuan.tujuan as tujuan_name', 'sasaran.sasaran as sasaran_name', DB::raw('NULL as program_name'), DB::raw('NULL as kegiatan_name')],
            'kegiatan' => ['tujuan.tujuan as tujuan_name', 'sasaran.sasaran as sasaran_name', 'program.nama as program_name', DB::raw('NULL as kegiatan_name')],
            'sub_kegiatan' => ['tujuan.tujuan as tujuan_name', 'sasaran.sasaran as sasaran_name', 'program.nama as program_name', 'kegiatan.nama as kegiatan_name'],
        };

        return $query
            ->leftJoin("{$indicatorTable} as indicator", function ($join) use ($indicatorForeignKey): void {
                $join->on("indicator.{$indicatorForeignKey}", '=', 'node.id')->whereNull('indicator.deleted_at');
            })
            ->leftJoin("{$targetTable} as target", "target.{$targetForeignKey}", '=', 'indicator.id')
            ->leftJoin('periode_tahun as periode', function ($join): void {
                $join->on('periode.id', '=', 'target.periode_tahun_id')->whereNull('periode.deleted_at');
            })
            ->where('renstra.id', $renstra->id)
            ->whereNull('renstra.deleted_at')
            ->whereNull('node.deleted_at')
            ->orderBy('node.urutan')
            ->orderBy('node.id')
            ->orderBy('indicator.urutan')
            ->orderBy('indicator.id')
            ->orderBy('periode.tahun')
            ->select([
                'node.id as node_id',
                $nodeCodeColumn,
                DB::raw("node.{$nodeNameColumn} as node_name"),
                'indicator.id as indicator_id',
                'indicator.indikator as indicator_name',
                'target.target as target_value',
                'target.target_text',
                'periode.tahun as target_year',
                ...$contextColumns,
            ]);
    }

    /**
     * Find active descendants hidden below a soft-deleted ancestor. They are not
     * included in completeness, but exposing them helps administrators repair old data.
     *
     * @return array<int, array<string, int|string|null>>
     */
    private function orphanedActiveNodes(RenstraOpd $renstra): array
    {
        $anomalies = collect();

        $definitions = [
            [
                'type' => 'sasaran',
                'label' => 'Sasaran Strategis OPD',
                'query' => DB::table('sasaran_opd as node')
                    ->join('tujuan_opd as tujuan', 'tujuan.id', '=', 'node.tujuan_opd_id')
                    ->where('tujuan.renstra_opd_id', $renstra->id)
                    ->whereNull('node.deleted_at')
                    ->whereNotNull('tujuan.deleted_at')
                    ->select(['node.id', 'node.kode', 'node.sasaran as name']),
            ],
            [
                'type' => 'program',
                'label' => 'Program OPD',
                'query' => DB::table('opd_program as node')
                    ->join('sasaran_opd as sasaran', 'sasaran.id', '=', 'node.sasaran_opd_id')
                    ->join('tujuan_opd as tujuan', 'tujuan.id', '=', 'sasaran.tujuan_opd_id')
                    ->where('node.renstra_opd_id', $renstra->id)
                    ->whereNull('node.deleted_at')
                    ->where(fn (Builder $query) => $query->whereNotNull('sasaran.deleted_at')->orWhereNotNull('tujuan.deleted_at'))
                    ->select(['node.id', 'node.kode', 'node.nama as name']),
            ],
            [
                'type' => 'kegiatan',
                'label' => 'Kegiatan OPD',
                'query' => DB::table('opd_kegiatan as node')
                    ->join('opd_program as program', 'program.id', '=', 'node.opd_program_id')
                    ->join('sasaran_opd as sasaran', 'sasaran.id', '=', 'program.sasaran_opd_id')
                    ->join('tujuan_opd as tujuan', 'tujuan.id', '=', 'sasaran.tujuan_opd_id')
                    ->where('program.renstra_opd_id', $renstra->id)
                    ->whereNull('node.deleted_at')
                    ->where(fn (Builder $query) => $query->whereNotNull('program.deleted_at')->orWhereNotNull('sasaran.deleted_at')->orWhereNotNull('tujuan.deleted_at'))
                    ->select(['node.id', 'node.kode', 'node.nama as name']),
            ],
            [
                'type' => 'sub_kegiatan',
                'label' => 'Sub Kegiatan OPD',
                'query' => DB::table('opd_sub_kegiatan as node')
                    ->join('opd_kegiatan as kegiatan', 'kegiatan.id', '=', 'node.opd_kegiatan_id')
                    ->join('opd_program as program', 'program.id', '=', 'kegiatan.opd_program_id')
                    ->join('sasaran_opd as sasaran', 'sasaran.id', '=', 'program.sasaran_opd_id')
                    ->join('tujuan_opd as tujuan', 'tujuan.id', '=', 'sasaran.tujuan_opd_id')
                    ->where('program.renstra_opd_id', $renstra->id)
                    ->whereNull('node.deleted_at')
                    ->where(fn (Builder $query) => $query->whereNotNull('kegiatan.deleted_at')->orWhereNotNull('program.deleted_at')->orWhereNotNull('sasaran.deleted_at')->orWhereNotNull('tujuan.deleted_at'))
                    ->select(['node.id', 'node.kode', 'node.nama as name']),
            ],
        ];

        foreach ($definitions as $definition) {
            foreach ($definition['query']->limit(100)->get() as $row) {
                $anomalies->push([
                    'id' => (int) $row->id,
                    'type' => $definition['type'],
                    'type_label' => $definition['label'],
                    'code' => $row->kode,
                    'name' => $row->name,
                    'reason' => 'Item masih aktif, tetapi salah satu induknya sudah dihapus.',
                ]);
            }
        }

        return $anomalies->values()->all();
    }

    /**
     * @param  array<int, int>  $renstraIds
     */
    private function stageQuery(string $stage, array $renstraIds): Builder
    {
        $query = match ($stage) {
            'tujuan' => DB::table('tujuan_opd as node')
                ->join('renstra_opd as renstra', 'renstra.id', '=', 'node.renstra_opd_id'),
            'sasaran' => DB::table('sasaran_opd as node')
                ->join('tujuan_opd as tujuan', function ($join): void {
                    $join->on('tujuan.id', '=', 'node.tujuan_opd_id')->whereNull('tujuan.deleted_at');
                })
                ->join('renstra_opd as renstra', 'renstra.id', '=', 'tujuan.renstra_opd_id'),
            'program' => DB::table('opd_program as node')
                ->join('sasaran_opd as sasaran', function ($join): void {
                    $join->on('sasaran.id', '=', 'node.sasaran_opd_id')->whereNull('sasaran.deleted_at');
                })
                ->join('tujuan_opd as tujuan', function ($join): void {
                    $join->on('tujuan.id', '=', 'sasaran.tujuan_opd_id')->whereNull('tujuan.deleted_at');
                })
                ->join('renstra_opd as renstra', 'renstra.id', '=', 'node.renstra_opd_id'),
            'kegiatan' => DB::table('opd_kegiatan as node')
                ->join('opd_program as program', function ($join): void {
                    $join->on('program.id', '=', 'node.opd_program_id')->whereNull('program.deleted_at');
                })
                ->join('sasaran_opd as sasaran', function ($join): void {
                    $join->on('sasaran.id', '=', 'program.sasaran_opd_id')->whereNull('sasaran.deleted_at');
                })
                ->join('tujuan_opd as tujuan', function ($join): void {
                    $join->on('tujuan.id', '=', 'sasaran.tujuan_opd_id')->whereNull('tujuan.deleted_at');
                })
                ->join('renstra_opd as renstra', 'renstra.id', '=', 'program.renstra_opd_id'),
            'sub_kegiatan' => DB::table('opd_sub_kegiatan as node')
                ->join('opd_kegiatan as kegiatan', function ($join): void {
                    $join->on('kegiatan.id', '=', 'node.opd_kegiatan_id')->whereNull('kegiatan.deleted_at');
                })
                ->join('opd_program as program', function ($join): void {
                    $join->on('program.id', '=', 'kegiatan.opd_program_id')->whereNull('program.deleted_at');
                })
                ->join('sasaran_opd as sasaran', function ($join): void {
                    $join->on('sasaran.id', '=', 'program.sasaran_opd_id')->whereNull('sasaran.deleted_at');
                })
                ->join('tujuan_opd as tujuan', function ($join): void {
                    $join->on('tujuan.id', '=', 'sasaran.tujuan_opd_id')->whereNull('tujuan.deleted_at');
                })
                ->join('renstra_opd as renstra', 'renstra.id', '=', 'program.renstra_opd_id'),
        };

        [$indicatorTable, $indicatorForeignKey, $targetTable, $targetForeignKey] = match ($stage) {
            'tujuan' => ['indikator_tujuan_opd', 'tujuan_opd_id', 'target_indikator_tujuan_opd', 'indikator_tujuan_opd_id'],
            'sasaran' => ['indikator_sasaran_opd', 'sasaran_opd_id', 'target_indikator_sasaran_opd', 'indikator_sasaran_opd_id'],
            'program' => ['indikator_opd_program', 'opd_program_id', 'target_indikator_opd_program', 'indikator_opd_program_id'],
            'kegiatan' => ['indikator_opd_kegiatan', 'opd_kegiatan_id', 'target_indikator_opd_kegiatan', 'indikator_opd_kegiatan_id'],
            'sub_kegiatan' => ['indikator_sub_kegiatan', 'opd_sub_kegiatan_id', 'target_indikator_sub_kegiatan', 'indikator_sub_kegiatan_id'],
        };

        return $query
            ->leftJoin("{$indicatorTable} as indicator", function ($join) use ($indicatorForeignKey): void {
                $join->on("indicator.{$indicatorForeignKey}", '=', 'node.id')->whereNull('indicator.deleted_at');
            })
            ->leftJoin("{$targetTable} as target", "target.{$targetForeignKey}", '=', 'indicator.id')
            ->leftJoin('periode_tahun as periode', function ($join): void {
                $join->on('periode.id', '=', 'target.periode_tahun_id')->whereNull('periode.deleted_at');
            })
            ->whereIn('renstra.id', $renstraIds)
            ->whereNull('renstra.deleted_at')
            ->whereNull('node.deleted_at')
            ->groupBy('renstra.id')
            ->selectRaw('renstra.id as renstra_id')
            ->selectRaw('COUNT(DISTINCT node.id) as node_count')
            ->selectRaw('COUNT(DISTINCT CASE WHEN indicator.id IS NOT NULL THEN node.id END) as indicator_parents_filled')
            ->selectRaw('COUNT(DISTINCT indicator.id) as indicators_total')
            ->selectRaw("COUNT(DISTINCT CASE
                WHEN target.id IS NOT NULL
                    AND periode.tahun BETWEEN renstra.tahun_awal AND (renstra.tahun_akhir + 1)
                    AND (target.target IS NOT NULL OR COALESCE(TRIM(target.target_text), '') <> '')
                THEN target.id
            END) as targets_filled");
    }
}
