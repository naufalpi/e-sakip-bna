<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\ActivityLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class RolePermissionController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $this->authorize('viewAny', Role::class);

        $roles = Role::query()
            ->with(['permissions' => fn ($query) => $query->orderBy('module')->orderBy('label')])
            ->orderBy('label')
            ->get()
            ->map(fn (Role $role) => [
                'id' => $role->id,
                'name' => $role->name,
                'label' => $role->label,
                'description' => $role->description,
                'permission_ids' => $role->permissions->pluck('id')->values(),
                'can_edit' => $role->name !== 'super_admin',
                'permissions' => $role->permissions
                    ->groupBy('module')
                    ->map(fn ($permissions, string $module) => [
                        'module' => $module,
                        'items' => $permissions->map(fn ($permission) => [
                            'id' => $permission->id,
                            'name' => $permission->name,
                            'label' => $permission->label,
                        ])->values(),
                    ])
                    ->values(),
            ]);

        $permissionGroups = Permission::query()
            ->orderBy('module')
            ->orderBy('label')
            ->get(['id', 'name', 'label', 'module', 'description'])
            ->groupBy('module')
            ->map(fn ($permissions, string $module) => [
                'module' => $module,
                'items' => $permissions->map(fn (Permission $permission) => [
                    'id' => $permission->id,
                    'name' => $permission->name,
                    'label' => $permission->label,
                    'description' => $permission->description,
                ])->values(),
            ])
            ->values();

        return Inertia::render('Master/RolePermission/Index', [
            'roles' => $roles,
            'permissionGroups' => $permissionGroups,
            'can' => [
                'manage' => $this->canAccessRolePermissions($request->user()),
            ],
        ]);
    }

    public function update(Request $request, Role $role, ActivityLogService $activityLogService): RedirectResponse
    {
        $this->authorize('update', $role);

        if ($role->name === 'super_admin') {
            throw ValidationException::withMessages([
                'role' => 'Permission super_admin tidak dapat dikurangi dari halaman ini.',
            ]);
        }

        $data = $request->validate([
            'permission_ids' => ['array'],
            'permission_ids.*' => ['integer', 'exists:permissions,id'],
        ]);

        $oldPermissionIds = $role->permissions()
            ->pluck('permissions.id')
            ->sort()
            ->values()
            ->all();
        $newPermissionIds = collect($data['permission_ids'] ?? [])
            ->unique()
            ->sort()
            ->values()
            ->all();

        $role->permissions()->sync($newPermissionIds);

        $activityLogService->log(
            action: 'permissions_synced',
            model: $role,
            oldValues: ['permission_ids' => $oldPermissionIds],
            newValues: ['permission_ids' => $newPermissionIds],
            description: "Permission role {$role->name} diperbarui."
        );

        return back()->with('success', 'Permission role berhasil diperbarui.');
    }

    private function canAccessRolePermissions(User $user): bool
    {
        return $user->isSuperAdmin()
            || ($user->hasRole('admin_kabupaten_dinkominfo')
                && $user->hasAnyPermission(['roles.manage', 'manage_roles']));
    }
}
