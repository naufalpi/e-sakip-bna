<?php

namespace App\Http\Controllers\Kinerja;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Kinerja\Concerns\BuildsKinerjaOptions;
use App\Http\Requests\Kinerja\StorePerjanjianKinerjaRequest;
use App\Http\Requests\Kinerja\UpdatePerjanjianKinerjaRequest;
use App\Jobs\ExportKinerjaReportDocumentJob;
use App\Models\DpaOpd;
use App\Models\OpdKegiatan;
use App\Models\OpdProgram;
use App\Models\OpdSubKegiatan;
use App\Models\Pegawai;
use App\Models\PerjanjianKinerja;
use App\Models\PerjanjianKinerjaItem;
use App\Models\RenstraOpd;
use App\Models\RiwayatPejabatJabatan;
use App\Models\Rkpd;
use App\Models\SasaranOpd;
use App\Models\User;
use App\Models\WorkflowSubmission;
use App\Services\Kinerja\KinerjaReportContentService;
use App\Services\Kinerja\KopDokumenService;
use App\Services\Kinerja\PerjanjianKinerjaDocumentService;
use App\Services\Kinerja\PerjanjianKinerjaSnapshotService;
use App\Services\Reports\ReportDocumentRenderService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class PerjanjianKinerjaController extends Controller
{
    use BuildsKinerjaOptions;

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', PerjanjianKinerja::class);

        $filters = $request->only(['search', 'status', 'level_pk', 'opd_id', 'periode_tahun_id', 'tahun']);
        $user = $request->user();

        $items = PerjanjianKinerja::query()
            ->with(['opd:id,kode,nama,singkatan', 'periodeTahun:id,tahun,nama', 'pegawai:id,nama,nip'])
            ->withCount([
                'items as items_count' => fn (Builder $query) => $query->where(fn (Builder $query) => $query
                    ->whereNull('jenis_item')
                    ->orWhere('jenis_item', '!=', 'program_opd')),
                'programs',
            ])
            ->withSum('programs as total_anggaran', 'anggaran')
            ->when($this->shouldLimitToUserOpd($user), fn (Builder $query) => $query->where('opd_id', $user->opd_id))
            ->when($filters['search'] ?? null, function (Builder $query, string $search) {
                $query->where(function (Builder $query) use ($search) {
                    $query->where('judul', 'ilike', "%{$search}%")
                        ->orWhere('nomor_dokumen', 'ilike', "%{$search}%")
                        ->orWhereHas('pegawai', fn (Builder $query) => $query
                            ->where('nama', 'ilike', "%{$search}%")
                            ->orWhere('nip', 'ilike', "%{$search}%"))
                        ->orWhereHas('opd', fn (Builder $query) => $query->where('nama', 'ilike', "%{$search}%")->orWhere('singkatan', 'ilike', "%{$search}%"));
                });
            })
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($filters['level_pk'] ?? null, fn (Builder $query, string $level) => $query->where('level_pk', $level))
            ->when($filters['opd_id'] ?? null, fn (Builder $query, string $opdId) => $query->where('opd_id', $opdId))
            ->when($filters['periode_tahun_id'] ?? null, fn (Builder $query, string $periodeId) => $query->where('periode_tahun_id', $periodeId))
            ->when($filters['tahun'] ?? null, fn (Builder $query, string $tahun) => $query->where('tahun', $tahun))
            ->orderByDesc('tahun')
            ->latest('id')
            ->paginate(10)
            ->withQueryString()
            ->through(fn (PerjanjianKinerja $pk) => [
                'id' => $pk->id,
                'judul' => $pk->judul,
                'nomor_dokumen' => $pk->nomor_dokumen,
                'tahun' => $pk->tahun,
                'status' => $pk->status,
                'tipe_pk' => $pk->tipe_pk,
                'level_pk' => $pk->level_pk,
                'tipe_pk_label' => $pk->levelLabel(),
                'sumber_data' => $pk->sumber_data,
                'pegawai' => $pk->pegawai ? ['id' => $pk->pegawai->id, 'nama' => $pk->pegawai->nama, 'nip' => $pk->pegawai->nip] : null,
                'items_count' => $pk->items_count,
                'programs_count' => $pk->programs_count,
                'total_anggaran' => (float) ($pk->total_anggaran ?? 0),
                'opd' => $pk->opd ? [
                    'id' => $pk->opd->id,
                    'kode' => $pk->opd->kode,
                    'nama' => $pk->opd->nama,
                    'singkatan' => $pk->opd->singkatan,
                ] : null,
                'periode_tahun' => $pk->periodeTahun ? [
                    'id' => $pk->periodeTahun->id,
                    'tahun' => $pk->periodeTahun->tahun,
                    'nama' => $pk->periodeTahun->nama,
                ] : null,
            ]);

        return Inertia::render('Kinerja/PerjanjianKinerja/Index', [
            'items' => $items,
            'filters' => $filters,
            'opdOptions' => $this->opdOptions($user),
            'periodeOptions' => $this->periodeOptions(),
            'can' => [
                'manage' => $user->can('create', PerjanjianKinerja::class),
                'manage_bupati' => ! $user->hasRole('admin_opd'),
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorize('create', PerjanjianKinerja::class);

        return Inertia::render('Kinerja/PerjanjianKinerja/Form', [
            'mode' => 'create',
            'item' => null,
            'opdOptions' => $this->opdOptions($request->user()),
            'periodeOptions' => $this->periodeOptions(),
            'renstraOptions' => $this->renstraOptions($request->user()),
            'rkpdOptions' => $this->rkpdOptions(),
            'dpaOptions' => $this->dpaOptions($request->user()),
            'can' => ['manage_bupati' => ! $request->user()->hasRole('admin_opd')],
            ...$this->subjectOptions($request->user()),
        ]);
    }

    public function cascadingScopeOptions(Request $request): JsonResponse
    {
        $this->authorize('create', PerjanjianKinerja::class);

        $data = $request->validate([
            'renstra_opd_id' => ['required', 'integer', 'exists:renstra_opd,id'],
        ]);

        $renstra = RenstraOpd::query()
            ->whereKey($data['renstra_opd_id'])
            ->whereIn('status', self::APPROVED_PLANNING_STATUSES)
            ->where('is_active_version', true)
            ->when($this->shouldLimitToUserOpd($request->user()), fn (Builder $query) => $query->where('opd_id', $request->user()->opd_id))
            ->firstOrFail(['id', 'opd_id']);

        $sasaran = SasaranOpd::query()
            ->whereHas('tujuan', fn (Builder $query) => $query->where('renstra_opd_id', $renstra->id))
            ->with('tujuan:id,tujuan')
            ->withCount('indikator')
            ->orderBy('urutan')
            ->orderBy('id')
            ->get(['id', 'tujuan_opd_id', 'kode', 'sasaran', 'urutan'])
            ->map(fn (SasaranOpd $item) => [
                'key' => 'sasaran_opd:'.$item->id,
                'label' => trim(($item->kode ? $item->kode.' - ' : '').$item->sasaran),
                'context' => $item->tujuan?->tujuan ? 'Tujuan OPD: '.$item->tujuan->tujuan : null,
                'indicator_count' => $item->indikator_count,
            ]);

        $programs = OpdProgram::query()
            ->where('renstra_opd_id', $renstra->id)
            ->with('sasaran:id,sasaran')
            ->withCount('indikator')
            ->orderBy('urutan')
            ->orderBy('id')
            ->get(['id', 'sasaran_opd_id', 'kode', 'nama', 'sasaran_program', 'urutan'])
            ->map(fn (OpdProgram $item) => [
                'key' => 'opd_program:'.$item->id,
                'label' => trim(($item->kode ? $item->kode.' - ' : '').$item->nama),
                'context' => collect([
                    $item->sasaran?->sasaran ? 'Sasaran OPD: '.$item->sasaran->sasaran : null,
                    $item->sasaran_program ? 'Sasaran Program: '.$item->sasaran_program : null,
                ])->filter()->implode(' · '),
                'indicator_count' => $item->indikator_count,
            ]);

        $activities = OpdKegiatan::query()
            ->whereHas('program', fn (Builder $query) => $query->where('renstra_opd_id', $renstra->id))
            ->with('program:id,nama,sasaran_program')
            ->withCount('indikator')
            ->orderBy('urutan')
            ->orderBy('id')
            ->get(['id', 'opd_program_id', 'kode', 'nama', 'sasaran_kegiatan', 'urutan'])
            ->map(fn (OpdKegiatan $item) => [
                'key' => 'opd_kegiatan:'.$item->id,
                'label' => trim(($item->kode ? $item->kode.' - ' : '').$item->nama),
                'context' => collect([
                    $item->program?->nama ? 'Program: '.$item->program->nama : null,
                    $item->sasaran_kegiatan ? 'Sasaran Kegiatan: '.$item->sasaran_kegiatan : null,
                ])->filter()->implode(' · '),
                'indicator_count' => $item->indikator_count,
            ]);

        $subActivities = OpdSubKegiatan::query()
            ->whereHas('kegiatan.program', fn (Builder $query) => $query->where('renstra_opd_id', $renstra->id))
            ->with('kegiatan:id,nama,sasaran_kegiatan')
            ->withCount('indikator')
            ->orderBy('urutan')
            ->orderBy('id')
            ->get(['id', 'opd_kegiatan_id', 'kode', 'nama', 'sasaran_sub_kegiatan', 'urutan'])
            ->map(fn (OpdSubKegiatan $item) => [
                'key' => 'opd_sub_kegiatan:'.$item->id,
                'label' => trim(($item->kode ? $item->kode.' - ' : '').$item->nama),
                'context' => collect([
                    $item->kegiatan?->nama ? 'Kegiatan: '.$item->kegiatan->nama : null,
                    $item->sasaran_sub_kegiatan ? 'Sasaran Sub Kegiatan: '.$item->sasaran_sub_kegiatan : null,
                ])->filter()->implode(' · '),
                'indicator_count' => $item->indikator_count,
            ]);

        return response()->json([
            'groups' => [
                ['type' => 'sasaran_opd', 'label' => 'Sasaran OPD', 'items' => $sasaran],
                ['type' => 'opd_program', 'label' => 'Program OPD', 'items' => $programs],
                ['type' => 'opd_kegiatan', 'label' => 'Kegiatan OPD', 'items' => $activities],
                ['type' => 'opd_sub_kegiatan', 'label' => 'Sub Kegiatan OPD', 'items' => $subActivities],
            ],
        ]);
    }

    public function store(
        StorePerjanjianKinerjaRequest $request,
        PerjanjianKinerjaSnapshotService $snapshotService,
        KopDokumenService $kopService,
    ): RedirectResponse {
        $data = $request->validated();
        $this->assertRenstraBelongsToOpd($data['renstra_opd_id'] ?? null, isset($data['opd_id']) ? (int) $data['opd_id'] : null);
        $data = $this->prepareSubjectData($data);
        $this->assertNoDuplicate($data);

        $pk = DB::transaction(function () use ($data, $snapshotService, $kopService): PerjanjianKinerja {
            $pk = PerjanjianKinerja::create($data);
            $kopService->applySnapshot($pk);
            $snapshotService->populate($pk);

            return $pk;
        });

        return redirect()->route('perjanjian-kinerja.show', $pk)->with('success', 'Perjanjian Kinerja berhasil ditambahkan.');
    }

    public function show(Request $request, PerjanjianKinerja $perjanjianKinerja, PerjanjianKinerjaDocumentService $documentService): Response
    {
        $this->authorize('view', $perjanjianKinerja);

        $perjanjianKinerja->load([
            'opd:id,kode,nama,singkatan',
            'periodeTahun:id,tahun,nama',
            'renstraOpd:id,judul,tahun_awal,tahun_akhir',
            'rkpd:id,judul,tahun,jenis_versi,status',
            'dpaOpd:id,judul,tahun,nomor_dpa,status',
            'pegawai:id,nama,nip,pangkat_golongan',
            'penempatanPegawai.jabatanOrganisasi:id,nama,level_jabatan',
            'atasanPegawai:id,nama,nip',
            'items.satuanIndikator:id,nama,simbol',
            'items.sasaranOpd:id,kode,sasaran',
            'items.indikatorSasaranOpd:id,kode,indikator',
            'items.opdProgram:id,kode,nama',
            'programs',
        ]);

        return Inertia::render('Kinerja/PerjanjianKinerja/Show', [
            'item' => $this->serializePerjanjianKinerja($perjanjianKinerja),
            'nodeOptions' => $request->user()->can('update', $perjanjianKinerja) && $perjanjianKinerja->opd_id
                ? $this->nodeOptionsForOpd((int) $perjanjianKinerja->opd_id)
                : [],
            'satuanOptions' => $request->user()->can('update', $perjanjianKinerja) ? $this->satuanOptions() : [],
            'workflow' => $this->workflowData($perjanjianKinerja, 'perjanjian_kinerja'),
            'documentPreview' => $documentService->build($perjanjianKinerja),
            'can' => [
                'manage' => $request->user()->can('update', $perjanjianKinerja),
                'edit_kop' => $request->user()->can('update', $perjanjianKinerja)
                    && in_array($perjanjianKinerja->status, ['draft', 'revision', 'rejected'], true),
                'review' => $this->canReviewWorkflow($request->user()),
                'lock' => $this->canLockWorkflow($request->user()),
                'export' => $this->canExportPerjanjianKinerja($request->user(), $perjanjianKinerja),
            ],
        ]);
    }

    public function print(
        PerjanjianKinerja $perjanjianKinerja,
        KinerjaReportContentService $contentService,
        ReportDocumentRenderService $renderService,
    ): HttpResponse {
        $this->authorize('view', $perjanjianKinerja);

        $rendered = $renderService->render(
            $contentService->build($perjanjianKinerja, 'perjanjian_kinerja'),
            'pdf',
        );

        return response($rendered['contents'], 200, [
            'Content-Type' => $rendered['mime_type'],
            'Content-Disposition' => 'inline; filename="'.$rendered['filename'].'"',
            'Cache-Control' => 'private, no-store, max-age=0',
        ]);
    }

    public function downloadWord(
        PerjanjianKinerja $perjanjianKinerja,
        KinerjaReportContentService $contentService,
        ReportDocumentRenderService $renderService,
    ): HttpResponse {
        $this->authorize('view', $perjanjianKinerja);

        $rendered = $renderService->render(
            $contentService->build($perjanjianKinerja, 'perjanjian_kinerja'),
            'word',
        );

        return response()->streamDownload(
            static fn () => print $rendered['contents'],
            $rendered['filename'],
            ['Content-Type' => $rendered['mime_type']],
        );
    }

    public function export(Request $request, PerjanjianKinerja $perjanjianKinerja): RedirectResponse
    {
        $this->authorize('view', $perjanjianKinerja);
        abort_unless($this->canExportPerjanjianKinerja($request->user(), $perjanjianKinerja), 403);

        $data = $request->validate([
            'format' => ['required', Rule::in(['pdf', 'word'])],
        ]);

        ExportKinerjaReportDocumentJob::dispatch('perjanjian_kinerja', $perjanjianKinerja->id, $request->user()->id, $data['format']);

        $formatLabel = $data['format'] === 'pdf' ? 'PDF' : 'Word';

        return back()->with('success', "Export Perjanjian Kinerja {$formatLabel} masuk antrean. Jalankan worker queue untuk memproses dokumen.");
    }

    public function edit(Request $request, PerjanjianKinerja $perjanjianKinerja): Response
    {
        $this->authorize('update', $perjanjianKinerja);

        return Inertia::render('Kinerja/PerjanjianKinerja/Form', [
            'mode' => 'edit',
            'item' => [
                'id' => $perjanjianKinerja->id,
                'opd_id' => $perjanjianKinerja->opd_id,
                'pegawai_id' => $perjanjianKinerja->pegawai_id,
                'penempatan_pegawai_id' => $perjanjianKinerja->penempatan_pegawai_id,
                'atasan_pegawai_id' => $perjanjianKinerja->atasan_pegawai_id,
                'tipe_pk' => $perjanjianKinerja->tipe_pk,
                'level_pk' => $perjanjianKinerja->level_pk,
                'renstra_opd_id' => $perjanjianKinerja->renstra_opd_id,
                'rkpd_id' => $perjanjianKinerja->rkpd_id,
                'dpa_opd_id' => $perjanjianKinerja->dpa_opd_id,
                'lingkup_kinerja_snapshot' => $perjanjianKinerja->lingkup_kinerja_snapshot ?? [],
                'periode_tahun_id' => $perjanjianKinerja->periode_tahun_id,
                'tahun' => $perjanjianKinerja->tahun,
                'judul' => $perjanjianKinerja->judul,
                'nomor_dokumen' => $perjanjianKinerja->nomor_dokumen,
                'tanggal_dokumen' => $perjanjianKinerja->tanggal_dokumen?->format('Y-m-d'),
                'tempat_penandatanganan' => $perjanjianKinerja->tempat_penandatanganan,
                'status' => $perjanjianKinerja->status,
                'catatan' => $perjanjianKinerja->catatan,
            ],
            'opdOptions' => $this->opdOptions($request->user()),
            'periodeOptions' => $this->periodeOptions(),
            'renstraOptions' => $this->renstraOptions($request->user()),
            'rkpdOptions' => $this->rkpdOptions(),
            'dpaOptions' => $this->dpaOptions($request->user()),
            'can' => ['manage_bupati' => ! $request->user()->hasRole('admin_opd')],
            ...$this->subjectOptions($request->user(), $perjanjianKinerja),
        ]);
    }

    public function update(
        UpdatePerjanjianKinerjaRequest $request,
        PerjanjianKinerja $perjanjianKinerja,
        PerjanjianKinerjaSnapshotService $snapshotService,
        KopDokumenService $kopService,
    ): RedirectResponse {
        $data = $request->validated();
        $this->assertRenstraBelongsToOpd($data['renstra_opd_id'] ?? null, isset($data['opd_id']) ? (int) $data['opd_id'] : null);
        $data = $this->prepareSubjectData($data);
        $this->assertNoDuplicate($data, $perjanjianKinerja);

        DB::transaction(function () use ($perjanjianKinerja, $data, $snapshotService, $kopService): void {
            $wasManual = $perjanjianKinerja->sumber_data === 'manual';
            $sourceChanged = collect(['level_pk', 'tipe_pk', 'sumber_data', 'rkpd_id', 'renstra_opd_id', 'dpa_opd_id', 'periode_tahun_id', 'tahun', 'lingkup_kinerja_snapshot'])
                ->contains(fn (string $field) => json_encode($perjanjianKinerja->getAttribute($field)) !== json_encode($data[$field] ?? null));
            $kopScopeChanged = collect(['level_pk', 'opd_id'])
                ->contains(fn (string $field) => (string) $perjanjianKinerja->getAttribute($field) !== (string) ($data[$field] ?? null));

            $perjanjianKinerja->update($data);

            if ($kopScopeChanged || blank($perjanjianKinerja->kop_dokumen_snapshot)) {
                $kopService->applySnapshot($perjanjianKinerja);
            }

            // Dokumen sumber dapat dikoreksi lalu disetujui kembali dengan ID yang sama.
            // Selalu bangun ulang snapshot nonmanual saat PK disimpan agar koreksi RENSTRA/DPA
            // benar-benar masuk, meskipun pilihan sumber pada form tidak berubah.
            if ($perjanjianKinerja->sumber_data !== 'manual' || ($sourceChanged && ! $wasManual)) {
                $snapshotService->populate($perjanjianKinerja);
            }
        });

        return redirect()->route('perjanjian-kinerja.show', $perjanjianKinerja)->with('success', 'Perjanjian Kinerja berhasil diperbarui.');
    }

    public function updateKop(Request $request, PerjanjianKinerja $perjanjianKinerja, KopDokumenService $kopService): RedirectResponse
    {
        $this->authorize('update', $perjanjianKinerja);

        if (! in_array($perjanjianKinerja->status, ['draft', 'revision', 'rejected'], true)) {
            throw ValidationException::withMessages([
                'kop_dokumen' => 'Kop dokumen hanya dapat diubah saat PK masih Draft, Perlu Perbaikan, atau Ditolak.',
            ]);
        }

        if ($request->boolean('gunakan_default')) {
            $kopService->applySnapshot($perjanjianKinerja);

            return back()->with('success', 'Kop PK dikembalikan ke pengaturan standar terbaru.');
        }

        $data = $request->validate([
            'nama_pemerintah' => ['required', 'string', 'max:255'],
            'nama_instansi' => ['required', 'string', 'max:255'],
            'alamat' => ['nullable', 'string', 'max:1000'],
            'telepon' => ['nullable', 'string', 'max:100'],
            'faksimile' => ['nullable', 'string', 'max:100'],
            'website' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'kota' => ['required', 'string', 'max:100'],
            'kode_pos' => ['nullable', 'string', 'max:20'],
        ]);

        $current = $perjanjianKinerja->kop_dokumen_snapshot ?: $kopService->snapshotFor($perjanjianKinerja);
        $perjanjianKinerja->forceFill([
            'kop_dokumen_snapshot' => [...$current, ...$data],
        ])->save();

        return back()->with('success', 'Kop pada dokumen PK berhasil diperbarui.');
    }

    public function destroy(PerjanjianKinerja $perjanjianKinerja): RedirectResponse
    {
        $this->authorize('delete', $perjanjianKinerja);

        $perjanjianKinerja->delete();

        return redirect()->route('perjanjian-kinerja.index')->with('success', 'Perjanjian Kinerja berhasil dihapus.');
    }

    private function serializePerjanjianKinerja(PerjanjianKinerja $pk): array
    {
        return [
            'id' => $pk->id,
            'judul' => $pk->judul,
            'nomor_dokumen' => $pk->nomor_dokumen,
            'tahun' => $pk->tahun,
            'status' => $pk->status,
            'catatan' => $pk->catatan,
            'tipe_pk' => $pk->tipe_pk,
            'level_pk' => $pk->level_pk,
            'tipe_pk_label' => $pk->levelLabel(),
            'sumber_data' => $pk->sumber_data,
            'tanggal_dokumen' => $pk->tanggal_dokumen?->format('Y-m-d'),
            'tempat_penandatanganan' => $pk->tempat_penandatanganan,
            'pegawai' => $pk->pegawai,
            'penempatan_pegawai' => $pk->penempatanPegawai ? [
                'id' => $pk->penempatanPegawai->id,
                'jabatan' => $pk->penempatanPegawai->jabatanOrganisasi,
            ] : null,
            'atasan_pegawai' => $pk->atasanPegawai,
            'nama_pegawai_snapshot' => $pk->nama_pegawai_snapshot,
            'nip_snapshot' => $pk->nip_snapshot,
            'jabatan_snapshot' => $pk->jabatan_snapshot,
            'nama_atasan_snapshot' => $pk->nama_atasan_snapshot,
            'nip_atasan_snapshot' => $pk->nip_atasan_snapshot,
            'jabatan_atasan_snapshot' => $pk->jabatan_atasan_snapshot,
            'opd' => $pk->opd,
            'periode_tahun' => $pk->periodeTahun,
            'renstra_opd' => $pk->renstraOpd,
            'rkpd' => $pk->rkpd,
            'dpa_opd' => $pk->dpaOpd,
            'items' => $pk->items->where('jenis_item', '!=', 'program_opd')->values()->map(fn (PerjanjianKinerjaItem $item) => [
                'id' => $item->id,
                'sasaran_opd_id' => $item->sasaran_opd_id,
                'indikator_sasaran_opd_id' => $item->indikator_sasaran_opd_id,
                'opd_program_id' => $item->opd_program_id,
                'satuan_indikator_id' => $item->satuan_indikator_id,
                'satuan_snapshot' => $item->satuan_snapshot,
                'jenis_item' => $item->jenis_item,
                'is_readonly' => $item->is_readonly,
                'kode' => $item->kode,
                'sasaran' => $item->sasaran,
                'indikator' => $item->indikator,
                'target' => $item->target,
                'target_text' => $item->target_text,
                'urutan' => $item->urutan,
                'satuan' => $item->satuanIndikator,
                'sasaran_opd' => $item->sasaranOpd,
                'indikator_sasaran_opd' => $item->indikatorSasaranOpd,
                'opd_program' => $item->opdProgram,
            ]),
            'programs' => $pk->programs->map(fn ($program) => [
                'id' => $program->id,
                'kode' => $program->kode,
                'nama_program' => $program->nama_program,
                'anggaran' => $program->anggaran,
                'keterangan' => $program->keterangan,
                'urutan' => $program->urutan,
            ]),
        ];
    }

    private function workflowData(PerjanjianKinerja $pk, string $module): ?array
    {
        $workflow = WorkflowSubmission::query()
            ->with(['histories.actor:id,name', 'submittedBy:id,name', 'currentReviewer:id,name'])
            ->where('related_table', $pk->getTable())
            ->where('related_id', $pk->id)
            ->where('module', $module)
            ->first();

        return $workflow?->toArray();
    }

    private function assertRenstraBelongsToOpd(mixed $renstraId, ?int $opdId): void
    {
        if (! $renstraId) {
            return;
        }

        if (! RenstraOpd::query()
            ->whereKey($renstraId)
            ->when($opdId, fn (Builder $query) => $query->where('opd_id', $opdId))
            ->whereIn('status', self::APPROVED_PLANNING_STATUSES)
            ->where('is_active_version', true)
            ->exists()) {
            throw ValidationException::withMessages([
                'renstra_opd_id' => 'Renstra yang dipilih harus sesuai OPD dan sudah disetujui atau terkunci.',
            ]);
        }
    }

    private function subjectOptions(User $user, ?PerjanjianKinerja $current = null): array
    {
        $currentEmployeeIds = collect([$current?->pegawai_id, $current?->atasan_pegawai_id])->filter()->map(fn ($id) => (int) $id)->all();
        $employees = Pegawai::query()
            ->where(fn (Builder $query) => $query
                ->where('status', 'active')
                ->when($currentEmployeeIds, fn (Builder $query) => $query->orWhereIn('pegawai.id', $currentEmployeeIds)))
            ->when($this->shouldLimitToUserOpd($user), function (Builder $query) use ($user, $currentEmployeeIds): void {
                $query->where(function (Builder $query) use ($user, $currentEmployeeIds): void {
                    $query->where('opd_id', $user->opd_id)
                        ->orWhereHas('penempatan.jabatanOrganisasi', fn (Builder $query) => $query
                            ->where('opd_id', $user->opd_id)
                            ->orWhere('level_jabatan', 'kepala_daerah'))
                        ->when($currentEmployeeIds, fn (Builder $query) => $query->orWhereIn('pegawai.id', $currentEmployeeIds));
                });
            })
            ->with(['penempatan.jabatanOrganisasi:id,opd_id,parent_id,nama,level_jabatan'])
            ->orderBy('nama')
            ->get(['id', 'opd_id', 'nama', 'nip']);

        return [
            'pegawaiOptions' => $employees->map(fn (Pegawai $pegawai) => [
                'id' => $pegawai->id,
                'opd_id' => $pegawai->opd_id,
                'label' => $pegawai->nama.($pegawai->nip ? " · NIP {$pegawai->nip}" : ''),
            ])->all(),
            'placementOptions' => $employees->flatMap(fn (Pegawai $pegawai) => $pegawai->penempatan->map(fn (RiwayatPejabatJabatan $placement) => [
                'id' => $placement->id,
                'pegawai_id' => $pegawai->id,
                'jabatan_organisasi_id' => $placement->jabatan_organisasi_id,
                'opd_id' => $placement->jabatanOrganisasi?->opd_id,
                'level_jabatan' => $placement->jabatanOrganisasi?->level_jabatan,
                'parent_jabatan_id' => $placement->jabatanOrganisasi?->parent_id,
                'tanggal_mulai' => $placement->tanggal_mulai?->format('Y-m-d'),
                'tanggal_selesai' => $placement->tanggal_selesai?->format('Y-m-d'),
                'label' => ($placement->jabatanOrganisasi?->nama ?? 'Jabatan tidak tersedia')." · TMT {$placement->tanggal_mulai?->format('Y-m-d')}",
            ]))->values()->all(),
        ];
    }

    private function prepareSubjectData(array $data): array
    {
        $data['level_pk'] = $data['level_pk'] ?? ($data['tipe_pk'] === 'individual' ? 'individu' : 'struktural');
        $data['tipe_pk'] = $data['level_pk'] === 'individu'
            ? ($data['tipe_pk'] ?? 'individual')
            : 'cascading';
        $pegawai = Pegawai::query()->findOrFail($data['pegawai_id']);
        $referenceDate = filled($data['tanggal_dokumen'] ?? null)
            ? $data['tanggal_dokumen']
            : now()->toDateString();

        $placement = null;
        if ($data['penempatan_pegawai_id'] ?? null) {
            $placement = RiwayatPejabatJabatan::query()
                ->with('jabatanOrganisasi:id,opd_id,parent_id,nama,level_jabatan')
                ->whereKey($data['penempatan_pegawai_id'])
                ->where('pegawai_id', $pegawai->id)
                ->whereDate('tanggal_mulai', '<=', $referenceDate)
                ->where(fn (Builder $query) => $query->whereNull('tanggal_selesai')->orWhereDate('tanggal_selesai', '>=', $referenceDate))
                ->first();

            if (! $placement) {
                throw ValidationException::withMessages(['penempatan_pegawai_id' => 'Penempatan tidak sesuai dengan pegawai yang dipilih.']);
            }

            if (! $placement->jabatanOrganisasi) {
                throw ValidationException::withMessages(['penempatan_pegawai_id' => 'Jabatan pada penempatan ini sudah tidak tersedia. Pilih penempatan aktif lainnya.']);
            }
        }

        $subjectOpdId = $placement?->jabatanOrganisasi?->opd_id ?? $pegawai->opd_id;
        if ($data['level_pk'] !== 'bupati' && (int) $subjectOpdId !== (int) ($data['opd_id'] ?? 0)) {
            throw ValidationException::withMessages([
                'pegawai_id' => 'Jabatan aktif pegawai harus berada pada OPD Perjanjian Kinerja.',
            ]);
        }

        if (in_array($data['level_pk'], ['bupati', 'kepala_opd'], true) && ! $placement) {
            throw ValidationException::withMessages(['penempatan_pegawai_id' => 'Pilih jabatan aktif yang digunakan sebagai identitas penandatangan PK.']);
        }

        if ($data['level_pk'] === 'bupati' && $placement?->jabatanOrganisasi?->level_jabatan !== 'kepala_daerah') {
            throw ValidationException::withMessages(['penempatan_pegawai_id' => 'PK Bupati hanya dapat memakai penempatan Kepala Daerah.']);
        }

        if ($data['level_pk'] === 'kepala_opd' && $placement?->jabatanOrganisasi?->level_jabatan !== 'jpt_pratama') {
            throw ValidationException::withMessages(['penempatan_pegawai_id' => 'PK Kepala OPD harus memakai penempatan JPT Pratama/Kepala Perangkat Daerah.']);
        }

        $atasan = null;
        $atasanPlacement = null;
        $parentJabatanId = $placement?->jabatanOrganisasi?->parent_id;
        if ($data['atasan_pegawai_id'] ?? null) {
            $atasan = Pegawai::query()->findOrFail($data['atasan_pegawai_id']);
            $atasanPlacement = RiwayatPejabatJabatan::query()
                ->with('jabatanOrganisasi:id,opd_id,nama,level_jabatan')
                ->where('pegawai_id', $atasan->id)
                ->when($parentJabatanId, fn (Builder $query, int $jabatanId) => $query->where('jabatan_organisasi_id', $jabatanId))
                ->when(! $parentJabatanId && $data['level_pk'] === 'kepala_opd', fn (Builder $query) => $query
                    ->whereHas('jabatanOrganisasi', fn (Builder $query) => $query->where('level_jabatan', 'kepala_daerah')))
                ->whereDate('tanggal_mulai', '<=', $referenceDate)
                ->where(fn (Builder $query) => $query->whereNull('tanggal_selesai')->orWhereDate('tanggal_selesai', '>=', $referenceDate))
                ->orderByDesc('tanggal_mulai')
                ->first();

            if ($data['level_pk'] === 'kepala_opd' && $atasanPlacement?->jabatanOrganisasi?->level_jabatan !== 'kepala_daerah') {
                throw ValidationException::withMessages(['atasan_pegawai_id' => 'Pihak Kedua PK Kepala OPD harus Bupati/Kepala Daerah aktif.']);
            }

            if (! in_array($data['level_pk'], ['bupati', 'kepala_opd'], true)
                && (int) ($atasanPlacement?->jabatanOrganisasi?->opd_id ?? $atasan->opd_id) !== (int) ($data['opd_id'] ?? 0)) {
                throw ValidationException::withMessages(['atasan_pegawai_id' => 'Atasan PK struktural/individu harus berada pada OPD yang sama.']);
            }

            if ($placement && $parentJabatanId && ! $atasanPlacement) {
                throw ValidationException::withMessages([
                    'atasan_pegawai_id' => 'Pihak Kedua harus pegawai aktif yang menduduki jabatan atasan langsung dari pemilik PK.',
                ]);
            }
        }

        if ($data['level_pk'] === 'kepala_opd' && ! $atasan) {
            throw ValidationException::withMessages(['atasan_pegawai_id' => 'Pilih Bupati sebagai Pihak Kedua PK Kepala OPD.']);
        }

        if (! in_array($data['level_pk'], ['bupati', 'kepala_opd'], true) && $placement && ! $parentJabatanId) {
            throw ValidationException::withMessages([
                'penempatan_pegawai_id' => 'Jabatan pemilik PK belum memiliki atasan langsung pada Struktur Organisasi.',
            ]);
        }

        if (! in_array($data['level_pk'], ['bupati'], true) && $placement && ! $atasan) {
            throw ValidationException::withMessages([
                'atasan_pegawai_id' => 'Pilih pegawai aktif pada jabatan atasan langsung sebagai Pihak Kedua.',
            ]);
        }

        if ($data['level_pk'] === 'bupati') {
            $data['opd_id'] = null;
            $data['renstra_opd_id'] = null;
            $data['dpa_opd_id'] = null;
            $data['renja_opd_id'] = null;
            $data['atasan_pegawai_id'] = null;
            $data['sumber_data'] = 'rkpd';
            $data['lingkup_kinerja_snapshot'] = null;
        } elseif ($data['level_pk'] === 'kepala_opd') {
            $dpa = DpaOpd::query()->find($data['dpa_opd_id']);
            $data['rkpd_id'] = null;
            $data['renja_opd_id'] = $dpa?->renja_opd_id;
            $data['sumber_data'] = 'dpa';
            $data['lingkup_kinerja_snapshot'] = null;
        } elseif ($data['level_pk'] === 'individu' && $data['tipe_pk'] === 'individual') {
            $data['renstra_opd_id'] = null;
            $data['rkpd_id'] = null;
            $data['dpa_opd_id'] = null;
            $data['renja_opd_id'] = null;
            $data['sumber_data'] = 'manual';
            $data['lingkup_kinerja_snapshot'] = null;
        } else {
            $data['rkpd_id'] = null;
            $data['dpa_opd_id'] = null;
            $data['renja_opd_id'] = null;
            $data['sumber_data'] = 'renstra_cascading';
        }

        $titleSubject = $data['level_pk'] === 'bupati'
            ? 'Bupati Banjarnegara'
            : ($placement?->jabatanOrganisasi?->nama ?: $pegawai->nama);
        $data['judul'] = "PK {$titleSubject} Tahun {$data['tahun']}";

        return [
            ...$data,
            'nama_pegawai_snapshot' => $pegawai->nama,
            'nip_snapshot' => $pegawai->nip,
            'jabatan_snapshot' => $placement?->jabatanOrganisasi?->nama,
            'nama_atasan_snapshot' => $atasan?->nama,
            'nip_atasan_snapshot' => $atasan?->nip,
            'jabatan_atasan_snapshot' => $atasanPlacement?->jabatanOrganisasi?->nama,
        ];
    }

    private function rkpdOptions(): array
    {
        return Rkpd::query()
            ->with('periodeTahun:id,tahun,nama')
            ->where('is_active_version', true)
            ->whereIn('jenis_versi', ['ditetapkan', 'perubahan'])
            ->whereIn('status', self::APPROVED_PLANNING_STATUSES)
            ->orderByDesc('tahun')
            ->get(['id', 'periode_tahun_id', 'tahun', 'judul', 'jenis_versi', 'status'])
            ->map(fn (Rkpd $rkpd) => [
                'id' => $rkpd->id,
                'periode_tahun_id' => $rkpd->periode_tahun_id,
                'tahun' => $rkpd->tahun,
                'label' => $rkpd->versionLabel().' '.$rkpd->tahun.' · '.$rkpd->judul,
            ])
            ->all();
    }

    private function dpaOptions(User $user): array
    {
        return DpaOpd::query()
            ->with(['opd:id,nama,singkatan', 'renjaOpd:id,renstra_opd_id'])
            ->when($this->shouldLimitToUserOpd($user), fn (Builder $query) => $query->where('opd_id', $user->opd_id))
            ->whereIn('status', self::APPROVED_PLANNING_STATUSES)
            ->orderByDesc('tahun')
            ->get(['id', 'opd_id', 'periode_tahun_id', 'renja_opd_id', 'tahun', 'jenis_anggaran', 'judul', 'nomor_dpa', 'status'])
            ->map(fn (DpaOpd $dpa) => [
                'id' => $dpa->id,
                'opd_id' => $dpa->opd_id,
                'periode_tahun_id' => $dpa->periode_tahun_id,
                'renja_opd_id' => $dpa->renja_opd_id,
                'renstra_opd_id' => $dpa->renjaOpd?->renstra_opd_id,
                'tahun' => $dpa->tahun,
                'label' => $dpa->typeLabel().' '.$dpa->tahun.' · '.($dpa->nomor_dpa ?: $dpa->judul),
            ])
            ->all();
    }

    private function assertNoDuplicate(array $data, ?PerjanjianKinerja $except = null): void
    {
        $query = PerjanjianKinerja::query()
            ->where('level_pk', $data['level_pk'])
            ->where('tahun', $data['tahun'])
            ->when($data['level_pk'] === 'bupati', fn (Builder $query) => $query->whereNull('opd_id'))
            ->when($data['level_pk'] !== 'bupati', fn (Builder $query) => $query->where('pegawai_id', $data['pegawai_id']))
            ->when($except, fn (Builder $query) => $query->where($query->getModel()->getQualifiedKeyName(), '!=', $except->getKey()));

        if ($query->exists()) {
            throw ValidationException::withMessages([
                'tahun' => 'Perjanjian Kinerja untuk pemilik, level, dan tahun tersebut sudah tersedia.',
            ]);
        }
    }

    private function canReviewWorkflow(User $user): bool
    {
        return $user->hasAnyRole(['super_admin', 'admin_kabupaten_bagian_organisasi', 'admin_kabupaten_inspektorat'])
            || $user->hasPermission('verify_realisasi')
            || $user->hasPermission('lock_period');
    }

    private function canLockWorkflow(User $user): bool
    {
        return $user->isSuperAdmin() || $user->hasPermission('lock_period');
    }

    private function canExportPerjanjianKinerja(User $user, PerjanjianKinerja $perjanjianKinerja): bool
    {
        return $user->can('update', $perjanjianKinerja)
            || ($user->hasRole('admin_opd') && (int) $user->opd_id === (int) $perjanjianKinerja->opd_id)
            || ($user->can('view', $perjanjianKinerja) && $user->hasPermission('export_laporan'));
    }
}
