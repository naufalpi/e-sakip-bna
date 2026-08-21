<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Http\Requests\Master\StoreJabatanOrganisasiImportRequest;
use App\Models\ImportBatch;
use App\Services\Imports\ImportTemplateService;
use App\Services\Master\JabatanOrganisasiImportApplyService;
use App\Services\Master\JabatanOrganisasiImportPreviewService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Inertia\Inertia;
use Inertia\Response;

class JabatanOrganisasiImportController extends Controller
{
    public function create(Request $request): Response
    {
        $this->authorizeManage($request);

        return Inertia::render('Master/JabatanOrganisasi/Import', [
            'recentImports' => $this->recentImports(),
        ]);
    }

    public function template(Request $request, ImportTemplateService $service): HttpResponse
    {
        $this->authorizeManage($request);
        $template = $service->make('jabatan_organisasi');

        return response($template['content'], 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="'.$template['filename'].'"',
        ]);
    }

    public function store(StoreJabatanOrganisasiImportRequest $request, JabatanOrganisasiImportPreviewService $service): RedirectResponse
    {
        $batch = $service->storePreview($request->file('file'), $request->user());

        return redirect()->route('master.jabatan-organisasi.import.show', $batch)
            ->with($batch->status === 'failed' ? 'error' : 'success', $batch->status === 'failed'
                ? 'File tidak dapat dipreview. Periksa format template dan pesan kesalahan.'
                : 'File sudah divalidasi. Periksa preview sebelum menerapkan import.');
    }

    public function show(Request $request, ImportBatch $importBatch): Response
    {
        $this->authorizeManage($request);
        $this->assertBatch($importBatch);
        $importBatch->load('uploadedBy:id,name');

        return Inertia::render('Master/JabatanOrganisasi/ImportPreview', [
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
            'rows' => $importBatch->rows()->limit(200)->get()->map(function ($row) {
                $prepared = $row->normalized_data['prepared'] ?? [];

                return [
                    'id' => $row->id,
                    'entity_type' => $row->normalized_data['entity_type'] ?? null,
                    'sheet' => $row->raw_data['sheet'] ?? null,
                    'sheet_row' => $row->raw_data['sheet_row'] ?? null,
                    'status' => $row->status,
                    'cells' => $row->raw_data['cells'] ?? [],
                    'prepared' => [
                        'action' => $prepared['action'] ?? null,
                        'nama' => $prepared['nama'] ?? null,
                        'level_jabatan' => $prepared['level_jabatan'] ?? null,
                        'opd_label' => $prepared['opd_label'] ?? null,
                        'unit_label' => $prepared['unit_label'] ?? null,
                        'parent_label' => $prepared['parent_label'] ?? null,
                        'jabatan_label' => $prepared['jabatan_label'] ?? null,
                        'nama_pejabat' => $prepared['nama_pejabat'] ?? null,
                        'nip' => $prepared['nip'] ?? null,
                        'jenis_pegawai' => $prepared['jenis_pegawai'] ?? null,
                        'jenis_penugasan' => $prepared['jenis_penugasan'] ?? null,
                        'tanggal_mulai' => $prepared['tanggal_mulai'] ?? null,
                        'tanggal_selesai' => $prepared['tanggal_selesai'] ?? null,
                        'account_label' => $prepared['account_label'] ?? null,
                    ],
                    'error_message' => $row->error_message,
                ];
            }),
            'recentImports' => $this->recentImports(),
            'can' => ['manage' => true],
        ]);
    }

    public function apply(Request $request, ImportBatch $importBatch, JabatanOrganisasiImportApplyService $service): RedirectResponse
    {
        $this->authorizeManage($request);
        $this->assertBatch($importBatch);
        $service->apply($importBatch, $request->user());

        return redirect()->route('master.jabatan-organisasi.import.show', $importBatch)
            ->with('success', 'Import jabatan dan pejabat berhasil diterapkan.');
    }

    private function authorizeManage(Request $request): void
    {
        abort_unless($request->user()?->hasPermission('jabatan_organisasi.manage'), 403);
    }

    private function assertBatch(ImportBatch $batch): void
    {
        abort_unless($batch->module === 'jabatan_organisasi' && $batch->import_type === 'jabatan_dan_pejabat', 404);
    }

    private function recentImports(): array
    {
        return ImportBatch::query()
            ->with('uploadedBy:id,name')
            ->where('module', 'jabatan_organisasi')
            ->where('import_type', 'jabatan_dan_pejabat')
            ->latest()
            ->limit(8)
            ->get()
            ->map(fn (ImportBatch $batch) => [
                'id' => $batch->id,
                'status' => $batch->status,
                'original_filename' => $batch->original_filename,
                'total_rows' => $batch->total_rows,
                'uploaded_by' => $batch->uploadedBy?->name,
                'created_at' => $batch->created_at?->timezone(config('app.timezone'))->format('d M Y H:i'),
            ])->all();
    }
}
