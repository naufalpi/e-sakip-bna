<?php

namespace App\Services\Kinerja;

use App\Models\User;
use App\Models\WorkflowHistory;
use App\Models\WorkflowSubmission;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class WorkflowService
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function transition(Model $model, string $module, string $action, User $actor, ?string $note = null, ?int $reviewerId = null, array $metadata = []): WorkflowSubmission
    {
        $this->authorizeAction($model, $action, $actor);

        $newStatus = $this->statusForAction($action);
        $oldStatus = (string) ($model->getAttribute('status') ?? 'draft');
        $relatedTable = $model->getTable();
        $relatedId = (int) $model->getKey();

        return DB::transaction(function () use ($model, $module, $action, $actor, $note, $reviewerId, $metadata, $newStatus, $oldStatus, $relatedTable, $relatedId) {
            $model->forceFill([
                'status' => $newStatus,
            ]);

            if ($action === 'submit' && $model->isFillable('submitted_by')) {
                $model->forceFill([
                    'submitted_by' => $actor->id,
                    'submitted_at' => now(),
                ]);
            }

            $model->save();

            $existingSubmission = WorkflowSubmission::query()
                ->where('related_table', $relatedTable)
                ->where('related_id', $relatedId)
                ->where('module', $module)
                ->first();

            $submission = WorkflowSubmission::updateOrCreate([
                'related_table' => $relatedTable,
                'related_id' => $relatedId,
                'module' => $module,
            ], [
                'status' => $newStatus,
                'submitted_by' => $action === 'submit' ? $actor->id : $existingSubmission?->submitted_by,
                'current_reviewer_id' => $reviewerId,
                'submitted_at' => $action === 'submit' ? now() : $existingSubmission?->submitted_at,
                'reviewed_at' => $action === 'submit' ? null : now(),
                'note' => $note,
                'metadata' => $metadata ?: null,
            ]);

            WorkflowHistory::create([
                'workflow_submission_id' => $submission->id,
                'related_table' => $relatedTable,
                'related_id' => $relatedId,
                'module' => $module,
                'from_status' => $oldStatus,
                'to_status' => $newStatus,
                'action' => $action,
                'actor_id' => $actor->id,
                'reviewer_id' => $reviewerId,
                'notes' => $note,
                'metadata' => $metadata ?: null,
            ]);

            return $submission->fresh(['histories.actor', 'submittedBy', 'currentReviewer']);
        });
    }

    private function authorizeAction(Model $model, string $action, User $actor): void
    {
        if ($action === 'submit') {
            if ($actor->can('update', $model)) {
                return;
            }

            throw new AuthorizationException('Anda tidak berwenang mengajukan data ini.');
        }

        $reviewerAllowed = $actor->hasAnyRole([
            'super_admin',
            'admin_kabupaten_bagian_organisasi',
            'admin_kabupaten_inspektorat',
        ]) || $actor->hasPermission('verify_realisasi');

        if ($action === 'lock') {
            $reviewerAllowed = $reviewerAllowed || $actor->hasPermission('lock_period');
        }

        if (! $reviewerAllowed) {
            throw new AuthorizationException('Anda tidak berwenang memproses workflow ini.');
        }
    }

    private function statusForAction(string $action): string
    {
        return match ($action) {
            'submit' => 'submitted',
            'verify' => 'verified',
            'approve' => 'approved',
            'reject' => 'rejected',
            'revision' => 'revision',
            'lock' => 'locked',
            default => throw new AuthorizationException('Aksi workflow tidak valid.'),
        };
    }
}
