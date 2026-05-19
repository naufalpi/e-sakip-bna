<?php

namespace App\Policies;

use App\Models\Rpjmd;
use App\Models\User;

class RpjmdPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyPermission(['rpjmd.view', 'view_rpjmd', 'rpjmd.manage', 'manage_rpjmd']);
    }

    public function view(User $user, Rpjmd $rpjmd): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->hasAnyPermission(['rpjmd.manage', 'manage_rpjmd']);
    }

    public function update(User $user, Rpjmd $rpjmd): bool
    {
        return $user->hasAnyPermission(['rpjmd.manage', 'manage_rpjmd']);
    }

    public function delete(User $user, Rpjmd $rpjmd): bool
    {
        return $user->hasAnyPermission(['rpjmd.manage', 'manage_rpjmd']);
    }
}
