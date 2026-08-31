<?php

namespace App\Services\Kinerja;

use App\Models\DpaOpd;
use App\Models\DpaOpdItem;
use App\Models\IndikatorSasaranDaerah;
use App\Models\IndikatorSasaranOpd;
use App\Models\IndikatorTujuanDaerah;
use App\Models\IndikatorTujuanOpd;
use App\Models\OpdProgram;
use App\Models\PerjanjianKinerja;
use App\Models\Rkpd;
use App\Models\RkpdItem;
use App\Models\SasaranDaerah;
use App\Models\SasaranOpd;
use App\Models\TujuanDaerah;
use App\Models\TujuanOpd;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PerjanjianKinerjaSnapshotService
{
    private const OFFICIAL_STATUSES = ['approved', 'locked'];

    public function populate(PerjanjianKinerja $pk): void
    {
        if (! in_array($pk->level_pk, ['bupati', 'kepala_opd'], true)) {
            return;
        }

        DB::transaction(function () use ($pk): void {
            $pk->items()->delete();
            $pk->programs()->delete();

            if ($pk->level_pk === 'bupati') {
                $this->populateBupati($pk);
            } else {
                $this->populateKepalaOpd($pk);
            }

            $pk->forceFill(['snapshot_dibuat_pada' => now()])->saveQuietly();
        });
    }

    private function populateBupati(PerjanjianKinerja $pk): void
    {
        $rkpd = Rkpd::query()
            ->with(['rpjmd:id,judul,tahun_awal,tahun_akhir'])
            ->find($pk->rkpd_id);

        if (! $rkpd
            || (int) $rkpd->tahun !== (int) $pk->tahun
            || (int) $rkpd->periode_tahun_id !== (int) $pk->periode_tahun_id
            || ! $rkpd->isOfficialVersion()
            || ! $rkpd->is_active_version) {
            throw ValidationException::withMessages([
                'rkpd_id' => 'PK Bupati harus memakai RKPD resmi aktif pada tahun yang sama.',
            ]);
        }

        $targets = $rkpd->ikuTargets()
            ->get(['indikator_type', 'indikator_id', 'target_rkpd'])
            ->keyBy(fn ($target) => $target->indikator_type.':'.$target->indikator_id);

        $items = collect();

        TujuanDaerah::query()
            ->forRpjmd((int) $rkpd->rpjmd_id)
            ->with(['indikator.satuanIndikator:id,nama,simbol'])
            ->orderBy('urutan')
            ->get()
            ->each(function (TujuanDaerah $tujuan) use ($items, $targets): void {
                $tujuan->indikator->each(function (IndikatorTujuanDaerah $indikator) use ($items, $targets, $tujuan): void {
                    $target = $targets->get('indikator_tujuan_daerah:'.$indikator->id);
                    $items->push($this->performanceItem(
                        'tujuan',
                        $tujuan->kode,
                        $tujuan->tujuan,
                        $indikator,
                        $target?->target_rkpd,
                    ));
                });
            });

        SasaranDaerah::query()
            ->whereHas('tujuan', fn ($query) => $query->forRpjmd((int) $rkpd->rpjmd_id))
            ->with(['tujuan:id,urutan', 'indikator.satuanIndikator:id,nama,simbol'])
            ->get()
            ->sortBy(fn (SasaranDaerah $sasaran) => sprintf('%06d-%06d-%010d', $sasaran->tujuan?->urutan ?? 0, $sasaran->urutan, $sasaran->id))
            ->each(function (SasaranDaerah $sasaran) use ($items, $targets): void {
                $sasaran->indikator->each(function (IndikatorSasaranDaerah $indikator) use ($items, $targets, $sasaran): void {
                    $target = $targets->get('indikator_sasaran_daerah:'.$indikator->id);
                    $items->push($this->performanceItem(
                        'sasaran',
                        $sasaran->kode,
                        $sasaran->sasaran,
                        $indikator,
                        $target?->target_rkpd,
                    ));
                });
            });

        $this->storeItems($pk, $items);
        $this->storeRkpdPrograms($pk, $rkpd);
    }

    private function populateKepalaOpd(PerjanjianKinerja $pk): void
    {
        $renstra = $pk->renstraOpd;
        $dpa = DpaOpd::query()->find($pk->dpa_opd_id);

        if (! $pk->opd_id
            || ! $renstra
            || (int) $renstra->opd_id !== (int) $pk->opd_id
            || ! in_array($renstra->status, self::OFFICIAL_STATUSES, true)
            || ! $renstra->is_active_version
            || (int) $pk->tahun < (int) $renstra->tahun_awal
            || (int) $pk->tahun > (int) $renstra->tahun_akhir) {
            throw ValidationException::withMessages([
                'renstra_opd_id' => 'PK Kepala OPD harus memakai Renstra resmi aktif yang sesuai OPD dan tahun.',
            ]);
        }

        if (! $dpa
            || (int) $dpa->opd_id !== (int) $pk->opd_id
            || (int) $dpa->tahun !== (int) $pk->tahun
            || (int) $dpa->periode_tahun_id !== (int) $pk->periode_tahun_id
            || ! $dpa->renjaOpd()->where('renstra_opd_id', $renstra->id)->exists()
            || ! in_array($dpa->status, self::OFFICIAL_STATUSES, true)) {
            throw ValidationException::withMessages([
                'dpa_opd_id' => 'PK Kepala OPD harus memakai DPA/DPPA yang sudah disetujui atau terkunci pada tahun yang sama.',
            ]);
        }

        $items = collect();

        TujuanOpd::query()
            ->where('renstra_opd_id', $renstra->id)
            ->with(['indikator' => fn ($query) => $query->with([
                'satuanIndikator:id,nama,simbol',
                'targets' => fn ($query) => $query->where('periode_tahun_id', $pk->periode_tahun_id),
            ])])
            ->orderBy('urutan')
            ->get()
            ->each(function (TujuanOpd $tujuan) use ($items): void {
                $tujuan->indikator->each(function (IndikatorTujuanOpd $indikator) use ($items, $tujuan): void {
                    $target = $indikator->targets->first();
                    $items->push($this->performanceItem(
                        'tujuan_opd',
                        $indikator->kode,
                        $tujuan->tujuan,
                        $indikator,
                        $target?->target_text,
                        $target?->target,
                    ));
                });
            });

        SasaranOpd::query()
            ->whereHas('tujuan', fn ($query) => $query->where('renstra_opd_id', $renstra->id))
            ->with([
                'tujuan:id,urutan',
                'indikator' => fn ($query) => $query->with([
                    'satuanIndikator:id,nama,simbol',
                    'targets' => fn ($query) => $query->where('periode_tahun_id', $pk->periode_tahun_id),
                ]),
            ])
            ->get()
            ->sortBy(fn (SasaranOpd $sasaran) => sprintf('%06d-%06d-%010d', $sasaran->tujuan?->urutan ?? 0, $sasaran->urutan, $sasaran->id))
            ->each(function (SasaranOpd $sasaran) use ($items): void {
                $sasaran->indikator->each(function (IndikatorSasaranOpd $indikator) use ($items, $sasaran): void {
                    $target = $indikator->targets->first();
                    $items->push([
                        ...$this->performanceItem(
                            'sasaran_opd',
                            $sasaran->kode,
                            $sasaran->sasaran,
                            $indikator,
                            $target?->target_text,
                            $target?->target,
                        ),
                        'sasaran_opd_id' => $sasaran->id,
                        'indikator_sasaran_opd_id' => $indikator->id,
                    ]);
                });
            });

        OpdProgram::query()
            ->where('renstra_opd_id', $renstra->id)
            ->with([
                'sasaran:id,urutan',
                'indikator' => fn ($query) => $query->with([
                    'satuanIndikator:id,nama,simbol',
                    'targets' => fn ($query) => $query->where('periode_tahun_id', $pk->periode_tahun_id),
                ]),
            ])
            ->get()
            ->sortBy(fn (OpdProgram $program) => sprintf('%06d-%06d-%010d', $program->sasaran?->urutan ?? 0, $program->urutan, $program->id))
            ->each(function (OpdProgram $program) use ($items): void {
                $program->indikator->each(function ($indikator) use ($items, $program): void {
                    $target = $indikator->targets->first();
                    $items->push([
                        ...$this->performanceItem(
                            'program_opd',
                            $program->kode,
                            $program->sasaran_program ?: $program->nama,
                            $indikator,
                            $target?->target_text,
                            $target?->target,
                        ),
                        'opd_program_id' => $program->id,
                    ]);
                });
            });

        $this->storeItems($pk, $items);
        $this->storeDpaPrograms($pk, $dpa, $renstra->id);
    }

    private function performanceItem(
        string $type,
        ?string $code,
        string $performance,
        Model $indicator,
        mixed $targetText = null,
        mixed $target = null,
    ): array {
        $unit = $indicator->getRelation('satuanIndikator');

        return [
            'sumber_item' => 'snapshot',
            'jenis_item' => $type,
            'level_cascading' => $type,
            'cascading_source_type' => $indicator->getTable(),
            'cascading_source_id' => $indicator->getKey(),
            'satuan_indikator_id' => $indicator->getAttribute('satuan_indikator_id'),
            'satuan_snapshot' => $unit?->simbol ?: $unit?->nama,
            'kode' => $code,
            'sasaran' => $performance,
            'indikator' => (string) $indicator->getAttribute('indikator'),
            'target' => $target,
            'target_text' => filled($targetText) ? (string) $targetText : null,
            'is_readonly' => true,
        ];
    }

    private function storeItems(PerjanjianKinerja $pk, Collection $items): void
    {
        $items->values()->each(function (array $item, int $index) use ($pk): void {
            $pk->items()->create([...$item, 'urutan' => $index + 1]);
        });
    }

    private function storeRkpdPrograms(PerjanjianKinerja $pk, Rkpd $rkpd): void
    {
        $groups = RkpdItem::query()
            ->where('rkpd_id', $rkpd->id)
            ->with(['programRpjmd:id,kode,nama', 'programPemerintahan:id,kode,nama'])
            ->orderBy('urutan')
            ->get()
            ->groupBy(fn (RkpdItem $item) => $item->program_rpjmd_id
                ? 'rpjmd:'.$item->program_rpjmd_id
                : 'master:'.($item->program_pemerintahan_id ?: ($item->kode ?: $item->nama_urusan_bidang_program_kegiatan_sub)));

        $this->storeProgramGroups($pk, $groups, function (Collection $items): array {
            /** @var RkpdItem $first */
            $first = $items->first();
            $program = $first->programRpjmd ?: $first->programPemerintahan;

            return [
                'program_rpjmd_id' => $first->program_rpjmd_id,
                'program_pemerintahan_id' => $first->program_pemerintahan_id,
                'kode' => $program?->kode ?: $first->kode,
                'nama_program' => $program?->nama ?: $first->nama_urusan_bidang_program_kegiatan_sub,
                'anggaran' => $items->sum(fn (RkpdItem $item) => (float) ($item->pagu_indikatif ?? 0)),
                'keterangan' => $this->fundingLabel($items->pluck('sumber_dana')),
            ];
        });
    }

    private function storeDpaPrograms(PerjanjianKinerja $pk, DpaOpd $dpa, int $renstraId): void
    {
        $opdPrograms = OpdProgram::query()
            ->where('renstra_opd_id', $renstraId)
            ->get(['id', 'program_pemerintahan_id', 'kode'])
            ->groupBy('program_pemerintahan_id');

        $groups = DpaOpdItem::query()
            ->where('dpa_opd_id', $dpa->id)
            ->orderBy('urutan')
            ->get()
            ->groupBy(fn (DpaOpdItem $item) => (string) ($item->program_pemerintahan_id ?: ($item->kode_program ?: $item->nama_program)));

        $this->storeProgramGroups($pk, $groups, function (Collection $items) use ($opdPrograms): array {
            /** @var DpaOpdItem $first */
            $first = $items->first();
            $opdProgram = $opdPrograms->get($first->program_pemerintahan_id)?->first();

            return [
                'opd_program_id' => $opdProgram?->id,
                'program_pemerintahan_id' => $first->program_pemerintahan_id,
                'kode' => $first->kode_program,
                'nama_program' => $first->nama_program ?: 'Program belum diberi nama',
                'anggaran' => $items->sum(fn (DpaOpdItem $item) => (float) ($item->pagu_dpa ?? 0)),
                'keterangan' => $this->fundingLabel($items->pluck('sumber_pendanaan')),
            ];
        });
    }

    private function storeProgramGroups(PerjanjianKinerja $pk, Collection $groups, callable $mapper): void
    {
        $rows = $groups
            ->map($mapper)
            ->filter(fn (array $row) => filled($row['nama_program'] ?? null))
            ->sort(fn (array $left, array $right) => strnatcasecmp((string) ($left['kode'] ?? ''), (string) ($right['kode'] ?? '')))
            ->values();

        $rows->each(function (array $row, int $index) use ($pk): void {
            $pk->programs()->create([...$row, 'urutan' => $index + 1]);
        });
    }

    private function fundingLabel(Collection $values): string
    {
        $labels = $values
            ->flatMap(fn ($value) => preg_split('/[,;]+/', (string) $value) ?: [])
            ->map(fn ($value) => trim((string) $value))
            ->filter()
            ->unique(fn ($value) => mb_strtolower($value))
            ->values();

        return $labels->isNotEmpty() ? $labels->implode(', ') : 'APBD';
    }
}
