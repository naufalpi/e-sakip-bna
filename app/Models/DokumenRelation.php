<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class DokumenRelation extends Model
{
    use LogsActivity;

    protected $fillable = [
        'dokumen_id',
        'related_type',
        'related_id',
        'label',
        'metadata',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
        ];
    }

    public function dokumen(): BelongsTo
    {
        return $this->belongsTo(Dokumen::class);
    }

    public function related(): MorphTo
    {
        return $this->morphTo(__FUNCTION__, 'related_type', 'related_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
