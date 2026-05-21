<?php

namespace App\Http\Controllers\Kinerja;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Kinerja\Concerns\BuildsKinerjaOptions;
use App\Http\Requests\Kinerja\StoreRencanaAksiRequest;
use App\Http\Requests\Kinerja\UpdateRencanaAksiRequest;
use App\Models\PerjanjianKinerja;
use App\Models\RencanaAksi;
use App\Models\RencanaAksiItem;
use App\Models\User;
use App\Models\WorkflowSubmission;
use App\Services\Perencanaan\PerencanaanHierarchyValidationService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class RencanaAksiController extends Controller
{
    use BuildsKinerjaOptions;

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', RencanaAksi::class);

        $filters = $request->only(['search', 'status', 'opd_id', 'periode_tahun_id', 'tahun']);
        $user = $request->user();

        $items = RencanaAksi::query()
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
            ->latest('id')
            ->paginate(10)
            ->withQueryString()
            ->through(fn (RencanaAksi $rencanaAksi) => [
                'id' => $rencanaAksi->id,
                'judul' => $rencanaAksi->judul,
                'tahun' => $rencanaAksi->tahun,
                'status' => $rencanaAksi->status,
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
            'perjanjianKinerjaOptions' => $this->perjanjianKinerjaOptions($request->user()),
        ]);
    }

    public function store(StoreRencanaAksiRequest $request, PerencanaanHierarchyValidationService $hierarchyValidation): RedirectResponse
    {
        $data = $request->validated();
        $this->assertPerjanjianKinerjaBelongsToOpd($data['perjanjian_kinerja_id'] ?? null, (int) $data['opd_id']);
        $hierarchyValidation->ensureRencanaAksiCanBeCreated($this->findPerjanjianKinerja($data['perjanjian_kinerja_id'] ?? null));

        $rencanaAksi = RencanaAksi::create($data);

        return redirect()->route('rencana-aksi.show', $rencanaAksi)->with('success', 'Rencana Aksi berhasil ditambahkan.');
    }

    public function show(Request $request, RencanaAksi $rencanaAksi): Response
    {
        $this->authorize('view', $rencanaAksi);

        $rencanaAksi->load([
            'opd:id,kode,nama,singkatan',
            'periodeTahun:id,tahun,nama',
            'perjanjianKinerja:id,judul,tahun,status',
            'items.perjanjianKinerjaItem:id,kode,indikator',
            'items.opdProgram:id,kode,nama',
            'items.opdKegiatan:id,kode,nama',
            'items.opdSubKegiatan:id,kode,nama',
        ]);

        return Inertia::render('Kinerja/RencanaAksi/Show', [
            'item' => $this->serializeRencanaAksi($rencanaAksi),
            'nodeOptions' => $request->user()->can('update', $rencanaAksi) ? $this->nodeOptionsForOpd((int) $rencanaAksi->opd_id) : [],
            'perjanjianKinerjaItemOptions' => $request->user()->can('update', $rencanaAksi) ? $this->perjanjianKinerjaItemOptions((int) $rencanaAksi->opd_id) : [],
            'workflow' => $this->workflowData($rencanaAksi, 'rencana_aksi'),
            'can' => [
                'manage' => $request->user()->can('update', $rencanaAksi),
                'review' => $this->canReviewWorkflow($request->user()),
                'lock' => $this->canLockWorkflow($request->user()),
            ],
        ]);
    }

    public function edit(Request $request, RencanaAksi $rencanaAksi): Response
    {
        $this->authorize('update', $rencanaAksi);

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
            ],
            'opdOptions' => $this->opdOptions($request->user()),
            'periodeOptions' => $this->periodeOptions(),
            'perjanjianKinerjaOptions' => $this->perjanjianKinerjaOptions($request->user()),
        ]);
    }

    public function update(UpdateRencanaAksiRequest $request, RencanaAksi $rencanaAksi, PerencanaanHierarchyValidationService $hierarchyValidation): RedirectResponse
    {
        $data = $request->validated();
        $this->assertPerjanjianKinerjaBelongsToOpd($data['perjanjian_kinerja_id'] ?? null, (int) $data['opd_id']);
        $hierarchyValidation->ensureRencanaAksiCanBeCreated($this->findPerjanjianKinerja($data['perjanjian_kinerja_id'] ?? null));

        $rencanaAksi->update($data);

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
            'opd' => $rencanaAksi->opd,
            'periode_tahun' => $rencanaAksi->periodeTahun,
            'perjanjian_kinerja' => $rencanaAksi->perjanjianKinerja,
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
                'perjanjian_kinerja_item' => $item->perjanjianKinerjaItem,
                'opd_program' => $item->opdProgram,
                'opd_kegiatan' => $item->opdKegiatan,
                'opd_sub_kegiatan' => $item->opdSubKegiatan,
            ]),
        ];
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
}
