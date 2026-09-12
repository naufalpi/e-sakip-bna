<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Http\Requests\Master\StorePegawaiImportRequest;
use App\Models\ImportBatch;
use App\Models\User;
use App\Services\Imports\ImportTemplateService;
use App\Services\Master\JabatanOrganisasiImportApplyService;
use App\Services\Master\JabatanOrganisasiImportPreviewService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Inertia\Inertia;
use Inertia\Response;

class PegawaiImportController extends Controller
{
    public function create(Request $request): Response
    {
        $this->authorizeManage($request);

        return Inertia::render('Master/JabatanOrganisasi/Import', [
            'recentImports' => $this->recentImports($request->user()),
            'importMode' => 'employee',
        ]);
    }

    public function template(Request $request, ImportTemplateService $service): HttpResponse
    {
        $this->authorizeManage($request);
        $template = $service->make('pegawai_opd', ['opd_id' => $this->scopeOpdId($request->user())]);

        return response($template['content'], 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="'.$template['filename'].'"',
        ]);
    }

    public function store(StorePegawaiImportRequest $request, JabatanOrganisasiImportPreviewService $service): RedirectResponse
    {
        $scopeOpdId = $this->scopeOpdId($request->user());
        $batch = $service->storePreview(
            $request->file('file'),
            $request->user(),
            JabatanOrganisasiImportPreviewService::MODE_EMPLOYEE,
            $scopeOpdId,
        );

        return redirect()->route('master.pegawai.import.show', $batch)
            ->with($batch->status === 'failed' ? 'error' : 'success', $batch->status === 'failed'
                ? 'File tidak dapat dipreview. Periksa format template dan pesan kesalahan.'
                : 'File sudah divalidasi. Periksa pegawai dan penempatannya sebelum menerapkan import.');
    }

    public function show(Request $request, ImportBatch $importBatch): Response
    {
        $this->authorizeManage($request);
        $this->assertBatchAccess($request->user(), $importBatch);
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
            'rows' => $importBatch->rows()
                ->orderByRaw("CASE WHEN status = 'invalid' THEN 0 ELSE 1 END")
                ->orderBy('row_number')
                ->limit(200)
                ->get()
                ->map(function ($row) {
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
                            'status_pegawai' => $prepared['status_pegawai'] ?? null,
                            'jenis_penugasan' => $prepared['jenis_penugasan'] ?? null,
                            'tanggal_mulai' => $prepared['tanggal_mulai'] ?? null,
                            'tanggal_selesai' => $prepared['tanggal_selesai'] ?? null,
                            'account_label' => $prepared['account_label'] ?? null,
                        ],
                        'error_message' => $row->error_message,
                    ];
                }),
            'recentImports' => $this->recentImports($request->user()),
            'can' => ['manage' => true],
            'importMode' => 'employee',
        ]);
    }

    public function apply(Request $request, ImportBatch $importBatch, JabatanOrganisasiImportApplyService $service): RedirectResponse
    {
        $this->authorizeManage($request);
        $this->assertBatchAccess($request->user(), $importBatch);
        $service->apply($importBatch, $request->user(), $this->scopeOpdId($request->user()));

        return redirect()->route('master.pegawai.import.show', $importBatch)
            ->with('success', 'Import Pegawai OPD dan penempatannya berhasil diterapkan.');
    }

    private function authorizeManage(Request $request): void
    {
        abort_unless($request->user()?->hasPermission('pegawai.manage'), 403);

        if ($this->isOpdScoped($request->user())) {
            abort_unless($request->user()->opd_id, 403, 'Admin OPD belum terhubung dengan perangkat daerah.');
        }
    }

    private function assertBatchAccess(User $user, ImportBatch $batch): void
    {
        abort_unless($batch->module === 'pegawai' && $batch->import_type === 'pegawai_dan_penempatan', 404);

        $scopeOpdId = $this->scopeOpdId($user);
        if ($scopeOpdId !== null) {
            abort_unless((int) data_get($batch->metadata, 'scope_opd_id') === $scopeOpdId, 403);
        }
    }

    private function recentImports(User $user): array
    {
        $scopeOpdId = $this->scopeOpdId($user);

        return ImportBatch::query()
            ->with('uploadedBy:id,name')
            ->where('module', 'pegawai')
            ->where('import_type', 'pegawai_dan_penempatan')
            ->when($scopeOpdId, fn ($query) => $query->where('metadata->scope_opd_id', $scopeOpdId))
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

    private function scopeOpdId(User $user): ?int
    {
        return $this->isOpdScoped($user) ? (int) $user->opd_id : null;
    }

    private function isOpdScoped(User $user): bool
    {
        return $user->hasRole('admin_opd')
            && ! $user->hasAnyRole([
                'super_admin',
                'admin_kabupaten_bagian_organisasi',
                'admin_kabupaten_bapperida',
                'admin_kabupaten_bpkad',
                'admin_kabupaten_inspektorat',
                'admin_kabupaten_dinkominfo',
            ]);
    }
}
