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
        if (! $this->viewAny($user)) {
            return false;
        }

        if (! $user->hasRole('admin_opd')) {
            return true;
        }

        return filled($user->opd_id)
            && (int) $renstraOpd->opd_id === (int) $user->opd_id;
    }

    public function create(User $user): bool
    {
        return $user->hasAnyPermission(['renstra.manage', 'manage_renstra_opd']);
    }

    public function viewCompletenessDiagnostics(User $user): bool
    {
        return $user->hasAnyRole([
            'super_admin',
            'admin_kabupaten_bagian_organisasi',
            'admin_kabupaten_bapperida',
        ]);
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

        if (! $user->hasRole('admin_opd')) {
            return true;
        }

        return filled($user->opd_id)
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

        if (! $user->hasAnyPermission(['renstra.manage', 'manage_renstra_opd'])) {
            return false;
        }

        if (! $user->hasRole('admin_opd')) {
            return true;
        }

        return filled($user->opd_id)
            && (int) $renstraOpd->opd_id === (int) $user->opd_id;
    }

    public function cancelRevision(User $user, RenstraOpd $renstraOpd): bool
    {
        return $renstraOpd->jenis_versi === 'perubahan'
            && in_array((string) $renstraOpd->status, ['draft', 'revision', 'rejected'], true)
            && $this->update($user, $renstraOpd);
    }
}
