<?php

namespace App\Policies;

use App\Models\Rkpd;
use App\Models\User;
use App\Policies\Concerns\PreventsLockedChanges;

class RkpdPolicy
{
    use PreventsLockedChanges;

    public function viewAny(User $user): bool
    {
        return $user->hasAnyPermission(['rkpd.view', 'rkpd.manage']);
    }

    public function view(User $user, Rkpd $rkpd): bool
    {
        return $user->hasAnyPermission(['rkpd.view', 'rkpd.manage']);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('rkpd.manage');
    }

    public function update(User $user, Rkpd $rkpd): bool
    {
        if (! $this->canChangeLocked($user, $rkpd)) {
            return false;
        }

        return $user->hasPermission('rkpd.manage');
    }

    public function delete(User $user, Rkpd $rkpd): bool
    {
        return $this->update($user, $rkpd);
    }
}
