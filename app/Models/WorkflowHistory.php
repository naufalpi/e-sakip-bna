<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkflowHistory extends Model
{
    use LogsActivity;

    protected $table = 'workflow_histories';

    protected $fillable = [
        'workflow_submission_id',
        'related_table',
        'related_id',
        'module',
        'from_status',
        'to_status',
        'action',
        'actor_id',
        'reviewer_id',
        'notes',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
        ];
    }

    public function workflowSubmission(): BelongsTo
    {
        return $this->belongsTo(WorkflowSubmission::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
