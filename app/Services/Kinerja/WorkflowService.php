<?php

namespace App\Services\Kinerja;

use App\Models\User;
use App\Models\WorkflowSubmission;
use App\Services\Workflow\ApproveWorkflowService;
use App\Services\Workflow\LockDataService;
use App\Services\Workflow\RejectWorkflowService;
use App\Services\Workflow\RequestRevisionWorkflowService;
use App\Services\Workflow\SubmitWorkflowService;
use App\Services\Workflow\WorkflowTransitionService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;

class WorkflowService
{
    public function __construct(
        private readonly SubmitWorkflowService $submitWorkflowService,
        private readonly ApproveWorkflowService $approveWorkflowService,
        private readonly RejectWorkflowService $rejectWorkflowService,
        private readonly RequestRevisionWorkflowService $requestRevisionWorkflowService,
        private readonly LockDataService $lockDataService,
        private readonly WorkflowTransitionService $workflowTransitionService,
    ) {}

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function transition(Model $model, string $module, string $action, User $actor, ?string $note = null, ?int $reviewerId = null, array $metadata = []): WorkflowSubmission
    {
        return match ($action) {
            'submit' => $this->submitWorkflowService->handle($model, $module, $actor, $note, $reviewerId, $metadata),
            'approve' => $this->approveWorkflowService->handle($model, $module, $actor, $note, $reviewerId, $metadata),
            'reject' => $this->rejectWorkflowService->handle($model, $module, $actor, $note, $reviewerId, $metadata),
            'revision' => $this->requestRevisionWorkflowService->handle($model, $module, $actor, $note, $reviewerId, $metadata),
            'lock' => $this->lockDataService->handle($model, $module, $actor, $note, $reviewerId, $metadata),
            'verify' => $this->workflowTransitionService->transition($model, $module, 'verify', $actor, $note, $reviewerId, $metadata),
            default => throw new AuthorizationException('Aksi workflow tidak valid.'),
        };
    }
}
