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
                ->join('renstra_opd as renstra', 'renstra.id', '=', 'node.renstra_opd_id'),
            'kegiatan' => DB::table('opd_kegiatan as node')
                ->join('opd_program as program', function ($join): void {
                    $join->on('program.id', '=', 'node.opd_program_id')->whereNull('program.deleted_at');
                })
                ->join('renstra_opd as renstra', 'renstra.id', '=', 'program.renstra_opd_id'),
            'sub_kegiatan' => DB::table('opd_sub_kegiatan as node')
                ->join('opd_kegiatan as kegiatan', function ($join): void {
                    $join->on('kegiatan.id', '=', 'node.opd_kegiatan_id')->whereNull('kegiatan.deleted_at');
                })
                ->join('opd_program as program', function ($join): void {
                    $join->on('program.id', '=', 'kegiatan.opd_program_id')->whereNull('program.deleted_at');
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
