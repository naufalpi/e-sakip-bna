<?php

namespace App\Http\Controllers\Evaluasi;

use App\Http\Controllers\Controller;
use App\Http\Requests\Evaluasi\StoreEvaluasiSakipRequest;
use App\Http\Requests\Evaluasi\UpdateEvaluasiSakipRequest;
use App\Models\EvaluasiSakip;
use App\Models\EvaluasiSakipItem;
use App\Models\KriteriaEvaluasi;
use App\Models\Opd;
use App\Models\PeriodeTahun;
use App\Models\RekomendasiEvaluasi;
use App\Models\TindakLanjutRekomendasi;
use App\Models\User;
use App\Services\Workflow\WorkflowDataService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class EvaluasiSakipController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', EvaluasiSakip::class);

        $filters = $request->only(['search', 'status', 'opd_id', 'periode_tahun_id', 'tahun', 'predikat']);
        $user = $request->user();

        $evaluasi = EvaluasiSakip::query()
            ->with(['opd:id,kode,nama,singkatan', 'periodeTahun:id,tahun,nama', 'evaluator:id,name'])
            ->withCount(['items', 'rekomendasi'])
            ->when($this->shouldLimitToUserOpd($user), fn (Builder $query) => $query->where('opd_id', $user->opd_id))
            ->when($filters['search'] ?? null, function (Builder $query, string $search) {
                $query->whereHas('opd', fn (Builder $query) => $query->where('nama', 'ilike', "%{$search}%")->orWhere('singkatan', 'ilike', "%{$search}%"));
            })
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($filters['opd_id'] ?? null, fn (Builder $query, string $opdId) => $query->where('opd_id', $opdId))
            ->when($filters['periode_tahun_id'] ?? null, fn (Builder $query, string $periodeId) => $query->where('periode_tahun_id', $periodeId))
            ->when($filters['tahun'] ?? null, fn (Builder $query, string $tahun) => $query->where('tahun', $tahun))
            ->when($filters['predikat'] ?? null, fn (Builder $query, string $predikat) => $query->where('predikat', $predikat))
            ->orderByDesc('tahun')
            ->latest('id')
            ->paginate(10)
            ->withQueryString()
            ->through(fn (EvaluasiSakip $evaluasi) => [
                'id' => $evaluasi->id,
                'tahun' => $evaluasi->tahun,
                'tanggal_evaluasi' => $evaluasi->tanggal_evaluasi?->toDateString(),
                'status' => $evaluasi->status,
                'nilai_akhir' => $evaluasi->nilai_akhir,
                'predikat' => $evaluasi->predikat,
                'items_count' => $evaluasi->items_count,
                'rekomendasi_count' => $evaluasi->rekomendasi_count,
                'opd' => $evaluasi->opd,
                'periode_tahun' => $evaluasi->periodeTahun,
                'evaluator' => $evaluasi->evaluator,
            ]);

        return Inertia::render('EvaluasiSakip/Index', [
            'evaluasi' => $evaluasi,
            'filters' => $filters,
            'opdOptions' => $this->opdOptions($user),
            'periodeOptions' => $this->periodeOptions(),
            'statusOptions' => $this->statusOptions(),
            'predikatOptions' => ['AA', 'A', 'BB', 'B', 'CC', 'C', 'D'],
            'can' => [
                'manage' => $user->can('create', EvaluasiSakip::class),
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorize('create', EvaluasiSakip::class);

        return Inertia::render('EvaluasiSakip/Form', [
            'mode' => 'create',
            'evaluasi' => null,
            'opdOptions' => $this->opdOptions($request->user()),
            'periodeOptions' => $this->periodeOptions(),
            'statusOptions' => $this->statusOptions(),
        ]);
    }

    public function store(StoreEvaluasiSakipRequest $request): RedirectResponse
    {
        $evaluasi = EvaluasiSakip::create([
            ...$request->validated(),
            'evaluator_id' => $request->user()->id,
        ]);

        return redirect()->route('evaluasi-sakip.show', $evaluasi)->with('success', 'Evaluasi SAKIP berhasil dibuat.');
    }

    public function show(Request $request, EvaluasiSakip $evaluasiSakip, WorkflowDataService $workflowDataService): Response
    {
        $this->authorize('view', $evaluasiSakip);

        $evaluasiSakip->load([
            'opd:id,kode,nama,singkatan',
            'periodeTahun:id,tahun,nama',
            'evaluator:id,name',
            'items.kriteria.subKomponen.komponen',
            'lhe.disusunOleh:id,name',
            'rekomendasi.item.kriteria:id,kode,nama',
            'rekomendasi.createdBy:id,name',
            'rekomendasi.tindakLanjut.createdBy:id,name',
            'rekomendasi.tindakLanjut.diverifikasiOleh:id,name',
        ]);

        return Inertia::render('EvaluasiSakip/Show', [
            'evaluasi' => $this->serializeEvaluasi($evaluasiSakip),
            'kriteriaOptions' => $request->user()->can('update', $evaluasiSakip) ? $this->kriteriaOptions() : [],
            'itemOptions' => $request->user()->can('update', $evaluasiSakip) ? $this->itemOptions($evaluasiSakip) : [],
            'statusOptions' => $this->statusOptions(),
            'can' => [
                'manage' => $request->user()->can('update', $evaluasiSakip),
                'tindak_lanjut' => $request->user()->can('tindakLanjut', $evaluasiSakip),
                'verify_tindak_lanjut' => $this->canVerifyTindakLanjut($request->user()),
                'review' => $this->canVerifyTindakLanjut($request->user()),
            ],
            'workflow' => $workflowDataService->forModel($evaluasiSakip, 'evaluasi_sakip'),
        ]);
    }

    public function edit(Request $request, EvaluasiSakip $evaluasiSakip): Response
    {
        $this->authorize('update', $evaluasiSakip);

        return Inertia::render('EvaluasiSakip/Form', [
            'mode' => 'edit',
            'evaluasi' => [
                'id' => $evaluasiSakip->id,
                'opd_id' => $evaluasiSakip->opd_id,
                'periode_tahun_id' => $evaluasiSakip->periode_tahun_id,
                'tahun' => $evaluasiSakip->tahun,
                'tanggal_evaluasi' => $evaluasiSakip->tanggal_evaluasi?->toDateString(),
                'status' => $evaluasiSakip->status,
                'catatan_umum' => $evaluasiSakip->catatan_umum,
            ],
            'opdOptions' => $this->opdOptions($request->user()),
            'periodeOptions' => $this->periodeOptions(),
            'statusOptions' => $this->statusOptions(),
        ]);
    }

    public function update(UpdateEvaluasiSakipRequest $request, EvaluasiSakip $evaluasiSakip): RedirectResponse
    {
        $evaluasiSakip->update($request->validated());

        return redirect()->route('evaluasi-sakip.show', $evaluasiSakip)->with('success', 'Evaluasi SAKIP berhasil diperbarui.');
    }

    public function destroy(EvaluasiSakip $evaluasiSakip): RedirectResponse
    {
        $this->authorize('delete', $evaluasiSakip);

        $evaluasiSakip->delete();

        return redirect()->route('evaluasi-sakip.index')->with('success', 'Evaluasi SAKIP berhasil dihapus.');
    }

    private function shouldLimitToUserOpd(User $user): bool
    {
        return $user->hasRole('admin_opd')
            && ! $user->hasAnyRole(['super_admin', 'admin_kabupaten_inspektorat', 'admin_kabupaten_bagian_organisasi']);
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
     * @return array<int, array{value: string, label: string}>
     */
    private function statusOptions(): array
    {
        return [
            ['value' => 'draft', 'label' => 'Draft'],
            ['value' => 'submitted', 'label' => 'Diajukan'],
            ['value' => 'revision', 'label' => 'Revisi'],
            ['value' => 'verified', 'label' => 'Terverifikasi'],
            ['value' => 'approved', 'label' => 'Disetujui'],
            ['value' => 'rejected', 'label' => 'Ditolak'],
            ['value' => 'locked', 'label' => 'Terkunci'],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function kriteriaOptions(): array
    {
        return KriteriaEvaluasi::query()
            ->with('subKomponen.komponen')
            ->where('status', 'active')
            ->whereHas('subKomponen', fn (Builder $query) => $query->where('status', 'active'))
            ->whereHas('subKomponen.komponen', fn (Builder $query) => $query->where('status', 'active'))
            ->orderBy('urutan')
            ->get()
            ->sortBy(fn (KriteriaEvaluasi $kriteria) => sprintf('%03d.%03d.%03d', $kriteria->subKomponen->komponen->urutan, $kriteria->subKomponen->urutan, $kriteria->urutan))
            ->map(fn (KriteriaEvaluasi $kriteria) => [
                'id' => $kriteria->id,
                'label' => "{$kriteria->subKomponen->komponen->kode}.{$kriteria->subKomponen->kode}.{$kriteria->kode} - ".str($kriteria->nama)->limit(120)->toString(),
                'bobot' => $kriteria->bobot,
                'nilai_maksimal' => $kriteria->nilai_maksimal,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function itemOptions(EvaluasiSakip $evaluasiSakip): array
    {
        return $evaluasiSakip->items
            ->map(fn (EvaluasiSakipItem $item) => [
                'id' => $item->id,
                'label' => $item->kriteria ? "{$item->kriteria->kode} - ".str($item->kriteria->nama)->limit(100)->toString() : "Item #{$item->id}",
            ])
            ->values()
            ->all();
    }

    private function serializeEvaluasi(EvaluasiSakip $evaluasi): array
    {
        return [
            'id' => $evaluasi->id,
            'tahun' => $evaluasi->tahun,
            'tanggal_evaluasi' => $evaluasi->tanggal_evaluasi?->toDateString(),
            'status' => $evaluasi->status,
            'nilai_akhir' => $evaluasi->nilai_akhir,
            'predikat' => $evaluasi->predikat,
            'catatan_umum' => $evaluasi->catatan_umum,
            'opd' => $evaluasi->opd,
            'periode_tahun' => $evaluasi->periodeTahun,
            'evaluator' => $evaluasi->evaluator,
            'items' => $evaluasi->items->map(fn (EvaluasiSakipItem $item) => [
                'id' => $item->id,
                'nilai' => $item->nilai,
                'skor' => $item->skor,
                'catatan' => $item->catatan,
                'rekomendasi_text' => $item->rekomendasi_text,
                'kriteria' => $item->kriteria ? [
                    'id' => $item->kriteria->id,
                    'kode' => $item->kriteria->kode,
                    'nama' => $item->kriteria->nama,
                    'bobot' => $item->kriteria->bobot,
                    'nilai_maksimal' => $item->kriteria->nilai_maksimal,
                    'sub_komponen' => $item->kriteria->subKomponen ? [
                        'kode' => $item->kriteria->subKomponen->kode,
                        'nama' => $item->kriteria->subKomponen->nama,
                        'komponen' => $item->kriteria->subKomponen->komponen ? [
                            'kode' => $item->kriteria->subKomponen->komponen->kode,
                            'nama' => $item->kriteria->subKomponen->komponen->nama,
                        ] : null,
                    ] : null,
                ] : null,
            ]),
            'lhe' => $evaluasi->lhe ? [
                'id' => $evaluasi->lhe->id,
                'nomor_lhe' => $evaluasi->lhe->nomor_lhe,
                'tanggal_lhe' => $evaluasi->lhe->tanggal_lhe?->toDateString(),
                'ringkasan' => $evaluasi->lhe->ringkasan,
                'nilai_akhir' => $evaluasi->lhe->nilai_akhir,
                'predikat' => $evaluasi->lhe->predikat,
                'status' => $evaluasi->lhe->status,
                'disusun_oleh' => $evaluasi->lhe->disusunOleh,
            ] : null,
            'rekomendasi' => $evaluasi->rekomendasi->map(fn (RekomendasiEvaluasi $rekomendasi) => [
                'id' => $rekomendasi->id,
                'nomor' => $rekomendasi->nomor,
                'rekomendasi' => $rekomendasi->rekomendasi,
                'prioritas' => $rekomendasi->prioritas,
                'status_tindak_lanjut' => $rekomendasi->status_tindak_lanjut,
                'target_tanggal' => $rekomendasi->target_tanggal?->toDateString(),
                'item' => $rekomendasi->item ? [
                    'id' => $rekomendasi->item->id,
                    'kriteria' => $rekomendasi->item->kriteria ? [
                        'kode' => $rekomendasi->item->kriteria->kode,
                        'nama' => $rekomendasi->item->kriteria->nama,
                    ] : null,
                ] : null,
                'tindak_lanjut' => $rekomendasi->tindakLanjut->map(fn (TindakLanjutRekomendasi $tl) => [
                    'id' => $tl->id,
                    'uraian_tindak_lanjut' => $tl->uraian_tindak_lanjut,
                    'status_tindak_lanjut' => $tl->status_tindak_lanjut,
                    'tanggal_tindak_lanjut' => $tl->tanggal_tindak_lanjut?->toDateString(),
                    'catatan_opd' => $tl->catatan_opd,
                    'catatan_verifikator' => $tl->catatan_verifikator,
                    'created_by' => $tl->createdBy,
                    'diverifikasi_oleh' => $tl->diverifikasiOleh,
                    'diverifikasi_at' => $tl->diverifikasi_at?->toDateTimeString(),
                ]),
            ]),
        ];
    }

    private function canVerifyTindakLanjut(User $user): bool
    {
        return $user->hasAnyRole(['super_admin', 'admin_kabupaten_inspektorat'])
            && $user->hasAnyPermission(['evaluasi.manage', 'manage_evaluasi']);
    }
}
