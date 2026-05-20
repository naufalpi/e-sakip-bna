<?php

namespace App\Policies;

use App\Models\Rpjmd;
use App\Models\User;

class RpjmdPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyPermission(['rpjmd.view', 'view_rpjmd', 'rpjmd.manage', 'manage_rpjmd']);
    }

    public function view(User $user, Rpjmd $rpjmd): bool
    {
        if ($this->canViewAllRpjmd($user)) {
            return true;
        }

        if ($user->hasRole('admin_opd')) {
            return filled($user->opd_id)
                && Rpjmd::query()
                    ->whereKey($rpjmd->id)
                    ->whereHas('visi.misi.tujuan.sasaran.strategi.programs.opdPenanggungJawab', function ($query) use ($user) {
                        $query->where('opds.id', $user->opd_id);
                    })
                    ->exists();
        }

        return false;
    }

    public function create(User $user): bool
    {
        return $user->hasAnyPermission(['rpjmd.manage', 'manage_rpjmd']);
    }

    public function update(User $user, Rpjmd $rpjmd): bool
    {
        return $user->hasAnyPermission(['rpjmd.manage', 'manage_rpjmd']);
    }

    public function delete(User $user, Rpjmd $rpjmd): bool
    {
        return $user->hasAnyPermission(['rpjmd.manage', 'manage_rpjmd']);
    }

    private function canViewAllRpjmd(User $user): bool
    {
        return $user->hasAnyPermission(['rpjmd.manage', 'manage_rpjmd'])
            || $user->hasAnyRole([
                'super_admin',
                'admin_kabupaten_bapperida',
                'admin_kabupaten_bagian_organisasi',
                'admin_kabupaten_inspektorat',
                'pimpinan',
            ]);
    }
}
