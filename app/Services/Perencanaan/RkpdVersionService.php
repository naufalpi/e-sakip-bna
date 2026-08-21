<?php

namespace App\Services\Perencanaan;

use App\Models\Rkpd;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class RkpdVersionService
{
    /**
     * Approval of RKPD Awal publishes an immutable snapshot as RKPD
     * Ditetapkan. Approval of RKPD Perubahan makes that version active.
     */
    public function publishAfterApproval(Rkpd $document, User $approver): Rkpd
    {
        return DB::transaction(function () use ($document, $approver): Rkpd {
            /** @var Rkpd $document */
            $document = Rkpd::query()->lockForUpdate()->findOrFail($document->id);
            $rootId = $this->lockRoot($document);

            if ($document->jenis_versi === 'awal') {
                return $this->publishEstablishedVersion($document, $approver, $rootId);
            }

            if ($document->jenis_versi === 'perubahan') {
                Rkpd::query()
                    ->where('root_version_id', $rootId)
                    ->update(['is_active_version' => false]);

                $document->forceFill([
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
    public function createChange(Rkpd $source, array $metadata): Rkpd
    {
        return DB::transaction(function () use ($source, $metadata): Rkpd {
            /** @var Rkpd $source */
            $source = Rkpd::query()->lockForUpdate()->findOrFail($source->id);
            $rootId = $this->lockRoot($source);

            if ($source->jenis_versi !== 'ditetapkan'
                || ! $source->is_active_version
                || ! in_array($source->status, ['approved', 'locked'], true)) {
                throw ValidationException::withMessages([
                    'document' => 'Perubahan hanya dapat dibuat dari RKPD Ditetapkan yang aktif.',
                ]);
            }

            $changeExists = Rkpd::query()
                ->where('root_version_id', $rootId)
                ->where('jenis_versi', 'perubahan')
                ->exists();

            if ($changeExists) {
                throw ValidationException::withMessages([
                    'document' => 'RKPD Perubahan untuk tahun ini sudah dibuat.',
                ]);
            }

            $change = $this->cloneDocument($source, [
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

    private function publishEstablishedVersion(Rkpd $source, User $approver, int $rootId): Rkpd
    {
        $existing = Rkpd::query()
            ->where('root_version_id', $rootId)
            ->where('jenis_versi', 'ditetapkan')
            ->first();

        if ($existing) {
            return $existing;
        }

        $established = $this->cloneDocument($source, [
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

    /** @param array<string, mixed> $overrides */
    private function cloneDocument(Rkpd $source, array $overrides): Rkpd
    {
        $attributes = $source->getAttributes();
        unset($attributes['id'], $attributes['created_at'], $attributes['updated_at'], $attributes['deleted_at']);

        $clone = new Rkpd;
        $clone->forceFill(array_merge($attributes, $overrides));
        $clone->save();

        $this->cloneRows('rkpd_items', 'rkpd_id', $source->id, $clone->id);
        $this->cloneRows('rkpd_iku_targets', 'rkpd_id', $source->id, $clone->id);

        return $clone;
    }

    private function cloneRows(string $table, string $foreignKey, int $sourceId, int $targetId): void
    {
        if (! Schema::hasTable($table)) {
            return;
        }

        $query = DB::table($table)->where($foreignKey, $sourceId);
        if (Schema::hasColumn($table, 'deleted_at')) {
            $query->whereNull('deleted_at');
        }

        foreach ($query->orderBy('id')->get() as $row) {
            $attributes = (array) $row;
            unset($attributes['id'], $attributes['created_at'], $attributes['updated_at'], $attributes['deleted_at']);

            $attributes[$foreignKey] = $targetId;
            $attributes['created_at'] = now();
            $attributes['updated_at'] = now();

            DB::table($table)->insert($attributes);
        }
    }

    private function lockRoot(Rkpd $document): int
    {
        $rootId = (int) ($document->root_version_id ?: $document->id);
        Rkpd::query()->lockForUpdate()->findOrFail($rootId);

        return $rootId;
    }
}
