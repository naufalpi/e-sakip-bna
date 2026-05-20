<?php

namespace App\Policies\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

trait PreventsLockedChanges
{
    protected function canChangeLocked(User $user, Model $model): bool
    {
        return $user->isSuperAdmin()
            || (string) ($model->getAttribute('status') ?? '') !== 'locked';
    }
}
