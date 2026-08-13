<?php

namespace App\Http\Controllers\Perencanaan;

use App\Http\Controllers\Controller;
use App\Http\Requests\Perencanaan\StoreRkpdItemImportRequest;
use App\Models\ImportBatch;
use App\Models\Rkpd;
use App\Services\Imports\ImportTemplateService;
use App\Services\Perencanaan\RkpdItemImportApplyService;
use App\Services\Perencanaan\RkpdItemImportPreviewService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Inertia\Inertia;
use Inertia\Response;

class RkpdItemImportController extends Controller
{
    public function create(Rkpd $rkpd): Response
    {
        $this->authorize('update', $rkpd);

        return Inertia::render('Rkpd/Import', [
            'rkpd' => $this->document($rkpd),
            'recentImports' => $this->recentImports($rkpd),
        ]);
    }

    public function template(Rkpd $rkpd, ImportTemplateService $service): HttpResponse
    {
        $this->authorize('update', $rkpd);
        $template = $service->make('rkpd');

        return response($template['content'], 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="'.$template['filename'].'"',
        ]);
    }

    public function store(StoreRkpdItemImportRequest $request, Rkpd $rkpd, RkpdItemImportPreviewService $service): RedirectResponse
    {
        $batch = $service->storePreview($request->file('file'), $rkpd, $request->user());

        return redirect()->route('rkpd.items.import.show', [$rkpd, $batch])
            ->with($batch->status === 'failed' ? 'error' : 'success', $batch->status === 'failed'
                ? 'File tidak dapat dipreview. Periksa format template dan pesan kesalahan.'
                : 'File sudah divalidasi. Periksa preview sebelum menerapkan import.');
    }

    public function apply(Request $request, Rkpd $rkpd, ImportBatch $importBatch, RkpdItemImportApplyService $service): RedirectResponse
    {
        $this->authorize('update', $rkpd);
        $service->apply($importBatch, $rkpd, $request->user());

        return redirect()->route('rkpd.items.import.show', [$rkpd, $importBatch])
            ->with('success', 'Import berhasil diterapkan ke baris RKPD.');
    }

    public function show(Rkpd $rkpd, ImportBatch $importBatch): Response
    {
        $this->authorize('update', $rkpd);
        abort_unless($importBatch->module === 'rkpd' && (int) ($importBatch->metadata['rkpd_id'] ?? 0) === (int) $rkpd->id, 404);
        $importBatch->load('uploadedBy:id,name');

        return Inertia::render('Rkpd/ImportPreview', [
            'rkpd' => $this->document($rkpd),
            'batch' => [
                'id' => $importBatch->id,
                'status' => $importBatch->status,
                'original_filename' => $importBatch->original_filename,
                'file_size' => $importBatch->file_size,
                'total_rows' => $importBatch->total_rows,
                'metadata' => $importBatch->metadata,
                'error_message' => $importBatch->error_message,
                'uploaded_by' => $importBatch->uploadedBy ? ['name' => $importBatch->uploadedBy->name] : null,
            ],
            'rows' => $importBatch->rows()->limit(100)->get()->map(fn ($row) => [
                'id' => $row->id,
                'row_number' => $row->row_number,
                'status' => $row->status,
                'cells' => $row->raw_data['cells'] ?? [],
                'is_header' => (bool) ($row->normalized_data['is_header'] ?? false),
                'resolved' => [
                    'perangkat_daerah_penanggung_jawab' => $row->normalized_data['prepared']['perangkat_daerah_penanggung_jawab'] ?? null,
                ],
                'error_message' => $row->error_message,
            ]),
            'recentImports' => $this->recentImports($rkpd),
            'can' => ['manage' => true],
        ]);
    }

    private function document(Rkpd $rkpd): array
    {
        return ['id' => $rkpd->id, 'judul' => $rkpd->judul, 'tahun' => $rkpd->tahun];
    }

    private function recentImports(Rkpd $rkpd): array
    {
        return ImportBatch::query()->with('uploadedBy:id,name')->where('module', 'rkpd')->where('metadata->rkpd_id', $rkpd->id)->latest()->limit(8)->get()
            ->map(fn (ImportBatch $batch) => ['id' => $batch->id, 'status' => $batch->status, 'original_filename' => $batch->original_filename, 'total_rows' => $batch->total_rows, 'uploaded_by' => $batch->uploadedBy?->name])->all();
    }
}
