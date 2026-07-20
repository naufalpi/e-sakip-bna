<?php

namespace App\Policies;

use App\Models\Role;
use App\Models\User;

class RolePolicy
{
    public function viewAny(User $user): bool
    {
        return $this->canViewRolePermissions($user);
    }

    public function view(User $user, Role $role): bool
    {
        return $this->canViewRolePermissions($user);
    }

    public function update(User $user, Role $role): bool
    {
        return $this->canManageRolePermissions($user);
    }

    private function canViewRolePermissions(User $user): bool
    {
        return $user->isSuperAdmin()
            || ($user->hasRole('admin_kabupaten_dinkominfo')
                && $user->hasAnyPermission(['roles.view', 'roles.manage', 'manage_roles']));
    }

    private function canManageRolePermissions(User $user): bool
    {
        return $user->isSuperAdmin()
            || ($user->hasRole('admin_kabupaten_dinkominfo')
                && $user->hasAnyPermission(['roles.manage', 'manage_roles']));
    }
}
