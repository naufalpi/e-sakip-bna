<?php

namespace App\Http\Controllers\Penganggaran;

use App\Http\Controllers\Controller;
use App\Http\Requests\Penganggaran\StoreDpaOpdRequest;
use App\Http\Requests\Penganggaran\UpdateDpaOpdRequest;
use App\Models\DpaOpd;
use App\Models\DpaOpdItem;
use App\Models\Opd;
use App\Models\RkaOpd;
use App\Models\User;
use App\Services\Penganggaran\DpaCreationService;
use App\Services\Penganggaran\DpaReadinessService;
use App\Services\Workflow\WorkflowDataService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Inertia\Response;

class DpaOpdController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', DpaOpd::class);
        $user = $request->user();
        $filters = $request->only(['search', 'status', 'opd_id', 'tahun', 'jenis_anggaran']);
        $baseQuery = fn () => DpaOpd::query()
            ->when($this->shouldLimitToUserOpd($user), fn (Builder $query) => $query->where('opd_id', $user->opd_id))
            ->when($user->hasOpdUnitScope(), fn (Builder $query) => $query->where('opd_unit_id', $user->opd_unit_id));

        $items = $baseQuery()
            ->with(['opd:id,kode,nama,singkatan', 'opdUnit:id,kode,nama', 'rkaOpd:id,judul,jenis_anggaran,status'])
            ->withCount('items')
            ->withSum('items as total_pagu_dpa', 'pagu_dpa')
            ->when($filters['search'] ?? null, function (Builder $query, string $search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query->where('judul', 'ilike', "%{$search}%")
                        ->orWhere('nomor_dpa', 'ilike', "%{$search}%")
                        ->orWhereHas('opd', fn (Builder $query) => $query->where('nama', 'ilike', "%{$search}%")->orWhere('singkatan', 'ilike', "%{$search}%"));
                });
            })
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($filters['opd_id'] ?? null, fn (Builder $query, string $opdId) => $query->where('opd_id', $opdId))
            ->when($filters['tahun'] ?? null, fn (Builder $query, string $tahun) => $query->where('tahun', $tahun))
            ->when($filters['jenis_anggaran'] ?? null, fn (Builder $query, string $jenis) => $query->where('jenis_anggaran', $jenis))
            ->orderByDesc('tahun')->orderByDesc('id')
            ->paginate(10)->withQueryString()
            ->through(fn (DpaOpd $dpa) => [
                ...$this->serializeDpa($dpa),
                'items_count' => $dpa->items_count,
                'total_pagu_dpa' => $dpa->total_pagu_dpa,
                'can_update' => $user->can('update', $dpa) || $user->can('verifyBudget', $dpa),
                'can_delete' => $user->can('delete', $dpa),
            ]);

        return Inertia::render('DpaOpd/Index', [
            'items' => $items,
            'filters' => $filters,
            'opdOptions' => $this->opdOptions($user),
            'stats' => [
                'documents' => $baseQuery()->count(),
                'draft' => $baseQuery()->whereIn('status', ['draft', 'revision', 'rejected'])->count(),
                'process' => $baseQuery()->whereIn('status', ['submitted', 'verified'])->count(),
                'official' => $baseQuery()->whereIn('status', ['approved', 'locked'])->count(),
            ],
            'can' => ['manage' => $user->can('create', DpaOpd::class)],
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorize('create', DpaOpd::class);

        return Inertia::render('DpaOpd/Form', [
            'mode' => 'create',
            'dpa' => null,
            'rkaOptions' => $this->eligibleRkaOptions($request->user()),
            'canVerify' => false,
        ]);
    }

    public function store(StoreDpaOpdRequest $request, DpaCreationService $service): RedirectResponse
    {
        $rka = RkaOpd::query()->findOrFail($request->integer('rka_opd_id'));
        $dpa = $service->createFromRka($rka, $request->safe()->except('rka_opd_id'));

        return redirect()->route('dpa-opd.show', $dpa)
            ->with('success', 'DPA berhasil dibuat dan rincian anggaran telah disalin dari RKA resmi.');
    }

    public function show(Request $request, DpaOpd $dpaOpd, WorkflowDataService $workflowDataService, DpaReadinessService $readinessService): Response
    {
        $this->authorize('view', $dpaOpd);
        $dpaOpd->load([
            'opd:id,kode,nama,singkatan', 'opdUnit:id,kode,nama', 'periodeTahun:id,tahun,nama',
            'rkaOpd:id,judul,nomor_dokumen,tahun,jenis_anggaran,status',
            'renjaOpd:id,judul,nomor_dokumen,tahun,jenis_versi,status',
            'rkpd:id,judul,tahun,jenis_versi,status',
        ]);
        $items = $dpaOpd->items()->with('cashPlans')->orderBy('kode_program')->orderBy('kode_kegiatan')->orderBy('kode_sub_kegiatan')->orderBy('urutan')->get();
        $serializedItems = $items->map(fn (DpaOpdItem $item) => $this->serializeItem($item))->values()->all();
        $workflow = $workflowDataService->forModel($dpaOpd, 'dpa_opd');
        $user = $request->user();

        $monthlyTotals = collect(range(1, 12))->mapWithKeys(fn (int $month) => [
            $month => $items->sum(fn (DpaOpdItem $item) => (float) ($item->cashPlans->firstWhere('bulan', $month)?->jumlah ?? 0)),
        ])->all();

        return Inertia::render('DpaOpd/Show', [
            'dpa' => $this->serializeDpa($dpaOpd),
            'items' => $serializedItems,
            'summary' => [
                'items_count' => count($serializedItems),
                'pagu_rka' => $items->sum(fn (DpaOpdItem $item) => (float) $item->pagu_rka),
                'pagu_dpa' => $items->sum(fn (DpaOpdItem $item) => (float) $item->pagu_dpa),
                'monthly_totals' => $monthlyTotals,
            ],
            'submissionReadiness' => $readinessService->inspect($dpaOpd),
            'approvalReadiness' => $readinessService->inspect($dpaOpd, true),
            'workflow' => $workflow,
            'can' => [
                'manage' => $user->can('update', $dpaOpd),
                'verifyBudget' => $user->can('verifyBudget', $dpaOpd),
                'review' => $user->isSuperAdmin() || $user->hasPermission('dpa.verify'),
                'lock' => $user->isSuperAdmin() || $user->hasPermission('lock_period'),
                'unlock' => $user->isSuperAdmin(),
                'withdraw' => $workflow && (int) ($workflow['submitted_by'] ?? 0) === (int) $user->id,
            ],
        ]);
    }

    public function edit(Request $request, DpaOpd $dpaOpd): Response
    {
        abort_unless($request->user()->can('update', $dpaOpd) || $request->user()->can('verifyBudget', $dpaOpd), 403);
        $dpaOpd->load(['opd:id,kode,nama,singkatan', 'opdUnit:id,kode,nama', 'rkaOpd:id,judul,tahun,jenis_anggaran,status']);

        return Inertia::render('DpaOpd/Form', [
            'mode' => 'edit',
            'dpa' => $this->serializeDpa($dpaOpd),
            'rkaOptions' => [],
            'canVerify' => $request->user()->can('verifyBudget', $dpaOpd),
        ]);
    }

    public function update(UpdateDpaOpdRequest $request, DpaOpd $dpaOpd): RedirectResponse
    {
        $payload = $request->validated();
        if (! $request->user()->can('update', $dpaOpd)) {
            $payload = Arr::only($payload, [
                'nomor_dpa', 'tanggal_pengesahan', 'nama_ppkd', 'nip_ppkd',
                'nama_sekretaris_daerah', 'nip_sekretaris_daerah', 'catatan_verifikasi',
            ]);
        } elseif (! $request->user()->isSuperAdmin()) {
            $payload = Arr::except($payload, [
                'nomor_dpa', 'tanggal_pengesahan', 'nama_ppkd', 'nip_ppkd',
                'nama_sekretaris_daerah', 'nip_sekretaris_daerah', 'catatan_verifikasi',
            ]);
        }
        $dpaOpd->update($payload);

        return redirect()->route('dpa-opd.show', $dpaOpd)->with('success', 'Informasi DPA berhasil diperbarui.');
    }

    public function destroy(DpaOpd $dpaOpd): RedirectResponse
    {
        $this->authorize('delete', $dpaOpd);
        $dpaOpd->delete();

        return redirect()->route('dpa-opd.index')->with('success', 'DPA berhasil dihapus.');
    }

    private function shouldLimitToUserOpd(User $user): bool
    {
        return $user->hasRole('admin_opd')
            && ! $user->hasAnyRole(['super_admin', 'admin_kabupaten_bagian_organisasi', 'admin_kabupaten_bapperida', 'admin_kabupaten_bpkad', 'admin_kabupaten_inspektorat']);
    }

    /** @return array<int, array<string, mixed>> */
    private function eligibleRkaOptions(User $user): array
    {
        return RkaOpd::query()
            ->with(['opd:id,kode,nama,singkatan', 'opdUnit:id,kode,nama'])
            ->withCount('items')->withSum('items as total_pagu', 'pagu_hasil_verifikasi')
            ->whereIn('status', ['approved', 'locked'])->whereDoesntHave('dpaDocuments')
            ->when($this->shouldLimitToUserOpd($user), fn (Builder $query) => $query->where('opd_id', $user->opd_id))
            ->when($user->hasOpdUnitScope(), fn (Builder $query) => $query->where('opd_unit_id', $user->opd_unit_id))
            ->orderByDesc('tahun')->orderBy('opd_id')->get()
            ->map(function (RkaOpd $rka): array {
                $opd = $rka->opd?->singkatan ?: $rka->opd?->nama ?: 'OPD';
                $type = $rka->jenis_anggaran === 'perubahan' ? 'RKA Perubahan APBD' : 'RKA APBD';
                $prefix = $rka->jenis_anggaran === 'perubahan' ? 'DPPA' : 'DPA';

                return [
                    'id' => $rka->id, 'label' => "{$opd} · {$type} {$rka->tahun}", 'opd' => $rka->opd,
                    'opd_unit' => $rka->opdUnit, 'tahun' => $rka->tahun, 'jenis_anggaran' => $rka->jenis_anggaran,
                    'type_label' => $type, 'items_count' => $rka->items_count, 'total_pagu' => $rka->total_pagu,
                    'default_title' => str("{$prefix} {$opd} TAHUN ANGGARAN {$rka->tahun}")->upper()->toString(),
                ];
            })->all();
    }

    /** @return array<int, array{id: int, label: string}> */
    private function opdOptions(User $user): array
    {
        return Opd::query()->where('status', 'active')
            ->when($this->shouldLimitToUserOpd($user), fn (Builder $query) => $query->whereKey($user->opd_id ?? 0))
            ->orderBy('nama')->get(['id', 'nama', 'singkatan'])
            ->map(fn ($opd) => ['id' => $opd->id, 'label' => $opd->singkatan ? "{$opd->singkatan} - {$opd->nama}" : $opd->nama])->all();
    }

    /** @return array<string, mixed> */
    private function serializeDpa(DpaOpd $dpa): array
    {
        return [
            'id' => $dpa->id, 'rka_opd_id' => $dpa->rka_opd_id, 'tahun' => $dpa->tahun,
            'jenis_anggaran' => $dpa->jenis_anggaran, 'type_label' => $dpa->typeLabel(), 'judul' => $dpa->judul,
            'nomor_dpa' => $dpa->nomor_dpa, 'tanggal_pengesahan' => $dpa->tanggal_pengesahan?->toDateString(),
            'nomor_perda_apbd' => $dpa->nomor_perda_apbd, 'tanggal_perda_apbd' => $dpa->tanggal_perda_apbd?->toDateString(),
            'nomor_perkada_penjabaran' => $dpa->nomor_perkada_penjabaran,
            'tanggal_perkada_penjabaran' => $dpa->tanggal_perkada_penjabaran?->toDateString(),
            'nama_pengguna_anggaran' => $dpa->nama_pengguna_anggaran, 'nip_pengguna_anggaran' => $dpa->nip_pengguna_anggaran,
            'nama_ppkd' => $dpa->nama_ppkd, 'nip_ppkd' => $dpa->nip_ppkd,
            'nama_sekretaris_daerah' => $dpa->nama_sekretaris_daerah, 'nip_sekretaris_daerah' => $dpa->nip_sekretaris_daerah,
            'status' => $dpa->status, 'catatan' => $dpa->catatan, 'catatan_verifikasi' => $dpa->catatan_verifikasi,
            'opd' => $dpa->opd, 'opd_unit' => $dpa->opdUnit, 'rka' => $dpa->rkaOpd, 'renja' => $dpa->renjaOpd, 'rkpd' => $dpa->rkpd,
        ];
    }

    /** @return array<string, mixed> */
    private function serializeItem(DpaOpdItem $item): array
    {
        return [
            ...Arr::only($item->toArray(), [
                'id', 'rka_opd_item_id', 'kode_urusan', 'nama_urusan', 'kode_bidang', 'nama_bidang',
                'kode_program', 'nama_program', 'kode_kegiatan', 'nama_kegiatan', 'kode_sub_kegiatan',
                'nama_sub_kegiatan', 'tolok_ukur_kinerja', 'target_kinerja', 'satuan_kinerja', 'sumber_pendanaan',
                'lokasi', 'kelompok_sasaran', 'bulan_mulai', 'bulan_selesai', 'jenis_belanja',
                'pagu_rka', 'pagu_dpa', 'alasan_penyesuaian', 'catatan', 'urutan',
            ]),
            'cash_plan' => $item->cashPlans->map(fn ($plan) => ['bulan' => $plan->bulan, 'jumlah' => $plan->jumlah])->values()->all(),
        ];
    }
}
