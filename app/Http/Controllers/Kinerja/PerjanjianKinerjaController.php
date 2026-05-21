<?php

namespace App\Http\Controllers\Kinerja;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Kinerja\Concerns\BuildsKinerjaOptions;
use App\Http\Requests\Kinerja\StorePerjanjianKinerjaRequest;
use App\Http\Requests\Kinerja\UpdatePerjanjianKinerjaRequest;
use App\Models\PerjanjianKinerja;
use App\Models\PerjanjianKinerjaItem;
use App\Models\RenstraOpd;
use App\Models\User;
use App\Models\WorkflowSubmission;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PerjanjianKinerjaController extends Controller
{
    use BuildsKinerjaOptions;

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', PerjanjianKinerja::class);

        $filters = $request->only(['search', 'status', 'opd_id', 'periode_tahun_id', 'tahun']);
        $user = $request->user();

        $items = PerjanjianKinerja::query()
            ->with(['opd:id,kode,nama,singkatan', 'periodeTahun:id,tahun,nama'])
            ->withCount('items')
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
            ->through(fn (PerjanjianKinerja $pk) => [
                'id' => $pk->id,
                'judul' => $pk->judul,
                'nomor_dokumen' => $pk->nomor_dokumen,
                'tahun' => $pk->tahun,
                'status' => $pk->status,
                'items_count' => $pk->items_count,
                'opd' => $pk->opd ? [
                    'id' => $pk->opd->id,
                    'kode' => $pk->opd->kode,
                    'nama' => $pk->opd->nama,
                    'singkatan' => $pk->opd->singkatan,
                ] : null,
                'periode_tahun' => $pk->periodeTahun ? [
                    'id' => $pk->periodeTahun->id,
                    'tahun' => $pk->periodeTahun->tahun,
                    'nama' => $pk->periodeTahun->nama,
                ] : null,
            ]);

        return Inertia::render('Kinerja/PerjanjianKinerja/Index', [
            'items' => $items,
            'filters' => $filters,
            'opdOptions' => $this->opdOptions($user),
            'periodeOptions' => $this->periodeOptions(),
            'can' => [
                'manage' => $user->can('create', PerjanjianKinerja::class),
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorize('create', PerjanjianKinerja::class);

        return Inertia::render('Kinerja/PerjanjianKinerja/Form', [
            'mode' => 'create',
            'item' => null,
            'opdOptions' => $this->opdOptions($request->user()),
            'periodeOptions' => $this->periodeOptions(),
            'renstraOptions' => $this->renstraOptions($request->user()),
        ]);
    }

    public function store(StorePerjanjianKinerjaRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $this->assertRenstraBelongsToOpd($data['renstra_opd_id'] ?? null, (int) $data['opd_id']);

        $pk = PerjanjianKinerja::create($data);

        return redirect()->route('perjanjian-kinerja.show', $pk)->with('success', 'Perjanjian Kinerja berhasil ditambahkan.');
    }

    public function show(Request $request, PerjanjianKinerja $perjanjianKinerja): Response
    {
        $this->authorize('view', $perjanjianKinerja);

        $perjanjianKinerja->load([
            'opd:id,kode,nama,singkatan',
            'periodeTahun:id,tahun,nama',
            'renstraOpd:id,judul,tahun_awal,tahun_akhir',
            'items.satuanIndikator:id,nama,simbol',
            'items.sasaranOpd:id,kode,sasaran',
            'items.indikatorSasaranOpd:id,kode,indikator',
            'items.opdProgram:id,kode,nama',
        ]);

        return Inertia::render('Kinerja/PerjanjianKinerja/Show', [
            'item' => $this->serializePerjanjianKinerja($perjanjianKinerja),
            'nodeOptions' => $request->user()->can('update', $perjanjianKinerja) ? $this->nodeOptionsForOpd((int) $perjanjianKinerja->opd_id) : [],
            'satuanOptions' => $request->user()->can('update', $perjanjianKinerja) ? $this->satuanOptions() : [],
            'workflow' => $this->workflowData($perjanjianKinerja, 'perjanjian_kinerja'),
            'can' => [
                'manage' => $request->user()->can('update', $perjanjianKinerja),
                'review' => $this->canReviewWorkflow($request->user()),
            ],
        ]);
    }

    public function edit(Request $request, PerjanjianKinerja $perjanjianKinerja): Response
    {
        $this->authorize('update', $perjanjianKinerja);

        return Inertia::render('Kinerja/PerjanjianKinerja/Form', [
            'mode' => 'edit',
            'item' => [
                'id' => $perjanjianKinerja->id,
                'opd_id' => $perjanjianKinerja->opd_id,
                'renstra_opd_id' => $perjanjianKinerja->renstra_opd_id,
                'periode_tahun_id' => $perjanjianKinerja->periode_tahun_id,
                'tahun' => $perjanjianKinerja->tahun,
                'judul' => $perjanjianKinerja->judul,
                'nomor_dokumen' => $perjanjianKinerja->nomor_dokumen,
                'status' => $perjanjianKinerja->status,
                'catatan' => $perjanjianKinerja->catatan,
            ],
            'opdOptions' => $this->opdOptions($request->user()),
            'periodeOptions' => $this->periodeOptions(),
            'renstraOptions' => $this->renstraOptions($request->user()),
        ]);
    }

    public function update(UpdatePerjanjianKinerjaRequest $request, PerjanjianKinerja $perjanjianKinerja): RedirectResponse
    {
        $data = $request->validated();
        $this->assertRenstraBelongsToOpd($data['renstra_opd_id'] ?? null, (int) $data['opd_id']);

        $perjanjianKinerja->update($data);

        return redirect()->route('perjanjian-kinerja.show', $perjanjianKinerja)->with('success', 'Perjanjian Kinerja berhasil diperbarui.');
    }

    public function destroy(PerjanjianKinerja $perjanjianKinerja): RedirectResponse
    {
        $this->authorize('delete', $perjanjianKinerja);

        $perjanjianKinerja->delete();

        return redirect()->route('perjanjian-kinerja.index')->with('success', 'Perjanjian Kinerja berhasil dihapus.');
    }

    private function serializePerjanjianKinerja(PerjanjianKinerja $pk): array
    {
        return [
            'id' => $pk->id,
            'judul' => $pk->judul,
            'nomor_dokumen' => $pk->nomor_dokumen,
            'tahun' => $pk->tahun,
            'status' => $pk->status,
            'catatan' => $pk->catatan,
            'opd' => $pk->opd,
            'periode_tahun' => $pk->periodeTahun,
            'renstra_opd' => $pk->renstraOpd,
            'items' => $pk->items->map(fn (PerjanjianKinerjaItem $item) => [
                'id' => $item->id,
                'sasaran_opd_id' => $item->sasaran_opd_id,
                'indikator_sasaran_opd_id' => $item->indikator_sasaran_opd_id,
                'opd_program_id' => $item->opd_program_id,
                'satuan_indikator_id' => $item->satuan_indikator_id,
                'kode' => $item->kode,
                'sasaran' => $item->sasaran,
                'indikator' => $item->indikator,
                'target' => $item->target,
                'target_text' => $item->target_text,
                'urutan' => $item->urutan,
                'satuan' => $item->satuanIndikator,
                'sasaran_opd' => $item->sasaranOpd,
                'indikator_sasaran_opd' => $item->indikatorSasaranOpd,
                'opd_program' => $item->opdProgram,
            ]),
        ];
    }

    private function workflowData(PerjanjianKinerja $pk, string $module): ?array
    {
        $workflow = WorkflowSubmission::query()
            ->with(['histories.actor:id,name', 'submittedBy:id,name', 'currentReviewer:id,name'])
            ->where('related_table', $pk->getTable())
            ->where('related_id', $pk->id)
            ->where('module', $module)
            ->first();

        return $workflow?->toArray();
    }

    private function assertRenstraBelongsToOpd(mixed $renstraId, int $opdId): void
    {
        if (! $renstraId) {
            return;
        }

        abort_unless(RenstraOpd::query()->whereKey($renstraId)->where('opd_id', $opdId)->exists(), 422, 'Renstra yang dipilih tidak sesuai OPD.');
    }

    private function canReviewWorkflow(User $user): bool
    {
        return $user->hasAnyRole(['super_admin', 'admin_kabupaten_bagian_organisasi', 'admin_kabupaten_inspektorat'])
            || $user->hasPermission('verify_realisasi')
            || $user->hasPermission('lock_period');
    }
}
