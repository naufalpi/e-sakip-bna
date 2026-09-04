<?php

namespace App\Services\Perencanaan;

use App\Models\DpaOpd;
use App\Models\RenjaOpd;
use App\Models\RkaOpd;
use App\Models\Rkpd;
use App\Models\User;
use App\Models\WorkflowHistory;
use App\Models\WorkflowSubmission;
use App\Services\ActivityLogger;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DocumentEstablishmentCancellationService
{
    public function __construct(private readonly ActivityLogger $activityLogger) {}

    public function cancelRkpd(Rkpd $document, User $actor, string $reason): Rkpd
    {
        $this->ensureSuperAdmin($actor);

        return DB::transaction(function () use ($document, $actor, $reason): Rkpd {
            /** @var Rkpd $established */
            $established = Rkpd::query()->lockForUpdate()->findOrFail($document->id);
            $versions = $this->lockedVersions($established);
            $working = $this->workingVersion($versions, 'RKPD');

            $this->ensureEstablishedVersion($established, 'RKPD');
            $this->ensureNoChangeVersion($versions, 'RKPD');

            $versionIds = $versions->modelKeys();
            $renjaBlockers = RenjaOpd::query()
                ->whereIn('rkpd_id', $versionIds)
                ->where(function ($query) {
                    $query->whereIn('status', ['approved', 'locked'])
                        ->orWhereIn('jenis_versi', ['ditetapkan', 'perubahan']);
                })
                ->count();
            $rkaBlockers = RkaOpd::query()->whereIn('rkpd_id', $versionIds)->count();
            $dpaBlockers = DpaOpd::query()->whereIn('rkpd_id', $versionIds)->count();

            if ($renjaBlockers > 0 || $rkaBlockers > 0 || $dpaBlockers > 0) {
                throw ValidationException::withMessages([
                    'document' => 'RKPD belum dapat dikembalikan ke Draft. Batalkan penetapan seluruh RENJA terlebih dahulu'
                        .' dan selesaikan dokumen turunan RKA/DPA yang masih terkait.',
                ]);
            }

            return $this->restoreWorkingVersion($working, $established, 'rkpd', 'RKPD', $actor, $reason);
        });
    }

    public function cancelRenja(RenjaOpd $document, User $actor, string $reason): RenjaOpd
    {
        $this->ensureSuperAdmin($actor);

        return DB::transaction(function () use ($document, $actor, $reason): RenjaOpd {
            /** @var RenjaOpd $established */
            $established = RenjaOpd::query()->lockForUpdate()->findOrFail($document->id);
            $versions = $this->lockedVersions($established);
            $working = $this->workingVersion($versions, 'RENJA');

            $this->ensureEstablishedVersion($established, 'RENJA');
            $this->ensureNoChangeVersion($versions, 'RENJA');

            $versionIds = $versions->modelKeys();
            $rkaBlockers = RkaOpd::query()->whereIn('renja_opd_id', $versionIds)->count();
            $dpaBlockers = DpaOpd::query()->whereIn('renja_opd_id', $versionIds)->count();

            if ($rkaBlockers > 0 || $dpaBlockers > 0) {
                throw ValidationException::withMessages([
                    'document' => 'RENJA belum dapat dikembalikan ke Draft karena masih memiliki dokumen RKA/DPA. '
                        .'Selesaikan atau hapus dokumen turunan tersebut terlebih dahulu.',
                ]);
            }

            return $this->restoreWorkingVersion($working, $established, 'renja_opd', 'RENJA', $actor, $reason);
        });
    }

    private function ensureSuperAdmin(User $actor): void
    {
        if (! $actor->isSuperAdmin()) {
            throw new AuthorizationException('Hanya Super Admin yang dapat membatalkan penetapan dokumen.');
        }
    }

    private function ensureEstablishedVersion(Model $document, string $label): void
    {
        if ((string) $document->getAttribute('jenis_versi') !== 'ditetapkan'
            || ! (bool) $document->getAttribute('is_active_version')
            || ! in_array((string) $document->getAttribute('status'), ['approved', 'locked'], true)) {
            throw ValidationException::withMessages([
                'document' => "Pembatalan hanya dapat dilakukan pada {$label} Ditetapkan yang aktif.",
            ]);
        }
    }

    /** @param Collection<int, Model> $versions */
    private function ensureNoChangeVersion(Collection $versions, string $label): void
    {
        if ($versions->contains(fn (Model $version) => $version->getAttribute('jenis_versi') === 'perubahan')) {
            throw ValidationException::withMessages([
                'document' => "{$label} Perubahan sudah tersedia. Pembatalan penetapan awal tidak dapat dilakukan.",
            ]);
        }
    }

    /** @return Collection<int, Model> */
    private function lockedVersions(Model $document): Collection
    {
        $rootId = (int) ($document->getAttribute('root_version_id') ?: $document->getKey());

        return $document::query()
            ->where('root_version_id', $rootId)
            ->lockForUpdate()
            ->get();
    }

    /** @param Collection<int, Model> $versions */
    private function workingVersion(Collection $versions, string $label): Model
    {
        $working = $versions->first(fn (Model $version) => $version->getAttribute('jenis_versi') === 'awal');

        if (! $working) {
            throw ValidationException::withMessages([
                'document' => "Versi awal {$label} tidak ditemukan sehingga penetapan tidak dapat dibatalkan dengan aman.",
            ]);
        }

        return $working;
    }

    /** @template TModel of Model
     * @param  TModel  $working
     * @param  TModel  $established
     * @return TModel
     */
    private function restoreWorkingVersion(
        Model $working,
        Model $established,
        string $module,
        string $label,
        User $actor,
        string $reason,
    ): Model {
        $previousStatus = (string) $working->getAttribute('status');
        $establishedId = (int) $established->getKey();

        $established->forceFill(['is_active_version' => false])->save();
        $established->delete();

        $working->forceFill([
            'status' => 'draft',
            'is_active_version' => true,
            'submitted_by' => null,
            'submitted_at' => null,
            'disahkan_oleh' => null,
            'disahkan_pada' => null,
        ])->save();

        $submission = WorkflowSubmission::query()
            ->where('related_table', $working->getTable())
            ->where('related_id', $working->getKey())
            ->where('module', $module)
            ->lockForUpdate()
            ->first();

        $metadata = array_merge($submission?->metadata ?? [], [
            'establishment_cancellation' => [
                'established_document_id' => $establishedId,
                'reason' => $reason,
                'cancelled_by' => $actor->id,
                'cancelled_at' => now()->toISOString(),
            ],
        ]);

        $submission = WorkflowSubmission::updateOrCreate([
            'related_table' => $working->getTable(),
            'related_id' => (int) $working->getKey(),
            'module' => $module,
        ], [
            'status' => 'draft',
            'submitted_by' => null,
            'current_reviewer_id' => null,
            'submitted_at' => null,
            'reviewed_at' => now(),
            'note' => $reason,
            'metadata' => $metadata,
        ]);

        WorkflowHistory::create([
            'workflow_submission_id' => $submission->id,
            'related_table' => $working->getTable(),
            'related_id' => (int) $working->getKey(),
            'module' => $module,
            'from_status' => $previousStatus,
            'to_status' => 'draft',
            'action' => 'cancel_establishment',
            'actor_id' => $actor->id,
            'reviewer_id' => null,
            'notes' => $reason,
            'metadata' => $metadata,
        ]);

        $this->activityLogger->log(
            'cancel_establishment',
            $working,
            ['status' => $previousStatus, 'established_document_id' => $establishedId],
            ['status' => 'draft', 'is_active_version' => true],
            "Penetapan {$label} dibatalkan dan versi awal dikembalikan ke Draft: {$reason}",
        );

        /** @var TModel */
        return $working->fresh();
    }
}
