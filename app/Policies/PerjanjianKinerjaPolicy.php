<?php

namespace App\Policies;

use App\Models\PerjanjianKinerja;
use App\Models\User;
use App\Policies\Concerns\PreventsLockedChanges;

class PerjanjianKinerjaPolicy
{
    use PreventsLockedChanges;

    public function viewAny(User $user): bool
    {
        return $user->hasAnyPermission(['kinerja.view', 'kinerja.manage', 'manage_perjanjian_kinerja']);
    }

    public function view(User $user, PerjanjianKinerja $perjanjianKinerja): bool
    {
        return $this->canViewAll($user)
            || ((int) $perjanjianKinerja->opd_id === (int) $user->opd_id && $user->hasRole('admin_opd'));
    }

    public function create(User $user): bool
    {
        return $user->hasAnyPermission(['kinerja.manage', 'manage_perjanjian_kinerja']);
    }

    public function update(User $user, PerjanjianKinerja $perjanjianKinerja): bool
    {
        if (! $this->canChangeLocked($user, $perjanjianKinerja)) {
            return false;
        }

        if (! $this->create($user)) {
            return false;
        }

        return $user->hasRole('super_admin')
            || ((int) $perjanjianKinerja->opd_id === (int) $user->opd_id && $user->hasRole('admin_opd'));
    }

    public function delete(User $user, PerjanjianKinerja $perjanjianKinerja): bool
    {
        return $this->update($user, $perjanjianKinerja);
    }

    private function canViewAll(User $user): bool
    {
        return $user->hasAnyRole([
            'super_admin',
            'admin_kabupaten_bagian_organisasi',
            'admin_kabupaten_bapperida',
            'admin_kabupaten_inspektorat',
        ]);
    }
}
