<?php

namespace App\Policies;

use App\Models\Role;
use App\Models\User;

class RolePolicy
{
    public function viewAny(User $user): bool
    {
        return $this->canAccessRolePermissions($user);
    }

    public function view(User $user, Role $role): bool
    {
        return $this->canAccessRolePermissions($user);
    }

    public function update(User $user, Role $role): bool
    {
        return $this->canAccessRolePermissions($user);
    }

    private function canAccessRolePermissions(User $user): bool
    {
        return $user->hasAnyRole(['super_admin', 'admin_kabupaten_dinkominfo']);
    }
}
