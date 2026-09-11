<?php

namespace App\Http\Controllers\Kinerja;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Kinerja\Concerns\BuildsKinerjaOptions;
use App\Http\Requests\Kinerja\StoreRencanaAksiRequest;
use App\Http\Requests\Kinerja\UpdateRencanaAksiRequest;
use App\Jobs\ExportKinerjaReportDocumentJob;
use App\Models\PerjanjianKinerja;
use App\Models\RencanaAksi;
use App\Models\RencanaAksiItem;
use App\Models\User;
use App\Models\WorkflowSubmission;
use App\Services\Kinerja\RencanaAksiSnapshotService;
use App\Services\Perencanaan\PerencanaanHierarchyValidationService;
use App\Support\Pagination\PerPagePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class RencanaAksiController extends Controller
{
    use BuildsKinerjaOptions;

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', RencanaAksi::class);

        $filters = $request->only(['search', 'status', 'opd_id', 'periode_tahun_id', 'tahun', 'per_page']);
        $filters['per_page'] = PerPagePaginator::selection($request);
        $user = $request->user();

        $itemsQuery = RencanaAksi::query()
            ->with(['opd:id,kode,nama,singkatan', 'periodeTahun:id,tahun,nama', 'perjanjianKinerja:id,judul,tahun'])
            ->withCount('items')
            ->when($this->shouldLimitToUserOpd($user), fn (Builder $query) => $query->where('opd_id', $user->opd_id))
            ->when($filters['search'] ?? null, function (Builder $query, string $search) {
                $query->where(function (Builder $query) use ($search) {
                    $query->where('judul', 'ilike', "%{$search}%")
                        ->orWhereHas('opd', fn (Builder $query) => $query->where('nama', 'ilike', "%{$search}%")->orWhere('singkatan', 'ilike', "%{$search}%"));
                });
            })
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($filters['opd_id'] ?? null, fn (Builder $query, string $opdId) => $query->where('opd_id', $opdId))
            ->when($filters['periode_tahun_id'] ?? null, fn (Builder $query, string $periodeId) => $query->where('periode_tahun_id', $periodeId))
            ->when($filters['tahun'] ?? null, fn (Builder $query, string $tahun) => $query->where('tahun', $tahun))
            ->orderByDesc('tahun')
            ->latest('id');

        $items = PerPagePaginator::paginate($itemsQuery, $request)
            ->through(fn (RencanaAksi $rencanaAksi) => [
                'id' => $rencanaAksi->id,
                'judul' => $rencanaAksi->judul,
                'tahun' => $rencanaAksi->tahun,
                'status' => $rencanaAksi->status,
                'format_version' => $rencanaAksi->format_version,
                'items_count' => $rencanaAksi->items_count,
                'opd' => $rencanaAksi->opd,
                'periode_tahun' => $rencanaAksi->periodeTahun,
                'perjanjian_kinerja' => $rencanaAksi->perjanjianKinerja,
            ]);

        return Inertia::render('Kinerja/RencanaAksi/Index', [
            'items' => $items,
            'filters' => $filters,
            'opdOptions' => $this->opdOptions($user),
            'periodeOptions' => $this->periodeOptions(),
            'can' => [
                'manage' => $user->can('create', RencanaAksi::class),
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorize('create', RencanaAksi::class);

        return Inertia::render('Kinerja/RencanaAksi/Form', [
            'mode' => 'create',
            'item' => null,
            'opdOptions' => $this->opdOptions($request->user()),
            'periodeOptions' => $this->periodeOptions(),
            'perjanjianKinerjaOptions' => $this->rencanaAksiSourceOptions($request),
        ]);
    }

    public function sourceReadiness(Request $request, PerjanjianKinerja $perjanjianKinerja, RencanaAksiSnapshotService $snapshotService): JsonResponse
    {
        $this->authorize('create', RencanaAksi::class);
        if ($this->shouldLimitToUserOpd($request->user()) && (int) $perjanjianKinerja->opd_id !== (int) $request->user()->opd_id) {
            abort(403);
        }

        return response()->json($snapshotService->inspect($perjanjianKinerja));
    }

    public function store(
        StoreRencanaAksiRequest $request,
        RencanaAksiSnapshotService $snapshotService,
        PerencanaanHierarchyValidationService $hierarchyValidation,
    ): RedirectResponse {
        $data = $request->validated();
        $pk = PerjanjianKinerja::query()->findOrFail($data['perjanjian_kinerja_id']);
        $this->assertPerjanjianKinerjaBelongsToOpd($pk->id, (int) $data['opd_id']);
        $rencanaAksi = DB::transaction(function () use ($data, $pk, $snapshotService, $hierarchyValidation): RencanaAksi {
            $pk = PerjanjianKinerja::query()->lockForUpdate()->findOrFail($pk->id);
            if (RencanaAksi::query()->where('perjanjian_kinerja_id', $pk->id)->exists()) {
                throw ValidationException::withMessages([
                    'perjanjian_kinerja_id' => 'Rencana Aksi untuk PK Kepala OPD ini sudah tersedia.',
                ]);
            }

            if ($pk->level_pk !== 'kepala_opd') {
                $hierarchyValidation->ensureRencanaAksiCanBeCreated($pk);

                return RencanaAksi::create([...$data, 'status' => 'draft']);
            }

            $snapshotService->ensureReady($pk);
            $rencanaAksi = RencanaAksi::create([
                ...$data,
                'opd_id' => $pk->opd_id,
                'periode_tahun_id' => $pk->periode_tahun_id,
                'tahun' => $pk->tahun,
                'status' => 'draft',
                'format_version' => 2,
            ]);
            $snapshotService->populate($rencanaAksi, $pk);

            return $rencanaAksi;
        });

        return redirect()->route('rencana-aksi.show', $rencanaAksi)->with('success', 'Rencana Aksi berhasil ditambahkan.');
    }

    public function show(Request $request, RencanaAksi $rencanaAksi): Response
    {
        $this->authorize('view', $rencanaAksi);

        $rencanaAksi->load([
            'opd:id,kode,nama,singkatan',
            'periodeTahun:id,tahun,nama',
            'perjanjianKinerja:id,judul,tahun,status',
            'renstraOpd:id,judul,tahun_awal,tahun_akhir',
            'dpaOpd:id,judul,jenis_anggaran,nomor_dpa',
            'items.perjanjianKinerjaItem:id,kode,indikator',
            'items.opdProgram:id,kode,nama',
            'items.opdKegiatan:id,kode,nama',
            'items.opdSubKegiatan:id,kode,nama',
            'items.targetTriwulan:id,rencana_aksi_item_id,triwulan,target,target_text',
        ]);

        return Inertia::render('Kinerja/RencanaAksi/Show', [
            'item' => $this->serializeRencanaAksi($rencanaAksi),
            'nodeOptions' => (int) $rencanaAksi->format_version < 2 && $request->user()->can('update', $rencanaAksi) ? $this->nodeOptionsForOpd((int) $rencanaAksi->opd_id) : [],
            'perjanjianKinerjaItemOptions' => (int) $rencanaAksi->format_version < 2 && $request->user()->can('update', $rencanaAksi) ? $this->perjanjianKinerjaItemOptions((int) $rencanaAksi->opd_id) : [],
            'workflow' => $this->workflowData($rencanaAksi, 'rencana_aksi'),
            'can' => [
                'manage' => $request->user()->can('update', $rencanaAksi),
                'review' => $this->canReviewWorkflow($request->user()),
                'lock' => $this->canLockWorkflow($request->user()),
                'export' => $this->canExportRencanaAksi($request->user(), $rencanaAksi),
            ],
        ]);
    }

    public function export(Request $request, RencanaAksi $rencanaAksi): RedirectResponse
    {
        $this->authorize('view', $rencanaAksi);
        abort_unless($this->canExportRencanaAksi($request->user(), $rencanaAksi), 403);

        $data = $request->validate([
            'format' => ['required', Rule::in(['pdf', 'word'])],
        ]);

        ExportKinerjaReportDocumentJob::dispatch('rencana_aksi', $rencanaAksi->id, $request->user()->id, $data['format']);

        $formatLabel = $data['format'] === 'pdf' ? 'PDF' : 'Word';

        return back()->with('success', "Export Rencana Aksi {$formatLabel} masuk antrean. Jalankan worker queue untuk memproses dokumen.");
    }

    public function edit(Request $request, RencanaAksi $rencanaAksi): Response
    {
        $this->authorize('update', $rencanaAksi);
        $rencanaAksi->load(['opd:id,nama,singkatan', 'perjanjianKinerja:id,judul', 'renstraOpd:id,judul', 'dpaOpd:id,judul,jenis_anggaran']);

        return Inertia::render('Kinerja/RencanaAksi/Form', [
            'mode' => 'edit',
            'item' => [
                'id' => $rencanaAksi->id,
                'opd_id' => $rencanaAksi->opd_id,
                'perjanjian_kinerja_id' => $rencanaAksi->perjanjian_kinerja_id,
                'periode_tahun_id' => $rencanaAksi->periode_tahun_id,
                'tahun' => $rencanaAksi->tahun,
                'judul' => $rencanaAksi->judul,
                'status' => $rencanaAksi->status,
                'catatan' => $rencanaAksi->catatan,
                'perjanjian_kinerja_label' => $rencanaAksi->perjanjianKinerja?->judul,
                'opd_label' => $rencanaAksi->opd?->singkatan ?: $rencanaAksi->opd?->nama,
                'renstra_label' => $rencanaAksi->renstraOpd?->judul,
                'dpa_label' => $rencanaAksi->dpaOpd ? $rencanaAksi->dpaOpd->typeLabel().' - '.$rencanaAksi->dpaOpd->judul : null,
            ],
            'opdOptions' => $this->opdOptions($request->user()),
            'periodeOptions' => $this->periodeOptions(),
            'perjanjianKinerjaOptions' => [],
        ]);
    }

    public function update(UpdateRencanaAksiRequest $request, RencanaAksi $rencanaAksi): RedirectResponse
    {
        $data = $request->validated();
        $rencanaAksi->update([
            'judul' => $data['judul'],
            'catatan' => $data['catatan'] ?? null,
        ]);

        return redirect()->route('rencana-aksi.show', $rencanaAksi)->with('success', 'Rencana Aksi berhasil diperbarui.');
    }

    public function destroy(RencanaAksi $rencanaAksi): RedirectResponse
    {
        $this->authorize('delete', $rencanaAksi);

        $rencanaAksi->delete();

        return redirect()->route('rencana-aksi.index')->with('success', 'Rencana Aksi berhasil dihapus.');
    }

    private function serializeRencanaAksi(RencanaAksi $rencanaAksi): array
    {
        return [
            'id' => $rencanaAksi->id,
            'judul' => $rencanaAksi->judul,
            'tahun' => $rencanaAksi->tahun,
            'status' => $rencanaAksi->status,
            'catatan' => $rencanaAksi->catatan,
            'format_version' => $rencanaAksi->format_version,
            'snapshot_dibuat_pada' => $rencanaAksi->snapshot_dibuat_pada?->toIso8601String(),
            'opd' => $rencanaAksi->opd,
            'periode_tahun' => $rencanaAksi->periodeTahun,
            'perjanjian_kinerja' => $rencanaAksi->perjanjianKinerja,
            'renstra_opd' => $rencanaAksi->renstraOpd,
            'dpa_opd' => $rencanaAksi->dpaOpd ? [
                'id' => $rencanaAksi->dpaOpd->id,
                'judul' => $rencanaAksi->dpaOpd->judul,
                'jenis_anggaran' => $rencanaAksi->dpaOpd->jenis_anggaran,
                'nomor_dpa' => $rencanaAksi->dpaOpd->nomor_dpa,
                'label' => $rencanaAksi->dpaOpd->typeLabel(),
            ] : null,
            'items' => $rencanaAksi->items->map(fn (RencanaAksiItem $item) => [
                'id' => $item->id,
                'perjanjian_kinerja_item_id' => $item->perjanjian_kinerja_item_id,
                'opd_program_id' => $item->opd_program_id,
                'opd_kegiatan_id' => $item->opd_kegiatan_id,
                'opd_sub_kegiatan_id' => $item->opd_sub_kegiatan_id,
                'periode_realisasi' => $item->periode_realisasi,
                'triwulan' => $item->triwulan,
                'bulan' => $item->bulan,
                'aksi' => $item->aksi,
                'indikator' => $item->indikator,
                'target' => $item->target,
                'target_text' => $item->target_text,
                'anggaran' => $item->anggaran,
                'penanggung_jawab' => $item->penanggung_jawab,
                'status' => $item->status,
                'urutan' => $item->urutan,
                'level' => $item->level,
                'kode_snapshot' => $item->kode_snapshot,
                'uraian_snapshot' => $item->uraian_snapshot,
                'formula_snapshot' => $item->formula_snapshot,
                'formula' => $item->formula,
                'satuan_snapshot' => $item->satuan_snapshot,
                'tipe_perhitungan_snapshot' => $item->tipe_perhitungan_snapshot,
                'is_snapshot' => $item->is_snapshot,
                'target_triwulan' => $item->targetTriwulan->map(fn ($target) => [
                    'triwulan' => $target->triwulan,
                    'target' => $target->target,
                    'target_text' => $target->target_text,
                ])->values(),
                'perjanjian_kinerja_item' => $item->perjanjianKinerjaItem,
                'opd_program' => $item->opdProgram,
                'opd_kegiatan' => $item->opdKegiatan,
                'opd_sub_kegiatan' => $item->opdSubKegiatan,
            ]),
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function rencanaAksiSourceOptions(Request $request): array
    {
        $user = $request->user();

        return PerjanjianKinerja::query()
            ->with(['opd:id,nama,singkatan', 'renstraOpd:id,judul', 'dpaOpd:id,judul,jenis_anggaran'])
            ->when($this->shouldLimitToUserOpd($user), fn (Builder $query) => $query->where('opd_id', $user->opd_id))
            ->where('level_pk', 'kepala_opd')
            ->where('tipe_pk', 'cascading')
            ->whereIn('status', ['approved', 'locked'])
            ->whereHas('items')
            ->whereDoesntHave('rencanaAksi')
            ->orderByDesc('tahun')->orderByDesc('id')
            ->get()
            ->map(function (PerjanjianKinerja $pk): array {
                return [
                    'id' => $pk->id,
                    'opd_id' => $pk->opd_id,
                    'periode_tahun_id' => $pk->periode_tahun_id,
                    'tahun' => $pk->tahun,
                    'label' => "{$pk->tahun} - {$pk->judul}",
                    'opd_label' => $pk->opd?->singkatan ?: $pk->opd?->nama,
                    'renstra_label' => $pk->renstraOpd?->judul,
                    'dpa_label' => $pk->dpaOpd ? $pk->dpaOpd->typeLabel().' - '.$pk->dpaOpd->judul : null,
                    'readiness' => null,
                ];
            })->values()->all();
    }

    private function workflowData(RencanaAksi $rencanaAksi, string $module): ?array
    {
        $workflow = WorkflowSubmission::query()
            ->with(['histories.actor:id,name', 'submittedBy:id,name', 'currentReviewer:id,name'])
            ->where('related_table', $rencanaAksi->getTable())
            ->where('related_id', $rencanaAksi->id)
            ->where('module', $module)
            ->first();

        return $workflow?->toArray();
    }

    private function assertPerjanjianKinerjaBelongsToOpd(mixed $perjanjianKinerjaId, int $opdId): void
    {
        if (! $perjanjianKinerjaId) {
            return;
        }

        if (! PerjanjianKinerja::query()->whereKey($perjanjianKinerjaId)->where('opd_id', $opdId)->exists()) {
            throw ValidationException::withMessages(['perjanjian_kinerja_id' => 'Perjanjian Kinerja tidak sesuai OPD Rencana Aksi.']);
        }
    }

    private function findPerjanjianKinerja(mixed $perjanjianKinerjaId): ?PerjanjianKinerja
    {
        if (! $perjanjianKinerjaId) {
            return null;
        }

        return PerjanjianKinerja::query()->find($perjanjianKinerjaId);
    }

    private function canReviewWorkflow(User $user): bool
    {
        return $user->hasAnyRole(['super_admin', 'admin_kabupaten_bagian_organisasi', 'admin_kabupaten_inspektorat'])
            || $user->hasPermission('verify_realisasi')
            || $user->hasPermission('lock_period');
    }

    private function canLockWorkflow(User $user): bool
    {
        return $user->isSuperAdmin() || $user->hasPermission('lock_period');
    }

    private function canExportRencanaAksi(User $user, RencanaAksi $rencanaAksi): bool
    {
        return $user->can('update', $rencanaAksi)
            || ($user->hasRole('admin_opd') && (int) $user->opd_id === (int) $rencanaAksi->opd_id)
            || ($user->can('view', $rencanaAksi) && $user->hasPermission('export_laporan'));
    }
}
