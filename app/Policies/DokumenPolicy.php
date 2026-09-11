<?php

namespace App\Policies;

use App\Models\Dokumen;
use App\Models\User;

class DokumenPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyPermission(['dokumen.view', 'dokumen.manage', 'manage_dokumen']);
    }

    public function view(User $user, Dokumen $dokumen): bool
    {
        if (! $this->viewAny($user)) {
            return false;
        }

        if (! $user->hasRole('admin_opd')) {
            return true;
        }

        return filled($user->opd_id)
            && (
                (int) $dokumen->opd_id === (int) $user->opd_id
                || (int) $dokumen->uploaded_by === (int) $user->id
            );
    }

    public function create(User $user): bool
    {
        return $user->hasAnyPermission(['dokumen.manage', 'manage_dokumen']);
    }

    public function update(User $user, Dokumen $dokumen): bool
    {
        if (! $this->create($user)) {
            return false;
        }

        if (! $user->hasRole('admin_opd')) {
            return true;
        }

        return filled($user->opd_id)
            && (
                (int) $dokumen->opd_id === (int) $user->opd_id
                || (int) $dokumen->uploaded_by === (int) $user->id
            );
    }

    public function delete(User $user, Dokumen $dokumen): bool
    {
        return $this->update($user, $dokumen);
    }

    public function download(User $user, Dokumen $dokumen): bool
    {
        return $this->view($user, $dokumen);
    }
}
