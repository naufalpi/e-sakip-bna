<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkflowSubmission extends Model
{
    use LogsActivity;

    protected $fillable = [
        'related_table',
        'related_id',
        'module',
        'status',
        'submitted_by',
        'current_reviewer_id',
        'submitted_at',
        'reviewed_at',
        'note',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function currentReviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'current_reviewer_id');
    }

    public function histories(): HasMany
    {
        return $this->hasMany(WorkflowHistory::class);
    }
}
