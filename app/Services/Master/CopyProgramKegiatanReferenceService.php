<?php

namespace App\Services\Master;

use App\Models\KegiatanPemerintahan;
use App\Models\PeriodeTahun;
use App\Models\ProgramPemerintahan;
use App\Models\ProgramRpjmd;
use App\Models\SubKegiatanPemerintahan;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
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

            // Program merupakan referensi lima tahunan dan tetap dapat disalin ke
            // periode RPJMD berikutnya. Kegiatan/sub kegiatan hanya ikut disalin
            // apabila tahun sumber dan tujuan keduanya sudah tersedia.
            foreach ($this->copyYearPairs($sourceYears, $targetYears, $sourcePrograms->pluck('id')) as $yearPair) {
                if (! $periodeByYear->has($yearPair['source_year']) || ! $periodeByYear->has($yearPair['target_year'])) {
                    continue;
                }

                $sourcePeriodeTahunId = (int) $periodeByYear[$yearPair['source_year']]->id;
                $targetPeriodeTahunId = (int) $periodeByYear[$yearPair['target_year']]->id;

                $sourcePrograms->each(function (ProgramPemerintahan $sourceProgram) use ($sourcePeriodeTahunId, $targetPeriodeTahunId, $targetProgramsBySourceId, &$result): void {
                    /** @var ProgramPemerintahan|null $targetProgram */
                    $targetProgram = $targetProgramsBySourceId[$sourceProgram->id] ?? null;

                    if (! $targetProgram) {
                        return;
                    }

                    KegiatanPemerintahan::query()
                        ->with(['subKegiatan' => fn ($query) => $query
                            ->with('indikatorReferensi')
                            ->where('periode_tahun_id', $sourcePeriodeTahunId)
                            ->orderBy('kode')])
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
                                [$targetSubKegiatan, $created] = $this->upsertSubKegiatan(
                                    [
                                        'periode_tahun_id' => $targetPeriodeTahunId,
                                        'kegiatan_pemerintahan_id' => $targetKegiatan->id,
                                        'kode' => $sourceSubKegiatan->kode,
                                    ],
                                    $this->subKegiatanPayload($sourceSubKegiatan),
                                );
                                $this->syncSubKegiatanIndicators($sourceSubKegiatan, $targetSubKegiatan);

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
                ->with('subKegiatan.indikatorReferensi')
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

                        if ($targetSubKegiatan->wasRecentlyCreated) {
                            $this->syncSubKegiatanIndicators($sourceSubKegiatan, $targetSubKegiatan);
                        }

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
                        ->with(['subKegiatan' => fn ($query) => $query
                            ->with('indikatorReferensi')
                            ->where('periode_tahun_id', $sourcePeriodeTahunId)
                            ->orderBy('kode')])
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

                                    if ($targetSubKegiatan->wasRecentlyCreated) {
                                        $this->syncSubKegiatanIndicators($sourceSubKegiatan, $targetSubKegiatan);
                                    }

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

    private function syncSubKegiatanIndicators(
        SubKegiatanPemerintahan $source,
        SubKegiatanPemerintahan $target,
    ): void {
        $source->loadMissing('indikatorReferensi');

        $indicators = $source->indikatorReferensi
            ->map(fn ($indicator) => [
                'indikator' => trim((string) $indicator->indikator),
                'satuan_indikator_id' => $indicator->satuan_indikator_id,
            ])
            ->filter(fn (array $indicator) => $indicator['indikator'] !== '')
            ->values();

        if ($indicators->isEmpty() && filled($source->indikator_sub_kegiatan)) {
            $indicators->push([
                'indikator' => trim((string) $source->indikator_sub_kegiatan),
                'satuan_indikator_id' => $source->satuan_indikator_id,
            ]);
        }

        $target->indikatorReferensi()->delete();
        $indicators->each(function (array $indicator, int $index) use ($target): void {
            $target->indikatorReferensi()->create([
                ...$indicator,
                'is_utama' => $index === 0,
                'urutan' => $index + 1,
            ]);
        });
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
        $usage = [];

        foreach ($this->programRpjmdUsages($programIds) as $item) {
            $this->addUsage($usage, 'RPJMD Kabupaten', $item['count'], $item['items'], 'Menu RPJMD Kabupaten, lalu lepaskan Program RPJMD yang memakai program master periode ini.');
        }

        foreach ($this->renstraUsages($programIds, $kegiatanIds, $subKegiatanIds) as $item) {
            $this->addUsage($usage, 'Renstra OPD', $item['count'], $item['items'], 'Menu Renstra OPD, buka dokumen terkait, lalu hapus/lepas program, kegiatan, atau sub kegiatan yang memakai master ini.');
        }

        $this->addUsage(
            $usage,
            'RKPD Kabupaten',
            ...$this->rkpdUsage($programIds, $kegiatanIds, $subKegiatanIds),
            hint: 'Menu RKPD Kabupaten, buka dokumen terkait, lalu hapus baris RKPD yang memakai master ini.',
        );

        $this->addUsage(
            $usage,
            'Renja OPD',
            ...$this->renjaUsage($programIds, $kegiatanIds, $subKegiatanIds),
            hint: 'Menu Renja OPD, buka dokumen terkait, lalu hapus baris Renja yang memakai master ini.',
        );

        if ($usage !== []) {
            throw new InvalidArgumentException($this->formatUsageMessage($usage));
        }
    }

    /**
     * @return array<int, array{count: int, items: array<int, string>}>
     */
    private function programRpjmdUsages(Collection $programIds): array
    {
        if ($programIds->isEmpty()) {
            return [];
        }

        $canUseDirectReference = $this->canUseColumn('program_rpjmd', 'program_pemerintahan_id');
        $canUsePivotReference = Schema::hasTable('program_rpjmd_program_pemerintahan');

        if (! $canUseDirectReference && ! $canUsePivotReference) {
            return [];
        }

        $query = ProgramRpjmd::query()
            ->where(function (EloquentBuilder $query) use ($programIds, $canUseDirectReference, $canUsePivotReference): void {
                $hasCondition = false;

                if ($canUseDirectReference) {
                    $query->whereIn('program_pemerintahan_id', $programIds);
                    $hasCondition = true;
                }

                if ($canUsePivotReference) {
                    $method = $hasCondition ? 'orWhereHas' : 'whereHas';
                    $query->{$method}('programPemerintahanReferences', fn (EloquentBuilder $query) => $query->whereIn('program_pemerintahan.id', $programIds));
                }
            })
            ->where(function (EloquentBuilder $query): void {
                $query
                    ->whereHas('sasaran.tujuan.visi.rpjmd')
                    ->orWhereHas('sasaran.tujuan.misi.rpjmd')
                    ->orWhereHas('sasaran.tujuan.misiTerkait.rpjmd')
                    ->orWhereHas('indikatorSasaran.sasaran.tujuan.visi.rpjmd')
                    ->orWhereHas('indikatorSasaran.sasaran.tujuan.misi.rpjmd')
                    ->orWhereHas('indikatorSasaran.sasaran.tujuan.misiTerkait.rpjmd');
            });

        $count = (int) (clone $query)->count();

        if ($count < 1) {
            return [];
        }

        return [[
            'count' => $count,
            'items' => (clone $query)
                ->orderBy('id')
                ->limit(5)
                ->get(['id', 'kode', 'nama'])
                ->map(fn ($row) => $this->formatCodeName($row->kode ?? null, $row->nama ?? null, 'Program RPJMD #'.$row->id))
                ->all(),
        ]];
    }

    /**
     * @return array<int, array{count: int, items: array<int, string>}>
     */
    private function renstraUsages(Collection $programIds, Collection $kegiatanIds, Collection $subKegiatanIds): array
    {
        return array_values(array_filter([
            $this->opdProgramUsage($programIds),
            $this->opdKegiatanUsage($kegiatanIds),
            $this->opdSubKegiatanUsage($subKegiatanIds),
        ], fn (array $usage) => $usage['count'] > 0));
    }

    /**
     * @return array{count: int, items: array<int, string>}
     */
    private function opdProgramUsage(Collection $programIds): array
    {
        if ($programIds->isEmpty() || ! $this->canUseColumn('opd_program', 'program_pemerintahan_id')) {
            return ['count' => 0, 'items' => []];
        }

        $query = DB::table('opd_program')
            ->leftJoin('renstra_opd', 'renstra_opd.id', '=', 'opd_program.renstra_opd_id')
            ->leftJoin('opds', 'opds.id', '=', 'renstra_opd.opd_id')
            ->whereIn('opd_program.program_pemerintahan_id', $programIds);
        $this->whereNotDeleted($query, 'opd_program');
        $this->whereNotDeleted($query, 'renstra_opd');

        return [
            'count' => (int) (clone $query)->count(),
            'items' => (clone $query)
                ->orderBy('opd_program.id')
                ->limit(5)
                ->get([
                    'opd_program.id',
                    'opd_program.kode',
                    'opd_program.nama',
                    'renstra_opd.judul as dokumen',
                    'opds.nama as opd',
                ])
                ->map(fn ($row) => $this->formatDocumentUsage($row->dokumen ?? null, $row->opd ?? null, 'Program', $row->kode ?? null, $row->nama ?? null, 'Opd Program #'.$row->id))
                ->all(),
        ];
    }

    /**
     * @return array{count: int, items: array<int, string>}
     */
    private function opdKegiatanUsage(Collection $kegiatanIds): array
    {
        if ($kegiatanIds->isEmpty() || ! $this->canUseColumn('opd_kegiatan', 'kegiatan_pemerintahan_id')) {
            return ['count' => 0, 'items' => []];
        }

        $query = DB::table('opd_kegiatan')
            ->leftJoin('opd_program', 'opd_program.id', '=', 'opd_kegiatan.opd_program_id')
            ->leftJoin('renstra_opd', 'renstra_opd.id', '=', 'opd_program.renstra_opd_id')
            ->leftJoin('opds', 'opds.id', '=', 'renstra_opd.opd_id')
            ->whereIn('opd_kegiatan.kegiatan_pemerintahan_id', $kegiatanIds);
        $this->whereNotDeleted($query, 'opd_kegiatan');
        $this->whereNotDeleted($query, 'opd_program');
        $this->whereNotDeleted($query, 'renstra_opd');

        return [
            'count' => (int) (clone $query)->count(),
            'items' => (clone $query)
                ->orderBy('opd_kegiatan.id')
                ->limit(5)
                ->get([
                    'opd_kegiatan.id',
                    'opd_kegiatan.kode',
                    'opd_kegiatan.nama',
                    'renstra_opd.judul as dokumen',
                    'opds.nama as opd',
                ])
                ->map(fn ($row) => $this->formatDocumentUsage($row->dokumen ?? null, $row->opd ?? null, 'Kegiatan', $row->kode ?? null, $row->nama ?? null, 'Opd Kegiatan #'.$row->id))
                ->all(),
        ];
    }

    /**
     * @return array{count: int, items: array<int, string>}
     */
    private function opdSubKegiatanUsage(Collection $subKegiatanIds): array
    {
        if ($subKegiatanIds->isEmpty() || ! $this->canUseColumn('opd_sub_kegiatan', 'sub_kegiatan_pemerintahan_id')) {
            return ['count' => 0, 'items' => []];
        }

        $query = DB::table('opd_sub_kegiatan')
            ->leftJoin('opd_kegiatan', 'opd_kegiatan.id', '=', 'opd_sub_kegiatan.opd_kegiatan_id')
            ->leftJoin('opd_program', 'opd_program.id', '=', 'opd_kegiatan.opd_program_id')
            ->leftJoin('renstra_opd', 'renstra_opd.id', '=', 'opd_program.renstra_opd_id')
            ->leftJoin('opds', 'opds.id', '=', 'renstra_opd.opd_id')
            ->whereIn('opd_sub_kegiatan.sub_kegiatan_pemerintahan_id', $subKegiatanIds);
        $this->whereNotDeleted($query, 'opd_sub_kegiatan');
        $this->whereNotDeleted($query, 'opd_kegiatan');
        $this->whereNotDeleted($query, 'opd_program');
        $this->whereNotDeleted($query, 'renstra_opd');

        return [
            'count' => (int) (clone $query)->count(),
            'items' => (clone $query)
                ->orderBy('opd_sub_kegiatan.id')
                ->limit(5)
                ->get([
                    'opd_sub_kegiatan.id',
                    'opd_sub_kegiatan.kode',
                    'opd_sub_kegiatan.nama',
                    'renstra_opd.judul as dokumen',
                    'opds.nama as opd',
                ])
                ->map(fn ($row) => $this->formatDocumentUsage($row->dokumen ?? null, $row->opd ?? null, 'Sub kegiatan', $row->kode ?? null, $row->nama ?? null, 'Opd Sub Kegiatan #'.$row->id))
                ->all(),
        ];
    }

    /**
     * @return array{0: int, 1: array<int, string>}
     */
    private function rkpdUsage(Collection $programIds, Collection $kegiatanIds, Collection $subKegiatanIds): array
    {
        if (! Schema::hasTable('rkpd_items') || ($programIds->isEmpty() && $kegiatanIds->isEmpty() && $subKegiatanIds->isEmpty())) {
            return [0, []];
        }

        $query = DB::table('rkpd_items')
            ->leftJoin('rkpd', 'rkpd.id', '=', 'rkpd_items.rkpd_id')
            ->leftJoin('opds', 'opds.id', '=', 'rkpd_items.opd_id');
        $this->whereReferenceMatches($query, 'rkpd_items', $programIds, $kegiatanIds, $subKegiatanIds);
        $this->whereNotDeleted($query, 'rkpd_items');
        $this->whereNotDeleted($query, 'rkpd');

        return [
            (int) (clone $query)->count(),
            (clone $query)
                ->orderBy('rkpd_items.id')
                ->limit(5)
                ->get([
                    'rkpd_items.id',
                    'rkpd_items.kode',
                    'rkpd_items.nama_urusan_bidang_program_kegiatan_sub as nama',
                    'rkpd.judul as dokumen',
                    'rkpd.tahun',
                    'opds.nama as opd',
                ])
                ->map(fn ($row) => $this->formatDocumentUsage($row->dokumen ?? ('RKPD Tahun '.($row->tahun ?? '-')), $row->opd ?? null, 'Baris RKPD', $row->kode ?? null, $row->nama ?? null, 'RKPD Item #'.$row->id))
                ->all(),
        ];
    }

    /**
     * @return array{0: int, 1: array<int, string>}
     */
    private function renjaUsage(Collection $programIds, Collection $kegiatanIds, Collection $subKegiatanIds): array
    {
        if (! Schema::hasTable('renja_opd_items') || ($programIds->isEmpty() && $kegiatanIds->isEmpty() && $subKegiatanIds->isEmpty())) {
            return [0, []];
        }

        $query = DB::table('renja_opd_items')
            ->leftJoin('renja_opd', 'renja_opd.id', '=', 'renja_opd_items.renja_opd_id')
            ->leftJoin('opds', 'opds.id', '=', 'renja_opd.opd_id');
        $this->whereReferenceMatches($query, 'renja_opd_items', $programIds, $kegiatanIds, $subKegiatanIds);
        $this->whereNotDeleted($query, 'renja_opd_items');
        $this->whereNotDeleted($query, 'renja_opd');

        return [
            (int) (clone $query)->count(),
            (clone $query)
                ->orderBy('renja_opd_items.id')
                ->limit(5)
                ->get([
                    'renja_opd_items.id',
                    'renja_opd_items.kode',
                    'renja_opd_items.nama_sub_kegiatan as nama',
                    'renja_opd.judul as dokumen',
                    'renja_opd.tahun',
                    'opds.nama as opd',
                ])
                ->map(fn ($row) => $this->formatDocumentUsage($row->dokumen ?? ('Renja Tahun '.($row->tahun ?? '-')), $row->opd ?? null, 'Baris Renja', $row->kode ?? null, $row->nama ?? null, 'Renja Item #'.$row->id))
                ->all(),
        ];
    }

    private function whereReferenceMatches(Builder $query, string $table, Collection $programIds, Collection $kegiatanIds, Collection $subKegiatanIds): void
    {
        $query->where(function (Builder $query) use ($table, $programIds, $kegiatanIds, $subKegiatanIds): void {
            $hasCondition = false;

            if ($programIds->isNotEmpty() && Schema::hasColumn($table, 'program_pemerintahan_id')) {
                $query->whereIn("{$table}.program_pemerintahan_id", $programIds);
                $hasCondition = true;
            }

            if ($kegiatanIds->isNotEmpty() && Schema::hasColumn($table, 'kegiatan_pemerintahan_id')) {
                $method = $hasCondition ? 'orWhereIn' : 'whereIn';
                $query->{$method}("{$table}.kegiatan_pemerintahan_id", $kegiatanIds);
                $hasCondition = true;
            }

            if ($subKegiatanIds->isNotEmpty() && Schema::hasColumn($table, 'sub_kegiatan_pemerintahan_id')) {
                $method = $hasCondition ? 'orWhereIn' : 'whereIn';
                $query->{$method}("{$table}.sub_kegiatan_pemerintahan_id", $subKegiatanIds);
                $hasCondition = true;
            }

            if (! $hasCondition) {
                $query->whereRaw('1 = 0');
            }
        });
    }

    /**
     * @param  array<string, array{count: int, items: array<int, string>, hint: string}>  $usage
     */
    private function addUsage(array &$usage, string $module, int $count, array $items, string $hint): void
    {
        if ($count < 1) {
            return;
        }

        if (! isset($usage[$module])) {
            $usage[$module] = [
                'count' => 0,
                'items' => [],
                'hint' => $hint,
            ];
        }

        $usage[$module]['count'] += $count;
        $usage[$module]['items'] = collect([...$usage[$module]['items'], ...$items])
            ->filter()
            ->unique()
            ->take(5)
            ->values()
            ->all();
    }

    /**
     * @param  array<string, array{count: int, items: array<int, string>, hint: string}>  $usage
     */
    private function formatUsageMessage(array $usage): string
    {
        $lines = ['Periode RPJMD ini belum bisa dihapus karena masih dipakai oleh data berikut:'];

        foreach ($usage as $module => $detail) {
            $lines[] = "- {$module}: {$detail['count']} data";

            foreach ($detail['items'] as $item) {
                $lines[] = "  - {$item}";
            }

            if ($detail['count'] > count($detail['items'])) {
                $lines[] = '  - dan '.($detail['count'] - count($detail['items'])).' data lainnya';
            }

            $lines[] = "  Lokasi: {$detail['hint']}";
        }

        $lines[] = 'Hapus data atau lepaskan relasinya terlebih dahulu, lalu ulangi Hapus Periode.';

        return implode("\n", $lines);
    }

    private function formatDocumentUsage(?string $document, ?string $opd, string $type, ?string $code, ?string $name, string $fallback): string
    {
        $parts = array_filter([
            $document ? Str::limit($document, 80) : null,
            $opd ? Str::limit($opd, 60) : null,
            $type.': '.$this->formatCodeName($code, $name, $fallback),
        ]);

        return implode(' / ', $parts);
    }

    private function formatCodeName(?string $code, ?string $name, string $fallback): string
    {
        $text = trim(implode(' - ', array_filter([
            trim((string) $code) !== '' ? trim((string) $code) : null,
            trim((string) $name) !== '' ? trim((string) $name) : null,
        ])));

        return Str::limit($text !== '' ? $text : $fallback, 110);
    }

    private function canUseColumn(string $table, string $column): bool
    {
        return Schema::hasTable($table) && Schema::hasColumn($table, $column);
    }

    private function whereNotDeleted(Builder $query, string $table): void
    {
        if (Schema::hasColumn($table, 'deleted_at')) {
            $query->whereNull("{$table}.deleted_at");
        }
    }
}
