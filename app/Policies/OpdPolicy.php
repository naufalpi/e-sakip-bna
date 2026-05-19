<?php

namespace App\Policies;

use App\Models\Opd;
use App\Models\User;

class OpdPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('opd.view');
    }

    public function view(User $user, Opd $opd): bool
    {
        if (! $user->hasPermission('opd.view')) {
            return false;
        }

        if ($user->hasRole('admin_opd')) {
            return $user->opd_id === $opd->id;
        }

        return true;
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('opd.manage');
    }

    public function update(User $user, Opd $opd): bool
    {
        return $user->hasPermission('opd.manage');
    }

    public function delete(User $user, Opd $opd): bool
    {
        return $user->hasPermission('opd.manage');
    }
}
