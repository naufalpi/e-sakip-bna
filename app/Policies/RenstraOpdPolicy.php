<?php

namespace App\Policies;

use App\Models\RenstraOpd;
use App\Models\User;
use App\Policies\Concerns\PreventsLockedChanges;

class RenstraOpdPolicy
{
    use PreventsLockedChanges;

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
        if ($renstraOpd->isArchivedVersion()) {
            return false;
        }

        if (! $this->canChangeLocked($user, $renstraOpd)) {
            return false;
        }

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

    public function createRevision(User $user, RenstraOpd $renstraOpd): bool
    {
        if (! $renstraOpd->is_active_version || ! in_array($renstraOpd->status, ['approved', 'locked'], true)) {
            return false;
        }

        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->hasAnyPermission(['renstra.manage', 'manage_renstra_opd'])
            && filled($user->opd_id)
            && (int) $renstraOpd->opd_id === (int) $user->opd_id;
    }

    public function cancelRevision(User $user, RenstraOpd $renstraOpd): bool
    {
        return $renstraOpd->jenis_versi === 'perubahan'
            && in_array((string) $renstraOpd->status, ['draft', 'revision', 'rejected'], true)
            && $this->update($user, $renstraOpd);
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
