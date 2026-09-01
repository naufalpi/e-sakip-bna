<?php

namespace App\Services\Perencanaan;

use App\Models\RenjaOpd;
use App\Models\Rkpd;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RenjaVersionService
{
    private const WORKING_RKPD_STATUSES = ['draft', 'submitted', 'verified', 'revision', 'rejected'];

    /**
     * Approval of RENJA Awal publishes a RENJA Ditetapkan snapshot. Approval
     * of RENJA Perubahan makes that snapshot the active OPD document.
     */
    public function publishAfterApproval(RenjaOpd $document, User $approver): RenjaOpd
    {
        return DB::transaction(function () use ($document, $approver): RenjaOpd {
            /** @var RenjaOpd $document */
            $document = RenjaOpd::query()->lockForUpdate()->findOrFail($document->id);
            $rootId = $this->lockRoot($document);

            if ($document->jenis_versi === 'awal') {
                return $this->publishEstablishedVersion($document, $approver, $rootId);
            }

            if ($document->jenis_versi === 'perubahan') {
                $reference = $this->referenceRkpd($document, 'perubahan', true);
                if (! $reference) {
                    throw ValidationException::withMessages([
                        'document' => 'RKPD Perubahan Ditetapkan belum tersedia untuk tahun RENJA ini.',
                    ]);
                }

                RenjaOpd::query()
                    ->where('root_version_id', $rootId)
                    ->update(['is_active_version' => false]);

                $document->forceFill([
                    'rkpd_id' => $reference->id,
                    'is_active_version' => true,
                    'disahkan_oleh' => $approver->id,
                    'disahkan_pada' => now(),
                ])->save();
            }

            return $document->fresh();
        });
    }

    /**
     * @param  array{alasan_perubahan: string, dasar_perubahan?: string|null, tanggal_berlaku?: string|null}  $metadata
     */
    public function createChange(RenjaOpd $source, array $metadata): RenjaOpd
    {
        return DB::transaction(function () use ($source, $metadata): RenjaOpd {
            /** @var RenjaOpd $source */
            $source = RenjaOpd::query()->lockForUpdate()->findOrFail($source->id);
            $rootId = $this->lockRoot($source);

            if ($source->jenis_versi !== 'ditetapkan'
                || ! $source->is_active_version
                || ! in_array($source->status, ['approved', 'locked'], true)) {
                throw ValidationException::withMessages([
                    'document' => 'Perubahan hanya dapat dibuat dari RENJA Ditetapkan yang aktif.',
                ]);
            }

            if (RenjaOpd::query()->where('root_version_id', $rootId)->where('jenis_versi', 'perubahan')->exists()) {
                throw ValidationException::withMessages([
                    'document' => 'RENJA Perubahan untuk OPD dan tahun ini sudah dibuat.',
                ]);
            }

            $reference = $this->workingRkpdChange($source) ?: $this->referenceRkpd($source, 'perubahan', true);
            if (! $reference) {
                throw ValidationException::withMessages([
                    'rkpd_id' => 'RKPD Perubahan tahap kerja atau RKPD Perubahan Ditetapkan harus tersedia sebelum membuat RENJA Perubahan.',
                ]);
            }

            $change = $this->cloneDocument($source, [
                'rkpd_id' => $reference->id,
                'status' => 'draft',
                'jenis_versi' => 'perubahan',
                'nomor_versi' => 3,
                'parent_version_id' => $source->id,
                'root_version_id' => $rootId,
                'is_active_version' => false,
                'alasan_perubahan' => $metadata['alasan_perubahan'],
                'dasar_perubahan' => $metadata['dasar_perubahan'] ?? null,
                'tanggal_berlaku' => $metadata['tanggal_berlaku'] ?? null,
                'submitted_by' => null,
                'submitted_at' => null,
                'disahkan_oleh' => null,
                'disahkan_pada' => null,
            ]);

            return $change->fresh();
        });
    }

    private function publishEstablishedVersion(RenjaOpd $source, User $approver, int $rootId): RenjaOpd
    {
        $existing = RenjaOpd::query()
            ->where('root_version_id', $rootId)
            ->where('jenis_versi', 'ditetapkan')
            ->first();

        if ($existing) {
            return $existing;
        }

        $reference = $this->referenceRkpd($source, 'ditetapkan');
        if (! $reference) {
            throw ValidationException::withMessages([
                'document' => 'RKPD Ditetapkan belum tersedia. Tetapkan RKPD terlebih dahulu sebelum menyetujui RENJA.',
            ]);
        }

        $established = $this->cloneDocument($source, [
            'rkpd_id' => $reference->id,
            'status' => 'approved',
            'jenis_versi' => 'ditetapkan',
            'nomor_versi' => 2,
            'parent_version_id' => $source->id,
            'root_version_id' => $rootId,
            'is_active_version' => true,
            'submitted_by' => null,
            'submitted_at' => null,
            'disahkan_oleh' => $approver->id,
            'disahkan_pada' => now(),
        ]);

        $source->forceFill([
            'is_active_version' => false,
            'disahkan_oleh' => $approver->id,
            'disahkan_pada' => now(),
        ])->save();

        return $established;
    }

    private function referenceRkpd(RenjaOpd $renja, string $version, bool $activeOnly = false): ?Rkpd
    {
        $renja->loadMissing('rkpd');
        $rootId = $renja->rkpd?->root_version_id ?: $renja->rkpd_id;

        return Rkpd::query()
            ->when($rootId, fn ($query) => $query->where('root_version_id', $rootId))
            ->when(! $rootId, fn ($query) => $query
                ->where('periode_tahun_id', $renja->periode_tahun_id)
                ->where('tahun', $renja->tahun))
            ->where('jenis_versi', $version)
            ->whereIn('status', ['approved', 'locked'])
            ->when($activeOnly, fn ($query) => $query->where('is_active_version', true))
            ->orderByDesc('nomor_versi')
            ->first();
    }

    private function workingRkpdChange(RenjaOpd $renja): ?Rkpd
    {
        $renja->loadMissing('rkpd');
        $rootId = $renja->rkpd?->root_version_id ?: $renja->rkpd_id;

        return Rkpd::query()
            ->when($rootId, fn ($query) => $query->where('root_version_id', $rootId))
            ->when(! $rootId, fn ($query) => $query
                ->where('periode_tahun_id', $renja->periode_tahun_id)
                ->where('tahun', $renja->tahun))
            ->where('jenis_versi', 'perubahan')
            ->whereIn('status', self::WORKING_RKPD_STATUSES)
            ->orderByDesc('nomor_versi')
            ->latest('id')
            ->first();
    }

    /** @param array<string, mixed> $overrides */
    private function cloneDocument(RenjaOpd $source, array $overrides): RenjaOpd
    {
        $attributes = $source->getAttributes();
        unset($attributes['id'], $attributes['created_at'], $attributes['updated_at'], $attributes['deleted_at']);

        $clone = new RenjaOpd;
        $clone->forceFill(array_merge($attributes, $overrides));
        $clone->save();

        foreach ($source->items()->reorder()->orderBy('id')->get() as $item) {
            $attributes = $item->getAttributes();
            unset($attributes['id'], $attributes['created_at'], $attributes['updated_at'], $attributes['deleted_at']);

            $clone->items()->create($attributes);
        }

        return $clone;
    }

    private function lockRoot(RenjaOpd $document): int
    {
        $rootId = (int) ($document->root_version_id ?: $document->id);
        RenjaOpd::query()->lockForUpdate()->findOrFail($rootId);

        return $rootId;
    }
}
