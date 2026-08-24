<?php

namespace App\Services\Workflow;

use App\Models\DpaOpd;
use App\Models\RenjaOpd;
use App\Models\RenstraOpd;
use App\Models\RkaOpd;
use App\Models\Rkpd;
use App\Models\Rpjmd;
use App\Models\User;
use App\Models\WorkflowHistory;
use App\Models\WorkflowSubmission;
use App\Services\Penganggaran\DpaReadinessService;
use App\Services\Penganggaran\RkaReadinessService;
use App\Services\Perencanaan\DocumentVersionActivationService;
use App\Services\Perencanaan\RenjaVersionService;
use App\Services\Perencanaan\RkpdVersionService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class WorkflowTransitionService
{
    public function __construct(
        private readonly WorkflowNotificationService $notificationService,
        private readonly WorkflowModuleRegistry $registry,
        private readonly DocumentVersionActivationService $documentVersionActivationService,
        private readonly RkpdVersionService $rkpdVersionService,
        private readonly RenjaVersionService $renjaVersionService,
        private readonly RkaReadinessService $rkaReadinessService,
        private readonly DpaReadinessService $dpaReadinessService,
        private readonly DocumentCorrectionService $documentCorrectionService,
    ) {}

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function transition(Model $model, string $module, string $action, User $actor, ?string $note = null, ?int $reviewerId = null, array $metadata = []): WorkflowSubmission
    {
        $this->authorizeAction($model, $module, $action, $actor);

        if ($module === 'rka_opd' && $action === 'verify') {
            throw ValidationException::withMessages([
                'action' => 'RKA mencatat dokumen final dan tidak menggunakan tahap verifikasi anggaran di E-SAKIP.',
            ]);
        }

        $newStatus = $this->statusForAction($action);
        $oldStatus = (string) ($model->getAttribute('status') ?? 'draft');
        $this->ensureValidTransition($action, $oldStatus);

        if ($action === 'submit') {
            $this->documentCorrectionService->ensureCorrectedSourceIsOfficial($model, $module);
        }

        if ($action === 'submit' && $model instanceof RkaOpd) {
            $this->rkaReadinessService->ensureReady($model);
        }

        if ($model instanceof DpaOpd) {
            if ($action === 'submit') {
                $this->dpaReadinessService->ensureReadyForSubmit($model);
            }
            if ($action === 'approve') {
                $this->dpaReadinessService->ensureReadyForApproval($model);
            }
        }

        $relatedTable = $model->getTable();
        $relatedId = (int) $model->getKey();

        return DB::transaction(function () use ($model, $module, $action, $actor, $note, $reviewerId, $metadata, $newStatus, $oldStatus, $relatedTable, $relatedId) {
            if ($action === 'correct') {
                $correctionMetadata = $this->documentCorrectionService->prepare(
                    $model,
                    $module,
                    $actor,
                    (string) $note,
                    (string) ($metadata['correction_reference'] ?? '')
                );
                $metadata = array_merge($metadata, $correctionMetadata, ['correction_type' => 'data_entry']);
            }

            $model->forceFill(['status' => $newStatus]);

            if ($action === 'submit' && $model->isFillable('submitted_by')) {
                $model->forceFill([
                    'submitted_by' => $actor->id,
                    'submitted_at' => now(),
                ]);
            }

            if ($action === 'withdraw' && $model->isFillable('submitted_by')) {
                $model->forceFill([
                    'submitted_by' => null,
                    'submitted_at' => null,
                ]);
            }

            if ($action === 'correct') {
                if ($model->isFillable('submitted_by')) {
                    $model->forceFill(['submitted_by' => null, 'submitted_at' => null]);
                }
                if ($model->isFillable('disahkan_oleh')) {
                    $model->forceFill(['disahkan_oleh' => null, 'disahkan_pada' => null]);
                }
            }

            if ($action === 'approve' && $model->isFillable('disahkan_oleh')) {
                $model->forceFill(['disahkan_oleh' => $actor->id, 'disahkan_pada' => now()]);
            }

            $model->save();

            if ($action === 'approve' && ($model instanceof Rpjmd || $model instanceof RenstraOpd)) {
                $this->documentVersionActivationService->activateAfterApproval($model, $actor);
            }

            if ($action === 'approve' && $model instanceof Rkpd) {
                $this->rkpdVersionService->publishAfterApproval($model, $actor);
            }

            if ($action === 'approve' && $model instanceof RenjaOpd) {
                $this->renjaVersionService->publishAfterApproval($model, $actor);
            }

            $existingSubmission = WorkflowSubmission::query()
                ->where('related_table', $relatedTable)
                ->where('related_id', $relatedId)
                ->where('module', $module)
                ->first();

            $currentReviewerId = $action === 'withdraw' ? null : $reviewerId;

            $submission = WorkflowSubmission::updateOrCreate([
                'related_table' => $relatedTable,
                'related_id' => $relatedId,
                'module' => $module,
            ], [
                'status' => $newStatus,
                'submitted_by' => $action === 'submit'
                    ? $actor->id
                    : ($action === 'withdraw' ? null : $existingSubmission?->submitted_by),
                'current_reviewer_id' => $currentReviewerId,
                'submitted_at' => $action === 'submit'
                    ? now()
                    : ($action === 'withdraw' ? null : $existingSubmission?->submitted_at),
                'reviewed_at' => in_array($action, ['submit', 'withdraw'], true) ? null : now(),
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
                'reviewer_id' => $currentReviewerId,
                'notes' => $note,
                'metadata' => $metadata ?: null,
            ]);

            $freshSubmission = $submission->fresh(['histories.actor', 'submittedBy', 'currentReviewer']);
            $this->notificationService->notify($model, $module, $action, $actor, $freshSubmission, $currentReviewerId, $metadata);

            return $freshSubmission;
        });
    }

    private function authorizeAction(Model $model, string $module, string $action, User $actor): void
    {
        if (($model instanceof Rpjmd || $model instanceof RenstraOpd || $model instanceof Rkpd || $model instanceof RenjaOpd) && $model->isArchivedVersion()) {
            throw new AuthorizationException('Versi arsip tidak dapat diproses. Buat dokumen Perubahan dari versi aktif.');
        }

        if ($action === 'correct') {
            if (! $this->registry->supportsDataCorrection($module)) {
                throw new AuthorizationException('Modul ini tidak mendukung koreksi data dokumen.');
            }

            if ($actor->isSuperAdmin()) {
                return;
            }

            throw new AuthorizationException('Hanya Super Admin yang dapat membatalkan persetujuan untuk koreksi data.');
        }

        if ($action === 'unlock') {
            if ($actor->isSuperAdmin()) {
                return;
            }

            throw new AuthorizationException('Anda tidak berwenang membuka kunci data ini.');
        }

        if ($this->isLocked($model) && ! $actor->isSuperAdmin()) {
            throw new AuthorizationException('Data sudah terkunci dan tidak dapat diproses.');
        }

        if ($action === 'submit') {
            if ($actor->can('update', $model)) {
                return;
            }

            throw new AuthorizationException('Anda tidak berwenang mengajukan data ini.');
        }

        if ($action === 'withdraw') {
            if (! $actor->can('update', $model)) {
                throw new AuthorizationException('Anda tidak berwenang menarik pengajuan ini.');
            }

            if ($actor->isSuperAdmin()) {
                return;
            }

            $submission = WorkflowSubmission::query()
                ->where('related_table', $model->getTable())
                ->where('related_id', (int) $model->getKey())
                ->where('module', $module)
                ->first();

            if ($submission && (int) $submission->submitted_by === (int) $actor->id) {
                return;
            }

            throw new AuthorizationException('Hanya pembuat pengajuan atau Super Admin yang dapat menarik pengajuan ini.');
        }

        if ($action === 'lock') {
            if ($actor->isSuperAdmin() || ($actor->hasPermission('lock_period') && $actor->can('view', $model))) {
                return;
            }

            throw new AuthorizationException('Anda tidak berwenang mengunci data ini.');
        }

        $reviewerAllowed = $actor->hasAnyRole($this->registry->reviewerRoles($module))
            || ($module === 'realisasi_kinerja' && $actor->hasPermission('verify_realisasi'))
            || ($module === 'rka_opd' && $actor->hasPermission('rka.verify'))
            || ($module === 'dpa_opd' && $actor->hasPermission('dpa.verify'))
            || (in_array($module, ['evaluasi_sakip', 'tindak_lanjut_rekomendasi'], true)
                && $actor->hasAnyPermission(['manage_evaluasi', 'evaluasi.manage']));

        if (! $reviewerAllowed) {
            throw new AuthorizationException('Anda tidak berwenang memproses pengajuan ini.');
        }
    }

    private function isLocked(Model $model): bool
    {
        return (string) ($model->getAttribute('status') ?? '') === 'locked';
    }

    private function ensureValidTransition(string $action, string $currentStatus): void
    {
        $allowedStatuses = match ($action) {
            'submit' => ['draft', 'revision', 'rejected'],
            'withdraw' => ['submitted'],
            'verify' => ['submitted'],
            'approve' => ['submitted', 'verified'],
            'reject', 'revision' => ['submitted', 'verified'],
            'lock' => ['approved'],
            'unlock' => ['locked'],
            'correct' => ['approved', 'locked'],
            default => [],
        };

        if (! in_array($currentStatus, $allowedStatuses, true)) {
            throw ValidationException::withMessages([
                'action' => "Aksi persetujuan tidak valid untuk status {$currentStatus}.",
            ]);
        }
    }

    private function statusForAction(string $action): string
    {
        return match ($action) {
            'submit' => 'submitted',
            'withdraw' => 'draft',
            'verify' => 'verified',
            'approve' => 'approved',
            'reject' => 'rejected',
            'revision' => 'revision',
            'lock' => 'locked',
            'unlock' => 'revision',
            'correct' => 'revision',
            default => throw new AuthorizationException('Aksi persetujuan tidak valid.'),
        };
    }
}
