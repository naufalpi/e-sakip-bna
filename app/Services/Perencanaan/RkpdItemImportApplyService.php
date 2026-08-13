<?php

namespace App\Services\Perencanaan;

use App\Models\ImportBatch;
use App\Models\ImportBatchRow;
use App\Models\Rkpd;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RkpdItemImportApplyService
{
    public function apply(ImportBatch $batch, Rkpd $rkpd, User $user): ImportBatch
    {
        if ($batch->module !== 'rkpd' || (int) ($batch->metadata['rkpd_id'] ?? 0) !== (int) $rkpd->id) {
            throw ValidationException::withMessages(['import_batch_id' => 'Batch import tidak sesuai dokumen RKPD.']);
        }

        if ($batch->status !== 'previewed') {
            throw ValidationException::withMessages(['import_batch_id' => 'Batch hanya dapat diterapkan setelah preview selesai.']);
        }

        $invalidRows = $batch->rows()->where('status', 'invalid')->count();
        $validRows = $batch->rows()->where('status', 'valid')->count();

        if ($invalidRows > 0) {
            throw ValidationException::withMessages(['import_batch_id' => 'Perbaiki seluruh baris tidak valid sebelum menerapkan import.']);
        }

        if ($validRows === 0) {
            throw ValidationException::withMessages(['import_batch_id' => 'Tidak ada baris valid yang dapat diterapkan.']);
        }

        DB::transaction(function () use ($batch, $rkpd, $user, $validRows) {
            $batch->update(['status' => 'processing']);
            $nextOrder = ((int) $rkpd->items()->max('urutan')) + 1;
            $documentStatus = in_array($rkpd->status, ['submitted', 'revision', 'verified', 'approved', 'rejected', 'locked'], true) ? $rkpd->status : 'draft';

            $batch->rows()->where('status', 'valid')->orderBy('row_number')->get()->each(function (ImportBatchRow $row) use ($rkpd, $documentStatus, &$nextOrder) {
                $payload = $row->normalized_data['prepared'] ?? [];
                $payload['status'] = $documentStatus;
                $payload['urutan'] = $payload['urutan'] ?? $nextOrder;
                $nextOrder = max($nextOrder, ((int) $payload['urutan']) + 1);

                $item = $rkpd->items()->create($payload);

                $row->update([
                    'status' => 'imported',
                    'normalized_data' => [...($row->normalized_data ?? []), 'applied' => ['rkpd_item_id' => $item->id]],
                ]);
            });

            $batch->update([
                'status' => 'imported',
                'error_message' => null,
                'metadata' => [
                    ...($batch->metadata ?? []),
                    'applied' => [
                        'imported_rows' => $validRows,
                        'failed_rows' => 0,
                        'skipped_rows' => $batch->rows()->where('status', 'skipped')->count(),
                        'applied_by' => $user->id,
                        'applied_at' => now()->toISOString(),
                        'rkpd_id' => $rkpd->id,
                    ],
                ],
            ]);
        });

        return $batch->fresh(['uploadedBy:id,name', 'rows']);
    }
}
