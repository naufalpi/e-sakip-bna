<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PlanningSyncBatch extends Model
{
    protected $fillable = [
        'source_module',
        'target_module',
        'source_id',
        'target_id',
        'tahun',
        'status',
        'filters',
        'summary',
        'created_by',
        'applied_by',
        'applied_at',
    ];

    protected function casts(): array
    {
        return [
            'filters' => 'array',
            'summary' => 'array',
            'applied_at' => 'datetime',
        ];
    }

    public function rows(): HasMany
    {
        return $this->hasMany(PlanningSyncBatchRow::class)->orderBy('id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function appliedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'applied_by');
    }
}
