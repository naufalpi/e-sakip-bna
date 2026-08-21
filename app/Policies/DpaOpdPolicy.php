<?php

namespace App\Policies;

use App\Models\DpaOpd;
use App\Models\User;
use App\Policies\Concerns\PreventsLockedChanges;

class DpaOpdPolicy
{
    use PreventsLockedChanges;

    public function viewAny(User $user): bool
    {
        return $user->hasAnyPermission(['dpa.view', 'dpa.manage', 'dpa.verify']);
    }

    public function view(User $user, DpaOpd $dpaOpd): bool
    {
        if ($user->isSuperAdmin() || $user->hasAnyRole([
            'admin_kabupaten_bagian_organisasi',
            'admin_kabupaten_bapperida',
            'admin_kabupaten_bpkad',
            'admin_kabupaten_inspektorat',
            'pimpinan',
        ])) {
            return $user->hasAnyPermission(['dpa.view', 'dpa.manage', 'dpa.verify']);
        }

        return $user->hasRole('admin_opd')
            && $user->hasAnyPermission(['dpa.view', 'dpa.manage'])
            && $user->canAccessOpd($dpaOpd->opd_id)
            && $user->canAccessOpdUnit($dpaOpd->opd_unit_id);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('dpa.manage');
    }

    public function update(User $user, DpaOpd $dpaOpd): bool
    {
        if (! in_array($dpaOpd->status, ['draft', 'revision', 'rejected'], true)
            || ! $this->canChangeLocked($user, $dpaOpd)
            || ! $user->hasPermission('dpa.manage')) {
            return false;
        }

        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->hasRole('admin_opd')
            && $user->canAccessOpd($dpaOpd->opd_id)
            && $user->canAccessOpdUnit($dpaOpd->opd_unit_id);
    }

    public function delete(User $user, DpaOpd $dpaOpd): bool
    {
        return $this->update($user, $dpaOpd);
    }

    public function verifyBudget(User $user, DpaOpd $dpaOpd): bool
    {
        return in_array($dpaOpd->status, ['submitted', 'verified'], true)
            && ($user->isSuperAdmin() || $user->hasPermission('dpa.verify'));
    }
}
