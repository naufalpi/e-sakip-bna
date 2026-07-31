<?php

namespace App\Services\Perencanaan;

use App\Models\PlanningSyncBatch;
use App\Models\PlanningSyncBatchRow;
use App\Models\RenjaOpd;
use App\Models\RenjaOpdItem;
use App\Models\Rkpd;
use App\Models\RkpdItem;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PlanningSyncService
{
    private const LOCKED_STATUSES = ['approved', 'locked'];

    /**
     * @var array<string, string>
     */
    private const FIELD_LABELS = [
        'sub_kegiatan_pemerintahan_id' => 'Sub Kegiatan',
        'indikator' => 'Indikator',
        'target_akhir_renstra' => 'Target Akhir Renstra',
        'realisasi_capaian_renja_tahun_lalu' => 'Realisasi Tahun Lalu',
        'prakiraan_capaian_target_renja_tahun_berjalan' => 'Prakiraan Tahun Berjalan',
        'target' => 'Target',
        'pagu_indikatif' => 'Pagu Indikatif',
        'lokasi' => 'Lokasi',
        'sumber_dana' => 'Sumber Dana',
        'prioritas_nasional' => 'Prioritas Nasional',
        'prioritas_daerah' => 'Prioritas Daerah',
        'kelompok_sasaran' => 'Kelompok Sasaran',
        'prakiraan_maju_target' => 'Prakiraan Maju Target',
        'prakiraan_maju_pagu_indikatif' => 'Prakiraan Maju Pagu',
    ];

    /**
     * @var array<int, string>
     */
    private const COMPARED_FIELDS = [
        'sub_kegiatan_pemerintahan_id',
        'indikator',
        'target_akhir_renstra',
        'realisasi_capaian_renja_tahun_lalu',
        'prakiraan_capaian_target_renja_tahun_berjalan',
        'target',
        'pagu_indikatif',
        'lokasi',
        'sumber_dana',
        'prioritas_nasional',
        'prioritas_daerah',
        'kelompok_sasaran',
        'prakiraan_maju_target',
        'prakiraan_maju_pagu_indikatif',
    ];

    public function __construct(private readonly ActivityLogger $activityLogger) {}

    /**
     * @param  array<string, mixed>  $filters
     */
    public function previewRenjaToRkpd(Rkpd $rkpd, User $user, array $filters = []): PlanningSyncBatch
    {
        $this->ensureMutable($rkpd, $user, 'RKPD');

        return DB::transaction(function () use ($rkpd, $user, $filters) {
            $renjaStatuses = $this->statuses($filters['renja_statuses'] ?? null);

            $batch = PlanningSyncBatch::create([
                'source_module' => 'renja_opd',
                'target_module' => 'rkpd',
                'target_id' => $rkpd->id,
                'tahun' => $rkpd->tahun,
                'status' => 'previewed',
                'filters' => [
                    'renja_statuses' => $renjaStatuses,
                    'opd_id' => $filters['opd_id'] ?? null,
                ],
                'created_by' => $user->id,
            ]);

            $targetItems = $this->rkpdItems($rkpd);
            $matchedTargetIds = collect();

            $renjas = RenjaOpd::query()
                ->with([
                    'opd:id,kode,nama,singkatan',
                    'opdUnit:id,opd_id,kode,nama',
                    'items.programPemerintahan:id,bidang_urusan_id,kode,nama',
                    'items.programPemerintahan.bidangUrusan:id,urusan_pemerintahan_id,kode,nama',
                    'items.programPemerintahan.bidangUrusan.urusanPemerintahan:id,kode,nama',
                    'items.kegiatanPemerintahan:id,program_pemerintahan_id,kode,nama',
                    'items.subKegiatanPemerintahan:id,kegiatan_pemerintahan_id,kode,nama',
                    'items.subKegiatanPemerintahan.kegiatanPemerintahan:id,program_pemerintahan_id,kode,nama',
                    'items.subKegiatanPemerintahan.kegiatanPemerintahan.programPemerintahan:id,bidang_urusan_id,kode,nama',
                    'items.subKegiatanPemerintahan.kegiatanPemerintahan.programPemerintahan.bidangUrusan:id,urusan_pemerintahan_id,kode,nama',
                    'items.subKegiatanPemerintahan.kegiatanPemerintahan.programPemerintahan.bidangUrusan.urusanPemerintahan:id,kode,nama',
                ])
                ->where(function ($query) use ($rkpd) {
                    $query->where('rkpd_id', $rkpd->id)
                        ->orWhere(function ($query) use ($rkpd) {
                            $query->where('periode_tahun_id', $rkpd->periode_tahun_id)
                                ->where('tahun', $rkpd->tahun);
                        });
                })
                ->when($filters['opd_id'] ?? null, fn ($query, $opdId) => $query->where('opd_id', $opdId))
                ->whereIn('status', $renjaStatuses)
                ->orderBy('opd_id')
                ->orderBy('id')
                ->get();

            foreach ($renjas as $renja) {
                foreach ($renja->items as $item) {
                    $source = $this->renjaSourcePayload($renja, $item);
                    $target = $this->findRkpdTarget($targetItems, $source);

                    if ($target) {
                        $matchedTargetIds->push((int) $target->id);
                    }

                    $targetPayload = $target ? $this->rkpdTargetPayload($target) : null;
                    $diff = $this->diff($source, $targetPayload);
                    $action = $target ? ($diff === [] ? 'unchanged' : 'update') : 'create';

                    $this->createRow($batch, [
                        'source_table' => 'renja_opd_items',
                        'source_id' => $item->id,
                        'target_table' => 'rkpd_items',
                        'target_id' => $target?->id,
                        'match_key' => $this->matchKey($source),
                        'action' => $action,
                        'selected' => in_array($action, ['create', 'update'], true),
                        'diff_values' => $this->diffPayload($source, $targetPayload, $diff),
                    ]);
                }
            }

            $targetItems
                ->reject(fn (RkpdItem $item) => $matchedTargetIds->contains((int) $item->id))
                ->each(function (RkpdItem $item) use ($batch) {
                    $target = $this->rkpdTargetPayload($item);

                    $this->createRow($batch, [
                        'target_table' => 'rkpd_items',
                        'target_id' => $item->id,
                        'match_key' => $this->matchKey($target),
                        'action' => 'target_only',
                        'selected' => false,
                        'diff_values' => $this->diffPayload(null, $target, []),
                        'message' => 'Baris hanya ada di RKPD.',
                    ]);
                });

            return $this->finishPreview($batch);
        });
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function previewRkpdToRenja(RenjaOpd $renja, User $user, array $filters = []): PlanningSyncBatch
    {
        $this->ensureMutable($renja, $user, 'Renja OPD');

        return DB::transaction(function () use ($renja, $user, $filters) {
            $rkpd = $renja->rkpd ?: Rkpd::query()
                ->where('periode_tahun_id', $renja->periode_tahun_id)
                ->where('tahun', $renja->tahun)
                ->latest('id')
                ->first();

            if (! $rkpd) {
                throw ValidationException::withMessages([
                    'sync' => 'RKPD tahun ini belum tersedia atau belum dihubungkan.',
                ]);
            }

            $batch = PlanningSyncBatch::create([
                'source_module' => 'rkpd',
                'target_module' => 'renja_opd',
                'source_id' => $rkpd->id,
                'target_id' => $renja->id,
                'tahun' => $renja->tahun,
                'status' => 'previewed',
                'filters' => [
                    'rkpd_id' => $rkpd->id,
                    'opd_id' => $renja->opd_id,
                    'opd_unit_id' => $renja->opd_unit_id,
                    ...$filters,
                ],
                'created_by' => $user->id,
            ]);

            $targetItems = $this->renjaItems($renja);
            $matchedTargetIds = collect();

            $rkpdItems = $rkpd->items()
                ->with([
                    'opd:id,kode,nama,singkatan',
                    'opdUnit:id,opd_id,kode,nama',
                    'programPemerintahan:id,bidang_urusan_id,kode,nama',
                    'programPemerintahan.bidangUrusan:id,urusan_pemerintahan_id,kode,nama',
                    'programPemerintahan.bidangUrusan.urusanPemerintahan:id,kode,nama',
                    'kegiatanPemerintahan:id,program_pemerintahan_id,kode,nama',
                    'subKegiatanPemerintahan:id,kegiatan_pemerintahan_id,kode,nama',
                ])
                ->where('opd_id', $renja->opd_id)
                ->when($renja->opd_unit_id, fn ($query) => $query->where('opd_unit_id', $renja->opd_unit_id))
                ->orderBy('urutan')
                ->orderBy('id')
                ->get();

            foreach ($rkpdItems as $item) {
                $source = $this->rkpdSourcePayload($item, $renja);
                $target = $this->findRenjaTarget($targetItems, $source, $renja);

                if ($target) {
                    $matchedTargetIds->push((int) $target->id);
                }

                $targetPayload = $target ? $this->renjaTargetPayload($renja, $target) : null;
                $diff = $this->diff($source, $targetPayload);
                $action = $target ? ($diff === [] ? 'unchanged' : 'update') : 'create';

                $this->createRow($batch, [
                    'source_table' => 'rkpd_items',
                    'source_id' => $item->id,
                    'target_table' => 'renja_opd_items',
                    'target_id' => $target?->id,
                    'match_key' => $this->matchKey($source),
                    'action' => $action,
                    'selected' => in_array($action, ['create', 'update'], true),
                    'diff_values' => $this->diffPayload($source, $targetPayload, $diff),
                ]);
            }

            $targetItems
                ->reject(fn (RenjaOpdItem $item) => $matchedTargetIds->contains((int) $item->id))
                ->each(function (RenjaOpdItem $item) use ($batch, $renja) {
                    $target = $this->renjaTargetPayload($renja, $item);

                    $this->createRow($batch, [
                        'target_table' => 'renja_opd_items',
                        'target_id' => $item->id,
                        'match_key' => $this->matchKey($target),
                        'action' => 'target_only',
                        'selected' => false,
                        'diff_values' => $this->diffPayload(null, $target, []),
                        'message' => 'Baris hanya ada di Renja OPD.',
                    ]);
                });

            return $this->finishPreview($batch);
        });
    }

    /**
     * @param  array<int, int>  $selectedRows
     * @return array<string, int>
     */
    public function apply(PlanningSyncBatch $batch, User $user, array $selectedRows = []): array
    {
        if ($batch->status !== 'previewed') {
            throw ValidationException::withMessages([
                'sync' => 'Batch sinkronisasi ini sudah diproses.',
            ]);
        }

        return DB::transaction(function () use ($batch, $user, $selectedRows) {
            $rows = $batch->rows()
                ->whereIn('action', ['create', 'update'])
                ->when($selectedRows !== [], fn ($query) => $query->whereIn('id', $selectedRows))
                ->when($selectedRows === [], fn ($query) => $query->where('selected', true))
                ->get();

            $applied = 0;
            $skipped = 0;

            if ($batch->source_module === 'renja_opd' && $batch->target_module === 'rkpd') {
                $rkpd = Rkpd::findOrFail($batch->target_id);
                $this->ensureMutable($rkpd, $user, 'RKPD');

                foreach ($rows as $row) {
                    if ($this->applyRenjaRowToRkpd($rkpd, $row)) {
                        $applied++;
                    } else {
                        $skipped++;
                    }
                }
            } elseif ($batch->source_module === 'rkpd' && $batch->target_module === 'renja_opd') {
                $renja = RenjaOpd::findOrFail($batch->target_id);
                $this->ensureMutable($renja, $user, 'Renja OPD');

                foreach ($rows as $row) {
                    if ($this->applyRkpdRowToRenja($renja, $row)) {
                        $applied++;
                    } else {
                        $skipped++;
                    }
                }
            } else {
                throw ValidationException::withMessages([
                    'sync' => 'Arah sinkronisasi tidak dikenali.',
                ]);
            }

            $batch->update([
                'status' => 'applied',
                'applied_by' => $user->id,
                'applied_at' => now(),
                'summary' => [
                    ...($batch->summary ?? []),
                    'applied' => $applied,
                    'skipped' => $skipped,
                ],
            ]);

            $this->activityLogger->log(
                action: 'planning_sync_applied',
                model: $batch,
                oldValues: null,
                newValues: $batch->summary,
                description: "Sinkronisasi {$batch->source_module} ke {$batch->target_module} diterapkan.",
            );

            return [
                'applied' => $applied,
                'skipped' => $skipped,
            ];
        });
    }

    /**
     * @return array<string, mixed>|null
     */
    public function serializePreview(?PlanningSyncBatch $batch): ?array
    {
        if (! $batch) {
            return null;
        }

        $batch->loadMissing('rows');

        return [
            'id' => $batch->id,
            'source_module' => $batch->source_module,
            'target_module' => $batch->target_module,
            'tahun' => $batch->tahun,
            'status' => $batch->status,
            'summary' => $batch->summary ?? [],
            'rows' => $batch->rows->map(fn (PlanningSyncBatchRow $row) => [
                'id' => $row->id,
                'action' => $row->action,
                'selected' => $row->selected,
                'status' => $row->status,
                'message' => $row->message,
                'diff_values' => $row->diff_values,
            ])->values()->all(),
        ];
    }

    private function ensureMutable(Rkpd|RenjaOpd $model, User $user, string $label): void
    {
        if (! $user->isSuperAdmin() && in_array((string) $model->status, self::LOCKED_STATUSES, true)) {
            throw ValidationException::withMessages([
                'sync' => "{$label} sudah disetujui atau terkunci. Lakukan revisi resmi sebelum sinkronisasi.",
            ]);
        }
    }

    /**
     * @return array<int, string>
     */
    private function statuses(mixed $statuses): array
    {
        $allowed = ['draft', 'submitted', 'revision', 'verified', 'approved', 'locked'];

        if (is_array($statuses)) {
            $selected = array_values(array_intersect($allowed, array_map('strval', $statuses)));

            if ($selected !== []) {
                return $selected;
            }
        }

        return $allowed;
    }

    /**
     * @return EloquentCollection<int, RkpdItem>
     */
    private function rkpdItems(Rkpd $rkpd): EloquentCollection
    {
        return $rkpd->items()
            ->with([
                'opd:id,kode,nama,singkatan',
                'opdUnit:id,opd_id,kode,nama',
                'programPemerintahan:id,bidang_urusan_id,kode,nama',
                'programPemerintahan.bidangUrusan:id,urusan_pemerintahan_id,kode,nama',
                'programPemerintahan.bidangUrusan.urusanPemerintahan:id,kode,nama',
                'kegiatanPemerintahan:id,program_pemerintahan_id,kode,nama',
                'subKegiatanPemerintahan:id,kegiatan_pemerintahan_id,kode,nama',
            ])
            ->get();
    }

    /**
     * @return EloquentCollection<int, RenjaOpdItem>
     */
    private function renjaItems(RenjaOpd $renja): EloquentCollection
    {
        return $renja->items()
            ->with([
                'programPemerintahan:id,bidang_urusan_id,kode,nama',
                'programPemerintahan.bidangUrusan:id,urusan_pemerintahan_id,kode,nama',
                'programPemerintahan.bidangUrusan.urusanPemerintahan:id,kode,nama',
                'kegiatanPemerintahan:id,program_pemerintahan_id,kode,nama',
                'subKegiatanPemerintahan:id,kegiatan_pemerintahan_id,kode,nama',
            ])
            ->get();
    }

    /**
     * @return array<string, mixed>
     */
    private function renjaSourcePayload(RenjaOpd $renja, RenjaOpdItem $item): array
    {
        $sub = $item->subKegiatanPemerintahan;
        $kegiatan = $sub?->kegiatanPemerintahan ?: $item->kegiatanPemerintahan;
        $program = $kegiatan?->programPemerintahan ?: $item->programPemerintahan;
        $bidang = $program?->bidangUrusan;
        $urusan = $bidang?->urusanPemerintahan;

        return [
            'source_id' => $item->id,
            'opd_id' => $renja->opd_id,
            'opd_unit_id' => $renja->opd_unit_id,
            'urusan_pemerintahan_id' => $urusan?->id,
            'bidang_urusan_id' => $bidang?->id,
            'program_pemerintahan_id' => $program?->id,
            'kegiatan_pemerintahan_id' => $kegiatan?->id,
            'sub_kegiatan_pemerintahan_id' => $sub?->id ?: $item->sub_kegiatan_pemerintahan_id,
            'kode' => $item->kode ?: $sub?->kode,
            'nama' => $item->nama_sub_kegiatan ?: $sub?->nama,
            'indikator' => $item->indikator,
            'target_akhir_renstra' => $item->target_akhir_renstra,
            'realisasi_capaian_renja_tahun_lalu' => $item->realisasi_capaian_renja_tahun_lalu,
            'prakiraan_capaian_target_renja_tahun_berjalan' => $item->prakiraan_capaian_target_renja_tahun_berjalan,
            'target' => $item->target,
            'pagu_indikatif' => $item->pagu_indikatif,
            'lokasi' => $item->lokasi,
            'sumber_dana' => $item->sumber_dana,
            'prioritas_nasional' => $item->prioritas_nasional,
            'prioritas_daerah' => $item->prioritas_daerah,
            'kelompok_sasaran' => $item->kelompok_sasaran,
            'prakiraan_maju_target' => $item->prakiraan_maju_target,
            'prakiraan_maju_pagu_indikatif' => $item->prakiraan_maju_pagu_indikatif,
            'status' => $item->status,
            'urutan' => $item->urutan,
            'labels' => [
                'opd' => $renja->opd?->singkatan ? "{$renja->opd->singkatan} - {$renja->opd->nama}" : $renja->opd?->nama,
                'opd_unit' => $renja->opdUnit?->nama,
                'program' => $this->label($program?->kode, $program?->nama),
                'kegiatan' => $this->label($kegiatan?->kode, $kegiatan?->nama),
                'sub_kegiatan' => $this->label($sub?->kode, $sub?->nama),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function rkpdSourcePayload(RkpdItem $item, RenjaOpd $renja): array
    {
        $target = $this->rkpdTargetPayload($item);

        return [
            ...$target,
            'opd_id' => $renja->opd_id,
            'opd_unit_id' => $renja->opd_unit_id,
            'source_id' => $item->id,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function rkpdTargetPayload(RkpdItem $item): array
    {
        return [
            'target_id' => $item->id,
            'opd_id' => $item->opd_id,
            'opd_unit_id' => $item->opd_unit_id,
            'urusan_pemerintahan_id' => $item->urusan_pemerintahan_id,
            'bidang_urusan_id' => $item->bidang_urusan_id,
            'program_pemerintahan_id' => $item->program_pemerintahan_id,
            'kegiatan_pemerintahan_id' => $item->kegiatan_pemerintahan_id,
            'sub_kegiatan_pemerintahan_id' => $item->sub_kegiatan_pemerintahan_id,
            'kode' => $item->kode,
            'nama' => $item->nama_urusan_bidang_program_kegiatan_sub,
            'indikator' => $item->indikator,
            'target_akhir_renstra' => $item->target_akhir_renstra,
            'realisasi_capaian_renja_tahun_lalu' => $item->realisasi_capaian_renja_tahun_lalu,
            'prakiraan_capaian_target_renja_tahun_berjalan' => $item->prakiraan_capaian_target_renja_tahun_berjalan,
            'target' => $item->target,
            'pagu_indikatif' => $item->pagu_indikatif,
            'lokasi' => $item->lokasi,
            'sumber_dana' => $item->sumber_dana,
            'prioritas_nasional' => $item->prioritas_nasional,
            'prioritas_daerah' => $item->prioritas_daerah,
            'kelompok_sasaran' => $item->kelompok_sasaran,
            'prakiraan_maju_target' => $item->prakiraan_maju_target,
            'prakiraan_maju_pagu_indikatif' => $item->prakiraan_maju_pagu_indikatif,
            'status' => $item->status,
            'urutan' => $item->urutan,
            'labels' => [
                'opd' => $item->opd?->singkatan ? "{$item->opd->singkatan} - {$item->opd->nama}" : $item->opd?->nama,
                'opd_unit' => $item->opdUnit?->nama,
                'program' => $this->label($item->programPemerintahan?->kode, $item->programPemerintahan?->nama),
                'kegiatan' => $this->label($item->kegiatanPemerintahan?->kode, $item->kegiatanPemerintahan?->nama),
                'sub_kegiatan' => $this->label($item->subKegiatanPemerintahan?->kode, $item->subKegiatanPemerintahan?->nama),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function renjaTargetPayload(RenjaOpd $renja, RenjaOpdItem $item): array
    {
        return [
            'target_id' => $item->id,
            'opd_id' => $renja->opd_id,
            'opd_unit_id' => $renja->opd_unit_id,
            'program_pemerintahan_id' => $item->program_pemerintahan_id,
            'kegiatan_pemerintahan_id' => $item->kegiatan_pemerintahan_id,
            'sub_kegiatan_pemerintahan_id' => $item->sub_kegiatan_pemerintahan_id,
            'kode' => $item->kode,
            'nama' => $item->nama_sub_kegiatan,
            'indikator' => $item->indikator,
            'target_akhir_renstra' => $item->target_akhir_renstra,
            'realisasi_capaian_renja_tahun_lalu' => $item->realisasi_capaian_renja_tahun_lalu,
            'prakiraan_capaian_target_renja_tahun_berjalan' => $item->prakiraan_capaian_target_renja_tahun_berjalan,
            'target' => $item->target,
            'pagu_indikatif' => $item->pagu_indikatif,
            'lokasi' => $item->lokasi,
            'sumber_dana' => $item->sumber_dana,
            'prioritas_nasional' => $item->prioritas_nasional,
            'prioritas_daerah' => $item->prioritas_daerah,
            'kelompok_sasaran' => $item->kelompok_sasaran,
            'prakiraan_maju_target' => $item->prakiraan_maju_target,
            'prakiraan_maju_pagu_indikatif' => $item->prakiraan_maju_pagu_indikatif,
            'status' => $item->status,
            'urutan' => $item->urutan,
            'labels' => [
                'opd' => $renja->opd?->singkatan ? "{$renja->opd->singkatan} - {$renja->opd->nama}" : $renja->opd?->nama,
                'opd_unit' => $renja->opdUnit?->nama,
                'program' => $this->label($item->programPemerintahan?->kode, $item->programPemerintahan?->nama),
                'kegiatan' => $this->label($item->kegiatanPemerintahan?->kode, $item->kegiatanPemerintahan?->nama),
                'sub_kegiatan' => $this->label($item->subKegiatanPemerintahan?->kode, $item->subKegiatanPemerintahan?->nama),
            ],
        ];
    }

    private function findRkpdTarget(EloquentCollection $items, array $source): ?RkpdItem
    {
        return $items->first(fn (RkpdItem $item) => (int) $item->renja_opd_item_id === (int) $source['source_id'])
            ?: $items->first(fn (RkpdItem $item) => $this->matchKey($this->rkpdTargetPayload($item)) === $this->matchKey($source));
    }

    private function findRenjaTarget(EloquentCollection $items, array $source, RenjaOpd $renja): ?RenjaOpdItem
    {
        return $items->first(fn (RenjaOpdItem $item) => $this->matchKey($this->renjaTargetPayload($renja, $item)) === $this->matchKey($source));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function diff(?array $source, ?array $target): array
    {
        if (! $source || ! $target) {
            return [];
        }

        $diff = [];

        foreach (self::COMPARED_FIELDS as $field) {
            if ($this->normalizeComparable($source[$field] ?? null, $field) !== $this->normalizeComparable($target[$field] ?? null, $field)) {
                $diff[] = [
                    'field' => $field,
                    'label' => self::FIELD_LABELS[$field] ?? $field,
                    'source' => $source[$field] ?? null,
                    'target' => $target[$field] ?? null,
                ];
            }
        }

        return $diff;
    }

    /**
     * @return array<string, mixed>
     */
    private function diffPayload(?array $source, ?array $target, array $diff): array
    {
        return [
            'source' => $source ? $this->publicPayload($source) : null,
            'target' => $target ? $this->publicPayload($target) : null,
            'fields' => $diff,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function publicPayload(array $payload): array
    {
        return [
            'opd_id' => $payload['opd_id'] ?? null,
            'opd_unit_id' => $payload['opd_unit_id'] ?? null,
            'sub_kegiatan_pemerintahan_id' => $payload['sub_kegiatan_pemerintahan_id'] ?? null,
            'kode' => $payload['kode'] ?? null,
            'nama' => $payload['nama'] ?? null,
            'indikator' => $payload['indikator'] ?? null,
            'target' => $payload['target'] ?? null,
            'pagu_indikatif' => $payload['pagu_indikatif'] ?? null,
            'lokasi' => $payload['lokasi'] ?? null,
            'sumber_dana' => $payload['sumber_dana'] ?? null,
            'prioritas_nasional' => $payload['prioritas_nasional'] ?? null,
            'prioritas_daerah' => $payload['prioritas_daerah'] ?? null,
            'kelompok_sasaran' => $payload['kelompok_sasaran'] ?? null,
            'labels' => $payload['labels'] ?? [],
        ];
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createRow(PlanningSyncBatch $batch, array $attributes): PlanningSyncBatchRow
    {
        return $batch->rows()->create($attributes);
    }

    private function finishPreview(PlanningSyncBatch $batch): PlanningSyncBatch
    {
        $summary = $batch->rows()
            ->selectRaw('action, count(*) as aggregate')
            ->groupBy('action')
            ->pluck('aggregate', 'action')
            ->map(fn ($value) => (int) $value)
            ->all();

        $summary = [
            'create' => $summary['create'] ?? 0,
            'update' => $summary['update'] ?? 0,
            'unchanged' => $summary['unchanged'] ?? 0,
            'target_only' => $summary['target_only'] ?? 0,
            'skipped' => $summary['skipped'] ?? 0,
        ];

        $batch->update(['summary' => $summary]);

        $this->activityLogger->log(
            action: 'planning_sync_previewed',
            model: $batch,
            oldValues: null,
            newValues: $summary,
            description: "Preview sinkronisasi {$batch->source_module} ke {$batch->target_module} dibuat.",
        );

        return $batch->fresh('rows');
    }

    private function applyRenjaRowToRkpd(Rkpd $rkpd, PlanningSyncBatchRow $row): bool
    {
        $item = RenjaOpdItem::query()
            ->with([
                'renjaOpd.opd:id,kode,nama,singkatan',
                'renjaOpd.opdUnit:id,opd_id,kode,nama',
                'programPemerintahan:id,bidang_urusan_id,kode,nama',
                'programPemerintahan.bidangUrusan:id,urusan_pemerintahan_id,kode,nama',
                'programPemerintahan.bidangUrusan.urusanPemerintahan:id,kode,nama',
                'kegiatanPemerintahan:id,program_pemerintahan_id,kode,nama',
                'subKegiatanPemerintahan:id,kegiatan_pemerintahan_id,kode,nama',
                'subKegiatanPemerintahan.kegiatanPemerintahan:id,program_pemerintahan_id,kode,nama',
                'subKegiatanPemerintahan.kegiatanPemerintahan.programPemerintahan:id,bidang_urusan_id,kode,nama',
                'subKegiatanPemerintahan.kegiatanPemerintahan.programPemerintahan.bidangUrusan:id,urusan_pemerintahan_id,kode,nama',
                'subKegiatanPemerintahan.kegiatanPemerintahan.programPemerintahan.bidangUrusan.urusanPemerintahan:id,kode,nama',
            ])
            ->find($row->source_id);

        if (! $item || ! $item->renjaOpd) {
            $row->update(['status' => 'skipped', 'message' => 'Sumber Renja sudah tidak tersedia.']);

            return false;
        }

        $source = $this->renjaSourcePayload($item->renjaOpd, $item);
        $target = $row->target_id ? RkpdItem::find($row->target_id) : null;
        $target ??= $this->findRkpdTarget($this->rkpdItems($rkpd), $source);

        $data = [
            'rkpd_id' => $rkpd->id,
            'renja_opd_id' => $item->renja_opd_id,
            'renja_opd_item_id' => $item->id,
            'opd_id' => $source['opd_id'],
            'opd_unit_id' => $source['opd_unit_id'],
            'urusan_pemerintahan_id' => $source['urusan_pemerintahan_id'],
            'bidang_urusan_id' => $source['bidang_urusan_id'],
            'program_pemerintahan_id' => $source['program_pemerintahan_id'],
            'kegiatan_pemerintahan_id' => $source['kegiatan_pemerintahan_id'],
            'sub_kegiatan_pemerintahan_id' => $source['sub_kegiatan_pemerintahan_id'],
            'kode' => $source['kode'],
            'nama_urusan_bidang_program_kegiatan_sub' => $source['nama'],
            'indikator' => $source['indikator'],
            'target_akhir_renstra' => $source['target_akhir_renstra'],
            'realisasi_capaian_renja_tahun_lalu' => $source['realisasi_capaian_renja_tahun_lalu'],
            'prakiraan_capaian_target_renja_tahun_berjalan' => $source['prakiraan_capaian_target_renja_tahun_berjalan'],
            'target' => $source['target'],
            'pagu_indikatif' => $source['pagu_indikatif'],
            'lokasi' => $source['lokasi'],
            'sumber_dana' => $source['sumber_dana'],
            'prioritas_nasional' => $source['prioritas_nasional'],
            'prioritas_daerah' => $source['prioritas_daerah'],
            'kelompok_sasaran' => $source['kelompok_sasaran'],
            'prakiraan_maju_target' => $source['prakiraan_maju_target'],
            'prakiraan_maju_pagu_indikatif' => $source['prakiraan_maju_pagu_indikatif'],
            'perangkat_daerah_penanggung_jawab' => $item->renjaOpd->opd?->nama,
            'urutan' => $source['urutan'] ?: ((int) $rkpd->items()->max('urutan')) + 1,
            'metadata' => [
                'source' => 'renja_opd',
                'sync_batch_id' => $row->planning_sync_batch_id,
            ],
        ];

        if ($target) {
            $target->update($data);
            $row->update(['target_id' => $target->id, 'status' => 'applied', 'message' => 'Baris RKPD diperbarui.']);
        } else {
            $target = RkpdItem::create([...$data, 'status' => 'draft']);
            $row->update(['target_id' => $target->id, 'status' => 'applied', 'message' => 'Baris RKPD dibuat.']);
        }

        return true;
    }

    private function applyRkpdRowToRenja(RenjaOpd $renja, PlanningSyncBatchRow $row): bool
    {
        $item = RkpdItem::query()
            ->with([
                'programPemerintahan:id,bidang_urusan_id,kode,nama',
                'programPemerintahan.bidangUrusan:id,urusan_pemerintahan_id,kode,nama',
                'kegiatanPemerintahan:id,program_pemerintahan_id,kode,nama',
                'subKegiatanPemerintahan:id,kegiatan_pemerintahan_id,kode,nama',
            ])
            ->find($row->source_id);

        if (! $item) {
            $row->update(['status' => 'skipped', 'message' => 'Sumber RKPD sudah tidak tersedia.']);

            return false;
        }

        $source = $this->rkpdSourcePayload($item, $renja);
        $target = $row->target_id ? RenjaOpdItem::find($row->target_id) : null;
        $target ??= $this->findRenjaTarget($this->renjaItems($renja), $source, $renja);

        $data = [
            'renja_opd_id' => $renja->id,
            'program_pemerintahan_id' => $source['program_pemerintahan_id'],
            'kegiatan_pemerintahan_id' => $source['kegiatan_pemerintahan_id'],
            'sub_kegiatan_pemerintahan_id' => $source['sub_kegiatan_pemerintahan_id'],
            'kode' => $source['kode'],
            'nama_sub_kegiatan' => $source['nama'],
            'indikator' => $source['indikator'],
            'target_akhir_renstra' => $source['target_akhir_renstra'],
            'realisasi_capaian_renja_tahun_lalu' => $source['realisasi_capaian_renja_tahun_lalu'],
            'prakiraan_capaian_target_renja_tahun_berjalan' => $source['prakiraan_capaian_target_renja_tahun_berjalan'],
            'target' => $source['target'],
            'pagu_indikatif' => $source['pagu_indikatif'],
            'lokasi' => $source['lokasi'],
            'sumber_dana' => $source['sumber_dana'],
            'prioritas_nasional' => $source['prioritas_nasional'],
            'prioritas_daerah' => $source['prioritas_daerah'],
            'kelompok_sasaran' => $source['kelompok_sasaran'],
            'prakiraan_maju_target' => $source['prakiraan_maju_target'],
            'prakiraan_maju_pagu_indikatif' => $source['prakiraan_maju_pagu_indikatif'],
            'urutan' => $source['urutan'] ?: ((int) $renja->items()->max('urutan')) + 1,
        ];

        if ($target) {
            $target->update($data);
            $row->update(['target_id' => $target->id, 'status' => 'applied', 'message' => 'Baris Renja diperbarui.']);
        } else {
            $target = RenjaOpdItem::create([...$data, 'status' => 'draft']);
            $row->update(['target_id' => $target->id, 'status' => 'applied', 'message' => 'Baris Renja dibuat.']);
        }

        return true;
    }

    private function matchKey(array $payload): string
    {
        return implode('|', [
            'opd:'.(int) ($payload['opd_id'] ?? 0),
            'unit:'.(int) ($payload['opd_unit_id'] ?? 0),
            'sub:'.(int) ($payload['sub_kegiatan_pemerintahan_id'] ?? 0),
            'indikator:'.$this->normalizeText($payload['indikator'] ?? null),
        ]);
    }

    private function normalizeComparable(mixed $value, string $field): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (in_array($field, ['pagu_indikatif', 'prakiraan_maju_pagu_indikatif'], true)) {
            return number_format((float) $value, 2, '.', '');
        }

        return $this->normalizeText($value);
    }

    private function normalizeText(mixed $value): string
    {
        return strtolower((string) preg_replace('/\s+/', ' ', trim((string) $value)));
    }

    private function label(?string $kode, ?string $nama): string
    {
        return trim(collect([$kode, $nama])->filter()->implode(' - ')) ?: '-';
    }
}
