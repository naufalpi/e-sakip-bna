<?php

namespace App\Services\Workflow;

use App\Models\User;
use App\Models\WorkflowSubmission;
use Illuminate\Database\Eloquent\Model;

class ApproveWorkflowService
{
    public function __construct(private readonly WorkflowTransitionService $workflowTransitionService) {}

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function handle(Model $model, string $module, User $actor, ?string $note = null, ?int $reviewerId = null, array $metadata = []): WorkflowSubmission
    {
        return $this->workflowTransitionService->transition($model, $module, 'approve', $actor, $note, $reviewerId, $metadata);
    }
}
