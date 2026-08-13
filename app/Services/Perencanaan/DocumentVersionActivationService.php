<?php

namespace App\Services\Perencanaan;

use App\Models\RenstraOpd;
use App\Models\Rpjmd;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class DocumentVersionActivationService
{
    /**
     * A new amendment only becomes the active reference after approval.
     * Previous versions are retained for audit, but are no longer current.
     */
    public function activateAfterApproval(Rpjmd|RenstraOpd $document, User $approver): void
    {
        if ($document->jenis_versi !== 'perubahan') {
            return;
        }

        DB::transaction(function () use ($document, $approver): void {
            $modelClass = $document::class;
            $document = $modelClass::query()->lockForUpdate()->findOrFail($document->id);
            $rootId = $this->rootVersionId($document);

            // Lock the whole lineage before changing the active version. This keeps
            // simultaneous approvals from producing two active document versions.
            $lineage = $modelClass::query()
                ->where('root_version_id', $rootId)
                ->lockForUpdate()
                ->get(['id', 'is_active_version']);
            $previousActiveIds = $lineage
                ->where('is_active_version', true)
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();

            $modelClass::query()
                ->where('root_version_id', $rootId)
                ->update(['is_active_version' => false]);

            $document->forceFill([
                'is_active_version' => true,
                'disahkan_oleh' => $approver->id,
                'disahkan_pada' => now(),
            ])->save();

            if ($document instanceof Rpjmd) {
                $this->markRenstraForAlignment($previousActiveIds, $document);
            }

            if ($document instanceof RenstraOpd && $document->rpjmd_id !== null) {
                $activeRpjmdExists = Rpjmd::query()
                    ->whereKey($document->rpjmd_id)
                    ->where('is_active_version', true)
                    ->exists();

                if ($activeRpjmdExists) {
                    $document->forceFill([
                        'perlu_penyesuaian_rpjmd' => false,
                        'rpjmd_perubahan_terbaru_id' => null,
                        'rpjmd_penyesuaian_terdeteksi_pada' => null,
                    ])->save();
                }
            }
        });
    }

    private function rootVersionId(Rpjmd|RenstraOpd $document): int
    {
        $modelClass = $document::class;
        $rootId = $document->root_version_id ?? $document->id;

        while ($parentId = $modelClass::query()->whereKey($rootId)->value('parent_version_id')) {
            $rootId = (int) $parentId;
        }

        return $rootId;
    }

    /** @param array<int, int> $previousActiveIds */
    private function markRenstraForAlignment(array $previousActiveIds, Rpjmd $revision): void
    {
        if ($previousActiveIds === []) {
            return;
        }

        RenstraOpd::query()
            ->where('is_active_version', true)
            ->whereIn('rpjmd_id', $previousActiveIds)
            ->update([
                'perlu_penyesuaian_rpjmd' => true,
                'rpjmd_perubahan_terbaru_id' => $revision->id,
                'rpjmd_penyesuaian_terdeteksi_pada' => now(),
            ]);
    }
}
