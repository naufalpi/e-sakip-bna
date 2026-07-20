<?php

namespace App\Services\Master;

use App\Models\KegiatanPemerintahan;
use App\Models\ProgramPemerintahan;
use App\Models\SubKegiatanPemerintahan;
use Illuminate\Support\Facades\DB;
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
            ];

            ProgramPemerintahan::query()
                ->where('tahun_awal', $sourceTahunAwal)
                ->where('tahun_akhir', $sourceTahunAkhir)
                ->orderBy('kode')
                ->get()
                ->each(function (ProgramPemerintahan $sourceProgram) use ($targetTahunAwal, $targetTahunAkhir, &$result): void {
                    $targetProgram = ProgramPemerintahan::query()->firstOrCreate(
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

                    $result[$targetProgram->wasRecentlyCreated ? 'program_created' : 'program_existing']++;
                });

            return $result;
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
                            [
                                'nama' => $sourceSubKegiatan->nama,
                                'status' => $sourceSubKegiatan->status,
                            ],
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
                                        [
                                            'nama' => $sourceSubKegiatan->nama,
                                            'status' => $sourceSubKegiatan->status,
                                        ],
                                    );

                                    $result[$targetSubKegiatan->wasRecentlyCreated ? 'sub_kegiatan_created' : 'sub_kegiatan_existing']++;
                                });
                            }
                        });
                });

            return $result;
        });
    }
}
