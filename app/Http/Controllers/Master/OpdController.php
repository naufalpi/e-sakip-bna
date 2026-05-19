<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Http\Requests\Master\StoreOpdRequest;
use App\Http\Requests\Master\UpdateOpdRequest;
use App\Models\Opd;
use App\Models\UrusanPemerintahan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OpdController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Opd::class);

        $filters = $request->only(['search', 'status']);
        $user = $request->user();

        $opds = Opd::query()
            ->with('urusanPemerintahan:id,kode,nama')
            ->when($user->hasRole('admin_opd'), fn ($query) => $query->whereKey($user->opd_id))
            ->when($filters['search'] ?? null, function ($query, string $search) {
                $query->where(function ($query) use ($search) {
                    $query->where('nama', 'ilike', "%{$search}%")
                        ->orWhere('kode', 'ilike', "%{$search}%")
                        ->orWhere('singkatan', 'ilike', "%{$search}%");
                });
            })
            ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->orderBy('nama')
            ->paginate(10)
            ->withQueryString()
            ->through(fn (Opd $opd) => $this->serializeOpd($opd));

        return Inertia::render('Master/Opd/Index', [
            'opds' => $opds,
            'filters' => $filters,
            'can' => [
                'create' => $request->user()->can('create', Opd::class),
            ],
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', Opd::class);

        return Inertia::render('Master/Opd/Form', [
            'mode' => 'create',
            'opd' => null,
            'urusanOptions' => $this->urusanOptions(),
        ]);
    }

    public function store(StoreOpdRequest $request): RedirectResponse
    {
        Opd::create($request->validated());

        return redirect()->route('master.opd.index')->with('success', 'OPD berhasil ditambahkan.');
    }

    public function edit(Opd $opd): Response
    {
        $this->authorize('update', $opd);

        return Inertia::render('Master/Opd/Form', [
            'mode' => 'edit',
            'opd' => $this->serializeOpd($opd->load('urusanPemerintahan:id,kode,nama')),
            'urusanOptions' => $this->urusanOptions(),
        ]);
    }

    public function update(UpdateOpdRequest $request, Opd $opd): RedirectResponse
    {
        $opd->update($request->validated());

        return redirect()->route('master.opd.index')->with('success', 'OPD berhasil diperbarui.');
    }

    public function destroy(Opd $opd): RedirectResponse
    {
        $this->authorize('delete', $opd);

        $opd->delete();

        return redirect()->route('master.opd.index')->with('success', 'OPD berhasil dihapus.');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function urusanOptions(): array
    {
        return UrusanPemerintahan::query()
            ->where('status', 'active')
            ->orderBy('kode')
            ->get(['id', 'kode', 'nama'])
            ->map(fn (UrusanPemerintahan $urusan) => [
                'id' => $urusan->id,
                'label' => "{$urusan->kode} - {$urusan->nama}",
            ])
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeOpd(Opd $opd): array
    {
        return [
            'id' => $opd->id,
            'urusan_pemerintahan_id' => $opd->urusan_pemerintahan_id,
            'urusan_pemerintahan' => $opd->urusanPemerintahan ? [
                'id' => $opd->urusanPemerintahan->id,
                'kode' => $opd->urusanPemerintahan->kode,
                'nama' => $opd->urusanPemerintahan->nama,
            ] : null,
            'kode' => $opd->kode,
            'nama' => $opd->nama,
            'singkatan' => $opd->singkatan,
            'jenis' => $opd->jenis,
            'alamat' => $opd->alamat,
            'telepon' => $opd->telepon,
            'email' => $opd->email,
            'nama_kepala' => $opd->nama_kepala,
            'nip_kepala' => $opd->nip_kepala,
            'status' => $opd->status,
        ];
    }
}
