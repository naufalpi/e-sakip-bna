<?php

namespace App\Policies\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

trait PreventsLockedChanges
{
    /**
     * Data yang sudah masuk review atau final tidak boleh diubah user biasa.
     * Perubahan resmi harus lewat aksi revisi workflow terlebih dahulu.
     */
    protected function canChangeLocked(User $user, Model $model): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return in_array((string) ($model->getAttribute('status') ?? 'draft'), [
            'draft',
            'active',
            'revision',
            'rejected',
        ], true);
    }
}
