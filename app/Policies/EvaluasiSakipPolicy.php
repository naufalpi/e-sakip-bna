<?php

namespace App\Policies;

use App\Models\EvaluasiSakip;
use App\Models\User;
use App\Policies\Concerns\PreventsLockedChanges;

class EvaluasiSakipPolicy
{
    use PreventsLockedChanges;

    public function viewAny(User $user): bool
    {
        return $user->hasAnyPermission(['evaluasi.view', 'evaluasi.manage', 'manage_evaluasi']);
    }

    public function view(User $user, EvaluasiSakip $evaluasiSakip): bool
    {
        if ($this->canViewAll($user)) {
            return true;
        }

        return $user->hasRole('admin_opd')
            && filled($user->opd_id)
            && (int) $evaluasiSakip->opd_id === (int) $user->opd_id;
    }

    public function create(User $user): bool
    {
        return $user->hasAnyPermission(['evaluasi.manage', 'manage_evaluasi'])
            && $user->hasAnyRole(['super_admin', 'admin_kabupaten_inspektorat']);
    }

    public function update(User $user, EvaluasiSakip $evaluasiSakip): bool
    {
        if (! $this->canChangeLocked($user, $evaluasiSakip)) {
            return false;
        }

        return $this->create($user);
    }

    public function delete(User $user, EvaluasiSakip $evaluasiSakip): bool
    {
        return $this->update($user, $evaluasiSakip);
    }

    public function tindakLanjut(User $user, EvaluasiSakip $evaluasiSakip): bool
    {
        if (! $this->canChangeLocked($user, $evaluasiSakip)) {
            return false;
        }

        return $user->hasRole('admin_opd')
            && filled($user->opd_id)
            && (int) $evaluasiSakip->opd_id === (int) $user->opd_id;
    }

    private function canViewAll(User $user): bool
    {
        return $user->hasAnyRole([
            'super_admin',
            'admin_kabupaten_inspektorat',
            'admin_kabupaten_bagian_organisasi',
        ]);
    }
}
