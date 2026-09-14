<?php

namespace App\Http\Controllers\RenstraOpd;

use App\Http\Controllers\Controller;
use App\Http\Requests\Perencanaan\CancelDocumentRevisionRequest;
use App\Http\Requests\Perencanaan\StoreDocumentRevisionRequest;
use App\Http\Requests\RenstraOpd\StoreRenstraOpdRequest;
use App\Http\Requests\RenstraOpd\UpdateRenstraOpdRequest;
use App\Models\IndikatorOpdKegiatan;
use App\Models\IndikatorOpdProgram;
use App\Models\IndikatorProgramRpjmd;
use App\Models\IndikatorSasaranDaerah;
use App\Models\IndikatorSasaranOpd;
use App\Models\IndikatorSubKegiatan;
use App\Models\IndikatorTujuanDaerah;
use App\Models\IndikatorTujuanOpd;
use App\Models\KegiatanPemerintahan;
use App\Models\Opd;
use App\Models\OpdKegiatan;
use App\Models\OpdProgram;
use App\Models\OpdSubKegiatan;
use App\Models\OpdUnit;
use App\Models\PeriodeTahun;
use App\Models\ProgramPemerintahan;
use App\Models\ProgramRpjmd;
use App\Models\RenstraOpd;
use App\Models\Rpjmd;
use App\Models\SasaranDaerah;
use App\Models\SasaranOpd;
use App\Models\SatuanIndikator;
use App\Models\SubKegiatanPemerintahan;
use App\Models\TujuanDaerah;
use App\Models\TujuanOpd;
use App\Models\User;
use App\Models\WorkflowSubmission;
use App\Services\Perencanaan\CancelDocumentRevisionService;
use App\Services\Perencanaan\DocumentRevisionService;
use App\Services\Renstra\RenstraPreviewExcelExportService;
use App\Services\Renstra\RenstraProgressSummaryService;
use App\Services\Workflow\WorkflowDataService;
use App\Support\Pagination\PerPagePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class RenstraOpdController extends Controller
{
    public function index(Request $request, RenstraProgressSummaryService $progressSummaryService): Response
    {
        $this->authorize('viewAny', RenstraOpd::class);

        $filters = $request->only(['search', 'status', 'opd_id', 'rpjmd_id', 'periode_tahun_id', 'per_page']);
        $filters['per_page'] = PerPagePaginator::selection($request);
        $user = $request->user();

        $renstraQuery = RenstraOpd::query()
            ->with([
                'opd:id,kode,nama,singkatan',
                'rpjmd:id,judul,tahun_awal,tahun_akhir,status',
                'rpjmdPerubahanTerbaru:id,judul,jenis_versi,nomor_versi',
                'periodeTahun:id,tahun,nama',
            ])
            ->when($this->shouldLimitToUserOpd($user), fn (Builder $query) => $query->where('opd_id', $user->opd_id))
            ->when($filters['search'] ?? null, function (Builder $query, string $search) {
                $query->where(function (Builder $query) use ($search) {
                    $query->where('judul', 'ilike', "%{$search}%")
                        ->orWhere('nomor_dokumen', 'ilike', "%{$search}%")
                        ->orWhereHas('opd', fn (Builder $query) => $query->where('nama', 'ilike', "%{$search}%")->orWhere('singkatan', 'ilike', "%{$search}%"));
                });
            })
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($filters['opd_id'] ?? null, fn (Builder $query, string $opdId) => $query->where('opd_id', $opdId))
            ->when($filters['rpjmd_id'] ?? null, fn (Builder $query, string $rpjmdId) => $query->where('rpjmd_id', $rpjmdId))
            ->when($filters['periode_tahun_id'] ?? null, fn (Builder $query, string $periodeId) => $query->where('periode_tahun_id', $periodeId))
            ->orderByDesc('tahun_awal')
            ->latest('id');

        $summaryQuery = clone $renstraQuery;
        $summaryQuery->setEagerLoads([]);
        $summaryRenstras = $summaryQuery
            ->reorder()
            ->get(['id', 'tahun_awal', 'tahun_akhir', 'jenis_versi']);
        $progressByRenstra = $progressSummaryService->summarize($summaryRenstras);

        $renstras = PerPagePaginator::paginate($renstraQuery, $request);

        $renstras->through(fn (RenstraOpd $renstra) => [
            'id' => $renstra->id,
            'judul' => $renstra->judul,
            'nomor_dokumen' => $renstra->nomor_dokumen,
            'tahun_awal' => $renstra->tahun_awal,
            'tahun_akhir' => $renstra->tahun_akhir,
            'status' => $renstra->status,
            'jenis_versi' => $renstra->jenis_versi,
            'nomor_versi' => $renstra->nomor_versi,
            'is_active_version' => $renstra->is_active_version,
            'version_label' => $renstra->versionLabel(),
            'perlu_penyesuaian_rpjmd' => $renstra->perlu_penyesuaian_rpjmd,
            'rpjmd_perubahan_terbaru' => $renstra->rpjmdPerubahanTerbaru ? [
                'id' => $renstra->rpjmdPerubahanTerbaru->id,
                'judul' => $renstra->rpjmdPerubahanTerbaru->judul,
                'version_label' => $renstra->rpjmdPerubahanTerbaru->versionLabel(),
            ] : null,
            'opd' => $renstra->opd ? [
                'id' => $renstra->opd->id,
                'kode' => $renstra->opd->kode,
                'nama' => $renstra->opd->nama,
                'singkatan' => $renstra->opd->singkatan,
            ] : null,
            'rpjmd' => $renstra->rpjmd ? [
                'id' => $renstra->rpjmd->id,
                'judul' => $renstra->rpjmd->judul,
                'tahun_awal' => $renstra->rpjmd->tahun_awal,
                'tahun_akhir' => $renstra->rpjmd->tahun_akhir,
            ] : null,
            'periode_tahun' => $renstra->periodeTahun ? [
                'id' => $renstra->periodeTahun->id,
                'tahun' => $renstra->periodeTahun->tahun,
                'nama' => $renstra->periodeTahun->nama,
            ] : null,
            'progress' => $progressByRenstra[$renstra->id],
            'can_update' => $user->can('update', $renstra),
            'can_delete' => $user->can('delete', $renstra),
            'can_cancel_revision' => $user->can('cancelRevision', $renstra),
        ]);

        return Inertia::render('RenstraOpd/Index', [
            'renstras' => $renstras,
            'summary' => [
                'original_count' => $summaryRenstras->where('jenis_versi', '!=', 'perubahan')->count(),
                'revision_count' => $summaryRenstras->where('jenis_versi', 'perubahan')->count(),
                'complete_count' => $summaryRenstras->filter(
                    fn (RenstraOpd $renstra): bool => ($progressByRenstra[$renstra->id]['percentage'] ?? 0) === 100,
                )->count(),
            ],
            'filters' => $filters,
            'opdOptions' => $this->opdOptions($user),
            'rpjmdOptions' => $this->rpjmdOptions(),
            'periodeOptions' => $this->periodeOptions(),
            'can' => [
                'manage' => $user->can('create', RenstraOpd::class),
                'view_completeness_diagnostics' => $user->can('viewCompletenessDiagnostics', RenstraOpd::class),
            ],
        ]);
    }

    public function completenessDiagnostics(
        RenstraOpd $renstraOpd,
        RenstraProgressSummaryService $progressSummaryService,
    ): JsonResponse {
        $this->authorize('viewCompletenessDiagnostics', RenstraOpd::class);
        $this->authorize('view', $renstraOpd);

        $renstraOpd->loadMissing('opd:id,nama,singkatan');

        return response()
            ->json($progressSummaryService->diagnose($renstraOpd))
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate');
    }

    public function create(Request $request): Response
    {
        $this->authorize('create', RenstraOpd::class);

        return Inertia::render('RenstraOpd/Form', [
            'mode' => 'create',
            'renstra' => null,
            'opdOptions' => $this->opdOptions($request->user()),
            'rpjmdOptions' => $this->rpjmdOptions(),
            'periodeOptions' => $this->periodeOptions(),
        ]);
    }

    public function store(StoreRenstraOpdRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['periode_tahun_id'] = $this->resolvePeriodeTahunId($data);
        $data['status'] = 'draft';

        $renstra = RenstraOpd::create($data);

        return redirect()->route('renstra-opd.show', $renstra)->with('success', 'Renstra OPD berhasil ditambahkan.');
    }

    public function show(Request $request, RenstraOpd $renstraOpd, WorkflowDataService $workflowDataService): Response
    {
        return $this->renderShow($request, $renstraOpd, $workflowDataService);
    }

    public function manage(
        Request $request,
        RenstraOpd $renstraOpd,
        string $section,
        WorkflowDataService $workflowDataService,
    ): Response {
        return $this->renderShow($request, $renstraOpd, $workflowDataService, $section);
    }

    private function renderShow(
        Request $request,
        RenstraOpd $renstraOpd,
        WorkflowDataService $workflowDataService,
        ?string $activeSection = null,
    ): Response {
        $this->authorize('view', $renstraOpd);

        $manage = $request->user()->can('update', $renstraOpd);
        $isManagementPage = $activeSection !== null;
        $loadManagementOptions = $manage && $isManagementPage;

        $renstraOpd->load($this->renstraRelations($activeSection));

        return Inertia::render('RenstraOpd/Show', [
            'renstra' => fn () => $this->serializeRenstra(
                $renstraOpd,
                $activeSection,
                $isManagementPage ? $this->managementBudgetTotals($renstraOpd, $activeSection) : [],
            ),
            // Partial reloads after saving targets need only the current tree.
            // Closures keep unrelated reference queries out of those requests.
            'rpjmdContext' => fn () => $isManagementPage
                ? ['visi' => [], 'misi' => [], 'program_groups' => []]
                : $this->rpjmdContext($renstraOpd),
            // The overview only links to dedicated management pages. Loading these
            // large dictionaries there added queries and payload that were never used.
            'nodeOptions' => fn () => $loadManagementOptions ? $this->nodeOptions($renstraOpd, $activeSection) : [],
            'rpjmdReferenceOptions' => fn () => $loadManagementOptions ? $this->rpjmdReferenceOptions($renstraOpd, $activeSection) : [],
            'masterReferenceOptions' => fn () => $loadManagementOptions ? $this->masterReferenceOptions($renstraOpd, $activeSection) : [],
            // Periode tahun juga dipakai oleh tabel preview untuk membentuk
            // kolom target dan pagu. Data ini harus tetap tersedia bagi
            // reviewer/viewer meskipun mereka tidak memiliki hak mengedit.
            'periodeOptions' => fn () => $this->periodeOptions(),
            'satuanOptions' => fn () => $loadManagementOptions ? $this->satuanOptions() : [],
            'can' => [
                'manage' => $manage,
                'createRevision' => $request->user()->can('createRevision', $renstraOpd),
                'cancelRevision' => $request->user()->can('cancelRevision', $renstraOpd),
                'withdraw' => $this->canWithdrawWorkflow($request->user(), $renstraOpd, 'renstra_opd'),
                'review' => $this->canReviewWorkflow($request->user()),
                'lock' => $this->canLockWorkflow($request->user()),
                'unlock' => $renstraOpd->is_active_version && $request->user()->isSuperAdmin(),
            ],
            'workflow' => fn () => $isManagementPage
                ? null
                : $workflowDataService->forModel($renstraOpd, 'renstra_opd'),
            'activeSection' => $activeSection,
        ]);
    }

    /**
     * Load only the cascading depth and indicators used by the requested workspace.
     * The regular overview still receives the complete tree for its preview table.
     *
     * @return array<int, string>
     */
    private function renstraRelations(?string $activeSection): array
    {
        $base = [
            'opd:id,kode,nama,singkatan',
            'rpjmd:id,judul,tahun_awal,tahun_akhir,status',
            'periodeTahun:id,tahun,nama,status',
            'rpjmdPerubahanTerbaru:id,judul,jenis_versi,nomor_versi',
        ];

        if ($activeSection === null) {
            return [...$base, ...$this->completeRenstraRelations()];
        }

        $relations = match ($activeSection) {
            'tujuan' => [
                'tujuan.tujuanDaerah:id,kode,tujuan',
                ...$this->indicatorRelations('tujuan.indikator', 'indikatorTujuanDaerah'),
            ],
            'sasaran' => [
                'tujuan.sasaran.sasaranDaerah:id,kode,sasaran',
                ...$this->indicatorRelations('tujuan.sasaran.indikator', 'indikatorSasaranDaerah'),
            ],
            'program' => [
                ...$this->programReferenceRelations(),
                ...$this->indicatorRelations('tujuan.sasaran.programs.indikator', 'indikatorProgramRpjmd'),
            ],
            'kegiatan' => [
                ...$this->programReferenceRelations(),
                'tujuan.sasaran.programs.kegiatan.kegiatanPemerintahan:id,kode,nama,program_pemerintahan_id',
                ...$this->indicatorRelations('tujuan.sasaran.programs.kegiatan.indikator'),
            ],
            'sub-kegiatan' => [
                ...$this->programReferenceRelations(),
                'tujuan.sasaran.programs.kegiatan.kegiatanPemerintahan:id,kode,nama,program_pemerintahan_id',
                'tujuan.sasaran.programs.kegiatan.subKegiatan.subKegiatanPemerintahan:id,kode,nama,kegiatan_pemerintahan_id',
                'tujuan.sasaran.programs.kegiatan.subKegiatan.opdUnit:id,kode,nama,jenis_unit',
                'tujuan.sasaran.programs.kegiatan.subKegiatan.anggaranTahunan.periodeTahun:id,tahun,nama',
                ...$this->indicatorRelations('tujuan.sasaran.programs.kegiatan.subKegiatan.indikator'),
            ],
            default => [],
        };

        return [...$base, ...$relations];
    }

    /**
     * @return array<int, string>
     */
    private function completeRenstraRelations(): array
    {
        return [
            'tujuan.tujuanDaerah:id,kode,tujuan',
            ...$this->indicatorRelations('tujuan.indikator', 'indikatorTujuanDaerah'),
            'tujuan.sasaran.sasaranDaerah:id,kode,sasaran',
            ...$this->indicatorRelations('tujuan.sasaran.indikator', 'indikatorSasaranDaerah'),
            ...$this->programReferenceRelations(),
            ...$this->indicatorRelations('tujuan.sasaran.programs.indikator', 'indikatorProgramRpjmd'),
            'tujuan.sasaran.programs.kegiatan.kegiatanPemerintahan:id,kode,nama,program_pemerintahan_id',
            ...$this->indicatorRelations('tujuan.sasaran.programs.kegiatan.indikator'),
            'tujuan.sasaran.programs.kegiatan.subKegiatan.subKegiatanPemerintahan:id,kode,nama,kegiatan_pemerintahan_id',
            'tujuan.sasaran.programs.kegiatan.subKegiatan.opdUnit:id,kode,nama,jenis_unit',
            'tujuan.sasaran.programs.kegiatan.subKegiatan.anggaranTahunan.periodeTahun:id,tahun,nama',
            ...$this->indicatorRelations('tujuan.sasaran.programs.kegiatan.subKegiatan.indikator'),
        ];
    }

    /**
     * @return array<int, string>
     */
    private function programReferenceRelations(): array
    {
        $prefix = 'tujuan.sasaran.programs';

        return [
            "{$prefix}.programRpjmd:id,kode,nama,program_pemerintahan_id",
            "{$prefix}.programRpjmd.programPemerintahan:id,kode,nama,bidang_urusan_id",
            "{$prefix}.programRpjmd.programPemerintahan.bidangUrusan:id,urusan_pemerintahan_id,kode,nama",
            "{$prefix}.programRpjmd.programPemerintahan.bidangUrusan.urusanPemerintahan:id,kode,nama",
            "{$prefix}.programRpjmd.programPemerintahan.bidangUrusan.opdPengampu:id",
            "{$prefix}.programRpjmd.programPemerintahanReferences:id,kode,nama,bidang_urusan_id",
            "{$prefix}.programRpjmd.programPemerintahanReferences.bidangUrusan:id,urusan_pemerintahan_id,kode,nama",
            "{$prefix}.programRpjmd.programPemerintahanReferences.bidangUrusan.urusanPemerintahan:id,kode,nama",
            "{$prefix}.programRpjmd.programPemerintahanReferences.bidangUrusan.opdPengampu:id",
            "{$prefix}.programPemerintahan:id,kode,nama,bidang_urusan_id",
            "{$prefix}.programPemerintahan.bidangUrusan:id,urusan_pemerintahan_id,kode,nama",
            "{$prefix}.programPemerintahan.bidangUrusan.urusanPemerintahan:id,kode,nama",
        ];
    }

    /**
     * @return array<int, string>
     */
    private function indicatorRelations(string $prefix, ?string $linkedRelation = null): array
    {
        return array_values(array_filter([
            $linkedRelation ? "{$prefix}.{$linkedRelation}:id,kode,indikator" : null,
            "{$prefix}.satuanIndikator:id,nama,simbol",
            "{$prefix}.opdPenanggungJawab:id,kode,nama,singkatan",
            "{$prefix}.targets.periodeTahun:id,tahun,nama",
        ]));
    }

    /**
     * Aggregate the budget displayed by Program/Kegiatan without hydrating every
     * descendant Sub Kegiatan and annual budget model into the request.
     *
     * @return array<int, float>
     */
    private function managementBudgetTotals(RenstraOpd $renstra, ?string $activeSection): array
    {
        $query = match ($activeSection) {
            'program' => DB::table('opd_program as node')
                ->leftJoin('opd_kegiatan as kegiatan', function ($join): void {
                    $join->on('kegiatan.opd_program_id', '=', 'node.id')->whereNull('kegiatan.deleted_at');
                })
                ->leftJoin('opd_sub_kegiatan as sub_kegiatan', function ($join): void {
                    $join->on('sub_kegiatan.opd_kegiatan_id', '=', 'kegiatan.id')->whereNull('sub_kegiatan.deleted_at');
                })
                ->leftJoin('anggaran_sub_kegiatan_renstra as anggaran', 'anggaran.opd_sub_kegiatan_id', '=', 'sub_kegiatan.id')
                ->where('node.renstra_opd_id', $renstra->id)
                ->whereNull('node.deleted_at'),
            'kegiatan' => DB::table('opd_kegiatan as node')
                ->join('opd_program as program', function ($join): void {
                    $join->on('program.id', '=', 'node.opd_program_id')->whereNull('program.deleted_at');
                })
                ->leftJoin('opd_sub_kegiatan as sub_kegiatan', function ($join): void {
                    $join->on('sub_kegiatan.opd_kegiatan_id', '=', 'node.id')->whereNull('sub_kegiatan.deleted_at');
                })
                ->leftJoin('anggaran_sub_kegiatan_renstra as anggaran', 'anggaran.opd_sub_kegiatan_id', '=', 'sub_kegiatan.id')
                ->where('program.renstra_opd_id', $renstra->id)
                ->whereNull('node.deleted_at'),
            default => null,
        };

        if (! $query) {
            return [];
        }

        return $query
            ->groupBy('node.id')
            ->selectRaw('node.id as node_id, COALESCE(SUM(anggaran.anggaran), 0) as total')
            ->pluck('total', 'node_id')
            ->map(fn ($total): float => (float) $total)
            ->all();
    }

    public function exportPreview(
        RenstraOpd $renstraOpd,
        RenstraPreviewExcelExportService $exportService,
    ): SymfonyResponse {
        $this->authorize('view', $renstraOpd);

        $export = $exportService->make($renstraOpd);

        return response($export['content'], 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="'.$export['filename'].'"',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
        ]);
    }

    public function edit(Request $request, RenstraOpd $renstraOpd): Response
    {
        $this->authorize('update', $renstraOpd);

        return Inertia::render('RenstraOpd/Form', [
            'mode' => 'edit',
            'renstra' => [
                'id' => $renstraOpd->id,
                'opd_id' => $renstraOpd->opd_id,
                'rpjmd_id' => $renstraOpd->rpjmd_id,
                'periode_tahun_id' => $renstraOpd->periode_tahun_id,
                'judul' => $renstraOpd->judul,
                'nomor_dokumen' => $renstraOpd->nomor_dokumen,
                'tahun_awal' => $renstraOpd->tahun_awal,
                'tahun_akhir' => $renstraOpd->tahun_akhir,
                'status' => $renstraOpd->status,
                'keterangan' => $renstraOpd->keterangan,
            ],
            'opdOptions' => $this->opdOptions($request->user()),
            'rpjmdOptions' => $this->rpjmdOptions(),
            'periodeOptions' => $this->periodeOptions(),
        ]);
    }

    public function update(UpdateRenstraOpdRequest $request, RenstraOpd $renstraOpd): RedirectResponse
    {
        $data = $request->validated();
        $data['periode_tahun_id'] = $this->resolvePeriodeTahunId($data);

        $renstraOpd->update($data);

        return redirect()->route('renstra-opd.show', $renstraOpd)->with('success', 'Renstra OPD berhasil diperbarui.');
    }

    public function storeRevision(
        StoreDocumentRevisionRequest $request,
        RenstraOpd $renstraOpd,
        DocumentRevisionService $revisionService,
    ): RedirectResponse {
        $this->authorize('createRevision', $renstraOpd);

        $revision = $revisionService->createRenstraRevision(
            $renstraOpd,
            $request->validated(),
            $request->user(),
        );

        return redirect()
            ->route('renstra-opd.show', $revision)
            ->with('success', 'Versi perubahan Renstra OPD berhasil dibuat. Lengkapi perubahan lalu ajukan untuk persetujuan.');
    }

    public function cancelRevision(
        CancelDocumentRevisionRequest $request,
        RenstraOpd $renstraOpd,
        CancelDocumentRevisionService $service,
    ): RedirectResponse {
        $this->authorize('cancelRevision', $renstraOpd);

        $previous = $service->cancelRenstraRevision($renstraOpd, $request->validated(), $request->user());

        return ($previous ? redirect()->route('renstra-opd.show', $previous) : redirect()->route('renstra-opd.index'))
            ->with('success', 'Perubahan Renstra OPD dibatalkan. Versi sebelumnya aktif kembali.');
    }

    public function destroy(RenstraOpd $renstraOpd): RedirectResponse
    {
        $this->authorize('delete', $renstraOpd);

        if ($renstraOpd->jenis_versi === 'perubahan') {
            return back()->with('error', $this->revisionDeleteBlockedMessage((string) $renstraOpd->status, 'Renstra OPD'));
        }

        $renstraOpd->delete();

        return redirect()->route('renstra-opd.index')->with('success', 'Renstra OPD berhasil dihapus.');
    }

    private function shouldLimitToUserOpd(User $user): bool
    {
        return $user->hasRole('admin_opd')
            && ! $user->hasAnyRole(['super_admin', 'admin_kabupaten_bagian_organisasi', 'admin_kabupaten_bapperida', 'admin_kabupaten_inspektorat']);
    }

    private function canReviewWorkflow(User $user): bool
    {
        return $user->hasAnyRole(['super_admin', 'admin_kabupaten_bagian_organisasi'])
            || $user->hasPermission('lock_period');
    }

    private function canLockWorkflow(User $user): bool
    {
        return $user->isSuperAdmin() || $user->hasPermission('lock_period');
    }

    private function canWithdrawWorkflow(User $user, Model $model, string $module): bool
    {
        if ((string) ($model->getAttribute('status') ?? '') !== 'submitted') {
            return false;
        }

        if (! $user->can('update', $model)) {
            return false;
        }

        if ($user->isSuperAdmin()) {
            return true;
        }

        $submittedBy = WorkflowSubmission::query()
            ->where('related_table', $model->getTable())
            ->where('related_id', (int) $model->getKey())
            ->where('module', $module)
            ->value('submitted_by');

        return $submittedBy !== null && (int) $submittedBy === (int) $user->id;
    }

    private function revisionDeleteBlockedMessage(string $status, string $label): string
    {
        if (in_array($status, ['draft', 'revision', 'rejected'], true)) {
            return "{$label} Perubahan belum resmi. Gunakan tombol Batalkan Perubahan agar versi sebelumnya aktif kembali.";
        }

        if (in_array($status, ['submitted', 'verified'], true)) {
            return "{$label} Perubahan sedang diajukan. Tarik pengajuan terlebih dahulu jika perlu dibatalkan.";
        }

        return "{$label} Perubahan sudah resmi. Buat Perubahan berikutnya untuk koreksi.";
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
    private function rpjmdOptions(): array
    {
        return Rpjmd::query()
            ->orderByDesc('tahun_awal')
            ->get(['id', 'judul', 'tahun_awal', 'tahun_akhir', 'status'])
            ->map(fn (Rpjmd $rpjmd) => [
                'id' => $rpjmd->id,
                'label' => "{$rpjmd->tahun_awal}-{$rpjmd->tahun_akhir} - {$rpjmd->judul}",
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
                'label' => "{$periode->tahun} - {$periode->nama}",
                'tahun' => $periode->tahun,
            ])
            ->all();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function resolvePeriodeTahunId(array $data): ?int
    {
        if (filled($data['periode_tahun_id'] ?? null)) {
            return (int) $data['periode_tahun_id'];
        }

        if (! filled($data['tahun_awal'] ?? null)) {
            return null;
        }

        return PeriodeTahun::query()
            ->where('tahun', (int) $data['tahun_awal'])
            ->value('id');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function satuanOptions(): array
    {
        return SatuanIndikator::query()
            ->where('status', 'active')
            ->orderBy('nama')
            ->get(['id', 'nama', 'simbol'])
            ->map(fn (SatuanIndikator $satuan) => [
                'id' => $satuan->id,
                'label' => $satuan->simbol ? "{$satuan->nama} ({$satuan->simbol})" : $satuan->nama,
            ])
            ->all();
    }

    /**
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function nodeOptions(RenstraOpd $renstra, string $activeSection): array
    {
        $tujuan = $renstra->tujuan;
        $sasaran = $tujuan->flatMap(fn (TujuanOpd $item) => $this->loadedRelation($item, 'sasaran'));
        $programs = $sasaran->flatMap(fn (SasaranOpd $item) => $this->loadedRelation($item, 'programs'));
        $kegiatan = $programs->flatMap(fn (OpdProgram $item) => $this->loadedRelation($item, 'kegiatan'));
        $subKegiatan = $kegiatan->flatMap(fn (OpdKegiatan $item) => $this->loadedRelation($item, 'subKegiatan'));
        $sasaranById = $sasaran->keyBy('id');
        $programById = $programs->keyBy('id');

        return [
            'tujuan' => in_array($activeSection, ['tujuan', 'sasaran'], true)
                ? $tujuan->map(fn (TujuanOpd $item) => ['id' => $item->id, 'label' => $this->nodeLabel(null, $item->tujuan)])->values()->all()
                : [],
            'indikator_tujuan' => $activeSection === 'tujuan'
                ? $tujuan->flatMap(fn (TujuanOpd $item) => $this->loadedRelation($item, 'indikator'))->map(fn (IndikatorTujuanOpd $item) => ['id' => $item->id, 'label' => $this->nodeLabel($item->kode, $item->indikator)])->values()->all()
                : [],
            'sasaran' => in_array($activeSection, ['sasaran', 'program'], true)
                ? $sasaran->map(fn (SasaranOpd $item) => ['id' => $item->id, 'label' => $this->nodeLabel($item->kode, $item->sasaran)])->values()->all()
                : [],
            'indikator_sasaran' => $activeSection === 'sasaran'
                ? $sasaran->flatMap(fn (SasaranOpd $item) => $this->loadedRelation($item, 'indikator'))->map(fn (IndikatorSasaranOpd $item) => ['id' => $item->id, 'label' => $this->nodeLabel($item->kode, $item->indikator)])->values()->all()
                : [],
            'program' => in_array($activeSection, ['program', 'kegiatan'], true) ? $programs
                ->map(function (OpdProgram $item) use ($sasaranById): array {
                    $sasaran = $sasaranById->get($item->sasaran_opd_id);
                    $context = collect([
                        $sasaran ? [
                            'label' => 'Sasaran OPD',
                            'value' => $this->nodeLabel($sasaran->kode, $sasaran->sasaran),
                            'tone' => 'opd',
                        ] : null,
                        filled($item->sasaran_program) ? [
                            'label' => 'Sasaran Program',
                            'value' => $item->sasaran_program,
                            'tone' => 'program',
                        ] : null,
                    ])->filter()->values();

                    return [
                        'id' => $item->id,
                        'label' => $this->nodeLabel($item->kode, $item->nama),
                        'description' => $context->map(fn (array $item) => $item['label'].': '.$item['value'])->join(' • ') ?: null,
                        'context' => $context->all(),
                    ];
                })
                ->values()
                ->all() : [],
            'indikator_program' => $activeSection === 'program'
                ? $programs->flatMap(fn (OpdProgram $item) => $this->loadedRelation($item, 'indikator'))->map(fn (IndikatorOpdProgram $item) => ['id' => $item->id, 'label' => $this->nodeLabel($item->kode, $item->indikator)])->values()->all()
                : [],
            'kegiatan' => in_array($activeSection, ['kegiatan', 'sub-kegiatan'], true) ? $kegiatan
                ->map(function (OpdKegiatan $item) use ($programById): array {
                    $program = $programById->get($item->opd_program_id);
                    $context = collect([
                        filled($program?->sasaran_program) ? [
                            'label' => 'Sasaran Program',
                            'value' => $program->sasaran_program,
                            'tone' => 'program',
                        ] : null,
                        filled($item->sasaran_kegiatan) ? [
                            'label' => 'Sasaran Kegiatan',
                            'value' => $item->sasaran_kegiatan,
                            'tone' => 'kegiatan',
                        ] : null,
                    ])->filter()->values();

                    return [
                        'id' => $item->id,
                        'label' => $this->nodeLabel($item->kode, $item->nama),
                        'description' => $context->map(fn (array $item) => $item['label'].': '.$item['value'])->join(' • ') ?: null,
                        'context' => $context->all(),
                        'group' => $program ? 'Program: '.$program->nama : null,
                    ];
                })
                ->values()
                ->all() : [],
            'indikator_kegiatan' => $activeSection === 'kegiatan'
                ? $kegiatan->flatMap(fn (OpdKegiatan $item) => $this->loadedRelation($item, 'indikator'))->map(fn (IndikatorOpdKegiatan $item) => ['id' => $item->id, 'label' => $this->nodeLabel($item->kode, $item->indikator)])->values()->all()
                : [],
            'sub_kegiatan' => $activeSection === 'sub-kegiatan'
                ? $subKegiatan->map(fn (OpdSubKegiatan $item) => ['id' => $item->id, 'label' => $this->nodeLabel($item->kode, $item->nama)])->values()->all()
                : [],
        ];
    }

    /**
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function rpjmdReferenceOptions(RenstraOpd $renstra, string $activeSection): array
    {
        $programRpjmdQuery = fn () => ProgramRpjmd::query()
            ->forRpjmd($renstra->rpjmd_id)
            ->when($this->shouldRestrictRpjmdProgramReferences($renstra), fn (Builder $query) => $query
                ->relevantForOpd((int) $renstra->opd_id));

        return [
            'tujuan_daerah' => $activeSection === 'tujuan'
                ? TujuanDaerah::query()->forRpjmd($renstra->rpjmd_id)->orderBy('urutan')->get(['id', 'kode', 'tujuan'])->map(fn ($item) => ['id' => $item->id, 'label' => $this->nodeLabel($item->kode, $item->tujuan)])->values()->all()
                : [],
            'indikator_tujuan_daerah' => $activeSection === 'tujuan'
                ? IndikatorTujuanDaerah::query()->whereHas('tujuan', fn (Builder $query) => $query->forRpjmd($renstra->rpjmd_id))->orderBy('urutan')->get(['id', 'kode', 'indikator'])->map(fn ($item) => ['id' => $item->id, 'label' => $this->nodeLabel($item->kode, $item->indikator)])->values()->all()
                : [],
            'sasaran_daerah' => $activeSection === 'sasaran'
                ? SasaranDaerah::query()->whereHas('tujuan', fn (Builder $query) => $query->forRpjmd($renstra->rpjmd_id))->orderBy('urutan')->get(['id', 'kode', 'sasaran'])->map(fn ($item) => ['id' => $item->id, 'label' => $this->nodeLabel($item->kode, $item->sasaran)])->values()->all()
                : [],
            'indikator_sasaran_daerah' => $activeSection === 'sasaran'
                ? IndikatorSasaranDaerah::query()->whereHas('sasaran.tujuan', fn (Builder $query) => $query->forRpjmd($renstra->rpjmd_id))->orderBy('urutan')->get(['id', 'kode', 'indikator'])->map(fn ($item) => ['id' => $item->id, 'label' => $this->nodeLabel($item->kode, $item->indikator)])->values()->all()
                : [],
            'program_rpjmd' => $activeSection === 'program' ? $programRpjmdQuery()
                ->with('programPemerintahan.bidangUrusan.opdPengampu:id')
                ->with('programPemerintahanReferences.bidangUrusan.opdPengampu:id')
                ->orderBy('urutan')
                ->get(['id', 'program_pemerintahan_id', 'kode', 'nama'])
                ->map(function (ProgramRpjmd $item) use ($renstra) {
                    $preferredReference = $item->preferredProgramPemerintahanReferenceForOpd(
                        $this->shouldRestrictRpjmdProgramReferences($renstra) ? (int) $renstra->opd_id : null,
                        $renstra->opd,
                    );

                    return [
                        'id' => $item->id,
                        'program_pemerintahan_id' => $preferredReference?->id ?? $item->program_pemerintahan_id,
                        'program_pemerintahan_ids' => $item->programPemerintahanReferenceIds(),
                        'label' => $this->nodeLabel($preferredReference?->kode ?? $item->kode, $preferredReference?->nama ?? $item->nama),
                        'description' => $preferredReference ? $this->nodeLabel($preferredReference->kode, $preferredReference->nama) : null,
                    ];
                })
                ->values()
                ->all() : [],
            'indikator_program_rpjmd' => $activeSection === 'program' ? IndikatorProgramRpjmd::query()
                ->whereHas('program', fn (Builder $query) => $query
                    ->forRpjmd($renstra->rpjmd_id)
                    ->when($this->shouldRestrictRpjmdProgramReferences($renstra), fn (Builder $query) => $query
                        ->relevantForOpd((int) $renstra->opd_id)))
                ->orderBy('urutan')
                ->get(['id', 'kode', 'indikator'])
                ->map(fn ($item) => ['id' => $item->id, 'label' => $this->nodeLabel($item->kode, $item->indikator)])
                ->values()
                ->all() : [],
        ];
    }

    private function shouldRestrictRpjmdProgramReferences(RenstraOpd $renstra): bool
    {
        return filled($renstra->opd_id);
    }

    /**
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function masterReferenceOptions(RenstraOpd $renstra, string $activeSection): array
    {
        $referencePeriodeTahunId = in_array($activeSection, ['kegiatan', 'sub-kegiatan'], true)
            ? $this->referencePeriodeTahunIdForMasterOptions($renstra)
            : null;
        $programMasterIds = collect();
        $kegiatanMasterIds = collect();

        if ($activeSection === 'kegiatan') {
            $programs = $renstra->tujuan
                ->flatMap(fn (TujuanOpd $tujuan) => $this->loadedRelation($tujuan, 'sasaran'))
                ->flatMap(fn (SasaranOpd $sasaran) => $this->loadedRelation($sasaran, 'programs'));
            $programMasterIds = $programs->contains(fn (OpdProgram $program): bool => blank($program->program_pemerintahan_id))
                ? collect()
                : $programs->pluck('program_pemerintahan_id')->filter()->unique()->values();
        }

        if ($activeSection === 'sub-kegiatan') {
            $kegiatan = $renstra->tujuan
                ->flatMap(fn (TujuanOpd $tujuan) => $this->loadedRelation($tujuan, 'sasaran'))
                ->flatMap(fn (SasaranOpd $sasaran) => $this->loadedRelation($sasaran, 'programs'))
                ->flatMap(fn (OpdProgram $program) => $this->loadedRelation($program, 'kegiatan'));
            $kegiatanMasterIds = $kegiatan->contains(fn (OpdKegiatan $item): bool => blank($item->kegiatan_pemerintahan_id))
                ? collect()
                : $kegiatan->pluck('kegiatan_pemerintahan_id')->filter()->unique()->values();
        }

        return [
            'program_pemerintahan' => $activeSection === 'program' ? ProgramPemerintahan::query()
                ->with('bidangUrusan.urusanPemerintahan:id,kode,nama')
                ->where('status', 'active')
                ->orderBy('kode')
                ->get(['id', 'bidang_urusan_id', 'kode', 'nama'])
                ->map(fn (ProgramPemerintahan $program) => [
                    'id' => $program->id,
                    'kode' => $program->kode,
                    'nama' => $program->nama,
                    'bidang_urusan_id' => $program->bidang_urusan_id,
                    'label' => $this->nodeLabel($program->kode, $program->nama),
                    'description' => $program->bidangUrusan ? $this->nodeLabel($program->bidangUrusan->kode, $program->bidangUrusan->nama) : null,
                    'group' => $program->bidangUrusan?->urusanPemerintahan ? $this->nodeLabel($program->bidangUrusan->urusanPemerintahan->kode, $program->bidangUrusan->urusanPemerintahan->nama) : null,
                ])
                ->values()
                ->all() : [],
            'kegiatan_pemerintahan' => $activeSection === 'kegiatan' ? KegiatanPemerintahan::query()
                ->with('programPemerintahan:id,kode,nama,bidang_urusan_id')
                ->where('status', 'active')
                ->when($referencePeriodeTahunId, fn (Builder $query, int $periodeId) => $query->where('periode_tahun_id', $periodeId))
                ->when($programMasterIds->isNotEmpty(), fn (Builder $query) => $query->whereIn('program_pemerintahan_id', $programMasterIds))
                ->orderBy('kode')
                ->get(['id', 'periode_tahun_id', 'program_pemerintahan_id', 'kode', 'nama'])
                ->map(fn (KegiatanPemerintahan $kegiatan) => [
                    'id' => $kegiatan->id,
                    'periode_tahun_id' => $kegiatan->periode_tahun_id,
                    'kode' => $kegiatan->kode,
                    'nama' => $kegiatan->nama,
                    'program_pemerintahan_id' => $kegiatan->program_pemerintahan_id,
                    'label' => $this->nodeLabel($kegiatan->kode, $kegiatan->nama),
                    'description' => $kegiatan->programPemerintahan ? $this->nodeLabel($kegiatan->programPemerintahan->kode, $kegiatan->programPemerintahan->nama) : null,
                    'group' => $kegiatan->programPemerintahan ? $this->nodeLabel($kegiatan->programPemerintahan->kode, $kegiatan->programPemerintahan->nama) : null,
                ])
                ->values()
                ->all() : [],
            'sub_kegiatan_pemerintahan' => $activeSection === 'sub-kegiatan' ? SubKegiatanPemerintahan::query()
                ->with(['satuanIndikator:id,nama,simbol', 'kegiatanPemerintahan.programPemerintahan:id,kode,nama,bidang_urusan_id'])
                ->where('status', 'active')
                ->when($referencePeriodeTahunId, fn (Builder $query, int $periodeId) => $query->where('periode_tahun_id', $periodeId))
                ->when($kegiatanMasterIds->isNotEmpty(), fn (Builder $query) => $query->whereIn('kegiatan_pemerintahan_id', $kegiatanMasterIds))
                ->orderBy('kode')
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
                ->map(fn (SubKegiatanPemerintahan $subKegiatan) => [
                    'id' => $subKegiatan->id,
                    'periode_tahun_id' => $subKegiatan->periode_tahun_id,
                    'kode' => $subKegiatan->kode,
                    'nama' => $subKegiatan->nama,
                    'sasaran_sub_kegiatan' => $subKegiatan->sasaran_sub_kegiatan,
                    'indikator_sub_kegiatan' => $subKegiatan->indikator_sub_kegiatan,
                    'satuan_indikator_id' => $subKegiatan->satuan_indikator_id,
                    'satuan_label' => $subKegiatan->satuanIndikator?->simbol ?: $subKegiatan->satuanIndikator?->nama,
                    'definisi_operasional' => $subKegiatan->definisi_operasional,
                    'kegiatan_pemerintahan_id' => $subKegiatan->kegiatan_pemerintahan_id,
                    'program_pemerintahan_id' => $subKegiatan->kegiatanPemerintahan?->program_pemerintahan_id,
                    'label' => $this->nodeLabel($subKegiatan->kode, $subKegiatan->nama),
                    'description' => $subKegiatan->kegiatanPemerintahan ? $this->nodeLabel($subKegiatan->kegiatanPemerintahan->kode, $subKegiatan->kegiatanPemerintahan->nama) : null,
                    'group' => $subKegiatan->kegiatanPemerintahan?->programPemerintahan ? $this->nodeLabel($subKegiatan->kegiatanPemerintahan->programPemerintahan->kode, $subKegiatan->kegiatanPemerintahan->programPemerintahan->nama) : null,
                ])
                ->values()
                ->all() : [],
            'opd_units' => $activeSection === 'sub-kegiatan' ? OpdUnit::query()
                ->where('opd_id', $renstra->opd_id)
                ->where('status', 'active')
                ->orderBy('kode')
                ->get(['id', 'kode', 'nama', 'jenis_unit'])
                ->map(fn (OpdUnit $unit) => [
                    'id' => $unit->id,
                    'kode' => $unit->kode,
                    'nama' => $unit->nama,
                    'jenis_unit' => $unit->jenis_unit,
                    'label' => $this->nodeLabel($unit->kode, $unit->nama),
                    'description' => $unit->jenis_unit,
                ])
                ->values()
                ->all() : [],
            // Indicator forms use free-text PD/unit responsibility fields. This
            // legacy list was not consumed by the page and could be very large.
            'opds' => [],
        ];
    }

    private function referencePeriodeTahunIdForMasterOptions(RenstraOpd $renstra): ?int
    {
        $candidates = collect([
            $renstra->periode_tahun_id,
            filled($renstra->tahun_awal)
                ? PeriodeTahun::query()->where('tahun', (int) $renstra->tahun_awal)->value('id')
                : null,
            PeriodeTahun::query()->where('status', 'active')->orderByDesc('tahun')->value('id'),
        ])
            ->filter(fn ($value) => filled($value))
            ->map(fn ($value) => (int) $value)
            ->unique()
            ->values();

        foreach ($candidates as $periodeId) {
            if (KegiatanPemerintahan::query()->where('periode_tahun_id', $periodeId)->where('status', 'active')->exists()) {
                return $periodeId;
            }
        }

        $fallbackPeriodeId = KegiatanPemerintahan::query()
            ->where('status', 'active')
            ->orderByDesc('periode_tahun_id')
            ->value('periode_tahun_id');

        return filled($fallbackPeriodeId) ? (int) $fallbackPeriodeId : null;
    }

    private function nodeLabel(?string $kode, ?string $label): string
    {
        return trim(($kode ? "{$kode} - " : '').str($label ?? '')->limit(90)->toString());
    }

    /**
     * @return array<string, mixed>
     */
    private function rpjmdContext(RenstraOpd $renstra): array
    {
        if (! filled($renstra->rpjmd_id)) {
            return [
                'visi' => [],
                'misi' => [],
                'program_groups' => [],
            ];
        }

        $rpjmd = Rpjmd::query()
            ->with([
                'visi:id,rpjmd_id,visi,urutan',
                'misi:id,rpjmd_id,kode,misi,urutan',
            ])
            ->find($renstra->rpjmd_id, ['id', 'judul', 'tahun_awal', 'tahun_akhir']);

        if (! $rpjmd) {
            return [
                'visi' => [],
                'misi' => [],
                'program_groups' => [],
            ];
        }

        $programs = ProgramRpjmd::query()
            ->forRpjmd((int) $renstra->rpjmd_id)
            ->when(filled($renstra->opd_id), fn (Builder $query) => $query->relevantForOpd((int) $renstra->opd_id))
            ->with([
                'sasaran:id,tujuan_daerah_id,kode,sasaran,urutan',
                'sasaran.tujuan:id,rpjmd_visi_id,rpjmd_misi_id,kode,tujuan,urutan',
                'sasaran.tujuan.misiTerkait:id,kode,misi,urutan',
                'programPemerintahan:id,kode,nama,bidang_urusan_id',
                'programPemerintahanReferences:id,kode,nama,bidang_urusan_id',
                'programPemerintahan.bidangUrusan.opdPengampu:id',
                'programPemerintahanReferences.bidangUrusan.opdPengampu:id',
            ])
            ->orderBy('urutan')
            ->get(['id', 'sasaran_daerah_id', 'program_pemerintahan_id', 'kode', 'nama', 'urutan']);

        return [
            'visi' => $rpjmd->visi
                ->map(fn ($visi) => [
                    'id' => $visi->id,
                    'visi' => $visi->visi,
                    'urutan' => $visi->urutan,
                ])
                ->values()
                ->all(),
            'misi' => $rpjmd->misi
                ->map(fn ($misi) => [
                    'id' => $misi->id,
                    'kode' => $misi->kode,
                    'misi' => $misi->misi,
                    'urutan' => $misi->urutan,
                ])
                ->values()
                ->all(),
            'program_groups' => $programs
                ->groupBy(fn (ProgramRpjmd $program) => (string) ($program->sasaran_daerah_id ?? "program-{$program->id}"))
                ->sortBy(fn (Collection $items) => sprintf(
                    '%010d-%010d',
                    $items->first()?->sasaran?->urutan ?? PHP_INT_MAX,
                    $items->first()?->sasaran?->id ?? PHP_INT_MAX,
                ))
                ->map(function (Collection $items) use ($renstra) {
                    /** @var ProgramRpjmd $first */
                    $first = $items->first();
                    $sasaran = $first?->sasaran;
                    $tujuan = $sasaran?->tujuan;

                    return [
                        'tujuan' => $tujuan ? [
                            'id' => $tujuan->id,
                            'kode' => $tujuan->kode,
                            'tujuan' => $tujuan->tujuan,
                            'misi' => $tujuan->misiTerkait
                                ->map(fn ($misi) => [
                                    'id' => $misi->id,
                                    'kode' => $misi->kode,
                                    'misi' => $misi->misi,
                                ])
                                ->values()
                                ->all(),
                        ] : null,
                        'sasaran' => $sasaran ? [
                            'id' => $sasaran->id,
                            'kode' => $sasaran->kode,
                            'sasaran' => $sasaran->sasaran,
                        ] : null,
                        'programs' => $items
                            ->sortBy(fn (ProgramRpjmd $program) => sprintf('%010d-%010d', $program->urutan ?? PHP_INT_MAX, $program->id))
                            ->map(function (ProgramRpjmd $program) use ($renstra) {
                                $preferredReference = $program->preferredProgramPemerintahanReferenceForOpd(
                                    filled($renstra->opd_id) ? (int) $renstra->opd_id : null,
                                    $renstra->opd,
                                );

                                return [
                                    'id' => $program->id,
                                    'kode' => $preferredReference?->kode ?? $program->kode,
                                    'nama' => $preferredReference?->nama ?? $program->nama,
                                    'rpjmd_kode' => $program->kode,
                                    'rpjmd_nama' => $program->nama,
                                ];
                            })
                            ->values()
                            ->all(),
                    ];
                })
                ->values()
                ->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeRenstra(
        RenstraOpd $renstra,
        ?string $activeSection = null,
        array $budgetTotals = [],
    ): array {
        $includeSasaran = $activeSection === null || $activeSection !== 'tujuan';
        $includePrograms = $activeSection === null || in_array($activeSection, ['program', 'kegiatan', 'sub-kegiatan'], true);
        $includeKegiatan = $activeSection === null || in_array($activeSection, ['kegiatan', 'sub-kegiatan'], true);
        $includeSubKegiatan = $activeSection === null || $activeSection === 'sub-kegiatan';

        return [
            'id' => $renstra->id,
            'judul' => $renstra->judul,
            'nomor_dokumen' => $renstra->nomor_dokumen,
            'tahun_awal' => $renstra->tahun_awal,
            'tahun_akhir' => $renstra->tahun_akhir,
            'status' => $renstra->status,
            'jenis_versi' => $renstra->jenis_versi,
            'nomor_versi' => $renstra->nomor_versi,
            'parent_version_id' => $renstra->parent_version_id,
            'is_active_version' => $renstra->is_active_version,
            'version_label' => $renstra->versionLabel(),
            'perlu_penyesuaian_rpjmd' => $renstra->perlu_penyesuaian_rpjmd,
            'rpjmd_perubahan_terbaru' => $renstra->rpjmdPerubahanTerbaru ? [
                'id' => $renstra->rpjmdPerubahanTerbaru->id,
                'judul' => $renstra->rpjmdPerubahanTerbaru->judul,
                'version_label' => $renstra->rpjmdPerubahanTerbaru->versionLabel(),
            ] : null,
            'alasan_perubahan' => $renstra->alasan_perubahan,
            'dasar_perubahan' => $renstra->dasar_perubahan,
            'tanggal_berlaku' => $renstra->tanggal_berlaku?->toDateString(),
            'keterangan' => $renstra->keterangan,
            'opd' => $renstra->opd ? [
                'id' => $renstra->opd->id,
                'kode' => $renstra->opd->kode,
                'nama' => $renstra->opd->nama,
                'singkatan' => $renstra->opd->singkatan,
            ] : null,
            'rpjmd' => $renstra->rpjmd ? [
                'id' => $renstra->rpjmd->id,
                'judul' => $renstra->rpjmd->judul,
                'tahun_awal' => $renstra->rpjmd->tahun_awal,
                'tahun_akhir' => $renstra->rpjmd->tahun_akhir,
            ] : null,
            'periode_tahun' => $renstra->periodeTahun ? [
                'id' => $renstra->periodeTahun->id,
                'tahun' => $renstra->periodeTahun->tahun,
                'nama' => $renstra->periodeTahun->nama,
            ] : null,
            'tujuan' => $renstra->tujuan->map(fn (TujuanOpd $tujuan) => [
                'id' => $tujuan->id,
                'tujuan_daerah_id' => $tujuan->tujuan_daerah_id,
                'kode' => null,
                'tujuan' => $tujuan->tujuan,
                'linked' => filled($tujuan->tujuan_daerah_id),
                'urutan' => $tujuan->urutan,
                'tujuan_daerah' => $tujuan->relationLoaded('tujuanDaerah') && $tujuan->tujuanDaerah ? [
                    'kode' => $tujuan->tujuanDaerah->kode,
                    'tujuan' => $tujuan->tujuanDaerah->tujuan,
                ] : null,
                'indikator' => $this->loadedRelation($tujuan, 'indikator')->map(fn (IndikatorTujuanOpd $indikator) => $this->serializeIndikator($indikator, 'indikatorTujuanDaerah')),
                'sasaran' => $includeSasaran
                    ? $this->loadedRelation($tujuan, 'sasaran')->map(fn (SasaranOpd $sasaran) => [
                        'id' => $sasaran->id,
                        'sasaran_daerah_id' => $sasaran->sasaran_daerah_id,
                        'kode' => $sasaran->kode,
                        'sasaran' => $sasaran->sasaran,
                        'linked' => filled($sasaran->sasaran_daerah_id),
                        'urutan' => $sasaran->urutan,
                        'sasaran_daerah' => $sasaran->relationLoaded('sasaranDaerah') && $sasaran->sasaranDaerah ? [
                            'kode' => $sasaran->sasaranDaerah->kode,
                            'sasaran' => $sasaran->sasaranDaerah->sasaran,
                        ] : null,
                        'indikator' => $this->loadedRelation($sasaran, 'indikator')->map(fn (IndikatorSasaranOpd $indikator) => $this->serializeIndikator($indikator, 'indikatorSasaranDaerah')),
                        'programs' => $includePrograms
                        ? $this->loadedRelation($sasaran, 'programs')->map(function (OpdProgram $program) use ($renstra, $activeSection, $budgetTotals, $includeKegiatan, $includeSubKegiatan) {
                            $programPemerintahan = $this->programPemerintahanForRenstraProgram($program, $renstra);

                            return [
                                'id' => $program->id,
                                'program_rpjmd_id' => $program->program_rpjmd_id,
                                'program_pemerintahan_id' => $programPemerintahan?->id ?? $program->program_pemerintahan_id,
                                'kode' => $program->kode,
                                'nama' => $program->nama,
                                'sasaran_program' => $program->sasaran_program,
                                'pagu_indikatif' => $activeSection === 'program'
                                    ? ($budgetTotals[$program->id] ?? 0)
                                    : ($activeSection === null || $activeSection === 'sub-kegiatan' ? $this->programBudgetTotal($program) : 0),
                                'status' => $program->status,
                                'linked' => filled($program->program_rpjmd_id),
                                'urutan' => $program->urutan,
                                'program_rpjmd' => $program->programRpjmd ? [
                                    'kode' => $program->programRpjmd->kode,
                                    'nama' => $program->programRpjmd->nama,
                                    'program_pemerintahan_id' => $programPemerintahan?->id ?? $program->programRpjmd->program_pemerintahan_id,
                                    'program_pemerintahan_ids' => $program->programRpjmd->programPemerintahanReferenceIds(),
                                    'program_pemerintahan' => $this->serializeProgramPemerintahan($programPemerintahan),
                                ] : null,
                                'program_pemerintahan' => $this->serializeProgramPemerintahan($programPemerintahan),
                                'indikator' => $this->loadedRelation($program, 'indikator')->map(fn (IndikatorOpdProgram $indikator) => $this->serializeIndikator($indikator, 'indikatorProgramRpjmd')),
                                'kegiatan' => $includeKegiatan
                                    ? $this->loadedRelation($program, 'kegiatan')->map(fn (OpdKegiatan $kegiatan) => [
                                        'id' => $kegiatan->id,
                                        'kegiatan_pemerintahan_id' => $kegiatan->kegiatan_pemerintahan_id,
                                        'kode' => $kegiatan->kode,
                                        'nama' => $kegiatan->nama,
                                        'sasaran_kegiatan' => $kegiatan->sasaran_kegiatan,
                                        'pagu_indikatif' => $activeSection === 'kegiatan'
                                            ? ($budgetTotals[$kegiatan->id] ?? 0)
                                            : ($activeSection === null || $activeSection === 'sub-kegiatan' ? $this->kegiatanBudgetTotal($kegiatan) : 0),
                                        'urutan' => $kegiatan->urutan,
                                        'kegiatan_pemerintahan' => $kegiatan->kegiatanPemerintahan ? [
                                            'kode' => $kegiatan->kegiatanPemerintahan->kode,
                                            'nama' => $kegiatan->kegiatanPemerintahan->nama,
                                            'program_pemerintahan_id' => $kegiatan->kegiatanPemerintahan->program_pemerintahan_id,
                                        ] : null,
                                        'indikator' => $this->loadedRelation($kegiatan, 'indikator')->map(fn (IndikatorOpdKegiatan $indikator) => $this->serializeIndikator($indikator)),
                                        'sub_kegiatan' => $includeSubKegiatan
                                            ? $this->loadedRelation($kegiatan, 'subKegiatan')->map(fn (OpdSubKegiatan $subKegiatan) => [
                                                'id' => $subKegiatan->id,
                                                'sub_kegiatan_pemerintahan_id' => $subKegiatan->sub_kegiatan_pemerintahan_id,
                                                'opd_unit_id' => $subKegiatan->opd_unit_id,
                                                'kode' => $subKegiatan->kode,
                                                'nama' => $subKegiatan->nama,
                                                'sasaran_sub_kegiatan' => $subKegiatan->sasaran_sub_kegiatan,
                                                'pagu_indikatif' => $this->subKegiatanBudgetTotal($subKegiatan),
                                                'urutan' => $subKegiatan->urutan,
                                                'anggaran' => $subKegiatan->anggaranTahunan->map(fn ($anggaran) => [
                                                    'id' => $anggaran->id,
                                                    'periode_tahun' => [
                                                        'id' => $anggaran->periodeTahun->id,
                                                        'tahun' => $anggaran->periodeTahun->tahun,
                                                        'nama' => $anggaran->periodeTahun->nama,
                                                    ],
                                                    'anggaran' => $anggaran->anggaran,
                                                ]),
                                                'sub_kegiatan_pemerintahan' => $subKegiatan->subKegiatanPemerintahan ? [
                                                    'kode' => $subKegiatan->subKegiatanPemerintahan->kode,
                                                    'nama' => $subKegiatan->subKegiatanPemerintahan->nama,
                                                    'kegiatan_pemerintahan_id' => $subKegiatan->subKegiatanPemerintahan->kegiatan_pemerintahan_id,
                                                ] : null,
                                                'opd_unit' => $subKegiatan->opdUnit ? [
                                                    'kode' => $subKegiatan->opdUnit->kode,
                                                    'nama' => $subKegiatan->opdUnit->nama,
                                                    'jenis_unit' => $subKegiatan->opdUnit->jenis_unit,
                                                ] : null,
                                                'indikator' => $this->loadedRelation($subKegiatan, 'indikator')->map(fn (IndikatorSubKegiatan $indikator) => $this->serializeIndikator($indikator)),
                                            ])
                                            : collect(),
                                    ])
                                    : collect(),
                            ];
                        })
                            : collect(),
                    ])
                    : collect(),
            ]),
        ];
    }

    /**
     * Return an already eager-loaded relation without allowing accidental N+1 queries.
     *
     * @return Collection<int, Model>
     */
    private function loadedRelation(Model $model, string $relation): Collection
    {
        return $model->relationLoaded($relation) ? collect($model->getRelation($relation)) : collect();
    }

    private function programBudgetTotal(OpdProgram $program): float
    {
        return $program->kegiatan->sum(fn (OpdKegiatan $kegiatan) => $this->kegiatanBudgetTotal($kegiatan));
    }

    private function kegiatanBudgetTotal(OpdKegiatan $kegiatan): float
    {
        return $kegiatan->subKegiatan->sum(fn (OpdSubKegiatan $subKegiatan) => $this->subKegiatanBudgetTotal($subKegiatan));
    }

    private function subKegiatanBudgetTotal(OpdSubKegiatan $subKegiatan): float
    {
        return $subKegiatan->anggaranTahunan->sum(fn ($anggaran) => (float) $anggaran->anggaran);
    }

    private function programPemerintahanForRenstraProgram(OpdProgram $program, RenstraOpd $renstra): ?ProgramPemerintahan
    {
        $opdId = filled($renstra->opd_id) ? (int) $renstra->opd_id : null;

        return $program->programPemerintahan
            ?? $program->programRpjmd?->preferredProgramPemerintahanReferenceForOpd($opdId, $renstra->opd)
            ?? $this->programPemerintahanByCode($program->kode, $renstra)
            ?? $program->programRpjmd?->programPemerintahan;
    }

    private function programPemerintahanByCode(?string $kode, RenstraOpd $renstra): ?ProgramPemerintahan
    {
        if (blank($kode)) {
            return null;
        }

        return ProgramPemerintahan::query()
            ->where('kode', $kode)
            ->where(function (Builder $query) use ($renstra): void {
                $query->whereNull('tahun_awal')
                    ->orWhere('tahun_awal', '<=', (int) $renstra->tahun_awal);
            })
            ->where(function (Builder $query) use ($renstra): void {
                $query->whereNull('tahun_akhir')
                    ->orWhere('tahun_akhir', '>=', (int) $renstra->tahun_awal);
            })
            ->with('bidangUrusan.urusanPemerintahan')
            ->orderByDesc('tahun_awal')
            ->first();
    }

    /**
     * @return array<string, mixed>|null
     */
    private function serializeProgramPemerintahan(?ProgramPemerintahan $program): ?array
    {
        if (! $program) {
            return null;
        }

        return [
            'id' => $program->id,
            'kode' => $program->kode,
            'nama' => $program->nama,
            'bidang_urusan' => $program->bidangUrusan ? [
                'id' => $program->bidangUrusan->id,
                'kode' => $program->bidangUrusan->kode,
                'nama' => $program->bidangUrusan->nama,
                'urusan_pemerintahan' => $program->bidangUrusan->urusanPemerintahan ? [
                    'id' => $program->bidangUrusan->urusanPemerintahan->id,
                    'kode' => $program->bidangUrusan->urusanPemerintahan->kode,
                    'nama' => $program->bidangUrusan->urusanPemerintahan->nama,
                ] : null,
            ] : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeIndikator(
        IndikatorTujuanOpd|IndikatorSasaranOpd|IndikatorOpdProgram|IndikatorOpdKegiatan|IndikatorSubKegiatan $indikator,
        ?string $linkedRelation = null,
    ): array {
        $payload = [
            'id' => $indikator->id,
            'kode' => $indikator->kode,
            'indikator' => $indikator->indikator,
            'satuan_indikator_id' => $indikator->satuan_indikator_id,
            'tipe_indikator' => $indikator->tipe_indikator,
            'definisi_operasional' => $indikator->definisi_operasional,
            'formula' => $indikator->formula,
            'formulasi_pengukuran' => $indikator->formulasi_pengukuran ?? $indikator->formula,
            'tipe_perhitungan' => $indikator->tipe_perhitungan,
            'opd_penanggung_jawab_id' => $indikator->opd_penanggung_jawab_id,
            'pd_penanggung_jawab' => $indikator->pd_penanggung_jawab,
            'sumber_data' => $indikator->sumber_data,
            'linked' => false,
            'urutan' => $indikator->urutan,
            'satuan' => $indikator->satuanIndikator ? [
                'nama' => $indikator->satuanIndikator->nama,
                'simbol' => $indikator->satuanIndikator->simbol,
            ] : null,
            'opd_penanggung_jawab' => $indikator->opdPenanggungJawab ? [
                'id' => $indikator->opdPenanggungJawab->id,
                'kode' => $indikator->opdPenanggungJawab->kode,
                'nama' => $indikator->opdPenanggungJawab->nama,
                'singkatan' => $indikator->opdPenanggungJawab->singkatan,
            ] : null,
            'targets' => $indikator->targets->map(fn ($target) => [
                'id' => $target->id,
                'periode_tahun' => [
                    'id' => $target->periodeTahun->id,
                    'tahun' => $target->periodeTahun->tahun,
                    'nama' => $target->periodeTahun->nama,
                ],
                'target' => $target->target,
                'target_text' => $target->target_text,
                'pagu' => null,
            ]),
        ];

        if ($linkedRelation) {
            $column = $this->linkedColumn($linkedRelation);
            $payload[$column] = $indikator->{$column};
            $payload['linked'] = filled($indikator->{$linkedRelation}?->id);
        }

        return $payload;
    }

    private function linkedColumn(string $linkedRelation): string
    {
        return match ($linkedRelation) {
            'indikatorTujuanDaerah' => 'indikator_tujuan_daerah_id',
            'indikatorSasaranDaerah' => 'indikator_sasaran_daerah_id',
            'indikatorProgramRpjmd' => 'indikator_program_rpjmd_id',
        };
    }
}
