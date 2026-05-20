<?php

namespace App\Services\Workflow;

use App\Models\Notification;
use App\Models\User;
use App\Models\WorkflowSubmission;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class WorkflowNotificationService
{
    public function __construct(private readonly WorkflowModuleRegistry $registry) {}

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function notify(Model $model, string $module, string $action, User $actor, WorkflowSubmission $submission, ?int $reviewerId = null, array $metadata = []): void
    {
        $recipients = $this->recipients($model, $module, $action, $submission, $reviewerId)
            ->reject(fn (User $user) => (int) $user->id === (int) $actor->id)
            ->unique('id')
            ->values();

        foreach ($recipients as $recipient) {
            Notification::create([
                'user_id' => $recipient->id,
                'type' => 'workflow',
                'title' => $this->title($module, $action),
                'message' => $this->message($module, $action, $actor),
                'data' => [
                    'module' => $module,
                    'module_label' => $this->registry->label($module),
                    'action' => $action,
                    'status' => $submission->status,
                    'related_table' => $model->getTable(),
                    'related_id' => $model->getKey(),
                    'workflow_submission_id' => $submission->id,
                    'metadata' => $metadata,
                ],
            ]);
        }
    }

    /**
     * @return Collection<int, User>
     */
    private function recipients(Model $model, string $module, string $action, WorkflowSubmission $submission, ?int $reviewerId)
    {
        if ($action === 'submit') {
            if ($reviewerId) {
                return User::query()->whereKey($reviewerId)->get();
            }

            return User::query()
                ->where('status', 'active')
                ->whereHas('roles', fn ($query) => $query->whereIn('name', $this->registry->reviewerRoles($module)))
                ->get();
        }

        $recipientIds = collect([$submission->submitted_by]);

        if ($model->getAttribute('submitted_by')) {
            $recipientIds->push($model->getAttribute('submitted_by'));
        }

        return User::query()
            ->where('status', 'active')
            ->where(fn ($query) => $query
                ->whereIn('id', $recipientIds->filter()->values()->all())
                ->orWhere(fn ($query) => $model->getAttribute('opd_id')
                    ? $query->where('opd_id', $model->getAttribute('opd_id'))->whereHas('roles', fn ($query) => $query->where('name', 'admin_opd'))
                    : $query->whereRaw('1 = 0')))
            ->get();
    }

    private function title(string $module, string $action): string
    {
        return match ($action) {
            'submit' => $this->registry->label($module).' diajukan',
            'verify' => $this->registry->label($module).' diverifikasi',
            'approve' => $this->registry->label($module).' disetujui',
            'reject' => $this->registry->label($module).' ditolak',
            'revision' => $this->registry->label($module).' perlu revisi',
            'lock' => $this->registry->label($module).' dikunci',
            default => $this->registry->label($module).' diperbarui',
        };
    }

    private function message(string $module, string $action, User $actor): string
    {
        return $actor->name.' memproses workflow '.$this->registry->label($module).' dengan aksi '.$action.'.';
    }
}
