<?php

namespace App\Policies;

use App\Models\RkaOpd;
use App\Models\User;
use App\Policies\Concerns\PreventsLockedChanges;

class RkaOpdPolicy
{
    use PreventsLockedChanges;

    public function viewAny(User $user): bool
    {
        return $user->hasAnyPermission(['rka.view', 'rka.manage', 'rka.verify']);
    }

    public function view(User $user, RkaOpd $rkaOpd): bool
    {
        if ($user->isSuperAdmin() || $user->hasAnyRole([
            'admin_kabupaten_bagian_organisasi',
            'admin_kabupaten_bapperida',
            'admin_kabupaten_inspektorat',
            'pimpinan',
        ])) {
            return $user->hasAnyPermission(['rka.view', 'rka.manage', 'rka.verify']);
        }

        return $user->hasRole('admin_opd')
            && $user->hasAnyPermission(['rka.view', 'rka.manage'])
            && $user->canAccessOpd($rkaOpd->opd_id)
            && $user->canAccessOpdUnit($rkaOpd->opd_unit_id);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('rka.manage');
    }

    public function update(User $user, RkaOpd $rkaOpd): bool
    {
        if (! in_array($rkaOpd->status, ['draft', 'revision', 'rejected'], true)
            || ! $this->canChangeLocked($user, $rkaOpd)
            || ! $user->hasPermission('rka.manage')) {
            return false;
        }

        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->hasRole('admin_opd')
            && $user->canAccessOpd($rkaOpd->opd_id)
            && $user->canAccessOpdUnit($rkaOpd->opd_unit_id);
    }

    public function delete(User $user, RkaOpd $rkaOpd): bool
    {
        return $this->update($user, $rkaOpd);
    }

    public function verifyBudget(User $user, RkaOpd $rkaOpd): bool
    {
        return in_array($rkaOpd->status, ['submitted', 'verified'], true)
            && ($user->isSuperAdmin() || $user->hasPermission('rka.verify'));
    }
}
