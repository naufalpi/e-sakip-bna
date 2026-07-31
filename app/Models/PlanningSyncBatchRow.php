<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlanningSyncBatchRow extends Model
{
    protected $fillable = [
        'planning_sync_batch_id',
        'source_table',
        'source_id',
        'target_table',
        'target_id',
        'match_key',
        'action',
        'diff_values',
        'selected',
        'status',
        'message',
    ];

    protected function casts(): array
    {
        return [
            'diff_values' => 'array',
            'selected' => 'boolean',
        ];
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(PlanningSyncBatch::class, 'planning_sync_batch_id');
    }
}
