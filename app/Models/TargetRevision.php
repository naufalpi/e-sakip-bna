<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TargetRevision extends Model
{
    use LogsActivity;

    protected $fillable = [
        'module',
        'target_table',
        'target_id',
        'owner_table',
        'owner_id',
        'opd_id',
        'status',
        'old_values',
        'new_values',
        'reason',
        'document_number',
        'document_date',
        'dokumen_id',
        'requested_by',
        'reviewed_by',
        'reviewed_at',
        'review_note',
        'applied_at',
    ];

    protected function casts(): array
    {
        return [
            'old_values' => 'array',
            'new_values' => 'array',
            'document_date' => 'date',
            'reviewed_at' => 'datetime',
            'applied_at' => 'datetime',
        ];
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function opd(): BelongsTo
    {
        return $this->belongsTo(Opd::class);
    }

    public function dokumen(): BelongsTo
    {
        return $this->belongsTo(Dokumen::class);
    }
}
