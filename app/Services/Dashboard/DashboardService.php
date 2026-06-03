<?php

namespace App\Services\Dashboard;

use App\Models\EvaluasiSakip;
use App\Models\Lkjip;
use App\Models\Opd;
use App\Models\PeriodeTahun;
use App\Models\PerjanjianKinerja;
use App\Models\RealisasiKinerja;
use App\Models\RekomendasiEvaluasi;
use App\Models\RencanaAksi;
use App\Models\RenstraOpd;
use App\Models\Rpjmd;
use App\Models\User;
use App\Models\WorkflowSubmission;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class DashboardService
{
    private const CACHE_TTL_SECONDS = 300;

    private const CACHE_SCHEMA_VERSION = 'v5';

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function forUser(User $user, array $filters): array
    {
        $user->loadMissing(['roles.permissions']);

        $tahun = $this->selectedYear($filters['tahun'] ?? null);
        $opdId = $this->selectedOpdId($user, $filters['opd_id'] ?? null);
        $scope = $this->dashboardScope($user);
        $cacheVersion = app(DashboardCacheService::class)->version();

        $cacheKey = sprintf(
            'dashboard:%s:data:%d:user:%d:scope:%s:tahun:%d:opd:%s',
            self::CACHE_SCHEMA_VERSION,
            $cacheVersion,
            $user->id,
            $scope,
            $tahun,
            $opdId ?: 'all',
        );

        return Cache::remember($cacheKey, now()->addSeconds(self::CACHE_TTL_SECONDS), function () use ($user, $tahun, $opdId, $scope, $cacheKey, $cacheVersion) {
            return $this->buildDashboard($user, $tahun, $opdId, $scope, $cacheKey, $cacheVersion);
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function buildDashboard(User $user, int $tahun, ?int $opdId, string $scope, string $cacheKey, int $cacheVersion): array
    {
        $opdOptions = $this->opdOptions($user);
        $visibleOpds = $this->visibleOpds($user, $opdId);
        $opdIds = $visibleOpds->pluck('id')->map(fn ($id) => (int) $id)->all();

        $rpjmdOpdIds = $this->rpjmdLinkedOpdIds($opdIds);
        $renstraOpdIds = $this->distinctOpdIds(RenstraOpd::query()
            ->whereIn('opd_id', $opdIds)
            ->where('tahun_awal', '<=', $tahun)
            ->where('tahun_akhir', '>=', $tahun));
        $pkOpdIds = $this->distinctOpdIds(PerjanjianKinerja::query()->whereIn('opd_id', $opdIds)->where('tahun', $tahun));
        $rencanaAksiOpdIds = $this->distinctOpdIds(RencanaAksi::query()->whereIn('opd_id', $opdIds)->where('tahun', $tahun));
        $realisasiOpdIds = $this->distinctOpdIds(RealisasiKinerja::query()->whereIn('opd_id', $opdIds)->where('tahun', $tahun));
        $lkjipOpdIds = $this->distinctOpdIds(Lkjip::query()->whereIn('opd_id', $opdIds)->where('tahun', $tahun));

        $evaluasiByOpd = EvaluasiSakip::query()
            ->whereIn('opd_id', $opdIds)
            ->where('tahun', $tahun)
            ->get(['id', 'opd_id', 'nilai_akhir', 'predikat', 'status'])
            ->keyBy('opd_id');
        $evaluasiOpdIds = $evaluasiByOpd->keys()->map(fn ($id) => (int) $id)->all();

        $rekomendasiTerbukaByOpd = $this->openRecommendationCountsByOpd($opdIds, $tahun);
        $capaianByOpd = $this->averageAchievementByOpd($opdIds, $tahun);
        $progressOpd = $this->progressByOpd(
            $visibleOpds,
            $rpjmdOpdIds,
            $renstraOpdIds,
            $pkOpdIds,
            $rencanaAksiOpdIds,
            $realisasiOpdIds,
            $lkjipOpdIds,
            $evaluasiByOpd,
            $rekomendasiTerbukaByOpd,
            $capaianByOpd,
            $tahun,
        );

        $achievementByYear = $this->achievementByYear($opdIds, $tahun);
        $workflowStatus = $this->workflowStatus($opdIds, $tahun);
        $recommendationStatus = $this->recommendationStatus($opdIds, $tahun);
        $evaluationRanking = $this->evaluationRanking($opdIds, $tahun);
        $openRecommendations = $this->openRecommendations($opdIds, $tahun);
        $overdueRecommendations = $this->overdueRecommendations($opdIds, $tahun);
        $overdueRecommendationCount = $this->overdueRecommendationCount($opdIds, $tahun);
        $latestWorkflow = $this->latestWorkflow($opdIds, $tahun);
        $achievementStatusDistribution = $this->achievementStatusDistribution($opdIds, $tahun);
        $efficiencyStatusDistribution = $this->efficiencyStatusDistribution($opdIds, $tahun);
        $quarterlyAchievement = $this->quarterlyAchievement($opdIds, $tahun);
        $opdsWithoutRealization = $this->opdsWithoutRealization($visibleOpds, $realisasiOpdIds);
        $opdPerformanceRanking = $this->opdPerformanceRanking($progressOpd);
        $achievementIndicatorDrilldown = $this->achievementIndicatorDrilldown($opdIds, $tahun);
        $sasaranDrilldown = $this->sasaranDrilldown($opdIds, $tahun);
        $programDrilldown = $this->programDrilldown($opdIds, $tahun);

        $totalOpd = count($opdIds);
        $openRecommendationTotal = array_sum($rekomendasiTerbukaByOpd);
        $avgEvaluation = $evaluasiByOpd->avg(fn (EvaluasiSakip $evaluasi) => (float) $evaluasi->nilai_akhir);
        $achievementCounts = collect($achievementStatusDistribution)->pluck('count', 'status');

        return [
            'dashboard' => [
                'type' => $scope,
                'title' => $this->dashboardTitle($scope),
                'description' => $this->dashboardDescription($scope),
                'tahun' => $tahun,
                'can_filter_opd' => ! $this->isOpdScoped($user),
            ],
            'filters' => [
                'tahun' => $tahun,
                'opd_id' => $opdId,
            ],
            'cache' => [
                'key' => $cacheKey,
                'version' => $cacheVersion,
                'store' => (string) config('cache.default'),
                'ttl_seconds' => self::CACHE_TTL_SECONDS,
                'generated_at' => now()->toISOString(),
            ],
            'opdOptions' => $opdOptions,
            'periodeOptions' => $this->periodeOptions(),
            'stats' => [
                'opd_count' => $totalOpd,
                'rpjmd_count' => Rpjmd::query()->where('tahun_awal', '<=', $tahun)->where('tahun_akhir', '>=', $tahun)->count(),
                'rpjmd_linked_opd_count' => count($rpjmdOpdIds),
                'renstra_opd_count' => count($renstraOpdIds),
                'perjanjian_kinerja_opd_count' => count($pkOpdIds),
                'rencana_aksi_opd_count' => count($rencanaAksiOpdIds),
                'realisasi_opd_count' => count($realisasiOpdIds),
                'lkjip_opd_count' => count($lkjipOpdIds),
                'evaluasi_opd_count' => count($evaluasiOpdIds),
                'avg_capaian' => $this->selectedYearAchievement($achievementByYear, $tahun),
                'avg_evaluasi' => $avgEvaluation !== null ? round((float) $avgEvaluation, 2) : 0,
                'rekomendasi_terbuka_count' => $openRecommendationTotal,
                'rekomendasi_overdue_count' => $overdueRecommendationCount,
                'opd_belum_realisasi_count' => count($opdsWithoutRealization),
                'indikator_merah_count' => (int) ($achievementCounts['merah'] ?? 0),
                'indikator_kuning_count' => (int) ($achievementCounts['kuning'] ?? 0),
                'indikator_hijau_count' => (int) ($achievementCounts['hijau'] ?? 0),
                'workflow_pending_count' => collect($workflowStatus)
                    ->whereIn('status', ['submitted', 'revision'])
                    ->sum('count'),
            ],
            'moduleCompletion' => [
                $this->completionRow('rpjmd', 'Terhubung RPJMD', count($rpjmdOpdIds), $totalOpd),
                $this->completionRow('renstra', 'Renstra OPD', count($renstraOpdIds), $totalOpd),
                $this->completionRow('pk', 'Perjanjian Kinerja', count($pkOpdIds), $totalOpd),
                $this->completionRow('rencana_aksi', 'Rencana Aksi', count($rencanaAksiOpdIds), $totalOpd),
                $this->completionRow('realisasi', 'Realisasi Kinerja', count($realisasiOpdIds), $totalOpd),
                $this->completionRow('lkjip', 'LKJIP', count($lkjipOpdIds), $totalOpd),
                $this->completionRow('evaluasi', 'Evaluasi SAKIP', count($evaluasiOpdIds), $totalOpd),
            ],
            'progressOpd' => $progressOpd,
            'opdPerformanceRanking' => $opdPerformanceRanking,
            'achievementByYear' => $achievementByYear,
            'workflowStatus' => $workflowStatus,
            'recommendationStatus' => $recommendationStatus,
            'evaluationRanking' => $evaluationRanking,
            'openRecommendations' => $openRecommendations,
            'overdueRecommendations' => $overdueRecommendations,
            'latestWorkflow' => $latestWorkflow,
            'achievementStatusDistribution' => $achievementStatusDistribution,
            'efficiencyStatusDistribution' => $efficiencyStatusDistribution,
            'quarterlyAchievement' => $quarterlyAchievement,
            'achievementIndicatorDrilldown' => $achievementIndicatorDrilldown,
            'sasaranDrilldown' => $sasaranDrilldown,
            'programDrilldown' => $programDrilldown,
            'opdsWithoutRealization' => $opdsWithoutRealization,
            'quickLinks' => $this->quickLinks($scope, $user),
        ];
    }

    private function selectedYear(mixed $value): int
    {
        if (is_numeric($value) && (int) $value >= 2000 && (int) $value <= 2100) {
            return (int) $value;
        }

        return (int) (PeriodeTahun::query()
            ->where('status', 'active')
            ->orderByDesc('tahun')
            ->value('tahun') ?: now()->year);
    }

    private function selectedOpdId(User $user, mixed $value): ?int
    {
        if ($this->isOpdScoped($user)) {
            return $user->opd_id ? (int) $user->opd_id : null;
        }

        return is_numeric($value) && (int) $value > 0 ? (int) $value : null;
    }

    private function dashboardScope(User $user): string
    {
        if ($this->isOpdScoped($user)) {
            return 'opd';
        }

        if ($user->hasRole('admin_kabupaten_inspektorat')) {
            return 'evaluasi';
        }

        if ($user->hasRole('pimpinan')) {
            return 'pimpinan';
        }

        return 'kabupaten';
    }

    private function isOpdScoped(User $user): bool
    {
        return $user->hasRole('admin_opd')
            && ! $user->hasAnyRole([
                'super_admin',
                'admin_kabupaten_bagian_organisasi',
                'admin_kabupaten_bapperida',
                'admin_kabupaten_inspektorat',
                'admin_kabupaten_dinkominfo',
                'pimpinan',
            ]);
    }

    private function dashboardTitle(string $scope): string
    {
        return match ($scope) {
            'opd' => 'Dashboard OPD',
            'evaluasi' => 'Dashboard Evaluasi',
            'pimpinan' => 'Dashboard Pimpinan',
            default => 'Dashboard Kabupaten',
        };
    }

    private function dashboardDescription(string $scope): string
    {
        return match ($scope) {
            'opd' => 'Monitoring input perencanaan, kinerja, realisasi, dan tindak lanjut rekomendasi OPD.',
            'evaluasi' => 'Ringkasan nilai SAKIP, LHE, rekomendasi, dan progres tindak lanjut OPD.',
            'pimpinan' => 'Ringkasan eksekutif progres OPD, capaian indikator, workflow, dan nilai evaluasi.',
            default => 'Monitoring kabupaten untuk progres input OPD, status workflow, capaian, dan evaluasi.',
        };
    }

    /**
     * @return Collection<int, Opd>
     */
    private function visibleOpds(User $user, ?int $opdId): Collection
    {
        return Opd::query()
            ->where('status', 'active')
            ->when($this->isOpdScoped($user), fn (Builder $query) => $query->whereKey($user->opd_id ?? 0))
            ->when(! $this->isOpdScoped($user) && $opdId, fn (Builder $query) => $query->whereKey($opdId))
            ->orderBy('nama')
            ->get(['id', 'kode', 'nama', 'singkatan']);
    }

    /**
     * @return array<int, array{id: int, label: string}>
     */
    private function opdOptions(User $user): array
    {
        return Opd::query()
            ->where('status', 'active')
            ->when($this->isOpdScoped($user), fn (Builder $query) => $query->whereKey($user->opd_id ?? 0))
            ->orderBy('nama')
            ->get(['id', 'nama', 'singkatan'])
            ->map(fn (Opd $opd) => [
                'id' => $opd->id,
                'label' => $opd->singkatan ? "{$opd->singkatan} - {$opd->nama}" : $opd->nama,
            ])
            ->all();
    }

    /**
     * @return array<int, array{tahun: int, label: string}>
     */
    private function periodeOptions(): array
    {
        return PeriodeTahun::query()
            ->orderByDesc('tahun')
            ->get(['tahun', 'nama', 'status'])
            ->map(fn (PeriodeTahun $periode) => [
                'tahun' => $periode->tahun,
                'label' => "{$periode->tahun} - {$periode->nama}".($periode->status === 'active' ? ' (Aktif)' : ''),
            ])
            ->all();
    }

    /**
     * @param  array<int, int>  $opdIds
     * @return array<int, int>
     */
    private function rpjmdLinkedOpdIds(array $opdIds): array
    {
        return DB::table('program_rpjmd_opd_penanggung_jawab')
            ->join('program_rpjmd', 'program_rpjmd.id', '=', 'program_rpjmd_opd_penanggung_jawab.program_rpjmd_id')
            ->whereNull('program_rpjmd.deleted_at')
            ->whereIn('program_rpjmd_opd_penanggung_jawab.opd_id', $opdIds)
            ->distinct()
            ->pluck('program_rpjmd_opd_penanggung_jawab.opd_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * @return array<int, int>
     */
    private function distinctOpdIds(Builder $query): array
    {
        return $query->distinct()
            ->pluck('opd_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * @param  array<int, int>  $opdIds
     * @return array<int, int>
     */
    private function openRecommendationCountsByOpd(array $opdIds, int $tahun): array
    {
        return RekomendasiEvaluasi::query()
            ->select('opd_id', DB::raw('count(*) as total'))
            ->whereIn('opd_id', $opdIds)
            ->whereIn('status_tindak_lanjut', ['belum', 'proses', 'perlu_perbaikan'])
            ->whereHas('evaluasiSakip', fn (Builder $query) => $query->where('tahun', $tahun))
            ->groupBy('opd_id')
            ->pluck('total', 'opd_id')
            ->mapWithKeys(fn ($total, $opdId) => [(int) $opdId => (int) $total])
            ->all();
    }

    /**
     * @param  array<int, int>  $opdIds
     * @return array<int, float>
     */
    private function averageAchievementByOpd(array $opdIds, int $tahun): array
    {
        return DB::table('realisasi_program')
            ->join('realisasi_kinerja', 'realisasi_kinerja.id', '=', 'realisasi_program.realisasi_kinerja_id')
            ->whereNull('realisasi_program.deleted_at')
            ->whereNull('realisasi_kinerja.deleted_at')
            ->whereIn('realisasi_kinerja.opd_id', $opdIds)
            ->where('realisasi_kinerja.tahun', $tahun)
            ->whereNotNull('realisasi_program.capaian_persen')
            ->select('realisasi_kinerja.opd_id', DB::raw('avg(realisasi_program.capaian_persen) as rata_capaian'))
            ->groupBy('realisasi_kinerja.opd_id')
            ->pluck('rata_capaian', 'opd_id')
            ->mapWithKeys(fn ($value, $opdId) => [(int) $opdId => round((float) $value, 2)])
            ->all();
    }

    /**
     * @param  Collection<int, Opd>  $opds
     * @param  array<int, int>  $rpjmdOpdIds
     * @param  array<int, int>  $renstraOpdIds
     * @param  array<int, int>  $pkOpdIds
     * @param  array<int, int>  $rencanaAksiOpdIds
     * @param  array<int, int>  $realisasiOpdIds
     * @param  array<int, int>  $lkjipOpdIds
     * @param  Collection<int, EvaluasiSakip>  $evaluasiByOpd
     * @param  array<int, int>  $rekomendasiTerbukaByOpd
     * @param  array<int, float>  $capaianByOpd
     * @return array<int, array<string, mixed>>
     */
    private function progressByOpd(
        Collection $opds,
        array $rpjmdOpdIds,
        array $renstraOpdIds,
        array $pkOpdIds,
        array $rencanaAksiOpdIds,
        array $realisasiOpdIds,
        array $lkjipOpdIds,
        Collection $evaluasiByOpd,
        array $rekomendasiTerbukaByOpd,
        array $capaianByOpd,
        int $tahun,
    ): array {
        return $opds
            ->map(function (Opd $opd) use ($rpjmdOpdIds, $renstraOpdIds, $pkOpdIds, $rencanaAksiOpdIds, $realisasiOpdIds, $lkjipOpdIds, $evaluasiByOpd, $rekomendasiTerbukaByOpd, $capaianByOpd, $tahun) {
                $modules = [
                    'rpjmd' => in_array($opd->id, $rpjmdOpdIds, true),
                    'renstra' => in_array($opd->id, $renstraOpdIds, true),
                    'pk' => in_array($opd->id, $pkOpdIds, true),
                    'rencana_aksi' => in_array($opd->id, $rencanaAksiOpdIds, true),
                    'realisasi' => in_array($opd->id, $realisasiOpdIds, true),
                    'lkjip' => in_array($opd->id, $lkjipOpdIds, true),
                    'evaluasi' => $evaluasiByOpd->has($opd->id),
                ];
                $done = count(array_filter($modules));
                /** @var EvaluasiSakip|null $evaluasi */
                $evaluasi = $evaluasiByOpd->get($opd->id);

                return [
                    'opd_id' => $opd->id,
                    'kode' => $opd->kode,
                    'nama' => $opd->nama,
                    'singkatan' => $opd->singkatan,
                    'modules' => $modules,
                    'progress_percent' => round($done / max(count($modules), 1) * 100),
                    'nilai_evaluasi' => $evaluasi?->nilai_akhir,
                    'predikat' => $evaluasi?->predikat,
                    'status_evaluasi' => $evaluasi?->status,
                    'capaian_persen' => $capaianByOpd[$opd->id] ?? null,
                    'rekomendasi_terbuka_count' => $rekomendasiTerbukaByOpd[$opd->id] ?? 0,
                    'detail_url' => route('dashboard', ['tahun' => $tahun, 'opd_id' => $opd->id]),
                    'renstra_url' => route('renstra-opd.index', ['opd_id' => $opd->id]),
                    'pk_url' => route('perjanjian-kinerja.index', ['tahun' => $tahun, 'opd_id' => $opd->id]),
                    'rencana_aksi_url' => route('rencana-aksi.index', ['tahun' => $tahun, 'opd_id' => $opd->id]),
                    'realisasi_url' => route('realisasi-kinerja.index', ['tahun' => $tahun, 'opd_id' => $opd->id]),
                    'lkjip_url' => route('lkjip.index', ['tahun' => $tahun, 'opd_id' => $opd->id]),
                    'evaluasi_url' => route('evaluasi-sakip.index', ['tahun' => $tahun, 'opd_id' => $opd->id]),
                ];
            })
            ->sortBy([
                ['progress_percent', 'asc'],
                ['nama', 'asc'],
            ])
            ->values()
            ->all();
    }

    /**
     * @param  array<int, int>  $opdIds
     * @return array<int, array{tahun: int, rata_capaian: float, indikator_count: int, selected: bool}>
     */
    private function achievementByYear(array $opdIds, int $selectedYear): array
    {
        return DB::table('realisasi_program')
            ->join('realisasi_kinerja', 'realisasi_kinerja.id', '=', 'realisasi_program.realisasi_kinerja_id')
            ->whereNull('realisasi_program.deleted_at')
            ->whereNull('realisasi_kinerja.deleted_at')
            ->whereIn('realisasi_kinerja.opd_id', $opdIds)
            ->whereNotNull('realisasi_program.capaian_persen')
            ->select('realisasi_kinerja.tahun', DB::raw('avg(realisasi_program.capaian_persen) as rata_capaian'), DB::raw('count(*) as indikator_count'))
            ->groupBy('realisasi_kinerja.tahun')
            ->orderBy('realisasi_kinerja.tahun')
            ->get()
            ->map(fn ($row) => [
                'tahun' => (int) $row->tahun,
                'rata_capaian' => round((float) $row->rata_capaian, 2),
                'indikator_count' => (int) $row->indikator_count,
                'selected' => (int) $row->tahun === $selectedYear,
            ])
            ->all();
    }

    /**
     * @param  array<int, array<string, mixed>>  $progressOpd
     * @return array<int, array<string, mixed>>
     */
    private function opdPerformanceRanking(array $progressOpd): array
    {
        return collect($progressOpd)
            ->map(function (array $row) {
                $progress = (float) ($row['progress_percent'] ?? 0);
                $capaian = $row['capaian_persen'] !== null ? min(max((float) $row['capaian_persen'], 0), 100) : 0;
                $evaluasi = $row['nilai_evaluasi'] !== null ? min(max((float) $row['nilai_evaluasi'], 0), 100) : 0;
                $rekomendasiScore = max(0, 100 - min((int) ($row['rekomendasi_terbuka_count'] ?? 0) * 20, 100));

                $row['monitoring_score'] = round(
                    ($progress * 0.35) + ($capaian * 0.30) + ($evaluasi * 0.30) + ($rekomendasiScore * 0.05),
                    2,
                );

                return $row;
            })
            ->sortBy([
                ['monitoring_score', 'desc'],
                ['capaian_persen', 'desc'],
                ['progress_percent', 'desc'],
                ['nama', 'asc'],
            ])
            ->take(10)
            ->values()
            ->map(function (array $row, int $index) {
                return [
                    'rank' => $index + 1,
                    'opd_id' => $row['opd_id'],
                    'kode' => $row['kode'],
                    'nama' => $row['nama'],
                    'singkatan' => $row['singkatan'],
                    'progress_percent' => $row['progress_percent'],
                    'capaian_persen' => $row['capaian_persen'],
                    'nilai_evaluasi' => $row['nilai_evaluasi'],
                    'predikat' => $row['predikat'],
                    'rekomendasi_terbuka_count' => $row['rekomendasi_terbuka_count'],
                    'monitoring_score' => $row['monitoring_score'],
                    'detail_url' => $row['detail_url'],
                ];
            })
            ->all();
    }

    /**
     * @param  array<int, int>  $opdIds
     * @return array<int, array<string, mixed>>
     */
    private function achievementIndicatorDrilldown(array $opdIds, int $tahun): array
    {
        return DB::table('realisasi_program')
            ->join('realisasi_kinerja', 'realisasi_kinerja.id', '=', 'realisasi_program.realisasi_kinerja_id')
            ->leftJoin('opds', 'opds.id', '=', 'realisasi_kinerja.opd_id')
            ->whereNull('realisasi_program.deleted_at')
            ->whereNull('realisasi_kinerja.deleted_at')
            ->whereIn('realisasi_kinerja.opd_id', $opdIds)
            ->where('realisasi_kinerja.tahun', $tahun)
            ->whereIn('realisasi_program.status_capaian', ['merah', 'kuning', 'hijau'])
            ->select([
                'realisasi_program.id',
                'realisasi_program.indikator',
                'realisasi_program.target',
                'realisasi_program.target_text',
                'realisasi_program.realisasi',
                'realisasi_program.realisasi_text',
                'realisasi_program.capaian_persen',
                'realisasi_program.status_capaian',
                'realisasi_program.serapan_anggaran_persen',
                'realisasi_program.status_efisiensi',
                'realisasi_kinerja.id as realisasi_kinerja_id',
                'realisasi_kinerja.opd_id',
                'realisasi_kinerja.periode_realisasi',
                'realisasi_kinerja.triwulan',
                'opds.nama as opd_nama',
                'opds.singkatan as opd_singkatan',
            ])
            ->orderByRaw("case realisasi_program.status_capaian when 'merah' then 1 when 'kuning' then 2 else 3 end")
            ->orderBy('realisasi_program.capaian_persen')
            ->orderBy('opds.nama')
            ->limit(15)
            ->get()
            ->map(fn ($row) => [
                'id' => (int) $row->id,
                'realisasi_kinerja_id' => (int) $row->realisasi_kinerja_id,
                'opd_id' => (int) $row->opd_id,
                'opd' => $row->opd_singkatan ?: $row->opd_nama,
                'indikator' => (string) $row->indikator,
                'target' => $row->target !== null ? (float) $row->target : null,
                'target_text' => $row->target_text,
                'realisasi' => $row->realisasi !== null ? (float) $row->realisasi : null,
                'realisasi_text' => $row->realisasi_text,
                'capaian_persen' => $row->capaian_persen !== null ? round((float) $row->capaian_persen, 2) : null,
                'status_capaian' => $row->status_capaian,
                'serapan_anggaran_persen' => $row->serapan_anggaran_persen !== null ? round((float) $row->serapan_anggaran_persen, 2) : null,
                'status_efisiensi' => $row->status_efisiensi,
                'periode_realisasi' => $row->periode_realisasi,
                'triwulan' => $row->triwulan,
                'triwulan_label' => $this->triwulanLabel($row->triwulan),
                'detail_url' => route('realisasi-kinerja.show', (int) $row->realisasi_kinerja_id),
                'opd_detail_url' => route('dashboard', ['tahun' => $tahun, 'opd_id' => (int) $row->opd_id]),
            ])
            ->all();
    }

    /**
     * @param  array<int, int>  $opdIds
     * @return array<int, array<string, mixed>>
     */
    private function sasaranDrilldown(array $opdIds, int $tahun): array
    {
        return DB::table('realisasi_program')
            ->join('realisasi_kinerja', 'realisasi_kinerja.id', '=', 'realisasi_program.realisasi_kinerja_id')
            ->leftJoin('perjanjian_kinerja_items', 'perjanjian_kinerja_items.id', '=', 'realisasi_program.perjanjian_kinerja_item_id')
            ->leftJoin('opds', 'opds.id', '=', 'realisasi_kinerja.opd_id')
            ->whereNull('realisasi_program.deleted_at')
            ->whereNull('realisasi_kinerja.deleted_at')
            ->whereIn('realisasi_kinerja.opd_id', $opdIds)
            ->where('realisasi_kinerja.tahun', $tahun)
            ->select([
                'realisasi_kinerja.opd_id',
                'opds.nama as opd_nama',
                'opds.singkatan as opd_singkatan',
                'perjanjian_kinerja_items.sasaran_opd_id',
                DB::raw("coalesce(perjanjian_kinerja_items.sasaran, 'Sasaran belum dikaitkan') as sasaran"),
                DB::raw('count(realisasi_program.id) as indicator_count'),
                DB::raw('avg(realisasi_program.capaian_persen) as avg_capaian'),
                DB::raw("sum(case when realisasi_program.status_capaian = 'merah' then 1 else 0 end) as merah_count"),
                DB::raw("sum(case when realisasi_program.status_capaian = 'kuning' then 1 else 0 end) as kuning_count"),
                DB::raw("sum(case when realisasi_program.status_capaian = 'hijau' then 1 else 0 end) as hijau_count"),
            ])
            ->groupBy('realisasi_kinerja.opd_id', 'opds.nama', 'opds.singkatan', 'perjanjian_kinerja_items.sasaran_opd_id', 'perjanjian_kinerja_items.sasaran')
            ->orderByRaw('avg(coalesce(realisasi_program.capaian_persen, 0)) asc')
            ->limit(10)
            ->get()
            ->map(fn ($row) => [
                'opd_id' => (int) $row->opd_id,
                'opd' => $row->opd_singkatan ?: $row->opd_nama,
                'sasaran_opd_id' => $row->sasaran_opd_id ? (int) $row->sasaran_opd_id : null,
                'sasaran' => (string) $row->sasaran,
                'indicator_count' => (int) $row->indicator_count,
                'avg_capaian' => $row->avg_capaian !== null ? round((float) $row->avg_capaian, 2) : null,
                'status_capaian' => $this->statusFromAverage($row->avg_capaian),
                'merah_count' => (int) $row->merah_count,
                'kuning_count' => (int) $row->kuning_count,
                'hijau_count' => (int) $row->hijau_count,
                'detail_url' => route('dashboard', ['tahun' => $tahun, 'opd_id' => (int) $row->opd_id]),
            ])
            ->all();
    }

    /**
     * @param  array<int, int>  $opdIds
     * @return array<int, array<string, mixed>>
     */
    private function programDrilldown(array $opdIds, int $tahun): array
    {
        return DB::table('realisasi_program')
            ->join('realisasi_kinerja', 'realisasi_kinerja.id', '=', 'realisasi_program.realisasi_kinerja_id')
            ->leftJoin('opd_program', 'opd_program.id', '=', 'realisasi_program.opd_program_id')
            ->leftJoin('opds', 'opds.id', '=', 'realisasi_kinerja.opd_id')
            ->whereNull('realisasi_program.deleted_at')
            ->whereNull('realisasi_kinerja.deleted_at')
            ->whereIn('realisasi_kinerja.opd_id', $opdIds)
            ->where('realisasi_kinerja.tahun', $tahun)
            ->select([
                'realisasi_kinerja.opd_id',
                'opds.nama as opd_nama',
                'opds.singkatan as opd_singkatan',
                'opd_program.id as opd_program_id',
                'opd_program.kode as program_kode',
                DB::raw("coalesce(opd_program.nama, 'Program belum dikaitkan') as program"),
                DB::raw('count(realisasi_program.id) as indicator_count'),
                DB::raw('avg(realisasi_program.capaian_persen) as avg_capaian'),
                DB::raw('avg(realisasi_program.serapan_anggaran_persen) as avg_serapan'),
                DB::raw('sum(coalesce(realisasi_program.anggaran, 0)) as total_anggaran'),
                DB::raw('sum(coalesce(realisasi_program.realisasi_anggaran, 0)) as total_realisasi_anggaran'),
                DB::raw("sum(case when realisasi_program.status_efisiensi = 'efisien' then 1 else 0 end) as efisien_count"),
                DB::raw("sum(case when realisasi_program.status_efisiensi = 'cukup_efisien' then 1 else 0 end) as cukup_efisien_count"),
                DB::raw("sum(case when realisasi_program.status_efisiensi = 'tidak_efisien' then 1 else 0 end) as tidak_efisien_count"),
            ])
            ->groupBy('realisasi_kinerja.opd_id', 'opds.nama', 'opds.singkatan', 'opd_program.id', 'opd_program.kode', 'opd_program.nama')
            ->orderByRaw('avg(coalesce(realisasi_program.capaian_persen, 0)) asc')
            ->limit(10)
            ->get()
            ->map(fn ($row) => [
                'opd_id' => (int) $row->opd_id,
                'opd' => $row->opd_singkatan ?: $row->opd_nama,
                'opd_program_id' => $row->opd_program_id ? (int) $row->opd_program_id : null,
                'program_kode' => $row->program_kode,
                'program' => (string) $row->program,
                'indicator_count' => (int) $row->indicator_count,
                'avg_capaian' => $row->avg_capaian !== null ? round((float) $row->avg_capaian, 2) : null,
                'avg_serapan' => $row->avg_serapan !== null ? round((float) $row->avg_serapan, 2) : null,
                'status_capaian' => $this->statusFromAverage($row->avg_capaian),
                'total_anggaran' => round((float) $row->total_anggaran, 2),
                'total_realisasi_anggaran' => round((float) $row->total_realisasi_anggaran, 2),
                'dominant_efficiency_status' => $this->dominantEfficiencyStatus([
                    'efisien' => (int) $row->efisien_count,
                    'cukup_efisien' => (int) $row->cukup_efisien_count,
                    'tidak_efisien' => (int) $row->tidak_efisien_count,
                ]),
                'detail_url' => route('dashboard', ['tahun' => $tahun, 'opd_id' => (int) $row->opd_id]),
            ])
            ->all();
    }

    /**
     * @param  array<int, array{tahun: int, rata_capaian: float}>  $achievementByYear
     */
    private function selectedYearAchievement(array $achievementByYear, int $tahun): float
    {
        foreach ($achievementByYear as $row) {
            if ((int) $row['tahun'] === $tahun) {
                return round((float) $row['rata_capaian'], 2);
            }
        }

        return 0;
    }

    /**
     * @param  array<int, int>  $opdIds
     * @return array<int, array{status: string, label: string, count: int, percent: int}>
     */
    private function achievementStatusDistribution(array $opdIds, int $tahun): array
    {
        $rows = DB::table('realisasi_program')
            ->join('realisasi_kinerja', 'realisasi_kinerja.id', '=', 'realisasi_program.realisasi_kinerja_id')
            ->whereNull('realisasi_program.deleted_at')
            ->whereNull('realisasi_kinerja.deleted_at')
            ->whereIn('realisasi_kinerja.opd_id', $opdIds)
            ->where('realisasi_kinerja.tahun', $tahun)
            ->whereIn('realisasi_program.status_capaian', ['merah', 'kuning', 'hijau'])
            ->select('realisasi_program.status_capaian as status', DB::raw('count(*) as total'))
            ->groupBy('realisasi_program.status_capaian')
            ->pluck('total', 'status')
            ->mapWithKeys(fn ($total, $status) => [(string) $status => (int) $total])
            ->all();

        return $this->distributionRows([
            'merah' => 'Merah',
            'kuning' => 'Kuning',
            'hijau' => 'Hijau',
        ], $rows);
    }

    /**
     * @param  array<int, int>  $opdIds
     * @return array<int, array{status: string, label: string, count: int, percent: int}>
     */
    private function efficiencyStatusDistribution(array $opdIds, int $tahun): array
    {
        $rows = DB::table('realisasi_program')
            ->join('realisasi_kinerja', 'realisasi_kinerja.id', '=', 'realisasi_program.realisasi_kinerja_id')
            ->whereNull('realisasi_program.deleted_at')
            ->whereNull('realisasi_kinerja.deleted_at')
            ->whereIn('realisasi_kinerja.opd_id', $opdIds)
            ->where('realisasi_kinerja.tahun', $tahun)
            ->whereIn('realisasi_program.status_efisiensi', ['efisien', 'cukup_efisien', 'tidak_efisien'])
            ->select('realisasi_program.status_efisiensi as status', DB::raw('count(*) as total'))
            ->groupBy('realisasi_program.status_efisiensi')
            ->pluck('total', 'status')
            ->mapWithKeys(fn ($total, $status) => [(string) $status => (int) $total])
            ->all();

        return $this->distributionRows([
            'efisien' => 'Efisien',
            'cukup_efisien' => 'Cukup Efisien',
            'tidak_efisien' => 'Tidak Efisien',
        ], $rows);
    }

    /**
     * @param  array<string, string>  $labels
     * @param  array<string, int>  $counts
     * @return array<int, array{status: string, label: string, count: int, percent: int}>
     */
    private function distributionRows(array $labels, array $counts): array
    {
        $total = array_sum($counts);

        return collect($labels)
            ->map(fn (string $label, string $status) => [
                'status' => $status,
                'label' => $label,
                'count' => $counts[$status] ?? 0,
                'percent' => $total > 0 ? (int) round(($counts[$status] ?? 0) / $total * 100) : 0,
            ])
            ->values()
            ->all();
    }

    private function statusFromAverage(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $number = (float) $value;

        if ($number < 70) {
            return 'merah';
        }

        if ($number <= 90) {
            return 'kuning';
        }

        return 'hijau';
    }

    /**
     * @param  array{efisien: int, cukup_efisien: int, tidak_efisien: int}  $counts
     */
    private function dominantEfficiencyStatus(array $counts): ?string
    {
        $filtered = collect($counts)->filter(fn (int $count) => $count > 0);

        if ($filtered->isEmpty()) {
            return null;
        }

        return (string) $filtered
            ->sortDesc()
            ->keys()
            ->first();
    }

    /**
     * @param  array<int, int>  $opdIds
     * @return array<int, array{triwulan: string, label: string, rata_capaian: float, indikator_count: int, opd_count: int, completion_percent: int}>
     */
    private function quarterlyAchievement(array $opdIds, int $tahun): array
    {
        $rows = DB::table('realisasi_program')
            ->join('realisasi_kinerja', 'realisasi_kinerja.id', '=', 'realisasi_program.realisasi_kinerja_id')
            ->whereNull('realisasi_program.deleted_at')
            ->whereNull('realisasi_kinerja.deleted_at')
            ->whereIn('realisasi_kinerja.opd_id', $opdIds)
            ->where('realisasi_kinerja.tahun', $tahun)
            ->where('realisasi_kinerja.periode_realisasi', 'triwulan')
            ->whereIn('realisasi_kinerja.triwulan', ['tw1', 'tw2', 'tw3', 'tw4'])
            ->whereNotNull('realisasi_program.capaian_persen')
            ->select(
                'realisasi_kinerja.triwulan',
                DB::raw('avg(realisasi_program.capaian_persen) as rata_capaian'),
                DB::raw('count(realisasi_program.id) as indikator_count'),
                DB::raw('count(distinct realisasi_kinerja.opd_id) as opd_count'),
            )
            ->groupBy('realisasi_kinerja.triwulan')
            ->get()
            ->keyBy('triwulan');

        $totalOpd = count($opdIds);

        return collect([
            'tw1' => 'Triwulan I',
            'tw2' => 'Triwulan II',
            'tw3' => 'Triwulan III',
            'tw4' => 'Triwulan IV',
        ])
            ->map(function (string $label, string $triwulan) use ($rows, $totalOpd) {
                $row = $rows->get($triwulan);
                $opdCount = $row ? (int) $row->opd_count : 0;

                return [
                    'triwulan' => $triwulan,
                    'label' => $label,
                    'rata_capaian' => $row ? round((float) $row->rata_capaian, 2) : 0,
                    'indikator_count' => $row ? (int) $row->indikator_count : 0,
                    'opd_count' => $opdCount,
                    'completion_percent' => $totalOpd > 0 ? (int) round($opdCount / $totalOpd * 100) : 0,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @param  array<int, int>  $opdIds
     * @return array<int, array{status: string, label: string, count: int}>
     */
    private function workflowStatus(array $opdIds, int $tahun): array
    {
        return $this->workflowBaseQuery($opdIds, $tahun)
            ->select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->get()
            ->map(fn (WorkflowSubmission $workflow) => [
                'status' => $workflow->status,
                'label' => $this->statusLabel($workflow->status),
                'count' => (int) $workflow->total,
            ])
            ->values()
            ->all();
    }

    /**
     * @param  array<int, int>  $opdIds
     */
    private function workflowBaseQuery(array $opdIds, int $tahun): Builder
    {
        $relatedIds = [
            'perjanjian_kinerja' => PerjanjianKinerja::query()->whereIn('opd_id', $opdIds)->where('tahun', $tahun)->pluck('id')->all(),
            'rencana_aksi' => RencanaAksi::query()->whereIn('opd_id', $opdIds)->where('tahun', $tahun)->pluck('id')->all(),
            'realisasi_kinerja' => RealisasiKinerja::query()->whereIn('opd_id', $opdIds)->where('tahun', $tahun)->pluck('id')->all(),
            'lkjip' => Lkjip::query()->whereIn('opd_id', $opdIds)->where('tahun', $tahun)->pluck('id')->all(),
        ];

        return WorkflowSubmission::query()
            ->where(function (Builder $query) use ($relatedIds) {
                $hasRelatedIds = false;

                foreach ($relatedIds as $table => $ids) {
                    if (count($ids) === 0) {
                        continue;
                    }

                    $hasRelatedIds = true;
                    $query->orWhere(fn (Builder $query) => $query
                        ->where('related_table', $table)
                        ->whereIn('related_id', $ids));
                }

                if (! $hasRelatedIds) {
                    $query->whereRaw('1 = 0');
                }
            });
    }

    /**
     * @param  array<int, int>  $opdIds
     * @return array<int, array{status: string, label: string, count: int}>
     */
    private function recommendationStatus(array $opdIds, int $tahun): array
    {
        return DB::table('rekomendasi_evaluasi')
            ->join('evaluasi_sakip', 'evaluasi_sakip.id', '=', 'rekomendasi_evaluasi.evaluasi_sakip_id')
            ->whereNull('rekomendasi_evaluasi.deleted_at')
            ->whereNull('evaluasi_sakip.deleted_at')
            ->whereIn('rekomendasi_evaluasi.opd_id', $opdIds)
            ->where('evaluasi_sakip.tahun', $tahun)
            ->select('rekomendasi_evaluasi.status_tindak_lanjut as status', DB::raw('count(*) as total'))
            ->groupBy('rekomendasi_evaluasi.status_tindak_lanjut')
            ->get()
            ->map(fn ($row) => [
                'status' => (string) $row->status,
                'label' => $this->statusTindakLanjutLabel((string) $row->status),
                'count' => (int) $row->total,
            ])
            ->all();
    }

    /**
     * @param  array<int, int>  $opdIds
     * @return array<int, array<string, mixed>>
     */
    private function evaluationRanking(array $opdIds, int $tahun): array
    {
        return EvaluasiSakip::query()
            ->with('opd:id,nama,singkatan')
            ->whereIn('opd_id', $opdIds)
            ->where('tahun', $tahun)
            ->orderByDesc('nilai_akhir')
            ->limit(10)
            ->get()
            ->map(fn (EvaluasiSakip $evaluasi) => [
                'id' => $evaluasi->id,
                'opd' => $evaluasi->opd?->singkatan ?: $evaluasi->opd?->nama,
                'nilai_akhir' => $evaluasi->nilai_akhir,
                'predikat' => $evaluasi->predikat,
                'status' => $evaluasi->status,
            ])
            ->all();
    }

    /**
     * @param  array<int, int>  $opdIds
     * @return array<int, array<string, mixed>>
     */
    private function openRecommendations(array $opdIds, int $tahun): array
    {
        return RekomendasiEvaluasi::query()
            ->with(['opd:id,nama,singkatan', 'evaluasiSakip:id,tahun'])
            ->whereIn('opd_id', $opdIds)
            ->whereIn('status_tindak_lanjut', ['belum', 'proses', 'perlu_perbaikan'])
            ->whereHas('evaluasiSakip', fn (Builder $query) => $query->where('tahun', $tahun))
            ->orderByRaw("case prioritas when 'tinggi' then 1 when 'sedang' then 2 else 3 end")
            ->latest('id')
            ->limit(8)
            ->get()
            ->map(fn (RekomendasiEvaluasi $rekomendasi) => [
                'id' => $rekomendasi->id,
                'opd' => $rekomendasi->opd?->singkatan ?: $rekomendasi->opd?->nama,
                'nomor' => $rekomendasi->nomor,
                'rekomendasi' => str($rekomendasi->rekomendasi)->limit(120)->toString(),
                'prioritas' => $rekomendasi->prioritas,
                'status_tindak_lanjut' => $rekomendasi->status_tindak_lanjut,
                'target_tanggal' => $rekomendasi->target_tanggal?->toDateString(),
            ])
            ->all();
    }

    /**
     * @param  array<int, int>  $opdIds
     * @return array<int, array<string, mixed>>
     */
    private function overdueRecommendations(array $opdIds, int $tahun): array
    {
        return RekomendasiEvaluasi::query()
            ->with(['opd:id,nama,singkatan', 'evaluasiSakip:id,tahun'])
            ->whereIn('opd_id', $opdIds)
            ->whereIn('status_tindak_lanjut', ['belum', 'proses', 'perlu_perbaikan'])
            ->whereNotNull('target_tanggal')
            ->whereDate('target_tanggal', '<', now()->toDateString())
            ->whereHas('evaluasiSakip', fn (Builder $query) => $query->where('tahun', $tahun))
            ->orderBy('target_tanggal')
            ->limit(8)
            ->get()
            ->map(fn (RekomendasiEvaluasi $rekomendasi) => [
                'id' => $rekomendasi->id,
                'opd' => $rekomendasi->opd?->singkatan ?: $rekomendasi->opd?->nama,
                'nomor' => $rekomendasi->nomor,
                'rekomendasi' => str($rekomendasi->rekomendasi)->limit(120)->toString(),
                'prioritas' => $rekomendasi->prioritas,
                'status_tindak_lanjut' => $rekomendasi->status_tindak_lanjut,
                'target_tanggal' => $rekomendasi->target_tanggal?->toDateString(),
                'overdue_days' => $rekomendasi->target_tanggal ? max(0, (int) $rekomendasi->target_tanggal->diffInDays(now())) : 0,
            ])
            ->all();
    }

    /**
     * @param  array<int, int>  $opdIds
     */
    private function overdueRecommendationCount(array $opdIds, int $tahun): int
    {
        return RekomendasiEvaluasi::query()
            ->whereIn('opd_id', $opdIds)
            ->whereIn('status_tindak_lanjut', ['belum', 'proses', 'perlu_perbaikan'])
            ->whereNotNull('target_tanggal')
            ->whereDate('target_tanggal', '<', now()->toDateString())
            ->whereHas('evaluasiSakip', fn (Builder $query) => $query->where('tahun', $tahun))
            ->count();
    }

    /**
     * @param  Collection<int, Opd>  $visibleOpds
     * @param  array<int, int>  $realisasiOpdIds
     * @return array<int, array{id: int, kode: string|null, nama: string, singkatan: string|null}>
     */
    private function opdsWithoutRealization(Collection $visibleOpds, array $realisasiOpdIds): array
    {
        return $visibleOpds
            ->reject(fn (Opd $opd) => in_array($opd->id, $realisasiOpdIds, true))
            ->take(10)
            ->map(fn (Opd $opd) => [
                'id' => $opd->id,
                'kode' => $opd->kode,
                'nama' => $opd->nama,
                'singkatan' => $opd->singkatan,
            ])
            ->values()
            ->all();
    }

    /**
     * @param  array<int, int>  $opdIds
     * @return array<int, array<string, mixed>>
     */
    private function latestWorkflow(array $opdIds, int $tahun): array
    {
        return $this->workflowBaseQuery($opdIds, $tahun)
            ->with(['submittedBy:id,name', 'currentReviewer:id,name'])
            ->latest('updated_at')
            ->limit(8)
            ->get()
            ->map(fn (WorkflowSubmission $workflow) => [
                'id' => $workflow->id,
                'module' => $workflow->module,
                'module_label' => $this->moduleLabel($workflow->module),
                'status' => $workflow->status,
                'status_label' => $this->statusLabel($workflow->status),
                'submitted_by' => $workflow->submittedBy?->name,
                'current_reviewer' => $workflow->currentReviewer?->name,
                'updated_at' => $workflow->updated_at?->toDateTimeString(),
            ])
            ->all();
    }

    /**
     * @return array{key: string, label: string, count: int, total: int, percent: int}
     */
    private function completionRow(string $key, string $label, int $count, int $total): array
    {
        return [
            'key' => $key,
            'label' => $label,
            'count' => $count,
            'total' => $total,
            'percent' => $total > 0 ? (int) round($count / $total * 100) : 0,
        ];
    }

    private function moduleLabel(string $module): string
    {
        return match ($module) {
            'perjanjian_kinerja' => 'Perjanjian Kinerja',
            'rencana_aksi' => 'Rencana Aksi',
            'realisasi_kinerja' => 'Realisasi Kinerja',
            'lkjip' => 'LKJIP',
            default => str($module)->replace('_', ' ')->title()->toString(),
        };
    }

    private function statusLabel(string $status): string
    {
        return match ($status) {
            'draft' => 'Draft',
            'submitted' => 'Diajukan',
            'revision' => 'Revisi',
            'verified' => 'Terverifikasi',
            'approved' => 'Disetujui',
            'rejected' => 'Ditolak',
            'locked' => 'Terkunci',
            default => str($status)->replace('_', ' ')->title()->toString(),
        };
    }

    private function statusTindakLanjutLabel(string $status): string
    {
        return match ($status) {
            'belum' => 'Belum',
            'proses' => 'Proses',
            'selesai' => 'Selesai',
            'ditolak' => 'Ditolak',
            'perlu_perbaikan' => 'Perlu Perbaikan',
            default => str($status)->replace('_', ' ')->title()->toString(),
        };
    }

    private function triwulanLabel(?string $triwulan): ?string
    {
        return match ($triwulan) {
            'tw1' => 'Triwulan I',
            'tw2' => 'Triwulan II',
            'tw3' => 'Triwulan III',
            'tw4' => 'Triwulan IV',
            default => $triwulan ? str($triwulan)->replace('_', ' ')->title()->toString() : null,
        };
    }

    /**
     * @return array<int, array{label: string, href: string}>
     */
    private function quickLinks(string $scope, User $user): array
    {
        $links = [
            ['permission' => 'rpjmd.view', 'fallback' => 'view_rpjmd', 'label' => 'RPJMD', 'href' => '/rpjmd'],
            ['permission' => 'renstra.view', 'fallback' => 'view_renstra_opd', 'label' => 'Renstra OPD', 'href' => '/renstra-opd'],
            ['permission' => 'kinerja.view', 'fallback' => 'manage_perjanjian_kinerja', 'label' => 'Perjanjian Kinerja', 'href' => '/perjanjian-kinerja'],
            ['permission' => 'kinerja.view', 'fallback' => 'input_realisasi', 'label' => 'Realisasi Kinerja', 'href' => '/realisasi-kinerja'],
            ['permission' => 'lkjip.view', 'fallback' => 'laporan.view', 'label' => 'LKJIP', 'href' => '/lkjip'],
            ['permission' => 'evaluasi.view', 'fallback' => 'manage_evaluasi', 'label' => 'Evaluasi SAKIP', 'href' => '/evaluasi-sakip'],
        ];

        if ($scope === 'pimpinan') {
            $links[] = ['permission' => 'dashboard.view', 'fallback' => 'view_dashboard_pimpinan', 'label' => 'Dashboard', 'href' => '/dashboard'];
        }

        return collect($links)
            ->filter(fn (array $link) => $user->hasPermission($link['permission']) || $user->hasPermission($link['fallback']))
            ->map(fn (array $link) => [
                'label' => $link['label'],
                'href' => $link['href'],
            ])
            ->values()
            ->all();
    }
}
