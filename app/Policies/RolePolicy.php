<?php

namespace App\Policies;

use App\Models\Role;
use App\Models\User;

class RolePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyPermission(['roles.view', 'manage_roles']);
    }

    public function view(User $user, Role $role): bool
    {
        return $user->hasAnyPermission(['roles.view', 'manage_roles']);
    }

    public function update(User $user, Role $role): bool
    {
        return $user->hasPermission('manage_roles');
    }
}
