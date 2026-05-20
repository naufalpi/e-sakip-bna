<?php

namespace App\Policies;

use App\Models\Lkjip;
use App\Models\User;
use App\Policies\Concerns\PreventsLockedChanges;

class LkjipPolicy
{
    use PreventsLockedChanges;

    public function viewAny(User $user): bool
    {
        return $user->hasAnyPermission(['lkjip.view', 'lkjip.manage', 'laporan.view', 'export_laporan']);
    }

    public function view(User $user, Lkjip $lkjip): bool
    {
        if (! $this->viewAny($user)) {
            return false;
        }

        return $this->canViewAll($user)
            || ((int) $lkjip->opd_id === (int) $user->opd_id && $user->hasRole('admin_opd'));
    }

    public function create(User $user): bool
    {
        return $user->hasAnyPermission(['lkjip.manage', 'laporan.manage']);
    }

    public function update(User $user, Lkjip $lkjip): bool
    {
        if (! $this->canChangeLocked($user, $lkjip)) {
            return false;
        }

        if (! $this->create($user)) {
            return false;
        }

        return $user->hasRole('super_admin')
            || ((int) $lkjip->opd_id === (int) $user->opd_id && $user->hasRole('admin_opd'));
    }

    public function delete(User $user, Lkjip $lkjip): bool
    {
        return $this->update($user, $lkjip);
    }

    private function canViewAll(User $user): bool
    {
        return $user->hasAnyRole([
            'super_admin',
            'admin_kabupaten_bagian_organisasi',
            'admin_kabupaten_bapperida',
            'admin_kabupaten_inspektorat',
            'pimpinan',
        ]);
    }
}
