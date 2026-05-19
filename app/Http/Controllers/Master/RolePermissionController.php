<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Models\Role;
use Inertia\Inertia;
use Inertia\Response;

class RolePermissionController extends Controller
{
    public function __invoke(): Response
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

        return Inertia::render('Master/RolePermission/Index', [
            'roles' => $roles,
        ]);
    }
}
