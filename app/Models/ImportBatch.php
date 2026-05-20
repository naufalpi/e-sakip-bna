<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ImportBatch extends Model
{
    use LogsActivity;

    protected $fillable = [
        'module',
        'import_type',
        'status',
        'original_filename',
        'mime_type',
        'file_size',
        'storage_disk',
        'storage_path',
        'uploaded_by',
        'total_rows',
        'preview_rows',
        'metadata',
        'error_message',
    ];

    protected function casts(): array
    {
        return [
            'file_size' => 'integer',
            'total_rows' => 'integer',
            'preview_rows' => 'integer',
            'metadata' => 'array',
        ];
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function rows(): HasMany
    {
        return $this->hasMany(ImportBatchRow::class)->orderBy('row_number');
    }
}
