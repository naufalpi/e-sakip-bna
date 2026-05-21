<?php

namespace App\Http\Controllers\Rpjmd;

use App\Http\Controllers\Controller;
use App\Http\Requests\Rpjmd\StoreRpjmdImportRequest;
use App\Models\ImportBatch;
use App\Models\Rpjmd;
use App\Services\RpjmdImportApplyService;
use App\Services\RpjmdImportPreviewService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RpjmdImportController extends Controller
{
    public function create(): Response
    {
        $this->authorize('create', Rpjmd::class);

        return Inertia::render('Rpjmd/Import', [
            'recentImports' => $this->recentImports(),
        ]);
    }

    public function store(StoreRpjmdImportRequest $request, RpjmdImportPreviewService $service): RedirectResponse
    {
        $batch = $service->storePreview($request->file('file'), $request->user());

        if ($batch->status === 'failed') {
            return redirect()
                ->route('rpjmd.import.show', $batch)
                ->with('error', 'File import tersimpan, tetapi preview gagal dibaca.');
        }

        return redirect()
            ->route('rpjmd.import.show', $batch)
            ->with('success', 'File import berhasil dibaca sebagai preview. Data belum dimasukkan ke cascading RPJMD.');
    }

    public function apply(Request $request, ImportBatch $importBatch, RpjmdImportApplyService $service): RedirectResponse
    {
        $this->authorize('create', Rpjmd::class);
        abort_unless($importBatch->module === 'rpjmd', 404);

        $batch = $service->apply($importBatch, $request->user());

        $message = match ($batch->status) {
            'imported' => ['success', 'Import berhasil diterapkan ke cascading RPJMD.'],
            'imported_with_errors' => ['warning', 'Import sebagian berhasil. Periksa baris yang gagal pada preview.'],
            default => ['error', 'Import gagal diterapkan. Periksa pesan error pada preview.'],
        };

        return redirect()
            ->route('rpjmd.import.show', $batch)
            ->with($message[0], $message[1]);
    }

    public function show(Request $request, ImportBatch $importBatch): Response
    {
        $this->authorize('create', Rpjmd::class);
        abort_unless($importBatch->module === 'rpjmd', 404);

        $importBatch->load(['uploadedBy:id,name']);

        $rows = $importBatch->rows()
            ->limit(25)
            ->get(['id', 'row_number', 'status', 'raw_data', 'normalized_data', 'error_message'])
            ->map(fn ($row) => [
                'id' => $row->id,
                'row_number' => $row->row_number,
                'status' => $row->status,
                'cells' => $row->raw_data['cells'] ?? [],
                'mapped' => $row->normalized_data['mapped'] ?? [],
                'is_header' => (bool) ($row->normalized_data['is_header'] ?? false),
                'error_message' => $row->error_message,
            ]);

        return Inertia::render('Rpjmd/ImportPreview', [
            'batch' => [
                'id' => $importBatch->id,
                'module' => $importBatch->module,
                'import_type' => $importBatch->import_type,
                'status' => $importBatch->status,
                'original_filename' => $importBatch->original_filename,
                'mime_type' => $importBatch->mime_type,
                'file_size' => $importBatch->file_size,
                'total_rows' => $importBatch->total_rows,
                'preview_rows' => $importBatch->preview_rows,
                'metadata' => $importBatch->metadata,
                'error_message' => $importBatch->error_message,
                'created_at' => $importBatch->created_at?->toISOString(),
                'uploaded_by' => $importBatch->uploadedBy ? [
                    'id' => $importBatch->uploadedBy->id,
                    'name' => $importBatch->uploadedBy->name,
                ] : null,
            ],
            'rows' => $rows,
            'recentImports' => $this->recentImports(),
            'can' => [
                'manage' => $request->user()->can('create', Rpjmd::class),
            ],
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function recentImports(): array
    {
        return ImportBatch::query()
            ->with('uploadedBy:id,name')
            ->where('module', 'rpjmd')
            ->latest()
            ->limit(8)
            ->get()
            ->map(fn (ImportBatch $batch) => [
                'id' => $batch->id,
                'status' => $batch->status,
                'original_filename' => $batch->original_filename,
                'total_rows' => $batch->total_rows,
                'preview_rows' => $batch->preview_rows,
                'created_at' => $batch->created_at?->toISOString(),
                'uploaded_by' => $batch->uploadedBy?->name,
            ])
            ->all();
    }
}
