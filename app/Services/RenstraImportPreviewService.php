<?php

namespace App\Services;

use App\Models\ImportBatch;
use App\Models\User;
use App\Services\Imports\ImportColumnValidationService;
use App\Services\Imports\SpreadsheetImportReader;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

class RenstraImportPreviewService
{
    private const MAX_ROWS = 1000;

    public function __construct(
        private readonly SpreadsheetImportReader $reader,
        private readonly ImportColumnValidationService $columnValidator,
    ) {}

    public function storePreview(UploadedFile $file, User $user): ImportBatch
    {
        $disk = config('filesystems.default', 'local');
        $path = $file->store('imports/renstra-opd/'.now()->format('Y/m'), $disk);

        if (! is_string($path)) {
            throw new RuntimeException('File import gagal disimpan.');
        }

        $batch = ImportBatch::create([
            'module' => 'renstra_opd',
            'import_type' => 'cascading_renstra_opd',
            'status' => 'processing',
            'original_filename' => $file->getClientOriginalName(),
            'mime_type' => $file->getClientMimeType(),
            'file_size' => $file->getSize() ?: 0,
            'storage_disk' => $disk,
            'storage_path' => $path,
            'uploaded_by' => $user->id,
            'metadata' => [
                'parser' => 'spreadsheet_preview',
                'max_rows' => self::MAX_ROWS,
                'note' => 'Preview import Renstra OPD. Data baru diterapkan setelah tombol Terapkan Import dijalankan.',
                'required_columns' => ['level', 'opd_kode/opd_id', 'rpjmd_id/rpjmd_judul', 'kode', 'uraian'],
            ],
        ]);

        try {
            $rows = $this->reader->readRows($file, self::MAX_ROWS);
            $columns = $this->reader->detectColumns($rows);
            $columnValidation = $this->columnValidator->validate('renstra_opd', $columns);

            DB::transaction(function () use ($batch, $rows, $columns, $columnValidation) {
                foreach ($rows as $index => $row) {
                    $batch->rows()->create([
                        'row_number' => $index + 1,
                        'status' => 'preview',
                        'raw_data' => [
                            'cells' => array_values($row),
                        ],
                        'normalized_data' => [
                            'is_header' => $index === 0,
                            'mapped' => $this->reader->mapRow($row, $columns),
                        ],
                    ]);
                }

                $batch->update([
                    'status' => 'previewed',
                    'total_rows' => count($rows),
                    'preview_rows' => min(count($rows), 25),
                    'metadata' => [
                        ...($batch->metadata ?? []),
                        'columns' => $columns,
                        'column_validation' => $columnValidation,
                    ],
                ]);
            });
        } catch (Throwable $exception) {
            $batch->update([
                'status' => 'failed',
                'error_message' => $exception->getMessage(),
            ]);
        }

        return $batch->fresh(['uploadedBy:id,name', 'rows']);
    }
}
