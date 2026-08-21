<?php

namespace App\Http\Controllers\Penganggaran;

use App\Http\Controllers\Controller;
use App\Http\Requests\Penganggaran\StoreRkaOpdRequest;
use App\Http\Requests\Penganggaran\UpdateRkaOpdRequest;
use App\Models\Opd;
use App\Models\RenjaOpd;
use App\Models\RkaOpd;
use App\Models\RkaOpdItem;
use App\Models\User;
use App\Services\Penganggaran\RkaCreationService;
use App\Services\Penganggaran\RkaReadinessService;
use App\Services\Workflow\WorkflowDataService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Inertia\Response;

class RkaOpdController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', RkaOpd::class);

        $user = $request->user();
        $filters = $request->only(['search', 'status', 'opd_id', 'tahun', 'jenis_anggaran']);
        $baseQuery = fn () => RkaOpd::query()
            ->when($this->shouldLimitToUserOpd($user), fn (Builder $query) => $query->where('opd_id', $user->opd_id))
            ->when($user->hasOpdUnitScope(), fn (Builder $query) => $query->where('opd_unit_id', $user->opd_unit_id));

        $items = $baseQuery()
            ->with([
                'opd:id,kode,nama,singkatan',
                'opdUnit:id,kode,nama',
                'renjaOpd:id,judul,tahun,jenis_versi,status',
            ])
            ->withCount('items')
            ->withSum('items as total_pagu_usulan', 'pagu_usulan')
            ->withSum('items as total_pagu_hasil_verifikasi', 'pagu_hasil_verifikasi')
            ->when($filters['search'] ?? null, function (Builder $query, string $search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query->where('judul', 'ilike', "%{$search}%")
                        ->orWhere('nomor_dokumen', 'ilike', "%{$search}%")
                        ->orWhereHas('opd', fn (Builder $query) => $query
                            ->where('nama', 'ilike', "%{$search}%")
                            ->orWhere('singkatan', 'ilike', "%{$search}%"));
                });
            })
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($filters['opd_id'] ?? null, fn (Builder $query, string $opdId) => $query->where('opd_id', $opdId))
            ->when($filters['tahun'] ?? null, fn (Builder $query, string $tahun) => $query->where('tahun', $tahun))
            ->when($filters['jenis_anggaran'] ?? null, fn (Builder $query, string $jenis) => $query->where('jenis_anggaran', $jenis))
            ->orderByDesc('tahun')
            ->orderByDesc('id')
            ->paginate(10)
            ->withQueryString()
            ->through(fn (RkaOpd $rka) => $this->serializeListRow($rka, $user));

        return Inertia::render('RkaOpd/Index', [
            'items' => $items,
            'filters' => $filters,
            'opdOptions' => $this->opdOptions($user),
            'stats' => [
                'documents' => $baseQuery()->count(),
                'draft' => $baseQuery()->whereIn('status', ['draft', 'revision', 'rejected'])->count(),
                'review' => $baseQuery()->whereIn('status', ['submitted', 'verified'])->count(),
                'approved' => $baseQuery()->whereIn('status', ['approved', 'locked'])->count(),
            ],
            'can' => ['manage' => $user->can('create', RkaOpd::class)],
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorize('create', RkaOpd::class);

        return Inertia::render('RkaOpd/Form', [
            'mode' => 'create',
            'rka' => null,
            'renjaOptions' => $this->eligibleRenjaOptions($request->user()),
            'canVerify' => false,
        ]);
    }

    public function store(StoreRkaOpdRequest $request, RkaCreationService $service): RedirectResponse
    {
        $renja = RenjaOpd::query()->findOrFail($request->integer('renja_opd_id'));
        $rka = $service->createFromRenja($renja, $request->safe()->except('renja_opd_id'));

        return redirect()->route('rka-opd.show', $rka)
            ->with('success', 'RKA berhasil dibuat dan rincian sub kegiatan telah disalin dari RENJA.');
    }

    public function show(Request $request, RkaOpd $rkaOpd, WorkflowDataService $workflowDataService, RkaReadinessService $readinessService): Response
    {
        $this->authorize('view', $rkaOpd);

        $rkaOpd->load([
            'opd:id,kode,nama,singkatan',
            'opdUnit:id,kode,nama',
            'periodeTahun:id,tahun,nama',
            'rkpd:id,judul,tahun,jenis_versi,status',
            'renjaOpd:id,judul,nomor_dokumen,tahun,jenis_versi,status',
        ]);

        $items = $rkaOpd->items()
            ->orderBy('kode_program')
            ->orderBy('kode_kegiatan')
            ->orderBy('kode_sub_kegiatan')
            ->orderBy('urutan')
            ->get()
            ->map(fn (RkaOpdItem $item) => $this->serializeItem($item))
            ->values()
            ->all();

        $workflow = $workflowDataService->forModel($rkaOpd, 'rka_opd');
        $user = $request->user();

        return Inertia::render('RkaOpd/Show', [
            'rka' => $this->serializeRka($rkaOpd),
            'items' => $items,
            'summary' => [
                'items_count' => count($items),
                'pagu_renja' => collect($items)->sum(fn (array $item) => (float) $item['pagu_renja']),
                'pagu_usulan' => collect($items)->sum(fn (array $item) => (float) $item['pagu_usulan']),
                'pagu_hasil_verifikasi' => collect($items)->sum(fn (array $item) => (float) $item['pagu_hasil_verifikasi']),
            ],
            'readiness' => $readinessService->inspect($rkaOpd),
            'workflow' => $workflow,
            'can' => [
                'manage' => $user->can('update', $rkaOpd),
                'verifyBudget' => $user->can('verifyBudget', $rkaOpd),
                'review' => $this->canReviewWorkflow($user),
                'lock' => $user->isSuperAdmin() || $user->hasPermission('lock_period'),
                'unlock' => $user->isSuperAdmin(),
                'withdraw' => $workflow && (int) ($workflow['submitted_by'] ?? 0) === (int) $user->id,
            ],
        ]);
    }

    public function edit(Request $request, RkaOpd $rkaOpd): Response
    {
        abort_unless($request->user()->can('update', $rkaOpd) || $request->user()->can('verifyBudget', $rkaOpd), 403);
        $rkaOpd->load(['opd:id,kode,nama,singkatan', 'opdUnit:id,kode,nama', 'renjaOpd:id,judul,tahun,jenis_versi,status']);

        return Inertia::render('RkaOpd/Form', [
            'mode' => 'edit',
            'rka' => $this->serializeRka($rkaOpd),
            'renjaOptions' => [],
            'canVerify' => $request->user()->can('verifyBudget', $rkaOpd),
        ]);
    }

    public function update(UpdateRkaOpdRequest $request, RkaOpd $rkaOpd): RedirectResponse
    {
        $payload = $request->validated();

        if (! $request->user()->can('update', $rkaOpd)) {
            $payload = Arr::only($payload, ['catatan_verifikasi']);
        } elseif (! $request->user()->isSuperAdmin()) {
            unset($payload['catatan_verifikasi']);
        }

        $rkaOpd->update($payload);

        return redirect()->route('rka-opd.show', $rkaOpd)->with('success', 'Informasi RKA berhasil diperbarui.');
    }

    public function destroy(RkaOpd $rkaOpd): RedirectResponse
    {
        $this->authorize('delete', $rkaOpd);
        $rkaOpd->delete();

        return redirect()->route('rka-opd.index')->with('success', 'RKA berhasil dihapus.');
    }

    private function shouldLimitToUserOpd(User $user): bool
    {
        return $user->hasRole('admin_opd')
            && ! $user->hasAnyRole(['super_admin', 'admin_kabupaten_bagian_organisasi', 'admin_kabupaten_bapperida', 'admin_kabupaten_inspektorat']);
    }

    private function canReviewWorkflow(User $user): bool
    {
        return $user->isSuperAdmin() || $user->hasPermission('rka.verify');
    }

    /** @return array<int, array<string, mixed>> */
    private function eligibleRenjaOptions(User $user): array
    {
        return RenjaOpd::query()
            ->with(['opd:id,kode,nama,singkatan', 'opdUnit:id,kode,nama', 'rkpd:id,judul,tahun,jenis_versi'])
            ->withCount('items')
            ->withSum('items as total_pagu', 'pagu_indikatif')
            ->whereIn('jenis_versi', ['ditetapkan', 'perubahan'])
            ->whereIn('status', ['approved', 'locked'])
            ->whereDoesntHave('rkaDocuments')
            ->when($this->shouldLimitToUserOpd($user), fn (Builder $query) => $query->where('opd_id', $user->opd_id))
            ->when($user->hasOpdUnitScope(), fn (Builder $query) => $query->where('opd_unit_id', $user->opd_unit_id))
            ->orderByDesc('tahun')
            ->orderBy('opd_id')
            ->get()
            ->map(function (RenjaOpd $renja): array {
                $opd = $renja->opd?->singkatan ?: $renja->opd?->nama ?: 'OPD';
                $jenis = $renja->jenis_versi === 'perubahan' ? 'RENJA Perubahan Ditetapkan' : 'RENJA Ditetapkan';

                return [
                    'id' => $renja->id,
                    'label' => "{$opd} · {$jenis} {$renja->tahun}",
                    'opd' => $renja->opd,
                    'opd_unit' => $renja->opdUnit,
                    'rkpd' => $renja->rkpd,
                    'tahun' => $renja->tahun,
                    'jenis_versi' => $renja->jenis_versi,
                    'jenis_label' => $jenis,
                    'items_count' => $renja->items_count,
                    'total_pagu' => $renja->total_pagu,
                    'default_title' => str(($renja->jenis_versi === 'perubahan' ? 'PERUBAHAN RKA ' : 'RKA ')."{$opd} TAHUN ANGGARAN {$renja->tahun}")->upper()->toString(),
                ];
            })
            ->all();
    }

    /** @return array<int, array{id: int, label: string}> */
    private function opdOptions(User $user): array
    {
        return Opd::query()
            ->where('status', 'active')
            ->when($this->shouldLimitToUserOpd($user), fn (Builder $query) => $query->whereKey($user->opd_id ?? 0))
            ->orderBy('nama')
            ->get(['id', 'nama', 'singkatan'])
            ->map(fn ($opd) => ['id' => $opd->id, 'label' => $opd->singkatan ? "{$opd->singkatan} - {$opd->nama}" : $opd->nama])
            ->all();
    }

    /** @return array<string, mixed> */
    private function serializeListRow(RkaOpd $rka, User $user): array
    {
        return [
            ...$this->serializeRka($rka),
            'items_count' => $rka->items_count,
            'total_pagu_usulan' => $rka->total_pagu_usulan,
            'total_pagu_hasil_verifikasi' => $rka->total_pagu_hasil_verifikasi,
            'can_update' => $user->can('update', $rka) || $user->can('verifyBudget', $rka),
            'can_delete' => $user->can('delete', $rka),
        ];
    }

    /** @return array<string, mixed> */
    private function serializeRka(RkaOpd $rka): array
    {
        return [
            'id' => $rka->id,
            'renja_opd_id' => $rka->renja_opd_id,
            'rkpd_id' => $rka->rkpd_id,
            'opd_id' => $rka->opd_id,
            'opd_unit_id' => $rka->opd_unit_id,
            'periode_tahun_id' => $rka->periode_tahun_id,
            'tahun' => $rka->tahun,
            'jenis_anggaran' => $rka->jenis_anggaran,
            'type_label' => $rka->typeLabel(),
            'judul' => $rka->judul,
            'nomor_dokumen' => $rka->nomor_dokumen,
            'tanggal_dokumen' => $rka->tanggal_dokumen?->toDateString(),
            'nomor_kua' => $rka->nomor_kua,
            'tanggal_kua' => $rka->tanggal_kua?->toDateString(),
            'nomor_ppas' => $rka->nomor_ppas,
            'tanggal_ppas' => $rka->tanggal_ppas?->toDateString(),
            'status' => $rka->status,
            'catatan' => $rka->catatan,
            'catatan_verifikasi' => $rka->catatan_verifikasi,
            'opd' => $rka->opd,
            'opd_unit' => $rka->opdUnit,
            'rkpd' => $rka->rkpd,
            'renja' => $rka->renjaOpd,
        ];
    }

    /** @return array<string, mixed> */
    private function serializeItem(RkaOpdItem $item): array
    {
        return Arr::only($item->toArray(), [
            'id', 'renja_opd_item_id', 'kode_urusan', 'nama_urusan', 'kode_bidang', 'nama_bidang',
            'kode_program', 'nama_program', 'kode_kegiatan', 'nama_kegiatan', 'kode_sub_kegiatan',
            'nama_sub_kegiatan', 'tolok_ukur_kinerja', 'target_kinerja', 'satuan_kinerja', 'sumber_pendanaan',
            'lokasi', 'kelompok_sasaran', 'bulan_mulai', 'bulan_selesai', 'jenis_belanja',
            'alokasi_tahun_sebelumnya', 'pagu_renja', 'pagu_usulan', 'pagu_hasil_verifikasi',
            'alokasi_tahun_berikutnya', 'alasan_penyesuaian', 'catatan', 'urutan',
        ]);
    }
}
