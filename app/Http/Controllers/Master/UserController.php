<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Http\Requests\Master\StoreUserRequest;
use App\Http\Requests\Master\UpdateUserRequest;
use App\Models\Opd;
use App\Models\OpdUnit;
use App\Models\Role;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', User::class);

        $filters = $request->only(['search', 'status', 'role']);

        $users = User::query()
            ->with(['opd:id,nama,singkatan', 'opdUnit:id,opd_id,kode,nama,jenis_unit', 'roles:id,name,label'])
            ->when($filters['search'] ?? null, function ($query, string $search) {
                $query->where(function ($query) use ($search) {
                    $query->where('name', 'ilike', "%{$search}%")
                        ->orWhere('username', 'ilike', "%{$search}%")
                        ->orWhere('email', 'ilike', "%{$search}%")
                        ->orWhere('phone', 'ilike', "%{$search}%")
                        ->orWhere('jabatan', 'ilike', "%{$search}%")
                        ->orWhereHas('opdUnit', function ($query) use ($search) {
                            $query->where('nama', 'ilike', "%{$search}%")
                                ->orWhere('kode', 'ilike', "%{$search}%")
                                ->orWhere('jenis_unit', 'ilike', "%{$search}%");
                        });
                });
            })
            ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->when($filters['role'] ?? null, fn ($query, string $role) => $query->whereHas('roles', fn ($query) => $query->where('name', $role)))
            ->orderBy('name')
            ->paginate(10)
            ->withQueryString()
            ->through(fn (User $user) => $this->serializeUser($user));

        return Inertia::render('Master/User/Index', [
            'users' => $users,
            'filters' => $filters,
            'roleOptions' => $this->roleOptions(),
            'can' => [
                'create' => $request->user()->can('create', User::class),
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorize('create', User::class);

        return Inertia::render('Master/User/Form', [
            'mode' => 'create',
            'user' => null,
            'roleOptions' => $this->roleOptions(),
            'opdOptions' => $this->opdOptions($request->user()),
            'opdUnitOptions' => $this->opdUnitOptions($request->user()),
        ]);
    }

    public function store(StoreUserRequest $request, ActivityLogger $activityLogger): RedirectResponse
    {
        $validated = $request->validated();
        $roleIds = $validated['role_ids'];
        unset($validated['role_ids'], $validated['password_confirmation']);

        DB::transaction(function () use ($validated, $roleIds, $activityLogger) {
            $user = User::create($validated);
            $user->roles()->sync($roleIds);

            $activityLogger->log(
                action: 'roles_synced',
                model: $user,
                newValues: ['role_ids' => $roleIds],
                description: 'Role user disinkronkan saat pembuatan user.',
            );
        });

        return redirect()->route('master.users.index')->with('success', 'User berhasil ditambahkan.');
    }

    public function edit(Request $request, User $user): Response
    {
        $this->authorize('update', $user);

        return Inertia::render('Master/User/Form', [
            'mode' => 'edit',
            'user' => $this->serializeUser($user->load(['opd:id,nama,singkatan', 'opdUnit:id,opd_id,kode,nama,jenis_unit', 'roles:id,name,label'])),
            'roleOptions' => $this->roleOptions(),
            'opdOptions' => $this->opdOptions($request->user()),
            'opdUnitOptions' => $this->opdUnitOptions($request->user()),
        ]);
    }

    public function update(UpdateUserRequest $request, User $user, ActivityLogger $activityLogger): RedirectResponse
    {
        $validated = $request->validated();
        $roleIds = $validated['role_ids'];
        unset($validated['role_ids'], $validated['password_confirmation']);

        if (blank($validated['password'] ?? null)) {
            unset($validated['password']);
        }

        DB::transaction(function () use ($user, $validated, $roleIds, $activityLogger) {
            $oldRoleIds = $user->roles()->pluck('roles.id')->all();

            $user->update($validated);
            $user->roles()->sync($roleIds);

            $activityLogger->log(
                action: 'roles_synced',
                model: $user,
                oldValues: ['role_ids' => $oldRoleIds],
                newValues: ['role_ids' => $roleIds],
                description: 'Role user diperbarui.',
            );
        });

        return redirect()->route('master.users.index')->with('success', 'User berhasil diperbarui.');
    }

    public function destroy(User $user): RedirectResponse
    {
        $this->authorize('delete', $user);

        $user->delete();

        return redirect()->route('master.users.index')->with('success', 'User berhasil dihapus.');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function roleOptions(): array
    {
        return Role::query()
            ->orderBy('label')
            ->get(['id', 'name', 'label'])
            ->map(fn (Role $role) => [
                'id' => $role->id,
                'name' => $role->name,
                'label' => $role->label,
            ])
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function opdOptions(User $user): array
    {
        return Opd::query()
            ->where('status', 'active')
            ->when(
                $user->hasRole('admin_opd') && ! $user->hasAnyRole(['super_admin', 'admin_kabupaten_dinkominfo']),
                fn ($query) => $query->whereKey($user->opd_id ?? 0),
            )
            ->orderBy('nama')
            ->get(['id', 'nama', 'singkatan'])
            ->map(fn (Opd $opd) => [
                'id' => $opd->id,
                'label' => $opd->singkatan ? "{$opd->singkatan} - {$opd->nama}" : $opd->nama,
            ])
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function opdUnitOptions(User $user): array
    {
        return OpdUnit::query()
            ->with('opd:id,nama,singkatan')
            ->where('status', 'active')
            ->when(
                $user->hasRole('admin_opd') && ! $user->hasAnyRole(['super_admin', 'admin_kabupaten_dinkominfo']),
                fn ($query) => $query->where('opd_id', $user->opd_id ?? 0),
            )
            ->orderBy('opd_id')
            ->orderBy('kode')
            ->get(['id', 'opd_id', 'kode', 'nama', 'jenis_unit'])
            ->map(fn (OpdUnit $unit) => [
                'id' => $unit->id,
                'opd_id' => $unit->opd_id,
                'kode' => $unit->kode,
                'nama' => $unit->nama,
                'jenis_unit' => $unit->jenis_unit,
                'label' => "{$unit->kode} - {$unit->nama}",
                'opd_label' => $unit->opd?->singkatan ?: $unit->opd?->nama,
            ])
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeUser(User $user): array
    {
        return [
            'id' => $user->id,
            'opd_id' => $user->opd_id,
            'opd_unit_id' => $user->opd_unit_id,
            'opd' => $user->opd ? [
                'id' => $user->opd->id,
                'nama' => $user->opd->nama,
                'singkatan' => $user->opd->singkatan,
            ] : null,
            'opd_unit' => $user->opdUnit ? [
                'id' => $user->opdUnit->id,
                'opd_id' => $user->opdUnit->opd_id,
                'kode' => $user->opdUnit->kode,
                'nama' => $user->opdUnit->nama,
                'jenis_unit' => $user->opdUnit->jenis_unit,
            ] : null,
            'name' => $user->name,
            'username' => $user->username,
            'email' => $user->email,
            'phone' => $user->phone,
            'jabatan' => $user->jabatan,
            'status' => $user->status,
            'last_login_at' => $user->last_login_at?->toISOString(),
            'roles' => $user->roles->map(fn (Role $role) => [
                'id' => $role->id,
                'name' => $role->name,
                'label' => $role->label,
            ])->values(),
            'role_ids' => $user->roles->pluck('id')->values(),
        ];
    }
}
