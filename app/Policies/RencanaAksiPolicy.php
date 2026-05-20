<?php

namespace App\Policies;

use App\Models\RencanaAksi;
use App\Models\User;

class RencanaAksiPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyPermission(['kinerja.view', 'kinerja.manage', 'manage_rencana_aksi']);
    }

    public function view(User $user, RencanaAksi $rencanaAksi): bool
    {
        return $this->canViewAll($user)
            || ((int) $rencanaAksi->opd_id === (int) $user->opd_id && $user->hasRole('admin_opd'));
    }

    public function create(User $user): bool
    {
        return $user->hasAnyPermission(['kinerja.manage', 'manage_rencana_aksi']);
    }

    public function update(User $user, RencanaAksi $rencanaAksi): bool
    {
        if (! $this->create($user)) {
            return false;
        }

        return $user->hasRole('super_admin')
            || ((int) $rencanaAksi->opd_id === (int) $user->opd_id && $user->hasRole('admin_opd'));
    }

    public function delete(User $user, RencanaAksi $rencanaAksi): bool
    {
        return $this->update($user, $rencanaAksi);
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
