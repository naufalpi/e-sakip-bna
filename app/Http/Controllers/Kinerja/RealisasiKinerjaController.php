<?php

namespace App\Http\Controllers\Kinerja;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Kinerja\Concerns\BuildsKinerjaOptions;
use App\Http\Requests\Kinerja\StoreRealisasiKinerjaRequest;
use App\Http\Requests\Kinerja\UpdateRealisasiKinerjaRequest;
use App\Models\PerjanjianKinerja;
use App\Models\RealisasiKinerja;
use App\Models\RealisasiProgram;
use App\Models\RencanaAksi;
use App\Models\User;
use App\Models\WorkflowSubmission;
use App\Services\Kinerja\CapaianKinerjaService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class RealisasiKinerjaController extends Controller
{
    use BuildsKinerjaOptions;

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', RealisasiKinerja::class);

        $filters = $request->only(['search', 'status', 'opd_id', 'periode_tahun_id', 'tahun', 'periode_realisasi', 'triwulan']);
        $user = $request->user();

        $items = RealisasiKinerja::query()
            ->with(['opd:id,kode,nama,singkatan', 'periodeTahun:id,tahun,nama', 'perjanjianKinerja:id,judul,tahun', 'rencanaAksi:id,judul,tahun'])
            ->withCount('programs')
            ->when($this->shouldLimitToUserOpd($user), fn (Builder $query) => $query->where('opd_id', $user->opd_id))
            ->when($filters['search'] ?? null, function (Builder $query, string $search) {
                $query->where(function (Builder $query) use ($search) {
                    $query->where('catatan', 'ilike', "%{$search}%")
                        ->orWhereHas('opd', fn (Builder $query) => $query->where('nama', 'ilike', "%{$search}%")->orWhere('singkatan', 'ilike', "%{$search}%"));
                });
            })
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($filters['opd_id'] ?? null, fn (Builder $query, string $opdId) => $query->where('opd_id', $opdId))
            ->when($filters['periode_tahun_id'] ?? null, fn (Builder $query, string $periodeId) => $query->where('periode_tahun_id', $periodeId))
            ->when($filters['tahun'] ?? null, fn (Builder $query, string $tahun) => $query->where('tahun', $tahun))
            ->when($filters['periode_realisasi'] ?? null, fn (Builder $query, string $periode) => $query->where('periode_realisasi', $periode))
            ->when($filters['triwulan'] ?? null, fn (Builder $query, string $triwulan) => $query->where('triwulan', $triwulan))
            ->orderByDesc('tahun')
            ->latest('id')
            ->paginate(10)
            ->withQueryString()
            ->through(fn (RealisasiKinerja $realisasi) => [
                'id' => $realisasi->id,
                'tahun' => $realisasi->tahun,
                'periode_realisasi' => $realisasi->periode_realisasi,
                'triwulan' => $realisasi->triwulan,
                'bulan' => $realisasi->bulan,
                'semester' => $realisasi->semester,
                'status' => $realisasi->status,
                'target_anggaran' => $realisasi->target_anggaran,
                'realisasi_anggaran' => $realisasi->realisasi_anggaran,
                'serapan_anggaran_persen' => $realisasi->serapan_anggaran_persen,
                'capaian_persen' => $realisasi->capaian_persen,
                'status_capaian' => $realisasi->status_capaian,
                'status_efisiensi' => $realisasi->status_efisiensi,
                'programs_count' => $realisasi->programs_count,
                'opd' => $realisasi->opd,
                'periode_tahun' => $realisasi->periodeTahun,
                'perjanjian_kinerja' => $realisasi->perjanjianKinerja,
                'rencana_aksi' => $realisasi->rencanaAksi,
            ]);

        return Inertia::render('Kinerja/RealisasiKinerja/Index', [
            'items' => $items,
            'filters' => $filters,
            'opdOptions' => $this->opdOptions($user),
            'periodeOptions' => $this->periodeOptions(),
            'can' => [
                'manage' => $user->can('create', RealisasiKinerja::class),
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorize('create', RealisasiKinerja::class);

        return Inertia::render('Kinerja/RealisasiKinerja/Form', [
            'mode' => 'create',
            'item' => null,
            'opdOptions' => $this->opdOptions($request->user()),
            'periodeOptions' => $this->periodeOptions(),
            'perjanjianKinerjaOptions' => $this->perjanjianKinerjaOptions($request->user()),
            'rencanaAksiOptions' => $this->rencanaAksiOptions($request->user()),
        ]);
    }

    public function store(StoreRealisasiKinerjaRequest $request, CapaianKinerjaService $capaianService): RedirectResponse
    {
        $data = $request->validated();
        $this->assertHeaderRelationsBelongToOpd($data, (int) $data['opd_id']);
        $data = $this->withHeaderMetrics($data, $capaianService);

        $realisasi = RealisasiKinerja::create($data);

        return redirect()->route('realisasi-kinerja.show', $realisasi)->with('success', 'Realisasi Kinerja berhasil ditambahkan.');
    }

    public function show(Request $request, RealisasiKinerja $realisasiKinerja): Response
    {
        $this->authorize('view', $realisasiKinerja);

        $realisasiKinerja->load([
            'opd:id,kode,nama,singkatan',
            'periodeTahun:id,tahun,nama',
            'perjanjianKinerja:id,judul,tahun,status',
            'rencanaAksi:id,judul,tahun,status',
            'programs.perjanjianKinerjaItem:id,kode,indikator',
            'programs.rencanaAksiItem:id,aksi',
            'programs.opdProgram:id,kode,nama',
            'programs.indikatorOpdProgram:id,kode,indikator',
        ]);

        return Inertia::render('Kinerja/RealisasiKinerja/Show', [
            'item' => $this->serializeRealisasiKinerja($realisasiKinerja),
            'nodeOptions' => $request->user()->can('update', $realisasiKinerja) ? $this->nodeOptionsForOpd((int) $realisasiKinerja->opd_id) : [],
            'perjanjianKinerjaItemOptions' => $request->user()->can('update', $realisasiKinerja) ? $this->perjanjianKinerjaItemOptions((int) $realisasiKinerja->opd_id) : [],
            'rencanaAksiItemOptions' => $request->user()->can('update', $realisasiKinerja) ? $this->rencanaAksiItemOptions((int) $realisasiKinerja->opd_id) : [],
            'workflow' => $this->workflowData($realisasiKinerja, 'realisasi_kinerja'),
            'can' => [
                'manage' => $request->user()->can('update', $realisasiKinerja),
                'review' => $this->canReviewWorkflow($request->user()),
            ],
        ]);
    }

    public function edit(Request $request, RealisasiKinerja $realisasiKinerja): Response
    {
        $this->authorize('update', $realisasiKinerja);

        return Inertia::render('Kinerja/RealisasiKinerja/Form', [
            'mode' => 'edit',
            'item' => [
                'id' => $realisasiKinerja->id,
                'opd_id' => $realisasiKinerja->opd_id,
                'perjanjian_kinerja_id' => $realisasiKinerja->perjanjian_kinerja_id,
                'rencana_aksi_id' => $realisasiKinerja->rencana_aksi_id,
                'periode_tahun_id' => $realisasiKinerja->periode_tahun_id,
                'tahun' => $realisasiKinerja->tahun,
                'periode_realisasi' => $realisasiKinerja->periode_realisasi,
                'triwulan' => $realisasiKinerja->triwulan,
                'bulan' => $realisasiKinerja->bulan,
                'semester' => $realisasiKinerja->semester,
                'status' => $realisasiKinerja->status,
                'target_anggaran' => $realisasiKinerja->target_anggaran,
                'realisasi_anggaran' => $realisasiKinerja->realisasi_anggaran,
                'serapan_anggaran_persen' => $realisasiKinerja->serapan_anggaran_persen,
                'capaian_persen' => $realisasiKinerja->capaian_persen,
                'status_capaian' => $realisasiKinerja->status_capaian,
                'status_efisiensi' => $realisasiKinerja->status_efisiensi,
                'analisis_efisiensi' => $realisasiKinerja->analisis_efisiensi,
                'catatan' => $realisasiKinerja->catatan,
            ],
            'opdOptions' => $this->opdOptions($request->user()),
            'periodeOptions' => $this->periodeOptions(),
            'perjanjianKinerjaOptions' => $this->perjanjianKinerjaOptions($request->user()),
            'rencanaAksiOptions' => $this->rencanaAksiOptions($request->user()),
        ]);
    }

    public function update(UpdateRealisasiKinerjaRequest $request, RealisasiKinerja $realisasiKinerja, CapaianKinerjaService $capaianService): RedirectResponse
    {
        $data = $request->validated();
        $this->assertHeaderRelationsBelongToOpd($data, (int) $data['opd_id']);
        $data = $this->withHeaderMetrics($data, $capaianService);

        $realisasiKinerja->update($data);

        return redirect()->route('realisasi-kinerja.show', $realisasiKinerja)->with('success', 'Realisasi Kinerja berhasil diperbarui.');
    }

    public function destroy(RealisasiKinerja $realisasiKinerja): RedirectResponse
    {
        $this->authorize('delete', $realisasiKinerja);

        $realisasiKinerja->delete();

        return redirect()->route('realisasi-kinerja.index')->with('success', 'Realisasi Kinerja berhasil dihapus.');
    }

    private function serializeRealisasiKinerja(RealisasiKinerja $realisasi): array
    {
        return [
            'id' => $realisasi->id,
            'tahun' => $realisasi->tahun,
            'periode_realisasi' => $realisasi->periode_realisasi,
            'triwulan' => $realisasi->triwulan,
            'bulan' => $realisasi->bulan,
            'semester' => $realisasi->semester,
            'status' => $realisasi->status,
            'target_anggaran' => $realisasi->target_anggaran,
            'realisasi_anggaran' => $realisasi->realisasi_anggaran,
            'serapan_anggaran_persen' => $realisasi->serapan_anggaran_persen,
            'capaian_persen' => $realisasi->capaian_persen,
            'status_capaian' => $realisasi->status_capaian,
            'status_efisiensi' => $realisasi->status_efisiensi,
            'analisis_efisiensi' => $realisasi->analisis_efisiensi,
            'catatan' => $realisasi->catatan,
            'opd' => $realisasi->opd,
            'periode_tahun' => $realisasi->periodeTahun,
            'perjanjian_kinerja' => $realisasi->perjanjianKinerja,
            'rencana_aksi' => $realisasi->rencanaAksi,
            'programs' => $realisasi->programs->map(fn (RealisasiProgram $program) => [
                'id' => $program->id,
                'indikator' => $program->indikator,
                'tipe_indikator' => $program->tipe_indikator,
                'target' => $program->target,
                'target_text' => $program->target_text,
                'realisasi' => $program->realisasi,
                'realisasi_text' => $program->realisasi_text,
                'capaian_persen' => $program->capaian_persen,
                'status_capaian' => $program->status_capaian,
                'anggaran' => $program->anggaran,
                'realisasi_anggaran' => $program->realisasi_anggaran,
                'serapan_anggaran_persen' => $program->serapan_anggaran_persen,
                'status_efisiensi' => $program->status_efisiensi,
                'analisis_efisiensi' => $program->analisis_efisiensi,
                'kendala' => $program->kendala,
                'tindak_lanjut' => $program->tindak_lanjut,
                'urutan' => $program->urutan,
                'perjanjian_kinerja_item' => $program->perjanjianKinerjaItem,
                'rencana_aksi_item' => $program->rencanaAksiItem,
                'opd_program' => $program->opdProgram,
                'indikator_opd_program' => $program->indikatorOpdProgram,
            ]),
        ];
    }

    private function workflowData(RealisasiKinerja $realisasi, string $module): ?array
    {
        $workflow = WorkflowSubmission::query()
            ->with(['histories.actor:id,name', 'submittedBy:id,name', 'currentReviewer:id,name'])
            ->where('related_table', $realisasi->getTable())
            ->where('related_id', $realisasi->id)
            ->where('module', $module)
            ->first();

        return $workflow?->toArray();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function withHeaderMetrics(array $data, CapaianKinerjaService $capaianService): array
    {
        $result = $data;
        $hasAnggaranInput = array_key_exists('target_anggaran', $data)
            || array_key_exists('realisasi_anggaran', $data)
            || array_key_exists('serapan_anggaran_persen', $data);
        $hasCapaianInput = array_key_exists('capaian_persen', $data)
            || array_key_exists('status_capaian', $data);

        $serapanAnggaran = $hasAnggaranInput
            ? ($capaianService->calculateSerapanAnggaran($data['target_anggaran'] ?? null, $data['realisasi_anggaran'] ?? null) ?? ($data['serapan_anggaran_persen'] ?? null))
            : null;
        $capaianPersen = $hasCapaianInput ? ($data['capaian_persen'] ?? null) : null;

        if ($hasAnggaranInput) {
            $result['serapan_anggaran_persen'] = $serapanAnggaran;
        }

        if ($hasCapaianInput) {
            $result['status_capaian'] = $capaianService->determineStatusCapaian($capaianPersen) ?? ($data['status_capaian'] ?? null);
        }

        if ($hasAnggaranInput || $hasCapaianInput || array_key_exists('status_efisiensi', $data)) {
            $result['status_efisiensi'] = $capaianService->determineEfisiensi($capaianPersen, $serapanAnggaran) ?? ($data['status_efisiensi'] ?? null);
        }

        return $result;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function assertHeaderRelationsBelongToOpd(array $data, int $opdId): void
    {
        if (($data['perjanjian_kinerja_id'] ?? null)
            && ! PerjanjianKinerja::query()->whereKey($data['perjanjian_kinerja_id'])->where('opd_id', $opdId)->exists()) {
            throw ValidationException::withMessages(['perjanjian_kinerja_id' => 'Perjanjian Kinerja tidak sesuai OPD Realisasi.']);
        }

        if (($data['rencana_aksi_id'] ?? null)
            && ! RencanaAksi::query()->whereKey($data['rencana_aksi_id'])->where('opd_id', $opdId)->exists()) {
            throw ValidationException::withMessages(['rencana_aksi_id' => 'Rencana Aksi tidak sesuai OPD Realisasi.']);
        }
    }

    private function canReviewWorkflow(User $user): bool
    {
        return $user->hasAnyRole(['super_admin', 'admin_kabupaten_bagian_organisasi', 'admin_kabupaten_inspektorat'])
            || $user->hasPermission('verify_realisasi')
            || $user->hasPermission('lock_period');
    }
}
