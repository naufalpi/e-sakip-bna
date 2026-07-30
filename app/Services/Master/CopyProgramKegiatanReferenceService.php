<?php

namespace App\Services\Master;

use App\Models\KegiatanPemerintahan;
use App\Models\PeriodeTahun;
use App\Models\ProgramPemerintahan;
use App\Models\SubKegiatanPemerintahan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;

class CopyProgramKegiatanReferenceService
{
    /**
     * @return array<string, int>
     */
    public function copyProgramPeriod(int $sourceTahunAwal, int $sourceTahunAkhir, int $targetTahunAwal, int $targetTahunAkhir): array
    {
        if ($sourceTahunAkhir < $sourceTahunAwal || $targetTahunAkhir < $targetTahunAwal) {
            throw new InvalidArgumentException('Rentang periode RPJMD tidak valid.');
        }

        if ($sourceTahunAwal === $targetTahunAwal && $sourceTahunAkhir === $targetTahunAkhir) {
            throw new InvalidArgumentException('Periode sumber dan periode tujuan tidak boleh sama.');
        }

        return DB::transaction(function () use ($sourceTahunAwal, $sourceTahunAkhir, $targetTahunAwal, $targetTahunAkhir): array {
            $result = [
                'program_created' => 0,
                'program_existing' => 0,
                'kegiatan_created' => 0,
                'kegiatan_existing' => 0,
                'sub_kegiatan_created' => 0,
                'sub_kegiatan_existing' => 0,
            ];

            $sourcePrograms = ProgramPemerintahan::query()
                ->where('tahun_awal', $sourceTahunAwal)
                ->where('tahun_akhir', $sourceTahunAkhir)
                ->orderBy('kode')
                ->get();

            if ($sourcePrograms->isEmpty()) {
                throw new InvalidArgumentException('Periode sumber belum memiliki program untuk disalin.');
            }

            $sourceYears = range($sourceTahunAwal, $sourceTahunAkhir);
            $targetYears = range($targetTahunAwal, $targetTahunAkhir);

            if (count($sourceYears) !== count($targetYears)) {
                throw new InvalidArgumentException('Rentang tahun sumber dan tujuan harus memiliki jumlah tahun yang sama.');
            }

            $periodeByYear = PeriodeTahun::query()
                ->whereIn('tahun', [...$sourceYears, ...$targetYears])
                ->get(['id', 'tahun'])
                ->keyBy('tahun');

            $missingYears = collect([...$sourceYears, ...$targetYears])
                ->unique()
                ->reject(fn (int $year) => $periodeByYear->has($year))
                ->values();

            if ($missingYears->isNotEmpty()) {
                throw new InvalidArgumentException('Periode tahun belum tersedia: '.$missingYears->join(', ').'. Lengkapi menu Periode Tahun terlebih dahulu.');
            }

            $targetProgramsBySourceId = [];

            $sourcePrograms->each(function (ProgramPemerintahan $sourceProgram) use ($targetTahunAwal, $targetTahunAkhir, &$result, &$targetProgramsBySourceId): void {
                [$targetProgram, $created] = $this->upsertProgram(
                    [
                        'tahun_awal' => $targetTahunAwal,
                        'tahun_akhir' => $targetTahunAkhir,
                        'bidang_urusan_id' => $sourceProgram->bidang_urusan_id,
                        'kode' => $sourceProgram->kode,
                    ],
                    [
                        'nama' => $sourceProgram->nama,
                        'status' => $sourceProgram->status,
                    ],
                );

                $targetProgramsBySourceId[$sourceProgram->id] = $targetProgram;
                $result[$created ? 'program_created' : 'program_existing']++;
            });

            foreach ($this->copyYearPairs($sourceYears, $targetYears, $sourcePrograms->pluck('id')) as $yearPair) {
                $sourcePeriodeTahunId = (int) $periodeByYear[$yearPair['source_year']]->id;
                $targetPeriodeTahunId = (int) $periodeByYear[$yearPair['target_year']]->id;

                $sourcePrograms->each(function (ProgramPemerintahan $sourceProgram) use ($sourcePeriodeTahunId, $targetPeriodeTahunId, $targetProgramsBySourceId, &$result): void {
                    /** @var ProgramPemerintahan|null $targetProgram */
                    $targetProgram = $targetProgramsBySourceId[$sourceProgram->id] ?? null;

                    if (! $targetProgram) {
                        return;
                    }

                    KegiatanPemerintahan::query()
                        ->with(['subKegiatan' => fn ($query) => $query->where('periode_tahun_id', $sourcePeriodeTahunId)->orderBy('kode')])
                        ->where('program_pemerintahan_id', $sourceProgram->id)
                        ->where('periode_tahun_id', $sourcePeriodeTahunId)
                        ->orderBy('kode')
                        ->get()
                        ->each(function (KegiatanPemerintahan $sourceKegiatan) use ($targetProgram, $targetPeriodeTahunId, &$result): void {
                            [$targetKegiatan, $created] = $this->upsertKegiatan(
                                [
                                    'periode_tahun_id' => $targetPeriodeTahunId,
                                    'program_pemerintahan_id' => $targetProgram->id,
                                    'kode' => $sourceKegiatan->kode,
                                ],
                                [
                                    'nama' => $sourceKegiatan->nama,
                                    'status' => $sourceKegiatan->status,
                                ],
                            );

                            $result[$created ? 'kegiatan_created' : 'kegiatan_existing']++;

                            $sourceKegiatan->subKegiatan->each(function (SubKegiatanPemerintahan $sourceSubKegiatan) use ($targetKegiatan, $targetPeriodeTahunId, &$result): void {
                                [, $created] = $this->upsertSubKegiatan(
                                    [
                                        'periode_tahun_id' => $targetPeriodeTahunId,
                                        'kegiatan_pemerintahan_id' => $targetKegiatan->id,
                                        'kode' => $sourceSubKegiatan->kode,
                                    ],
                                    $this->subKegiatanPayload($sourceSubKegiatan),
                                );

                                $result[$created ? 'sub_kegiatan_created' : 'sub_kegiatan_existing']++;
                            });
                        });
                });
            }

            return $result;
        });
    }

    /**
     * @return array<string, int>
     */
    public function deleteProgramPeriod(int $tahunAwal, int $tahunAkhir): array
    {
        if ($tahunAkhir < $tahunAwal) {
            throw new InvalidArgumentException('Rentang periode RPJMD tidak valid.');
        }

        return DB::transaction(function () use ($tahunAwal, $tahunAkhir): array {
            $programIds = ProgramPemerintahan::withTrashed()
                ->where('tahun_awal', $tahunAwal)
                ->where('tahun_akhir', $tahunAkhir)
                ->pluck('id');

            if ($programIds->isEmpty()) {
                return [
                    'program_deleted' => 0,
                    'kegiatan_deleted' => 0,
                    'sub_kegiatan_deleted' => 0,
                ];
            }

            $kegiatanIds = KegiatanPemerintahan::withTrashed()
                ->whereIn('program_pemerintahan_id', $programIds)
                ->pluck('id');

            $subKegiatanIds = SubKegiatanPemerintahan::withTrashed()
                ->whereIn('kegiatan_pemerintahan_id', $kegiatanIds)
                ->pluck('id');

            $this->ensurePeriodIsUnused($programIds, $kegiatanIds, $subKegiatanIds);

            $subKegiatanDeleted = $subKegiatanIds->isEmpty()
                ? 0
                : SubKegiatanPemerintahan::withTrashed()->whereIn('id', $subKegiatanIds)->forceDelete();

            $kegiatanDeleted = $kegiatanIds->isEmpty()
                ? 0
                : KegiatanPemerintahan::withTrashed()->whereIn('id', $kegiatanIds)->forceDelete();

            $programDeleted = ProgramPemerintahan::withTrashed()->whereIn('id', $programIds)->forceDelete();

            return [
                'program_deleted' => (int) $programDeleted,
                'kegiatan_deleted' => (int) $kegiatanDeleted,
                'sub_kegiatan_deleted' => (int) $subKegiatanDeleted,
            ];
        });
    }

    /**
     * @return array<string, int>
     */
    public function copyKegiatanYear(int $programPemerintahanId, int $sourcePeriodeTahunId, int $targetPeriodeTahunId): array
    {
        if ($sourcePeriodeTahunId === $targetPeriodeTahunId) {
            throw new InvalidArgumentException('Tahun sumber dan tahun tujuan tidak boleh sama.');
        }

        ProgramPemerintahan::query()->findOrFail($programPemerintahanId);

        return DB::transaction(function () use ($programPemerintahanId, $sourcePeriodeTahunId, $targetPeriodeTahunId): array {
            $result = [
                'kegiatan_created' => 0,
                'kegiatan_existing' => 0,
                'sub_kegiatan_created' => 0,
                'sub_kegiatan_existing' => 0,
            ];

            KegiatanPemerintahan::query()
                ->with('subKegiatan')
                ->where('program_pemerintahan_id', $programPemerintahanId)
                ->where('periode_tahun_id', $sourcePeriodeTahunId)
                ->orderBy('kode')
                ->get()
                ->each(function (KegiatanPemerintahan $sourceKegiatan) use ($programPemerintahanId, $targetPeriodeTahunId, &$result): void {
                    $targetKegiatan = KegiatanPemerintahan::query()->firstOrCreate(
                        [
                            'periode_tahun_id' => $targetPeriodeTahunId,
                            'program_pemerintahan_id' => $programPemerintahanId,
                            'kode' => $sourceKegiatan->kode,
                        ],
                        [
                            'nama' => $sourceKegiatan->nama,
                            'status' => $sourceKegiatan->status,
                        ],
                    );

                    $result[$targetKegiatan->wasRecentlyCreated ? 'kegiatan_created' : 'kegiatan_existing']++;

                    $sourceKegiatan->subKegiatan->each(function (SubKegiatanPemerintahan $sourceSubKegiatan) use ($targetKegiatan, $targetPeriodeTahunId, &$result): void {
                        $targetSubKegiatan = SubKegiatanPemerintahan::query()->firstOrCreate(
                            [
                                'periode_tahun_id' => $targetPeriodeTahunId,
                                'kegiatan_pemerintahan_id' => $targetKegiatan->id,
                                'kode' => $sourceSubKegiatan->kode,
                            ],
                            $this->subKegiatanPayload($sourceSubKegiatan),
                        );

                        $result[$targetSubKegiatan->wasRecentlyCreated ? 'sub_kegiatan_created' : 'sub_kegiatan_existing']++;
                    });
                });

            return $result;
        });
    }

    /**
     * @param  array<int, int>  $targetPeriodeTahunIds
     * @return array<string, int>
     */
    public function copyKegiatanYearsForProgramPeriod(int $tahunAwal, int $tahunAkhir, int $sourcePeriodeTahunId, array $targetPeriodeTahunIds): array
    {
        if ($tahunAkhir < $tahunAwal) {
            throw new InvalidArgumentException('Rentang periode RPJMD tidak valid.');
        }

        $targetPeriodeTahunIds = collect($targetPeriodeTahunIds)
            ->map(fn (int|string $id) => (int) $id)
            ->filter(fn (int $id) => $id > 0 && $id !== $sourcePeriodeTahunId)
            ->unique()
            ->values()
            ->all();

        if ($targetPeriodeTahunIds === []) {
            throw new InvalidArgumentException('Pilih minimal satu tahun tujuan yang berbeda dari tahun sumber.');
        }

        return DB::transaction(function () use ($tahunAwal, $tahunAkhir, $sourcePeriodeTahunId, $targetPeriodeTahunIds): array {
            $result = [
                'program_scanned' => 0,
                'kegiatan_created' => 0,
                'kegiatan_existing' => 0,
                'sub_kegiatan_created' => 0,
                'sub_kegiatan_existing' => 0,
            ];

            ProgramPemerintahan::query()
                ->where('tahun_awal', $tahunAwal)
                ->where('tahun_akhir', $tahunAkhir)
                ->orderBy('kode')
                ->get()
                ->each(function (ProgramPemerintahan $program) use ($sourcePeriodeTahunId, $targetPeriodeTahunIds, &$result): void {
                    $result['program_scanned']++;

                    KegiatanPemerintahan::query()
                        ->with(['subKegiatan' => fn ($query) => $query->where('periode_tahun_id', $sourcePeriodeTahunId)->orderBy('kode')])
                        ->where('program_pemerintahan_id', $program->id)
                        ->where('periode_tahun_id', $sourcePeriodeTahunId)
                        ->orderBy('kode')
                        ->get()
                        ->each(function (KegiatanPemerintahan $sourceKegiatan) use ($program, $targetPeriodeTahunIds, &$result): void {
                            foreach ($targetPeriodeTahunIds as $targetPeriodeTahunId) {
                                $targetKegiatan = KegiatanPemerintahan::query()->firstOrCreate(
                                    [
                                        'periode_tahun_id' => $targetPeriodeTahunId,
                                        'program_pemerintahan_id' => $program->id,
                                        'kode' => $sourceKegiatan->kode,
                                    ],
                                    [
                                        'nama' => $sourceKegiatan->nama,
                                        'status' => $sourceKegiatan->status,
                                    ],
                                );

                                $result[$targetKegiatan->wasRecentlyCreated ? 'kegiatan_created' : 'kegiatan_existing']++;

                                $sourceKegiatan->subKegiatan->each(function (SubKegiatanPemerintahan $sourceSubKegiatan) use ($targetKegiatan, $targetPeriodeTahunId, &$result): void {
                                    $targetSubKegiatan = SubKegiatanPemerintahan::query()->firstOrCreate(
                                        [
                                            'periode_tahun_id' => $targetPeriodeTahunId,
                                            'kegiatan_pemerintahan_id' => $targetKegiatan->id,
                                            'kode' => $sourceSubKegiatan->kode,
                                        ],
                                        $this->subKegiatanPayload($sourceSubKegiatan),
                                    );

                                    $result[$targetSubKegiatan->wasRecentlyCreated ? 'sub_kegiatan_created' : 'sub_kegiatan_existing']++;
                                });
                            }
                        });
                });

            return $result;
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function subKegiatanPayload(SubKegiatanPemerintahan $subKegiatan): array
    {
        return [
            'nama' => $subKegiatan->nama,
            'sasaran_sub_kegiatan' => $subKegiatan->sasaran_sub_kegiatan,
            'indikator_sub_kegiatan' => $subKegiatan->indikator_sub_kegiatan,
            'satuan_indikator_id' => $subKegiatan->satuan_indikator_id,
            'definisi_operasional' => $subKegiatan->definisi_operasional,
            'status' => $subKegiatan->status,
        ];
    }

    /**
     * @param  array<int, int>  $sourceYears
     * @param  array<int, int>  $targetYears
     * @return array<int, array{source_year: int, target_year: int}>
     */
    private function copyYearPairs(array $sourceYears, array $targetYears, $sourceProgramIds): array
    {
        $populatedSourceYears = KegiatanPemerintahan::query()
            ->join('periode_tahun', 'periode_tahun.id', '=', 'kegiatan_pemerintahan.periode_tahun_id')
            ->whereIn('program_pemerintahan_id', $sourceProgramIds)
            ->whereIn('periode_tahun.tahun', $sourceYears)
            ->distinct()
            ->orderBy('periode_tahun.tahun')
            ->pluck('periode_tahun.tahun')
            ->map(fn (int|string $year) => (int) $year)
            ->values()
            ->all();

        if ($populatedSourceYears !== [] && ! in_array($sourceYears[0], $populatedSourceYears, true)) {
            return collect($populatedSourceYears)
                ->take(count($targetYears))
                ->map(fn (int $sourceYear, int $offset) => [
                    'source_year' => $sourceYear,
                    'target_year' => (int) $targetYears[$offset],
                ])
                ->values()
                ->all();
        }

        return collect($sourceYears)
            ->map(fn (int $sourceYear, int $offset) => [
                'source_year' => $sourceYear,
                'target_year' => (int) $targetYears[$offset],
            ])
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<string, mixed>  $values
     * @return array{0: ProgramPemerintahan, 1: bool}
     */
    private function upsertProgram(array $attributes, array $values): array
    {
        /** @var ProgramPemerintahan $program */
        $program = ProgramPemerintahan::withTrashed()->firstOrNew($attributes);
        $created = ! $program->exists;

        if ($program->exists && $program->trashed()) {
            $program->restore();
        }

        $program->fill($values)->save();

        return [$program, $created];
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<string, mixed>  $values
     * @return array{0: KegiatanPemerintahan, 1: bool}
     */
    private function upsertKegiatan(array $attributes, array $values): array
    {
        /** @var KegiatanPemerintahan $kegiatan */
        $kegiatan = KegiatanPemerintahan::withTrashed()->firstOrNew($attributes);
        $created = ! $kegiatan->exists;

        if ($kegiatan->exists && $kegiatan->trashed()) {
            $kegiatan->restore();
        }

        $kegiatan->fill($values)->save();

        return [$kegiatan, $created];
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<string, mixed>  $values
     * @return array{0: SubKegiatanPemerintahan, 1: bool}
     */
    private function upsertSubKegiatan(array $attributes, array $values): array
    {
        /** @var SubKegiatanPemerintahan $subKegiatan */
        $subKegiatan = SubKegiatanPemerintahan::withTrashed()->firstOrNew($attributes);
        $created = ! $subKegiatan->exists;

        if ($subKegiatan->exists && $subKegiatan->trashed()) {
            $subKegiatan->restore();
        }

        $subKegiatan->fill($values)->save();

        return [$subKegiatan, $created];
    }

    private function ensurePeriodIsUnused($programIds, $kegiatanIds, $subKegiatanIds): void
    {
        $checks = [
            ['program_rpjmd', 'program_pemerintahan_id', $programIds, 'program RPJMD'],
            ['program_rpjmd_program_pemerintahan', 'program_pemerintahan_id', $programIds, 'program RPJMD'],
            ['opd_program', 'program_pemerintahan_id', $programIds, 'Renstra OPD'],
            ['opd_kegiatan', 'kegiatan_pemerintahan_id', $kegiatanIds, 'Renstra OPD'],
            ['opd_sub_kegiatan', 'sub_kegiatan_pemerintahan_id', $subKegiatanIds, 'Renstra OPD'],
            ['rkpd_items', 'program_pemerintahan_id', $programIds, 'RKPD'],
            ['rkpd_items', 'kegiatan_pemerintahan_id', $kegiatanIds, 'RKPD'],
            ['rkpd_items', 'sub_kegiatan_pemerintahan_id', $subKegiatanIds, 'RKPD'],
            ['renja_opd_items', 'program_pemerintahan_id', $programIds, 'Renja OPD'],
            ['renja_opd_items', 'kegiatan_pemerintahan_id', $kegiatanIds, 'Renja OPD'],
            ['renja_opd_items', 'sub_kegiatan_pemerintahan_id', $subKegiatanIds, 'Renja OPD'],
        ];

        foreach ($checks as [$table, $column, $ids, $label]) {
            if ($this->tableUsesIds($table, $column, $ids)) {
                throw new InvalidArgumentException("Periode ini sudah dipakai di {$label}. Hapus atau lepaskan relasinya terlebih dahulu.");
            }
        }
    }

    private function tableUsesIds(string $table, string $column, $ids): bool
    {
        if ($ids->isEmpty() || ! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
            return false;
        }

        $query = DB::table($table)->whereIn($column, $ids);

        if (Schema::hasColumn($table, 'deleted_at')) {
            $query->whereNull('deleted_at');
        }

        return $query->exists();
    }
}
