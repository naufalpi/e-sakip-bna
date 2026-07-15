<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Http\Requests\Master\StoreOpdRequest;
use App\Http\Requests\Master\UpdateOpdRequest;
use App\Models\Opd;
use App\Models\OpdUnit;
use App\Models\UrusanPemerintahan;
use App\Models\User;
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
            ->with([
                'urusanPemerintahan:id,kode,nama',
                'units' => fn ($query) => $query
                    ->select(['id', 'opd_id', 'parent_id', 'kode', 'nama', 'jenis_unit', 'nama_pimpinan', 'nip_pimpinan', 'status'])
                    ->when($this->shouldLimitToUserUnit($user), fn ($query) => $query->whereKey($user->opd_unit_id))
                    ->with('parent:id,kode,nama')
                    ->orderBy('kode'),
            ])
            ->withCount(['units' => fn ($query) => $query->when($this->shouldLimitToUserUnit($user), fn ($query) => $query->whereKey($user->opd_unit_id))])
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
            'totalUnits' => $this->totalUnits($user),
            'filters' => $filters,
            'can' => [
                'create' => $request->user()->can('create', Opd::class),
                'manageUnits' => $this->canManageOpdUnits($request->user()),
            ],
            'jenisUnitOptions' => $this->jenisUnitOptions(),
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
            'units_count' => $opd->units_count ?? $opd->units->count(),
            'units' => $opd->relationLoaded('units')
                ? $opd->units->map(fn (OpdUnit $unit) => [
                    'id' => $unit->id,
                    'opd_id' => $unit->opd_id,
                    'parent_id' => $unit->parent_id,
                    'kode' => $unit->kode,
                    'nama' => $unit->nama,
                    'jenis_unit' => $unit->jenis_unit,
                    'nama_pimpinan' => $unit->nama_pimpinan,
                    'nip_pimpinan' => $unit->nip_pimpinan,
                    'status' => $unit->status,
                    'parent' => $unit->parent ? [
                        'id' => $unit->parent->id,
                        'kode' => $unit->parent->kode,
                        'nama' => $unit->parent->nama,
                    ] : null,
                ])->all()
                : [],
        ];
    }

    private function canManageOpdUnits(User $user): bool
    {
        if ($this->shouldLimitToUserUnit($user)) {
            return false;
        }

        return $user->hasPermission('opd.manage')
            || $user->hasPermission('opd_units.manage')
            || ($user->hasRole('admin_opd') && filled($user->opd_id));
    }

    private function totalUnits(User $user): int
    {
        return OpdUnit::query()
            ->when($user->hasRole('admin_opd'), fn ($query) => $query->where('opd_id', $user->opd_id))
            ->when($this->shouldLimitToUserUnit($user), fn ($query) => $query->whereKey($user->opd_unit_id))
            ->count();
    }

    private function shouldLimitToUserUnit(User $user): bool
    {
        return $user->hasRole('admin_opd')
            && filled($user->opd_unit_id)
            && ! $user->hasAnyRole([
                'super_admin',
                'admin_kabupaten_bagian_organisasi',
                'admin_kabupaten_bapperida',
                'admin_kabupaten_inspektorat',
                'admin_kabupaten_dinkominfo',
            ]);
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function jenisUnitOptions(): array
    {
        return [
            ['value' => 'dinas', 'label' => 'Dinas/Badan Induk'],
            ['value' => 'badan', 'label' => 'Badan'],
            ['value' => 'satuan', 'label' => 'Satuan'],
            ['value' => 'sekretariat', 'label' => 'Sekretariat'],
            ['value' => 'inspektorat', 'label' => 'Inspektorat'],
            ['value' => 'kecamatan', 'label' => 'Kecamatan'],
            ['value' => 'bidang', 'label' => 'Bidang'],
            ['value' => 'bagian', 'label' => 'Bagian'],
            ['value' => 'subbagian', 'label' => 'Subbagian'],
            ['value' => 'seksi', 'label' => 'Seksi'],
            ['value' => 'uptd', 'label' => 'UPTD'],
            ['value' => 'puskesmas', 'label' => 'Puskesmas'],
            ['value' => 'sekolah', 'label' => 'Sekolah'],
            ['value' => 'labkes', 'label' => 'Labkes'],
            ['value' => 'rsud', 'label' => 'RSUD'],
            ['value' => 'kelurahan', 'label' => 'Kelurahan'],
            ['value' => 'lainnya', 'label' => 'Lainnya'],
        ];
    }
}
