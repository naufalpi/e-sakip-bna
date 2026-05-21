<?php

namespace App\Models\Concerns;

use App\Models\TargetTriwulanIndikator;
use Illuminate\Database\Eloquent\Relations\HasMany;

trait HasTargetTriwulan
{
    public function targetTriwulan(): HasMany
    {
        return $this->hasMany(TargetTriwulanIndikator::class, 'related_id')
            ->where('related_table', $this->getTable())
            ->orderBy('triwulan');
    }
}
