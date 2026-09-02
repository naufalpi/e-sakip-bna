<?php

namespace App\Http\Controllers\Perencanaan;

use App\Http\Controllers\Controller;
use App\Http\Requests\Perencanaan\StoreDocumentRevisionRequest;
use App\Http\Requests\Perencanaan\StoreRenjaOpdRequest;
use App\Http\Requests\Perencanaan\UpdateRenjaOpdRequest;
use App\Models\Opd;
use App\Models\OpdUnit;
use App\Models\PeriodeTahun;
use App\Models\PlanningSyncBatch;
use App\Models\RenjaOpd;
use App\Models\RenjaOpdItem;
use App\Models\RenstraOpd;
use App\Models\Rkpd;
use App\Models\SubKegiatanPemerintahan;
use App\Models\User;
use App\Services\Perencanaan\PlanningSyncService;
use App\Services\Perencanaan\RenjaInitialItemService;
use App\Services\Perencanaan\RenjaProgramScopeService;
use App\Services\Perencanaan\RenjaVersionService;
use App\Services\Workflow\WorkflowDataService;
use App\Support\Pagination\PerPagePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class RenjaOpdController extends Controller
{
    public function __construct(
        private readonly RenjaProgramScopeService $renjaProgramScopeService,
        private readonly RenjaInitialItemService $renjaInitialItemService,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', RenjaOpd::class);

        $filters = $request->only(['search', 'status', 'opd_id', 'periode_tahun_id', 'tahun', 'jenis_versi', 'per_page']);
        $filters['per_page'] = PerPagePaginator::selection($request);
        $user = $request->user();

        $itemsQuery = RenjaOpd::query()
            ->with(['opd:id,kode,nama,singkatan', 'opdUnit:id,kode,nama', 'rkpd:id,judul,tahun,status,jenis_versi', 'periodeTahun:id,tahun,nama'])
            ->withCount('items')
            ->when($this->shouldLimitToUserOpd($user), fn (Builder $query) => $query->where('opd_id', $user->opd_id))
            ->when($user->hasOpdUnitScope(), fn (Builder $query) => $query->where('opd_unit_id', $user->opd_unit_id))
            ->when($filters['search'] ?? null, function (Builder $query, string $search) {
                $query->where(function (Builder $query) use ($search) {
                    $query->where('judul', 'ilike', "%{$search}%")
                        ->orWhere('nomor_dokumen', 'ilike', "%{$search}%")
                        ->orWhereHas('opd', fn (Builder $query) => $query->where('nama', 'ilike', "%{$search}%")->orWhere('singkatan', 'ilike', "%{$search}%"));
                });
            })
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($filters['opd_id'] ?? null, fn (Builder $query, string $opdId) => $query->where('opd_id', $opdId))
            ->when($filters['periode_tahun_id'] ?? null, fn (Builder $query, string $periodeId) => $query->where('periode_tahun_id', $periodeId))
            ->when($filters['tahun'] ?? null, fn (Builder $query, string $tahun) => $query->where('tahun', $tahun))
            ->when($filters['jenis_versi'] ?? null, fn (Builder $query, string $jenisVersi) => $query->where('jenis_versi', $jenisVersi))
            ->orderByDesc('tahun')
            ->orderByDesc('nomor_versi')
            ->latest('id');

        $items = PerPagePaginator::paginate($itemsQuery, $request)
            ->through(fn (RenjaOpd $renja) => [
                'id' => $renja->id,
                'judul' => $renja->judul,
                'nomor_dokumen' => $renja->nomor_dokumen,
                'tahun' => $renja->tahun,
                'status' => $renja->status,
                'jenis_versi' => $renja->jenis_versi,
                'version_label' => $renja->versionLabel(),
                'nomor_versi' => $renja->nomor_versi,
                'is_active_version' => $renja->is_active_version,
                'can_update' => $user->can('update', $renja),
                'can_delete' => $user->can('delete', $renja),
                'items_count' => $renja->items_count,
                'opd' => $renja->opd ? [
                    'id' => $renja->opd->id,
                    'kode' => $renja->opd->kode,
                    'nama' => $renja->opd->nama,
                    'singkatan' => $renja->opd->singkatan,
                ] : null,
                'opd_unit' => $renja->opdUnit ? [
                    'id' => $renja->opdUnit->id,
                    'kode' => $renja->opdUnit->kode,
                    'nama' => $renja->opdUnit->nama,
                ] : null,
                'rkpd' => $renja->rkpd ? [
                    'id' => $renja->rkpd->id,
                    'judul' => $renja->rkpd->judul,
                    'tahun' => $renja->rkpd->tahun,
                    'jenis_versi' => $renja->rkpd->jenis_versi,
                    'version_label' => $renja->rkpd->versionLabel(),
                ] : null,
                'periode_tahun' => $renja->periodeTahun ? [
                    'id' => $renja->periodeTahun->id,
                    'tahun' => $renja->periodeTahun->tahun,
                    'nama' => $renja->periodeTahun->nama,
                ] : null,
            ]);

        return Inertia::render('RenjaOpd/Index', [
            'items' => $items,
            'filters' => $filters,
            'opdOptions' => $this->opdOptions($user),
            'periodeOptions' => $this->periodeOptions(),
            'can' => [
                'manage' => $user->can('create', RenjaOpd::class),
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorize('create', RenjaOpd::class);

        return Inertia::render('RenjaOpd/Form', [
            'mode' => 'create',
            'renja' => null,
            'rkpdOptions' => $this->rkpdOptions(),
            'renstraOptions' => $this->renstraOptions($request->user()),
            'opdOptions' => $this->opdOptions($request->user()),
            'opdUnitOptions' => $this->opdUnitOptions($request->user()),
            'periodeOptions' => $this->periodeOptions(),
        ]);
    }

    public function store(StoreRenjaOpdRequest $request): RedirectResponse
    {
        [$renja, $bootstrap] = DB::transaction(function () use ($request): array {
            $renja = RenjaOpd::create([
                ...$request->validated(),
                'status' => 'draft',
                'jenis_versi' => 'awal',
                'nomor_versi' => 1,
                'is_active_version' => true,
            ]);

            return [$renja, $this->renjaInitialItemService->bootstrapFromRenstra($renja)];
        });

        $message = $bootstrap['copied'] > 0
            ? "RENJA OPD berhasil dibuat. {$bootstrap['copied']} sub kegiatan dari RENSTRA telah disiapkan."
            : 'RENJA OPD berhasil dibuat.';

        $redirect = redirect()->route('renja-opd.show', $renja)->with('success', $message);

        if ($bootstrap['skipped'] > 0) {
            $redirect->with('warning', "{$bootstrap['skipped']} sub kegiatan RENSTRA dilewati karena struktur master tahun RENJA belum tersedia.");
        }

        return $redirect;
    }

    public function show(Request $request, RenjaOpd $renjaOpd, WorkflowDataService $workflowDataService): Response
    {
        $this->authorize('view', $renjaOpd);

        $filters = $request->only(['search', 'status']);
        $canManage = $request->user()->can('update', $renjaOpd);

        $renjaOpd->load(['opd:id,kode,nama,singkatan', 'opdUnit:id,kode,nama', 'rkpd:id,judul,tahun,status,jenis_versi,nomor_versi', 'periodeTahun:id,tahun,nama']);

        $versionHistory = RenjaOpd::query()
            ->where('root_version_id', $renjaOpd->root_version_id ?: $renjaOpd->id)
            ->orderBy('nomor_versi')
            ->get()
            ->map(fn (RenjaOpd $version) => [
                'id' => $version->id,
                'jenis_versi' => $version->jenis_versi,
                'version_label' => $version->versionLabel(),
                'status' => $version->status,
                'is_active_version' => $version->is_active_version,
                'disahkan_pada' => $version->disahkan_pada?->toISOString(),
            ])
            ->values()
            ->all();

        $itemsQuery = $renjaOpd->items()
            ->with([
                'programPemerintahan:id,bidang_urusan_id,kode,nama',
                'programPemerintahan.bidangUrusan:id,urusan_pemerintahan_id,kode,nama',
                'programPemerintahan.bidangUrusan.urusanPemerintahan:id,kode,nama',
                'kegiatanPemerintahan:id,program_pemerintahan_id,kode,nama',
                'subKegiatanPemerintahan:id,kegiatan_pemerintahan_id,kode,nama',
            ])
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($filters['search'] ?? null, function (Builder $query, string $search) {
                $query->where(function (Builder $query) use ($search) {
                    $query->where('kode', 'ilike', "%{$search}%")
                        ->orWhere('nama_sub_kegiatan', 'ilike', "%{$search}%")
                        ->orWhere('indikator', 'ilike', "%{$search}%");
                });
            });

        $previewItems = (clone $itemsQuery)
            ->orderBy('program_pemerintahan_id')
            ->orderBy('kegiatan_pemerintahan_id')
            ->orderBy('sub_kegiatan_pemerintahan_id')
            ->orderBy('urutan')
            ->orderBy('id')
            ->get()
            ->map(fn (RenjaOpdItem $item) => $this->serializeItem($item, $renjaOpd))
            ->values()
            ->all();

        $items = $itemsQuery
            ->orderBy('urutan')
            ->orderBy('id')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (RenjaOpdItem $item) => $this->serializeItem($item, $renjaOpd));

        $syncBatch = $request->filled('sync_batch')
            ? PlanningSyncBatch::query()
                ->whereKey($request->integer('sync_batch'))
                ->where('source_module', 'rkpd')
                ->where('target_module', 'renja_opd')
                ->where('target_id', $renjaOpd->id)
                ->where('status', 'previewed')
                ->with('rows')
                ->first()
            : null;

        return Inertia::render('RenjaOpd/Show', [
            'renja' => $this->serializeRenja($renjaOpd),
            'items' => $items,
            'previewItems' => $previewItems,
            'summary' => [
                'items_count' => count($previewItems),
                'total_pagu' => collect($previewItems)->sum(fn (array $item) => (float) ($item['pagu_indikatif'] ?? 0)),
                'total_prakiraan_maju_pagu' => collect($previewItems)->sum(fn (array $item) => (float) ($item['prakiraan_maju_pagu_indikatif'] ?? 0)),
            ],
            'filters' => $filters,
            'subKegiatanOptions' => $canManage ? $this->subKegiatanOptions($renjaOpd) : [],
            'existingSubKegiatanRows' => $canManage ? $renjaOpd->items()->get(['id', 'sub_kegiatan_pemerintahan_id'])->map(fn (RenjaOpdItem $item) => [
                'id' => $item->id,
                'sub_kegiatan_pemerintahan_id' => $item->sub_kegiatan_pemerintahan_id,
            ])->all() : [],
            'syncPreview' => app(PlanningSyncService::class)->serializePreview($syncBatch),
            'workflow' => $workflowDataService->forModel($renjaOpd, 'renja_opd'),
            'versionHistory' => $versionHistory,
            'can' => [
                'manage' => $canManage,
                'review' => ! $renjaOpd->isArchivedVersion() && $this->canReviewWorkflow($request->user()),
                'lock' => $renjaOpd->is_active_version && $this->canLockWorkflow($request->user()),
                'unlock' => $renjaOpd->is_active_version && $this->canUnlockWorkflow($request->user()),
                'createRevision' => $request->user()->can('createRevision', $renjaOpd),
            ],
        ]);
    }

    public function edit(Request $request, RenjaOpd $renjaOpd): Response
    {
        $this->authorize('update', $renjaOpd);

        return Inertia::render('RenjaOpd/Form', [
            'mode' => 'edit',
            'renja' => $this->serializeRenja($renjaOpd),
            'rkpdOptions' => $this->rkpdOptions(),
            'renstraOptions' => $this->renstraOptions($request->user()),
            'opdOptions' => $this->opdOptions($request->user()),
            'opdUnitOptions' => $this->opdUnitOptions($request->user()),
            'periodeOptions' => $this->periodeOptions(),
        ]);
    }

    public function update(UpdateRenjaOpdRequest $request, RenjaOpd $renjaOpd): RedirectResponse
    {
        $renjaOpd->update([
            ...$request->validated(),
            'status' => $renjaOpd->status,
        ]);

        return redirect()->route('renja-opd.show', $renjaOpd)->with('success', 'Renja OPD berhasil diperbarui.');
    }

    public function destroy(RenjaOpd $renjaOpd): RedirectResponse
    {
        $this->authorize('delete', $renjaOpd);

        $renjaOpd->delete();

        return redirect()->route('renja-opd.index')->with('success', 'Renja OPD berhasil dihapus.');
    }

    public function createRevision(
        StoreDocumentRevisionRequest $request,
        RenjaOpd $renjaOpd,
        RenjaVersionService $versionService,
    ): RedirectResponse {
        $this->authorize('createRevision', $renjaOpd);

        $revision = $versionService->createChange($renjaOpd, $request->validated());

        return redirect()->route('renja-opd.show', $revision)
            ->with('success', 'RENJA Perubahan berhasil dibuat dari RENJA Ditetapkan.');
    }

    private function shouldLimitToUserOpd(User $user): bool
    {
        return $user->hasRole('admin_opd')
            && ! $user->hasAnyRole(['super_admin', 'admin_kabupaten_bagian_organisasi', 'admin_kabupaten_bapperida', 'admin_kabupaten_inspektorat']);
    }

    private function canReviewWorkflow(User $user): bool
    {
        return $user->hasAnyRole(['super_admin', 'admin_kabupaten_bapperida', 'admin_kabupaten_bagian_organisasi']);
    }

    private function canLockWorkflow(User $user): bool
    {
        return $user->isSuperAdmin() || $user->hasPermission('lock_period');
    }

    private function canUnlockWorkflow(User $user): bool
    {
        return $user->isSuperAdmin();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function rkpdOptions(): array
    {
        return Rkpd::query()
            ->orderByDesc('tahun')
            ->orderBy('nomor_versi')
            ->get(['id', 'judul', 'tahun', 'status', 'jenis_versi', 'nomor_versi', 'is_active_version'])
            ->map(fn (Rkpd $rkpd) => [
                'id' => $rkpd->id,
                'tahun' => $rkpd->tahun,
                'label' => "{$rkpd->tahun} - {$rkpd->versionLabel()} - {$rkpd->judul}",
                'jenis_versi' => $rkpd->jenis_versi,
                'status' => $rkpd->status,
                'is_active_version' => $rkpd->is_active_version,
            ])
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function renstraOptions(User $user): array
    {
        return RenstraOpd::query()
            ->with('opd:id,nama,singkatan')
            ->whereIn('status', ['approved', 'locked'])
            ->where('is_active_version', true)
            ->when($this->shouldLimitToUserOpd($user), fn (Builder $query) => $query->where('opd_id', $user->opd_id))
            ->orderByDesc('tahun_awal')
            ->get(['id', 'opd_id', 'judul', 'tahun_awal', 'tahun_akhir', 'status'])
            ->map(fn (RenstraOpd $renstra) => [
                'id' => $renstra->id,
                'opd_id' => $renstra->opd_id,
                'label' => "{$renstra->tahun_awal}-{$renstra->tahun_akhir} - ".($renstra->opd?->singkatan ?: $renstra->opd?->nama ?: $renstra->judul),
                'tahun_awal' => $renstra->tahun_awal,
                'tahun_akhir' => $renstra->tahun_akhir,
                'status' => $renstra->status,
            ])
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function opdOptions(User $user): array
    {
        return Opd::query()
            ->where('status', 'active')
            ->when($this->shouldLimitToUserOpd($user), fn (Builder $query) => $query->whereKey($user->opd_id ?? 0))
            ->orderBy('nama')
            ->get(['id', 'kode', 'nama', 'singkatan'])
            ->map(fn (Opd $opd) => [
                'id' => $opd->id,
                'label' => $opd->singkatan ? "{$opd->singkatan} - {$opd->nama}" : $opd->nama,
            ])
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function opdUnitOptions(User $user): array
    {
        return OpdUnit::query()
            ->with('opd:id,nama,singkatan')
            ->where('status', 'active')
            ->when($this->shouldLimitToUserOpd($user), fn (Builder $query) => $query->where('opd_id', $user->opd_id))
            ->when($user->hasOpdUnitScope(), fn (Builder $query) => $query->whereKey($user->opd_unit_id))
            ->orderBy('nama')
            ->get(['id', 'opd_id', 'kode', 'nama'])
            ->map(fn (OpdUnit $unit) => [
                'id' => $unit->id,
                'opd_id' => $unit->opd_id,
                'label' => "{$unit->nama} - ".($unit->opd?->singkatan ?: $unit->opd?->nama),
            ])
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function periodeOptions(): array
    {
        return PeriodeTahun::query()
            ->orderBy('tahun')
            ->get(['id', 'tahun', 'nama'])
            ->map(fn (PeriodeTahun $periode) => [
                'id' => $periode->id,
                'tahun' => $periode->tahun,
                'label' => "{$periode->tahun} - {$periode->nama}",
            ])
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function subKegiatanOptions(RenjaOpd $renjaOpd): array
    {
        $programPemerintahanIds = $this->renjaProgramScopeService->programPemerintahanIds($renjaOpd);

        if ($programPemerintahanIds === []) {
            return [];
        }

        return SubKegiatanPemerintahan::query()
            ->with([
                'satuanIndikator:id,nama,simbol',
                'kegiatanPemerintahan:id,program_pemerintahan_id,kode,nama',
                'kegiatanPemerintahan.programPemerintahan:id,bidang_urusan_id,kode,nama',
                'kegiatanPemerintahan.programPemerintahan.bidangUrusan:id,urusan_pemerintahan_id,kode,nama',
                'kegiatanPemerintahan.programPemerintahan.bidangUrusan.urusanPemerintahan:id,kode,nama',
            ])
            ->where('periode_tahun_id', $renjaOpd->periode_tahun_id)
            ->where('status', 'active')
            ->whereHas('kegiatanPemerintahan', fn (Builder $query) => $query->whereIn('program_pemerintahan_id', $programPemerintahanIds))
            ->orderBy('kode')
            ->limit(3000)
            ->get([
                'id',
                'periode_tahun_id',
                'kegiatan_pemerintahan_id',
                'kode',
                'nama',
                'sasaran_sub_kegiatan',
                'indikator_sub_kegiatan',
                'satuan_indikator_id',
                'definisi_operasional',
            ])
            ->map(function (SubKegiatanPemerintahan $subKegiatan) {
                $kegiatan = $subKegiatan->kegiatanPemerintahan;
                $program = $kegiatan?->programPemerintahan;
                $bidang = $program?->bidangUrusan;
                $urusan = $bidang?->urusanPemerintahan;

                return [
                    'id' => $subKegiatan->id,
                    'value' => $subKegiatan->id,
                    'kode' => $subKegiatan->kode,
                    'nama' => $subKegiatan->nama,
                    'program_id' => $program?->id,
                    'kegiatan_id' => $kegiatan?->id,
                    'bidang_id' => $bidang?->id,
                    'urusan_id' => $urusan?->id,
                    'sasaran_sub_kegiatan' => $subKegiatan->sasaran_sub_kegiatan,
                    'indikator_sub_kegiatan' => $subKegiatan->indikator_sub_kegiatan,
                    'satuan_indikator_id' => $subKegiatan->satuan_indikator_id,
                    'satuan_label' => $subKegiatan->satuanIndikator?->simbol ?: $subKegiatan->satuanIndikator?->nama,
                    'definisi_operasional' => $subKegiatan->definisi_operasional,
                    'label' => "{$subKegiatan->kode} - {$subKegiatan->nama}",
                    'description' => $this->label($kegiatan?->kode, $kegiatan?->nama),
                    'group' => $this->label($program?->kode, $program?->nama),
                ];
            })
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeRenja(RenjaOpd $renja): array
    {
        return [
            'id' => $renja->id,
            'rkpd_id' => $renja->rkpd_id,
            'renstra_opd_id' => $renja->renstra_opd_id,
            'opd_id' => $renja->opd_id,
            'opd_unit_id' => $renja->opd_unit_id,
            'periode_tahun_id' => $renja->periode_tahun_id,
            'tahun' => $renja->tahun,
            'judul' => $renja->judul,
            'nomor_dokumen' => $renja->nomor_dokumen,
            'status' => $renja->status,
            'catatan' => $renja->catatan,
            'jenis_versi' => $renja->jenis_versi,
            'version_label' => $renja->versionLabel(),
            'nomor_versi' => $renja->nomor_versi,
            'parent_version_id' => $renja->parent_version_id,
            'root_version_id' => $renja->root_version_id,
            'is_active_version' => $renja->is_active_version,
            'alasan_perubahan' => $renja->alasan_perubahan,
            'dasar_perubahan' => $renja->dasar_perubahan,
            'tanggal_berlaku' => $renja->tanggal_berlaku?->toDateString(),
            'disahkan_pada' => $renja->disahkan_pada?->toISOString(),
            'opd' => $renja->opd ? [
                'id' => $renja->opd->id,
                'kode' => $renja->opd->kode,
                'nama' => $renja->opd->nama,
                'singkatan' => $renja->opd->singkatan,
            ] : null,
            'opd_unit' => $renja->opdUnit ? [
                'id' => $renja->opdUnit->id,
                'kode' => $renja->opdUnit->kode,
                'nama' => $renja->opdUnit->nama,
            ] : null,
            'rkpd' => $renja->rkpd ? [
                'id' => $renja->rkpd->id,
                'judul' => $renja->rkpd->judul,
                'tahun' => $renja->rkpd->tahun,
                'jenis_versi' => $renja->rkpd->jenis_versi,
                'version_label' => $renja->rkpd->versionLabel(),
            ] : null,
            'periode_tahun' => $renja->periodeTahun ? [
                'id' => $renja->periodeTahun->id,
                'tahun' => $renja->periodeTahun->tahun,
                'nama' => $renja->periodeTahun->nama,
            ] : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeItem(RenjaOpdItem $item, ?RenjaOpd $renja = null): array
    {
        $renja ??= $item->renjaOpd;
        $program = $item->programPemerintahan;
        $bidang = $program?->bidangUrusan;
        $urusan = $bidang?->urusanPemerintahan;

        return [
            'id' => $item->id,
            'opd_id' => $renja?->opd_id,
            'opd_unit_id' => $renja?->opd_unit_id,
            'opd_sub_kegiatan_id' => $item->opd_sub_kegiatan_id,
            'sumber_item' => $item->sumber_item ?: 'manual',
            'is_from_renstra' => $item->isFromRenstra(),
            'program_pemerintahan_id' => $item->program_pemerintahan_id,
            'kegiatan_pemerintahan_id' => $item->kegiatan_pemerintahan_id,
            'sub_kegiatan_pemerintahan_id' => $item->sub_kegiatan_pemerintahan_id,
            'indikator_sub_kegiatan_id' => $item->indikator_sub_kegiatan_id,
            'kode' => $item->kode,
            'nama_sub_kegiatan' => $item->nama_sub_kegiatan,
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
            'opd' => $renja?->opd ? [
                'id' => $renja->opd->id,
                'kode' => $renja->opd->kode,
                'nama' => $renja->opd->nama,
                'singkatan' => $renja->opd->singkatan,
            ] : null,
            'opd_unit' => $renja?->opdUnit ? [
                'id' => $renja->opdUnit->id,
                'kode' => $renja->opdUnit->kode,
                'nama' => $renja->opdUnit->nama,
            ] : null,
            'urusan' => $this->label($urusan?->kode, $urusan?->nama),
            'bidang' => $this->label($bidang?->kode, $bidang?->nama),
            'program' => $this->label($item->programPemerintahan?->kode, $item->programPemerintahan?->nama),
            'program_kode' => $item->programPemerintahan?->kode,
            'program_nama' => $item->programPemerintahan?->nama,
            'kegiatan' => $this->label($item->kegiatanPemerintahan?->kode, $item->kegiatanPemerintahan?->nama),
            'kegiatan_kode' => $item->kegiatanPemerintahan?->kode,
            'kegiatan_nama' => $item->kegiatanPemerintahan?->nama,
            'sub_kegiatan' => $this->label($item->subKegiatanPemerintahan?->kode, $item->subKegiatanPemerintahan?->nama),
            'perangkat_daerah_penanggung_jawab' => $renja?->opd?->nama,
        ];
    }

    private function label(?string $kode, ?string $nama): string
    {
        return trim(collect([$kode, $nama])->filter()->implode(' - ')) ?: '-';
    }
}
