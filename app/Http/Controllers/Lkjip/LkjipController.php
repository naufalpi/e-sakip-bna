<?php

namespace App\Http\Controllers\Lkjip;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Kinerja\Concerns\BuildsKinerjaOptions;
use App\Http\Requests\Lkjip\StoreLkjipRequest;
use App\Http\Requests\Lkjip\UpdateLkjipRequest;
use App\Models\EvaluasiSakip;
use App\Models\Lkjip;
use App\Models\LkjipBab;
use App\Models\PerjanjianKinerja;
use App\Models\RealisasiKinerja;
use App\Models\User;
use App\Services\Workflow\WorkflowDataService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class LkjipController extends Controller
{
    use BuildsKinerjaOptions;

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Lkjip::class);

        $filters = $request->only(['search', 'status', 'opd_id', 'periode_tahun_id', 'tahun']);
        $user = $request->user();

        $items = Lkjip::query()
            ->with(['opd:id,kode,nama,singkatan', 'periodeTahun:id,tahun,nama'])
            ->withCount('bab')
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
            ->when($filters['periode_tahun_id'] ?? null, fn (Builder $query, string $periodeId) => $query->where('periode_tahun_id', $periodeId))
            ->when($filters['tahun'] ?? null, fn (Builder $query, string $tahun) => $query->where('tahun', $tahun))
            ->orderByDesc('tahun')
            ->latest('id')
            ->paginate(10)
            ->withQueryString()
            ->through(fn (Lkjip $lkjip) => [
                'id' => $lkjip->id,
                'judul' => $lkjip->judul,
                'nomor_dokumen' => $lkjip->nomor_dokumen,
                'tahun' => $lkjip->tahun,
                'status' => $lkjip->status,
                'bab_count' => $lkjip->bab_count,
                'opd' => $lkjip->opd,
                'periode_tahun' => $lkjip->periodeTahun,
            ]);

        return Inertia::render('Lkjip/Index', [
            'items' => $items,
            'filters' => $filters,
            'opdOptions' => $this->opdOptions($user),
            'periodeOptions' => $this->periodeOptions(),
            'can' => [
                'manage' => $user->can('create', Lkjip::class),
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorize('create', Lkjip::class);

        return Inertia::render('Lkjip/Form', [
            'mode' => 'create',
            'item' => null,
            'opdOptions' => $this->opdOptions($request->user()),
            'periodeOptions' => $this->periodeOptions(),
            'perjanjianKinerjaOptions' => $this->perjanjianKinerjaOptions($request->user()),
            'realisasiOptions' => $this->realisasiOptions($request->user()),
            'evaluasiOptions' => $this->evaluasiOptions($request->user()),
        ]);
    }

    public function store(StoreLkjipRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $this->assertRelationsBelongToOpd($data);

        $lkjip = DB::transaction(function () use ($data) {
            /** @var Lkjip $lkjip */
            $lkjip = Lkjip::create($data);
            $this->createDefaultBab($lkjip);

            return $lkjip;
        });

        return redirect()->route('lkjip.show', $lkjip)->with('success', 'LKJIP berhasil ditambahkan.');
    }

    public function show(Request $request, Lkjip $lkjip, WorkflowDataService $workflowDataService): Response
    {
        $this->authorize('view', $lkjip);

        $lkjip->load([
            'opd:id,kode,nama,singkatan',
            'periodeTahun:id,tahun,nama',
            'perjanjianKinerja:id,judul,tahun,status',
            'realisasiKinerja:id,tahun,periode_realisasi,triwulan,bulan,semester,status',
            'evaluasiSakip:id,tahun,nilai_akhir,predikat,status',
            'bab',
        ]);

        return Inertia::render('Lkjip/Show', [
            'item' => $this->serializeLkjip($lkjip),
            'workflow' => $workflowDataService->forModel($lkjip, 'lkjip'),
            'can' => [
                'manage' => $request->user()->can('update', $lkjip),
                'review' => $this->canReviewWorkflow($request->user()),
            ],
        ]);
    }

    public function edit(Request $request, Lkjip $lkjip): Response
    {
        $this->authorize('update', $lkjip);

        return Inertia::render('Lkjip/Form', [
            'mode' => 'edit',
            'item' => [
                'id' => $lkjip->id,
                'opd_id' => $lkjip->opd_id,
                'periode_tahun_id' => $lkjip->periode_tahun_id,
                'perjanjian_kinerja_id' => $lkjip->perjanjian_kinerja_id,
                'realisasi_kinerja_id' => $lkjip->realisasi_kinerja_id,
                'evaluasi_sakip_id' => $lkjip->evaluasi_sakip_id,
                'tahun' => $lkjip->tahun,
                'judul' => $lkjip->judul,
                'nomor_dokumen' => $lkjip->nomor_dokumen,
                'ringkasan_eksekutif' => $lkjip->ringkasan_eksekutif,
                'status' => $lkjip->status,
                'catatan' => $lkjip->catatan,
            ],
            'opdOptions' => $this->opdOptions($request->user()),
            'periodeOptions' => $this->periodeOptions(),
            'perjanjianKinerjaOptions' => $this->perjanjianKinerjaOptions($request->user()),
            'realisasiOptions' => $this->realisasiOptions($request->user()),
            'evaluasiOptions' => $this->evaluasiOptions($request->user()),
        ]);
    }

    public function update(UpdateLkjipRequest $request, Lkjip $lkjip): RedirectResponse
    {
        $data = $request->validated();
        $this->assertRelationsBelongToOpd($data);

        $lkjip->update($data);

        return redirect()->route('lkjip.show', $lkjip)->with('success', 'LKJIP berhasil diperbarui.');
    }

    public function destroy(Lkjip $lkjip): RedirectResponse
    {
        $this->authorize('delete', $lkjip);

        $lkjip->delete();

        return redirect()->route('lkjip.index')->with('success', 'LKJIP berhasil dihapus.');
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeLkjip(Lkjip $lkjip): array
    {
        return [
            'id' => $lkjip->id,
            'judul' => $lkjip->judul,
            'nomor_dokumen' => $lkjip->nomor_dokumen,
            'tahun' => $lkjip->tahun,
            'status' => $lkjip->status,
            'ringkasan_eksekutif' => $lkjip->ringkasan_eksekutif,
            'catatan' => $lkjip->catatan,
            'opd' => $lkjip->opd,
            'periode_tahun' => $lkjip->periodeTahun,
            'perjanjian_kinerja' => $lkjip->perjanjianKinerja,
            'realisasi_kinerja' => $lkjip->realisasiKinerja,
            'evaluasi_sakip' => $lkjip->evaluasiSakip,
            'bab' => $lkjip->bab->map(fn (LkjipBab $bab) => [
                'id' => $bab->id,
                'kode' => $bab->kode,
                'judul' => $bab->judul,
                'jenis' => $bab->jenis,
                'konten' => $bab->konten,
                'urutan' => $bab->urutan,
            ]),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function realisasiOptions(User $user): array
    {
        return RealisasiKinerja::query()
            ->with('opd:id,nama,singkatan')
            ->when($this->shouldLimitToUserOpd($user), fn (Builder $query) => $query->where('opd_id', $user->opd_id))
            ->orderByDesc('tahun')
            ->get(['id', 'opd_id', 'tahun', 'periode_realisasi', 'triwulan', 'bulan', 'semester', 'status'])
            ->map(fn (RealisasiKinerja $realisasi) => [
                'id' => $realisasi->id,
                'opd_id' => $realisasi->opd_id,
                'label' => trim(($realisasi->opd?->singkatan ? "{$realisasi->opd->singkatan} - " : '')."{$realisasi->tahun} - {$realisasi->periode_realisasi} ".($realisasi->triwulan ?: $realisasi->bulan ?: $realisasi->semester ?: '')),
            ])
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function evaluasiOptions(User $user): array
    {
        return EvaluasiSakip::query()
            ->with('opd:id,nama,singkatan')
            ->when($this->shouldLimitToUserOpd($user), fn (Builder $query) => $query->where('opd_id', $user->opd_id))
            ->orderByDesc('tahun')
            ->get(['id', 'opd_id', 'tahun', 'nilai_akhir', 'predikat', 'status'])
            ->map(fn (EvaluasiSakip $evaluasi) => [
                'id' => $evaluasi->id,
                'opd_id' => $evaluasi->opd_id,
                'label' => trim(($evaluasi->opd?->singkatan ? "{$evaluasi->opd->singkatan} - " : '')."{$evaluasi->tahun} - Nilai {$evaluasi->nilai_akhir} ({$evaluasi->predikat})"),
            ])
            ->all();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function assertRelationsBelongToOpd(array $data): void
    {
        $opdId = (int) $data['opd_id'];
        $checks = [
            'perjanjian_kinerja_id' => PerjanjianKinerja::class,
            'realisasi_kinerja_id' => RealisasiKinerja::class,
            'evaluasi_sakip_id' => EvaluasiSakip::class,
        ];

        foreach ($checks as $field => $modelClass) {
            if (blank($data[$field] ?? null)) {
                continue;
            }

            $exists = $modelClass::query()
                ->whereKey($data[$field])
                ->where('opd_id', $opdId)
                ->exists();

            if (! $exists) {
                throw ValidationException::withMessages([$field => 'Data referensi tidak sesuai dengan OPD LKJIP.']);
            }
        }
    }

    private function createDefaultBab(Lkjip $lkjip): void
    {
        foreach ([
            ['kode' => 'BAB I', 'judul' => 'Pendahuluan', 'jenis' => 'pendahuluan'],
            ['kode' => 'BAB II', 'judul' => 'Perencanaan Kinerja', 'jenis' => 'perencanaan'],
            ['kode' => 'BAB III', 'judul' => 'Akuntabilitas Kinerja', 'jenis' => 'akuntabilitas'],
            ['kode' => 'BAB IV', 'judul' => 'Penutup', 'jenis' => 'penutup'],
            ['kode' => 'LAMPIRAN', 'judul' => 'Lampiran', 'jenis' => 'lampiran'],
        ] as $index => $bab) {
            $lkjip->bab()->create([
                ...$bab,
                'konten' => null,
                'urutan' => $index + 1,
            ]);
        }
    }

    private function canReviewWorkflow(User $user): bool
    {
        return $user->hasAnyRole(['super_admin', 'admin_kabupaten_bagian_organisasi'])
            || $user->hasPermission('lock_period');
    }
}
