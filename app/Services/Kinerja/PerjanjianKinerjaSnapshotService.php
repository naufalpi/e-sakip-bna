<?php

namespace App\Services\Kinerja;

use App\Models\DpaOpd;
use App\Models\DpaOpdItem;
use App\Models\IndikatorSasaranDaerah;
use App\Models\IndikatorSasaranOpd;
use App\Models\IndikatorTujuanDaerah;
use App\Models\IndikatorTujuanOpd;
use App\Models\OpdKegiatan;
use App\Models\OpdProgram;
use App\Models\OpdSubKegiatan;
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
        $isAutomatic = in_array($pk->level_pk, ['bupati', 'kepala_opd'], true);
        $isDirectCascading = $pk->sumber_data === 'renstra_cascading';

        if (! $isAutomatic && ! $isDirectCascading && $pk->sumber_data !== 'manual') {
            return;
        }

        DB::transaction(function () use ($pk): void {
            $pk->items()->delete();
            $pk->programs()->delete();

            if ($pk->sumber_data === 'manual') {
                $pk->forceFill(['snapshot_dibuat_pada' => null])->saveQuietly();

                return;
            }

            if ($pk->level_pk === 'bupati') {
                $this->populateBupati($pk);
            } elseif ($pk->level_pk === 'kepala_opd') {
                $this->populateKepalaOpd($pk);
            } else {
                $this->populateDirectCascading($pk);
            }

            $pk->forceFill(['snapshot_dibuat_pada' => now()])->saveQuietly();
        });
    }

    private function populateDirectCascading(PerjanjianKinerja $pk): void
    {
        $renstra = $pk->renstraOpd;

        if (! $pk->opd_id
            || ! $renstra
            || (int) $renstra->opd_id !== (int) $pk->opd_id
            || ! in_array($renstra->status, self::OFFICIAL_STATUSES, true)
            || ! $renstra->is_active_version
            || ! $pk->periodeTahun()->where('tahun', $pk->tahun)->exists()
            || (int) $pk->tahun < (int) $renstra->tahun_awal
            || (int) $pk->tahun > (int) $renstra->tahun_akhir) {
            throw ValidationException::withMessages([
                'renstra_opd_id' => 'PK Cascading harus memakai Renstra resmi aktif yang sesuai OPD dan tahun.',
            ]);
        }

        $selection = collect($pk->lingkup_kinerja_snapshot ?? [])->filter()->unique()->values();
        if ($selection->isEmpty()) {
            throw ValidationException::withMessages([
                'lingkup_kinerja_snapshot' => 'Pilih minimal satu item cascading dari Renstra.',
            ]);
        }

        if ($pk->level_pk === 'individu'
            && $pk->tipe_pk === 'cascading'
            && $selection->contains(fn (string $key) => ! str_starts_with($key, 'opd_kegiatan:') && ! str_starts_with($key, 'opd_sub_kegiatan:'))) {
            throw ValidationException::withMessages([
                'lingkup_kinerja_snapshot' => 'PK Kasi/Kasubbag/JF/Pelaksana hanya dapat mengambil Kegiatan atau Sub Kegiatan dari cascading Renstra.',
            ]);
        }

        $items = collect();

        $selection->each(function (string $key) use ($items, $pk, $renstra): void {
            [$type, $rawId] = array_pad(explode(':', $key, 2), 2, null);
            $id = (int) $rawId;

            $node = match ($type) {
                'sasaran_opd' => SasaranOpd::query()
                    ->whereKey($id)
                    ->whereHas('tujuan', fn ($query) => $query->where('renstra_opd_id', $renstra->id))
                    ->with(['indikator' => fn ($query) => $query->with([
                        'satuanIndikator:id,nama,simbol',
                        'targets' => fn ($query) => $query->where('periode_tahun_id', $pk->periode_tahun_id),
                    ])])
                    ->first(),
                'opd_program' => OpdProgram::query()
                    ->whereKey($id)
                    ->where('renstra_opd_id', $renstra->id)
                    ->with(['indikator' => fn ($query) => $query->with([
                        'satuanIndikator:id,nama,simbol',
                        'targets' => fn ($query) => $query->where('periode_tahun_id', $pk->periode_tahun_id),
                    ])])
                    ->first(),
                'opd_kegiatan' => OpdKegiatan::query()
                    ->whereKey($id)
                    ->whereHas('program', fn ($query) => $query->where('renstra_opd_id', $renstra->id))
                    ->with(['indikator' => fn ($query) => $query->with([
                        'satuanIndikator:id,nama,simbol',
                        'targets' => fn ($query) => $query->where('periode_tahun_id', $pk->periode_tahun_id),
                    ])])
                    ->first(),
                'opd_sub_kegiatan' => OpdSubKegiatan::query()
                    ->whereKey($id)
                    ->whereHas('kegiatan.program', fn ($query) => $query->where('renstra_opd_id', $renstra->id))
                    ->with(['indikator' => fn ($query) => $query->with([
                        'satuanIndikator:id,nama,simbol',
                        'targets' => fn ($query) => $query->where('periode_tahun_id', $pk->periode_tahun_id),
                    ])])
                    ->first(),
                default => null,
            };

            if (! $node) {
                throw ValidationException::withMessages([
                    'lingkup_kinerja_snapshot' => 'Salah satu item cascading tidak ditemukan pada Renstra yang dipilih.',
                ]);
            }

            if ($node->indikator->isEmpty()) {
                throw ValidationException::withMessages([
                    'lingkup_kinerja_snapshot' => 'Salah satu item cascading yang dipilih belum memiliki indikator pada Renstra.',
                ]);
            }

            $performance = match ($type) {
                'sasaran_opd' => $node->sasaran,
                'opd_program' => $node->sasaran_program ?: $node->nama,
                'opd_kegiatan' => $node->sasaran_kegiatan ?: $node->nama,
                'opd_sub_kegiatan' => $node->sasaran_sub_kegiatan ?: $node->nama,
            };
            $itemType = match ($type) {
                'sasaran_opd' => 'sasaran_opd',
                'opd_program' => 'program_opd',
                'opd_kegiatan' => 'kegiatan_opd',
                'opd_sub_kegiatan' => 'sub_kegiatan_opd',
            };

            $node->indikator->each(function (Model $indicator) use ($items, $node, $type, $itemType, $performance): void {
                $target = $indicator->targets->first();
                $references = match ($type) {
                    'sasaran_opd' => [
                        'sasaran_opd_id' => $node->id,
                        'indikator_sasaran_opd_id' => $indicator->id,
                    ],
                    'opd_program' => ['opd_program_id' => $node->id],
                    default => [],
                };

                $items->push([
                    ...$this->performanceItem(
                        $itemType,
                        $node->kode,
                        $performance,
                        $indicator,
                        $target?->target_text,
                        $target?->target,
                    ),
                    ...$references,
                ]);
            });
        });

        if ($items->isEmpty()) {
            throw ValidationException::withMessages([
                'lingkup_kinerja_snapshot' => 'Item yang dipilih belum memiliki indikator sehingga belum dapat dimasukkan ke PK.',
            ]);
        }

        $this->ensureTargetsAvailable($items, 'lingkup_kinerja_snapshot', 'Target tahunan salah satu indikator cascading belum tersedia pada Renstra untuk tahun PK.');
        $this->storeItems($pk, $items);
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

        if (! $dpa->items()->exists()) {
            throw ValidationException::withMessages([
                'dpa_opd_id' => 'DPA/DPPA belum memiliki rincian anggaran yang dapat dimasukkan ke PK Kepala OPD.',
            ]);
        }

        $items = collect();

        $goals = TujuanOpd::query()
            ->where('renstra_opd_id', $renstra->id)
            ->with(['indikator' => fn ($query) => $query->with([
                'satuanIndikator:id,nama,simbol',
                'targets' => fn ($query) => $query->where('periode_tahun_id', $pk->periode_tahun_id),
            ])])
            ->orderBy('urutan')
            ->orderBy('id')
            ->get();

        $goals->each(function (TujuanOpd $tujuan) use ($items): void {
            if ($tujuan->indikator->isEmpty()) {
                throw ValidationException::withMessages([
                    'renstra_opd_id' => 'Salah satu Tujuan OPD belum memiliki indikator pada Renstra.',
                ]);
            }

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

        $objectives = SasaranOpd::query()
            ->whereHas('tujuan', fn ($query) => $query->where('renstra_opd_id', $renstra->id))
            ->with([
                'tujuan:id,urutan',
                'indikator' => fn ($query) => $query->with([
                    'satuanIndikator:id,nama,simbol',
                    'targets' => fn ($query) => $query->where('periode_tahun_id', $pk->periode_tahun_id),
                ]),
            ])
            ->get()
            ->sortBy(fn (SasaranOpd $sasaran) => sprintf('%06d-%06d-%010d', $sasaran->tujuan?->urutan ?? 0, $sasaran->urutan, $sasaran->id));

        $objectives->each(function (SasaranOpd $sasaran) use ($items): void {
            if ($sasaran->indikator->isEmpty()) {
                throw ValidationException::withMessages([
                    'renstra_opd_id' => 'Salah satu Sasaran OPD belum memiliki indikator pada Renstra.',
                ]);
            }

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

        if ($goals->isEmpty() || $objectives->isEmpty()) {
            throw ValidationException::withMessages([
                'renstra_opd_id' => 'Renstra belum memiliki Tujuan OPD dan Sasaran OPD yang lengkap untuk PK Kepala OPD.',
            ]);
        }

        $this->ensureTargetsAvailable($items, 'renstra_opd_id', 'Target tahunan salah satu indikator Tujuan/Sasaran OPD belum tersedia pada Renstra untuk tahun PK.');
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
            ->get(['id', 'program_pemerintahan_id', 'kode', 'nama']);
        $programsByMaster = $opdPrograms
            ->filter(fn (OpdProgram $program) => filled($program->program_pemerintahan_id))
            ->groupBy(fn (OpdProgram $program) => (string) $program->program_pemerintahan_id);
        $programsByCode = $opdPrograms
            ->filter(fn (OpdProgram $program) => filled($program->kode))
            ->groupBy(fn (OpdProgram $program) => $this->normalizeProgramIdentity($program->kode));
        $programsByName = $opdPrograms
            ->filter(fn (OpdProgram $program) => filled($program->nama))
            ->groupBy(fn (OpdProgram $program) => $this->normalizeProgramIdentity($program->nama));

        $groups = DpaOpdItem::query()
            ->where('dpa_opd_id', $dpa->id)
            ->orderBy('urutan')
            ->get()
            ->groupBy(function (DpaOpdItem $item): string {
                if (filled($item->kode_program)) {
                    return 'code:'.$this->normalizeProgramIdentity($item->kode_program);
                }

                if (filled($item->program_pemerintahan_id)) {
                    return 'master:'.$item->program_pemerintahan_id;
                }

                if (filled($item->nama_program)) {
                    return 'name:'.$this->normalizeProgramIdentity($item->nama_program);
                }

                return 'item:'.$item->id;
            });

        $this->storeProgramGroups($pk, $groups, function (Collection $items) use ($programsByMaster, $programsByCode, $programsByName): array {
            /** @var DpaOpdItem $first */
            $first = $items->first();
            $masterId = $items->pluck('program_pemerintahan_id')->filter()->first();
            $code = $items->pluck('kode_program')->first(fn ($value) => filled($value));
            $name = $items->pluck('nama_program')->first(fn ($value) => filled($value));
            $opdProgram = ($masterId ? $programsByMaster->get((string) $masterId)?->first() : null)
                ?: (filled($code) ? $programsByCode->get($this->normalizeProgramIdentity($code))?->first() : null)
                ?: (filled($name) ? $programsByName->get($this->normalizeProgramIdentity($name))?->first() : null);

            return [
                'opd_program_id' => $opdProgram?->id,
                'program_pemerintahan_id' => $masterId,
                'kode' => $code,
                'nama_program' => $name ?: 'Program belum diberi nama',
                'anggaran' => $items->sum(fn (DpaOpdItem $item) => (float) ($item->pagu_dpa ?? 0)),
                'keterangan' => $this->fundingLabel($items->pluck('sumber_pendanaan')),
            ];
        });
    }

    private function ensureTargetsAvailable(Collection $items, string $field, string $message): void
    {
        if ($items->contains(fn (array $item) => $item['target'] === null && blank($item['target_text'] ?? null))) {
            throw ValidationException::withMessages([$field => $message]);
        }
    }

    private function normalizeProgramIdentity(mixed $value): string
    {
        return mb_strtolower(preg_replace('/\s+/', ' ', trim((string) $value)) ?? '');
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
