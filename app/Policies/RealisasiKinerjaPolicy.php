<?php

namespace App\Policies;

use App\Models\RealisasiKinerja;
use App\Models\User;
use App\Policies\Concerns\PreventsLockedChanges;

class RealisasiKinerjaPolicy
{
    use PreventsLockedChanges;

    public function viewAny(User $user): bool
    {
        return $user->hasAnyPermission(['kinerja.view', 'kinerja.manage', 'input_realisasi', 'verify_realisasi']);
    }

    public function view(User $user, RealisasiKinerja $realisasiKinerja): bool
    {
        if (! $this->viewAny($user)) {
            return false;
        }

        return ! $user->hasRole('admin_opd')
            || (int) $realisasiKinerja->opd_id === (int) $user->opd_id;
    }

    public function create(User $user): bool
    {
        return $user->hasAnyPermission(['kinerja.manage', 'input_realisasi']);
    }

    public function update(User $user, RealisasiKinerja $realisasiKinerja): bool
    {
        if (! $this->canChangeLocked($user, $realisasiKinerja)) {
            return false;
        }

        if (! $this->create($user)) {
            return false;
        }

        return ! $user->hasRole('admin_opd')
            || (int) $realisasiKinerja->opd_id === (int) $user->opd_id;
    }

    public function delete(User $user, RealisasiKinerja $realisasiKinerja): bool
    {
        return $this->update($user, $realisasiKinerja);
    }
}
