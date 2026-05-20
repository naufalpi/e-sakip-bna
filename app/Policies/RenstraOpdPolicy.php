<?php

namespace App\Policies;

use App\Models\RenstraOpd;
use App\Models\User;

class RenstraOpdPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyPermission(['renstra.view', 'view_renstra_opd', 'renstra.manage', 'manage_renstra_opd']);
    }

    public function view(User $user, RenstraOpd $renstraOpd): bool
    {
        if ($this->canViewAllRenstra($user)) {
            return true;
        }

        return $user->hasRole('admin_opd')
            && filled($user->opd_id)
            && (int) $renstraOpd->opd_id === (int) $user->opd_id;
    }

    public function create(User $user): bool
    {
        return $user->hasAnyPermission(['renstra.manage', 'manage_renstra_opd']);
    }

    public function update(User $user, RenstraOpd $renstraOpd): bool
    {
        if (! $user->hasAnyPermission(['renstra.manage', 'manage_renstra_opd'])) {
            return false;
        }

        if ($this->canManageAllRenstra($user)) {
            return true;
        }

        return $user->hasRole('admin_opd')
            && filled($user->opd_id)
            && (int) $renstraOpd->opd_id === (int) $user->opd_id;
    }

    public function delete(User $user, RenstraOpd $renstraOpd): bool
    {
        return $this->update($user, $renstraOpd);
    }

    private function canViewAllRenstra(User $user): bool
    {
        return $user->hasAnyRole([
            'super_admin',
            'admin_kabupaten_bagian_organisasi',
            'admin_kabupaten_bapperida',
            'admin_kabupaten_inspektorat',
        ]);
    }

    private function canManageAllRenstra(User $user): bool
    {
        return $user->hasAnyRole([
            'super_admin',
        ]);
    }
}
