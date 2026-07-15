<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Http\Requests\Master\StoreOpdUnitRequest;
use App\Http\Requests\Master\UpdateOpdUnitRequest;
use App\Models\Opd;
use App\Models\OpdUnit;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class OpdUnitController extends Controller
{
    public function redirectToOpd(): RedirectResponse
    {
        return redirect()->route('master.opd.index');
    }

    public function index(Request $request): Response
    {
        abort_unless($request->user()->hasPermission('opd.view'), 403);

        $filters = $request->only(['search', 'status', 'opd_id', 'jenis_unit']);
        $user = $request->user();

        $items = OpdUnit::query()
            ->with(['opd:id,kode,nama,singkatan', 'parent:id,kode,nama'])
            ->withCount('children')
            ->when($this->shouldLimitToUserOpd($user), fn (Builder $query) => $query->where('opd_id', $user->opd_id))
            ->when($this->shouldLimitToUserUnit($user), fn (Builder $query) => $query->whereKey($user->opd_unit_id))
            ->when($filters['search'] ?? null, function (Builder $query, string $search) {
                $query->where(function (Builder $query) use ($search) {
                    $query->where('kode', 'ilike', "%{$search}%")
                        ->orWhere('nama', 'ilike', "%{$search}%")
                        ->orWhere('jenis_unit', 'ilike', "%{$search}%")
                        ->orWhere('nama_pimpinan', 'ilike', "%{$search}%");
                });
            })
            ->when(($filters['opd_id'] ?? null) && ! $this->shouldLimitToUserOpd($user), fn (Builder $query, string $opdId) => $query->where('opd_id', $opdId))
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($filters['jenis_unit'] ?? null, fn (Builder $query, string $jenis) => $query->where('jenis_unit', $jenis))
            ->orderBy('opd_id')
            ->orderBy('kode')
            ->paginate(10)
            ->withQueryString()
            ->through(fn (OpdUnit $unit) => $this->serialize($unit));

        return Inertia::render('Master/OpdUnit/Index', [
            'items' => $items,
            'filters' => $filters,
            'opdOptions' => $this->opdOptions($user),
            'jenisOptions' => $this->jenisOptions(),
            'can' => [
                'manage' => $this->canManageOpdUnits($user),
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        abort_unless($this->canManageOpdUnits($request->user()), 403);

        return Inertia::render('Master/OpdUnit/Form', [
            'mode' => 'create',
            'item' => null,
            'opdOptions' => $this->opdOptions($request->user()),
            'parentOptions' => $this->parentOptions($request->user()),
            'jenisOptions' => $this->jenisOptions(),
        ]);
    }

    public function store(StoreOpdUnitRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $this->assertAllowedOpd($request->user(), (int) $data['opd_id']);
        $this->assertParentValid($data['parent_id'] ?? null, (int) $data['opd_id']);

        OpdUnit::create($data);

        return redirect()->route('master.opd.index')->with('success', 'Unit OPD berhasil ditambahkan.');
    }

    public function edit(Request $request, OpdUnit $opdUnit): Response
    {
        abort_unless($this->canManageOpdUnits($request->user()), 403);
        $this->abortUnlessAllowedOpd($request->user(), (int) $opdUnit->opd_id);

        return Inertia::render('Master/OpdUnit/Form', [
            'mode' => 'edit',
            'item' => $this->serialize($opdUnit->load(['opd:id,kode,nama,singkatan', 'parent:id,kode,nama'])),
            'opdOptions' => $this->opdOptions($request->user()),
            'parentOptions' => $this->parentOptions($request->user(), $opdUnit),
            'jenisOptions' => $this->jenisOptions(),
        ]);
    }

    public function update(UpdateOpdUnitRequest $request, OpdUnit $opdUnit): RedirectResponse
    {
        $data = $request->validated();
        $this->abortUnlessAllowedOpd($request->user(), (int) $opdUnit->opd_id);
        $this->assertAllowedOpd($request->user(), (int) $data['opd_id']);
        $this->assertParentValid($data['parent_id'] ?? null, (int) $data['opd_id'], $opdUnit);

        $opdUnit->update($data);

        return redirect()->route('master.opd.index')->with('success', 'Unit OPD berhasil diperbarui.');
    }

    public function destroy(Request $request, OpdUnit $opdUnit): RedirectResponse
    {
        abort_unless($this->canManageOpdUnits($request->user()), 403);
        $this->abortUnlessAllowedOpd($request->user(), (int) $opdUnit->opd_id);

        $opdUnit->delete();

        return redirect()->route('master.opd.index')->with('success', 'Unit OPD berhasil dihapus.');
    }

    private function shouldLimitToUserOpd(User $user): bool
    {
        return $user->hasRole('admin_opd')
            && ! $user->hasAnyRole([
                'super_admin',
                'admin_kabupaten_bagian_organisasi',
                'admin_kabupaten_bapperida',
                'admin_kabupaten_inspektorat',
                'admin_kabupaten_dinkominfo',
            ]);
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

    private function assertAllowedOpd(User $user, int $opdId): void
    {
        if ($this->shouldLimitToUserOpd($user) && (int) $user->opd_id !== $opdId) {
            throw ValidationException::withMessages(['opd_id' => 'OPD tidak sesuai dengan cakupan user.']);
        }
    }

    private function abortUnlessAllowedOpd(User $user, int $opdId): void
    {
        if ($this->shouldLimitToUserOpd($user) && (int) $user->opd_id !== $opdId) {
            abort(403);
        }
    }

    private function shouldLimitToUserUnit(User $user): bool
    {
        return $this->shouldLimitToUserOpd($user) && filled($user->opd_unit_id);
    }

    private function assertParentValid(mixed $parentId, int $opdId, ?OpdUnit $unit = null): void
    {
        if (! $parentId) {
            return;
        }

        $parent = OpdUnit::query()->findOrFail($parentId);

        if ((int) $parent->opd_id !== $opdId) {
            throw ValidationException::withMessages(['parent_id' => 'Induk unit harus berada pada OPD yang sama.']);
        }

        if (! $unit) {
            return;
        }

        $current = $parent;
        $visited = [];

        while ($current) {
            if ((int) $current->id === (int) $unit->id) {
                throw ValidationException::withMessages(['parent_id' => 'Induk unit tidak boleh membentuk siklus hierarki.']);
            }

            if (in_array($current->id, $visited, true)) {
                break;
            }

            $visited[] = $current->id;
            $current = $current->parent;
        }
    }

    private function serialize(OpdUnit $unit): array
    {
        return [
            'id' => $unit->id,
            'opd_id' => $unit->opd_id,
            'parent_id' => $unit->parent_id,
            'kode' => $unit->kode,
            'nama' => $unit->nama,
            'jenis_unit' => $unit->jenis_unit,
            'nama_pimpinan' => $unit->nama_pimpinan,
            'nip_pimpinan' => $unit->nip_pimpinan,
            'status' => $unit->status,
            'children_count' => $unit->children_count ?? null,
            'opd' => $unit->opd ? [
                'id' => $unit->opd->id,
                'kode' => $unit->opd->kode,
                'nama' => $unit->opd->nama,
                'singkatan' => $unit->opd->singkatan,
            ] : null,
            'parent' => $unit->parent ? [
                'id' => $unit->parent->id,
                'kode' => $unit->parent->kode,
                'nama' => $unit->parent->nama,
            ] : null,
        ];
    }

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

    private function parentOptions(User $user, ?OpdUnit $current = null): array
    {
        return OpdUnit::query()
            ->with('opd:id,nama,singkatan')
            ->when($this->shouldLimitToUserOpd($user), fn (Builder $query) => $query->where('opd_id', $user->opd_id))
            ->when($this->shouldLimitToUserUnit($user), fn (Builder $query) => $query->whereKey($user->opd_unit_id))
            ->when($current, fn (Builder $query) => $query->whereKeyNot($current->id))
            ->orderBy('opd_id')
            ->orderBy('kode')
            ->get(['id', 'opd_id', 'kode', 'nama'])
            ->map(fn (OpdUnit $unit) => [
                'id' => $unit->id,
                'opd_id' => $unit->opd_id,
                'label' => ($unit->opd?->singkatan ?: $unit->opd?->nama ?: 'OPD').' - '.$unit->kode.' - '.$unit->nama,
            ])
            ->all();
    }

    private function jenisOptions(): array
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
