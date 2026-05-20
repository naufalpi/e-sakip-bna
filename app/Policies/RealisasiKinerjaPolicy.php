<?php

namespace App\Policies;

use App\Models\RealisasiKinerja;
use App\Models\User;

class RealisasiKinerjaPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyPermission(['kinerja.view', 'kinerja.manage', 'input_realisasi', 'verify_realisasi']);
    }

    public function view(User $user, RealisasiKinerja $realisasiKinerja): bool
    {
        return $this->canViewAll($user)
            || ((int) $realisasiKinerja->opd_id === (int) $user->opd_id && $user->hasRole('admin_opd'));
    }

    public function create(User $user): bool
    {
        return $user->hasAnyPermission(['kinerja.manage', 'input_realisasi']);
    }

    public function update(User $user, RealisasiKinerja $realisasiKinerja): bool
    {
        if (! $this->create($user)) {
            return false;
        }

        return $user->hasRole('super_admin')
            || ((int) $realisasiKinerja->opd_id === (int) $user->opd_id && $user->hasRole('admin_opd'));
    }

    public function delete(User $user, RealisasiKinerja $realisasiKinerja): bool
    {
        return $this->update($user, $realisasiKinerja);
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
