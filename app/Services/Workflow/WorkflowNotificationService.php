<?php

namespace App\Services\Workflow;

use App\Models\User;
use App\Models\WorkflowSubmission;
use App\Services\Notifications\SakipNotificationService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class WorkflowNotificationService
{
    public function __construct(
        private readonly WorkflowModuleRegistry $registry,
        private readonly SakipNotificationService $notificationService,
    ) {}

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function notify(Model $model, string $module, string $action, User $actor, WorkflowSubmission $submission, ?int $reviewerId = null, array $metadata = []): void
    {
        $recipients = $this->recipients($model, $module, $action, $submission, $reviewerId)
            ->reject(fn (User $user) => (int) $user->id === (int) $actor->id)
            ->unique('id')
            ->values();

        $this->notificationService->notify(
            $recipients,
            $this->type($action),
            $this->title($module, $action),
            $this->message($module, $action, $actor),
            [
                'dedupe_key' => 'workflow:'.$submission->id.':'.$action.':'.$submission->status,
                'module' => $module,
                'module_label' => $this->registry->label($module),
                'action' => $action,
                'status' => $submission->status,
                'related_table' => $model->getTable(),
                'related_id' => $model->getKey(),
                'workflow_submission_id' => $submission->id,
                'metadata' => $metadata,
            ],
            $this->url($module, $model),
        );
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
            'unlock' => $this->registry->label($module).' dibuka untuk revisi',
            default => $this->registry->label($module).' diperbarui',
        };
    }

    private function type(string $action): string
    {
        return match ($action) {
            'submit' => 'workflow_submitted',
            'verify' => 'workflow_verified',
            'approve' => 'workflow_approved',
            'reject' => 'workflow_rejected',
            'revision' => 'workflow_revision',
            'lock' => 'workflow_locked',
            'unlock' => 'workflow_unlocked',
            default => 'workflow_updated',
        };
    }

    private function message(string $module, string $action, User $actor): string
    {
        return $actor->name.' memproses workflow '.$this->registry->label($module).' dengan aksi '.$action.'.';
    }

    private function url(string $module, Model $model): ?string
    {
        return match ($module) {
            'rpjmd' => route('rpjmd.show', $model->getKey()),
            'renstra_opd' => route('renstra-opd.show', $model->getKey()),
            'perjanjian_kinerja' => route('perjanjian-kinerja.show', $model->getKey()),
            'rencana_aksi' => route('rencana-aksi.show', $model->getKey()),
            'realisasi_kinerja' => route('realisasi-kinerja.show', $model->getKey()),
            'lkjip' => route('lkjip.show', $model->getKey()),
            'evaluasi_sakip' => route('evaluasi-sakip.show', $model->getKey()),
            default => null,
        };
    }
}
