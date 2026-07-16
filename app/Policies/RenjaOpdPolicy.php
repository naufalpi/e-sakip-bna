<?php

namespace App\Policies;

use App\Models\RenjaOpd;
use App\Models\User;
use App\Policies\Concerns\PreventsLockedChanges;

class RenjaOpdPolicy
{
    use PreventsLockedChanges;

    public function viewAny(User $user): bool
    {
        return $user->hasAnyPermission(['renja.view', 'renja.manage']);
    }

    public function view(User $user, RenjaOpd $renjaOpd): bool
    {
        if ($user->hasAnyRole([
            'super_admin',
            'admin_kabupaten_bagian_organisasi',
            'admin_kabupaten_bapperida',
            'admin_kabupaten_inspektorat',
            'pimpinan',
        ])) {
            return $user->hasPermission('renja.view') || $user->hasPermission('renja.manage');
        }

        return $user->hasRole('admin_opd')
            && $user->canAccessOpd($renjaOpd->opd_id)
            && $user->canAccessOpdUnit($renjaOpd->opd_unit_id);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('renja.manage');
    }

    public function update(User $user, RenjaOpd $renjaOpd): bool
    {
        if (! $this->canChangeLocked($user, $renjaOpd)) {
            return false;
        }

        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->hasPermission('renja.manage')
            && $user->hasRole('admin_opd')
            && $user->canAccessOpd($renjaOpd->opd_id)
            && $user->canAccessOpdUnit($renjaOpd->opd_unit_id);
    }

    public function delete(User $user, RenjaOpd $renjaOpd): bool
    {
        return $this->update($user, $renjaOpd);
    }
}
