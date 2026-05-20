<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ImportBatchRow extends Model
{
    protected $fillable = [
        'import_batch_id',
        'row_number',
        'status',
        'raw_data',
        'normalized_data',
        'error_message',
    ];

    protected function casts(): array
    {
        return [
            'row_number' => 'integer',
            'raw_data' => 'array',
            'normalized_data' => 'array',
        ];
    }

    public function importBatch(): BelongsTo
    {
        return $this->belongsTo(ImportBatch::class);
    }
}
