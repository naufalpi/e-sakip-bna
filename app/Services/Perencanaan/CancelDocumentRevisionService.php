<?php

namespace App\Services\Perencanaan;

use App\Models\RenstraOpd;
use App\Models\Rpjmd;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CancelDocumentRevisionService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function cancelRpjmdRevision(Rpjmd $revision, array $data, User $actor): ?Rpjmd
    {
        return DB::transaction(function () use ($revision, $data, $actor) {
            $this->ensureCancelable($revision, 'RPJMD');
            $previous = $this->previousVersion($revision);

            $revision->forceFill([
                'is_active_version' => false,
                'dibatalkan_oleh' => $actor->id,
                'dibatalkan_pada' => now(),
                'alasan_pembatalan' => $data['alasan_pembatalan'] ?? null,
            ])->save();

            $revision->delete();

            if ($previous instanceof Rpjmd) {
                $previous->forceFill(['is_active_version' => true])->save();
            }

            RenstraOpd::query()
                ->where('rpjmd_perubahan_terbaru_id', $revision->id)
                ->update([
                    'perlu_penyesuaian_rpjmd' => false,
                    'rpjmd_perubahan_terbaru_id' => null,
                    'rpjmd_penyesuaian_terdeteksi_pada' => null,
                ]);

            return $previous instanceof Rpjmd ? $previous->fresh() : null;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function cancelRenstraRevision(RenstraOpd $revision, array $data, User $actor): ?RenstraOpd
    {
        return DB::transaction(function () use ($revision, $data, $actor) {
            $this->ensureCancelable($revision, 'Renstra OPD');
            $previous = $this->previousVersion($revision);

            $revision->forceFill([
                'is_active_version' => false,
                'dibatalkan_oleh' => $actor->id,
                'dibatalkan_pada' => now(),
                'alasan_pembatalan' => $data['alasan_pembatalan'] ?? null,
            ])->save();

            $revision->delete();

            if ($previous instanceof RenstraOpd) {
                $previous->forceFill(['is_active_version' => true])->save();
            }

            return $previous instanceof RenstraOpd ? $previous->fresh() : null;
        });
    }

    private function ensureCancelable(Model $revision, string $label): void
    {
        if ((string) $revision->getAttribute('jenis_versi') !== 'perubahan') {
            throw ValidationException::withMessages([
                'dokumen' => "{$label} murni tidak dibatalkan lewat aksi ini.",
            ]);
        }

        $status = (string) $revision->getAttribute('status');

        if (in_array($status, ['submitted', 'verified'], true)) {
            throw ValidationException::withMessages([
                'dokumen' => "{$label} sedang diajukan. Tarik pengajuan terlebih dahulu, lalu batalkan perubahan.",
            ]);
        }

        if (in_array($status, ['approved', 'locked'], true)) {
            throw ValidationException::withMessages([
                'dokumen' => "{$label} sudah resmi. Buat perubahan berikutnya untuk koreksi.",
            ]);
        }

        if (! in_array($status, ['draft', 'revision', 'rejected'], true)) {
            throw ValidationException::withMessages([
                'dokumen' => "{$label} tidak dapat dibatalkan pada status ini.",
            ]);
        }

        if (! $this->isLatestRevision($revision)) {
            throw ValidationException::withMessages([
                'dokumen' => 'Hanya perubahan terakhir yang dapat dibatalkan.',
            ]);
        }
    }

    private function previousVersion(Model $revision): ?Model
    {
        $rootId = $this->rootVersionId($revision);
        $keyName = $revision->getKeyName();

        return $revision::query()
            ->where($keyName, '!=', $revision->getKey())
            ->where(function ($query) use ($keyName, $rootId) {
                $query->where('root_version_id', $rootId)
                    ->orWhere($keyName, $rootId);
            })
            ->orderByDesc('nomor_versi')
            ->orderByDesc('id')
            ->first();
    }

    private function isLatestRevision(Model $revision): bool
    {
        $rootId = $this->rootVersionId($revision);

        $latest = $revision::query()
            ->where('jenis_versi', 'perubahan')
            ->where(function ($query) use ($rootId) {
                $query->where('root_version_id', $rootId)
                    ->orWhere('parent_version_id', $rootId);
            })
            ->orderByDesc('nomor_versi')
            ->orderByDesc('id')
            ->first();

        return (int) $latest?->getKey() === (int) $revision->getKey();
    }

    private function rootVersionId(Model $revision): int
    {
        return (int) ($revision->getAttribute('root_version_id') ?: $revision->getAttribute('parent_version_id') ?: $revision->getKey());
    }
}
