<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Http\Requests\Master\StoreProgramPemerintahanReferenceRequest;
use App\Http\Requests\Master\UpdateProgramPemerintahanReferenceRequest;
use App\Models\BidangUrusan;
use App\Models\KegiatanPemerintahan;
use App\Models\PeriodeTahun;
use App\Models\ProgramPemerintahan;
use App\Models\Rpjmd;
use App\Models\SubKegiatanPemerintahan;
use App\Services\Master\CopyProgramKegiatanReferenceService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProgramPemerintahanController extends Controller
{
    public function index(Request $request): Response|RedirectResponse
    {
        abort_unless($request->user()->hasPermission('urusan.view'), 403);

        $level = $this->currentLevel($request);
        if ($level === 'kegiatan' && ! $request->integer('program_id')) {
            return redirect()->route('master.program-pemerintahan.index');
        }

        if ($level === 'sub_kegiatan' && ! $request->integer('kegiatan_id')) {
            return redirect()->route('master.program-pemerintahan.index');
        }

        $program = $level === 'kegiatan'
            ? ProgramPemerintahan::query()
                ->with('bidangUrusan.urusanPemerintahan:id,kode,nama')
                ->withCount('kegiatan')
                ->findOrFail((int) $request->integer('program_id'))
            : null;

        $kegiatan = $level === 'sub_kegiatan'
            ? KegiatanPemerintahan::query()
                ->with('programPemerintahan.bidangUrusan.urusanPemerintahan:id,kode,nama')
                ->withCount('subKegiatan')
                ->findOrFail((int) $request->integer('kegiatan_id'))
            : null;

        $program ??= $kegiatan?->programPemerintahan;

        $selectedProgramPeriod = $this->selectedProgramPeriod($request, $program);
        $selectedPeriodeId = $this->selectedPeriodeId($request, $kegiatan);
        $filters = [
            ...$request->only(['search', 'status', 'bidang_urusan_id', 'tahun_awal', 'tahun_akhir']),
            'periode_tahun_id' => $selectedPeriodeId,
            'tahun_awal' => $selectedProgramPeriod['tahun_awal'],
            'tahun_akhir' => $selectedProgramPeriod['tahun_akhir'],
        ];
        $items = $this->paginatedItems(
            $filters,
            (int) $request->integer('page', 1),
            $level,
            $program?->id,
            $kegiatan?->id,
            $selectedPeriodeId,
            $selectedProgramPeriod,
        );

        return Inertia::render('Master/ProgramPemerintahan/Index', [
            'items' => $items,
            'filters' => $filters,
            'level' => $level,
            'context' => [
                'program' => $program ? $this->programContext($program) : null,
                'kegiatan' => $kegiatan ? $this->kegiatanContext($kegiatan) : null,
            ],
            'summary' => $this->summary($selectedPeriodeId, $selectedProgramPeriod, $program, $kegiatan),
            'options' => [
                'periode' => $this->periodeOptions(),
                'programPeriode' => $this->programPeriodeOptions(),
                'bidang' => $this->bidangOptions(),
                'program' => $this->programOptions($selectedProgramPeriod),
                'kegiatan' => $this->kegiatanOptions($selectedPeriodeId),
            ],
            'selectedPeriodeId' => $selectedPeriodeId,
            'selectedProgramPeriod' => $selectedProgramPeriod,
            'can' => [
                'manage' => $request->user()->hasPermission('urusan.manage'),
            ],
        ]);
    }

    public function store(StoreProgramPemerintahanReferenceRequest $request): RedirectResponse
    {
        $data = $request->validated();

        match ($data['type']) {
            'program' => ProgramPemerintahan::create([
                'bidang_urusan_id' => $data['bidang_urusan_id'],
                'tahun_awal' => $data['tahun_awal'],
                'tahun_akhir' => $data['tahun_akhir'],
                'kode' => $data['kode'],
                'nama' => $data['nama'],
                'status' => $data['status'],
            ]),
            'kegiatan' => KegiatanPemerintahan::create([
                'periode_tahun_id' => $data['periode_tahun_id'],
                'program_pemerintahan_id' => $data['program_pemerintahan_id'],
                'kode' => $data['kode'],
                'nama' => $data['nama'],
                'status' => $data['status'],
            ]),
            'sub_kegiatan' => SubKegiatanPemerintahan::create([
                'periode_tahun_id' => $data['periode_tahun_id'],
                'kegiatan_pemerintahan_id' => $data['kegiatan_pemerintahan_id'],
                'kode' => $data['kode'],
                'nama' => $data['nama'],
                'status' => $data['status'],
            ]),
        };

        return $this->redirectToContext($request)->with('success', 'Referensi program/kegiatan berhasil disimpan.');
    }

    public function bulkStore(Request $request): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('urusan.manage'), 403);

        $data = $request->validate([
            'type' => ['required', 'in:program,kegiatan,sub_kegiatan'],
            'periode_tahun_id' => ['required_unless:type,program', 'nullable', 'integer', 'exists:periode_tahun,id'],
            'tahun_awal' => ['required_if:type,program', 'nullable', 'integer', 'min:2000', 'max:2100'],
            'tahun_akhir' => ['required_if:type,program', 'nullable', 'integer', 'min:2000', 'max:2100', 'gte:tahun_awal'],
            'bidang_urusan_id' => ['required_if:type,program', 'nullable', 'integer', 'exists:bidang_urusan,id'],
            'program_pemerintahan_id' => ['required_if:type,kegiatan', 'nullable', 'integer', 'exists:program_pemerintahan,id'],
            'kegiatan_pemerintahan_id' => ['required_if:type,sub_kegiatan', 'nullable', 'integer', 'exists:kegiatan_pemerintahan,id'],
            'rows' => ['required', 'string', 'max:60000'],
            'status' => ['required', 'in:active,inactive'],
        ]);

        $rows = collect(preg_split('/\r\n|\r|\n/', $data['rows']) ?: [])
            ->map(fn (string $line) => trim($line))
            ->filter()
            ->map(fn (string $line) => $this->parseBulkLine($line))
            ->filter(fn (?array $row) => $row !== null)
            ->unique(fn (array $row) => $row['kode'])
            ->values();

        if ($rows->isEmpty()) {
            return back()->with('error', 'Tidak ada baris valid. Gunakan format: kode | nama.');
        }

        if ($data['type'] !== 'program') {
            $data['periode_tahun_id'] = $this->resolvePeriodeTahunIdFromData($data);
        }

        DB::transaction(function () use ($data, $rows) {
            foreach ($rows as $row) {
                match ($data['type']) {
                    'program' => ProgramPemerintahan::updateOrCreate(
                        [
                            'tahun_awal' => $data['tahun_awal'],
                            'tahun_akhir' => $data['tahun_akhir'],
                            'bidang_urusan_id' => $data['bidang_urusan_id'],
                            'kode' => $row['kode'],
                        ],
                        ['nama' => $row['nama'], 'status' => $data['status']],
                    ),
                    'kegiatan' => KegiatanPemerintahan::updateOrCreate(
                        [
                            'periode_tahun_id' => $data['periode_tahun_id'],
                            'program_pemerintahan_id' => $data['program_pemerintahan_id'],
                            'kode' => $row['kode'],
                        ],
                        ['nama' => $row['nama'], 'status' => $data['status']],
                    ),
                    'sub_kegiatan' => SubKegiatanPemerintahan::updateOrCreate(
                        [
                            'periode_tahun_id' => $data['periode_tahun_id'],
                            'kegiatan_pemerintahan_id' => $data['kegiatan_pemerintahan_id'],
                            'kode' => $row['kode'],
                        ],
                        ['nama' => $row['nama'], 'status' => $data['status']],
                    ),
                };
            }
        });

        return $this->redirectToContext($request)->with('success', $rows->count().' baris referensi berhasil disimpan.');
    }

    public function copy(Request $request, CopyProgramKegiatanReferenceService $service): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('urusan.manage'), 403);

        $data = $request->validate([
            'source_tahun_awal' => ['required', 'integer', 'min:2000', 'max:2100'],
            'source_tahun_akhir' => ['required', 'integer', 'min:2000', 'max:2100', 'gte:source_tahun_awal'],
            'target_tahun_awal' => ['required', 'integer', 'min:2000', 'max:2100'],
            'target_tahun_akhir' => ['required', 'integer', 'min:2000', 'max:2100', 'gte:target_tahun_awal'],
        ], [
            'source_tahun_akhir.gte' => 'Tahun akhir sumber harus lebih besar atau sama dengan tahun awal.',
            'target_tahun_akhir.gte' => 'Tahun akhir tujuan harus lebih besar atau sama dengan tahun awal.',
        ]);

        try {
            $result = $service->copyProgramPeriod(
                (int) $data['source_tahun_awal'],
                (int) $data['source_tahun_akhir'],
                (int) $data['target_tahun_awal'],
                (int) $data['target_tahun_akhir'],
            );
        } catch (InvalidArgumentException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return redirect()
            ->route('master.program-pemerintahan.index', [
                'tahun_awal' => $data['target_tahun_awal'],
                'tahun_akhir' => $data['target_tahun_akhir'],
            ])
            ->with(
                'success',
                "Salin selesai: {$result['program_created']} program baru ditambahkan untuk periode RPJMD {$data['target_tahun_awal']}-{$data['target_tahun_akhir']}.",
            );
    }

    public function copyKegiatanYear(Request $request, CopyProgramKegiatanReferenceService $service): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('urusan.manage'), 403);

        $data = $request->validate([
            'program_pemerintahan_id' => ['required', 'integer', 'exists:program_pemerintahan,id'],
            'source_periode_tahun_id' => ['required', 'integer', 'exists:periode_tahun,id', 'different:target_periode_tahun_id'],
            'target_periode_tahun_id' => ['required', 'integer', 'exists:periode_tahun,id'],
        ], [
            'source_periode_tahun_id.different' => 'Tahun sumber dan tahun tujuan tidak boleh sama.',
        ]);

        try {
            $result = $service->copyKegiatanYear(
                (int) $data['program_pemerintahan_id'],
                (int) $data['source_periode_tahun_id'],
                (int) $data['target_periode_tahun_id'],
            );
        } catch (InvalidArgumentException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return redirect()
            ->route('master.program-pemerintahan.index', [
                'level' => 'kegiatan',
                'program_id' => $data['program_pemerintahan_id'],
                'periode_tahun_id' => $data['target_periode_tahun_id'],
            ])
            ->with(
                'success',
                "Salin selesai: {$result['kegiatan_created']} kegiatan dan {$result['sub_kegiatan_created']} sub kegiatan baru ditambahkan.",
            );
    }

    public function copyKegiatanYears(Request $request, CopyProgramKegiatanReferenceService $service): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('urusan.manage'), 403);

        $data = $request->validate([
            'tahun_awal' => ['required', 'integer', 'min:2000', 'max:2100'],
            'tahun_akhir' => ['required', 'integer', 'min:2000', 'max:2100', 'gte:tahun_awal'],
            'source_periode_tahun_id' => ['required', 'integer', 'exists:periode_tahun,id'],
            'target_periode_tahun_ids' => ['required', 'array', 'min:1'],
            'target_periode_tahun_ids.*' => ['required', 'integer', 'exists:periode_tahun,id'],
        ], [
            'tahun_akhir.gte' => 'Tahun akhir RPJMD harus lebih besar atau sama dengan tahun awal.',
            'target_periode_tahun_ids.required' => 'Pilih minimal satu tahun tujuan.',
            'target_periode_tahun_ids.min' => 'Pilih minimal satu tahun tujuan.',
        ]);

        $periodeIds = collect([$data['source_periode_tahun_id'], ...$data['target_periode_tahun_ids']])
            ->map(fn (int|string $id) => (int) $id)
            ->unique()
            ->values();
        $periodeById = PeriodeTahun::query()
            ->whereIn('id', $periodeIds)
            ->get(['id', 'tahun'])
            ->keyBy('id');
        $sourcePeriode = $periodeById[(int) $data['source_periode_tahun_id']] ?? null;

        if (! $sourcePeriode || $sourcePeriode->tahun < $data['tahun_awal'] || $sourcePeriode->tahun > $data['tahun_akhir']) {
            return back()->with('error', 'Tahun sumber harus berada dalam rentang RPJMD yang dipilih.');
        }

        foreach ($data['target_periode_tahun_ids'] as $targetPeriodeTahunId) {
            $targetPeriode = $periodeById[(int) $targetPeriodeTahunId] ?? null;

            if (! $targetPeriode || $targetPeriode->tahun < $data['tahun_awal'] || $targetPeriode->tahun > $data['tahun_akhir']) {
                return back()->with('error', 'Semua tahun tujuan harus berada dalam rentang RPJMD yang dipilih.');
            }
        }

        try {
            $result = $service->copyKegiatanYearsForProgramPeriod(
                (int) $data['tahun_awal'],
                (int) $data['tahun_akhir'],
                (int) $data['source_periode_tahun_id'],
                array_map('intval', $data['target_periode_tahun_ids']),
            );
        } catch (InvalidArgumentException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return redirect()
            ->route('master.program-pemerintahan.index', [
                'tahun_awal' => $data['tahun_awal'],
                'tahun_akhir' => $data['tahun_akhir'],
            ])
            ->with(
                'success',
                "Salin selesai: {$result['kegiatan_created']} kegiatan dan {$result['sub_kegiatan_created']} sub kegiatan baru ditambahkan ke tahun tujuan.",
            );
    }

    public function update(UpdateProgramPemerintahanReferenceRequest $request, string $type, int $id): RedirectResponse
    {
        $data = $request->validated();
        $model = $this->findReference($type, $id);

        match ($type) {
            'program' => $model->update([
                'bidang_urusan_id' => $data['bidang_urusan_id'],
                'tahun_awal' => $data['tahun_awal'],
                'tahun_akhir' => $data['tahun_akhir'],
                'kode' => $data['kode'],
                'nama' => $data['nama'],
                'status' => $data['status'],
            ]),
            'kegiatan' => $model->update([
                'periode_tahun_id' => $data['periode_tahun_id'],
                'program_pemerintahan_id' => $data['program_pemerintahan_id'],
                'kode' => $data['kode'],
                'nama' => $data['nama'],
                'status' => $data['status'],
            ]),
            'sub_kegiatan' => $model->update([
                'periode_tahun_id' => $data['periode_tahun_id'],
                'kegiatan_pemerintahan_id' => $data['kegiatan_pemerintahan_id'],
                'kode' => $data['kode'],
                'nama' => $data['nama'],
                'status' => $data['status'],
            ]),
            default => abort(404),
        };

        return $this->redirectToContext($request)->with('success', 'Referensi program/kegiatan berhasil diperbarui.');
    }

    public function destroy(Request $request, string $type, int $id): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('urusan.manage'), 403);

        $model = $this->findReference($type, $id);

        if ($this->hasChildren($model)) {
            return back()->with('error', 'Data masih memiliki turunan. Hapus atau nonaktifkan turunannya terlebih dahulu.');
        }

        $model->delete();

        return back()->with('success', 'Referensi program/kegiatan berhasil dihapus.');
    }

    public function export(Request $request): StreamedResponse
    {
        abort_unless($request->user()->hasPermission('urusan.view'), 403);
        abort(404, 'Export program/kegiatan sementara dinonaktifkan.');
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    /**
     * @param  array{tahun_awal: int, tahun_akhir: int}  $programPeriod
     */
    private function paginatedItems(
        array $filters,
        int $page,
        string $level,
        ?int $programId,
        ?int $kegiatanId,
        int $periodeTahunId,
        array $programPeriod,
    ): LengthAwarePaginator {
        $items = $this->allItems($filters, $level, $programId, $kegiatanId, $periodeTahunId, $programPeriod);
        $perPage = 15;

        return new LengthAwarePaginator(
            $items->forPage($page, $perPage)->values(),
            $items->count(),
            $perPage,
            $page,
            [
                'path' => request()->url(),
                'query' => request()->query(),
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    /**
     * @param  array{tahun_awal: int, tahun_akhir: int}  $programPeriod
     */
    private function allItems(
        array $filters,
        string $level = 'program',
        ?int $programId = null,
        ?int $kegiatanId = null,
        int $periodeTahunId = 0,
        array $programPeriod = ['tahun_awal' => 0, 'tahun_akhir' => 0],
    ): Collection {
        $items = match ($level) {
            'kegiatan' => $this->kegiatanRows($programId, $periodeTahunId),
            'sub_kegiatan' => $this->subKegiatanRows($kegiatanId, $periodeTahunId),
            default => $this->programRows($filters['bidang_urusan_id'] ?? null, $programPeriod, $periodeTahunId),
        };

        return $items
            ->when($filters['status'] ?? null, fn (Collection $items, string $status) => $items->where('status', $status))
            ->when($filters['search'] ?? null, function (Collection $items, string $search) {
                $needle = str($search)->lower()->toString();

                return $items->filter(fn (array $item) => str($item['search_text'])->lower()->contains($needle));
            })
            ->sortBy('sort_key')
            ->values();
    }

    /**
     * @param  array{tahun_awal: int, tahun_akhir: int}  $programPeriod
     */
    private function programRows(mixed $bidangUrusanId, array $programPeriod, int $periodeTahunId): Collection
    {
        return ProgramPemerintahan::query()
            ->with(['bidangUrusan.urusanPemerintahan:id,kode,nama'])
            ->withCount(['kegiatan' => fn ($query) => $query->where('periode_tahun_id', $periodeTahunId)])
            ->where('tahun_awal', $programPeriod['tahun_awal'])
            ->where('tahun_akhir', $programPeriod['tahun_akhir'])
            ->when($bidangUrusanId, fn ($query) => $query->where('bidang_urusan_id', $bidangUrusanId))
            ->orderBy('kode')
            ->get()
            ->map(fn (ProgramPemerintahan $program) => [
                'id' => $program->id,
                'type' => 'program',
                'level' => 'Program',
                'periode_tahun_id' => null,
                'tahun_awal' => $program->tahun_awal,
                'tahun_akhir' => $program->tahun_akhir,
                'periode_label' => "{$program->tahun_awal}-{$program->tahun_akhir}",
                'kode' => $program->kode,
                'nama' => $program->nama,
                'status' => $program->status,
                'parent_id' => $program->bidang_urusan_id,
                'parent_label' => $this->label($program->bidangUrusan?->kode, $program->bidangUrusan?->nama),
                'bidang_label' => $this->label($program->bidangUrusan?->kode, $program->bidangUrusan?->nama),
                'urusan_label' => $this->label(
                    $program->bidangUrusan?->urusanPemerintahan?->kode,
                    $program->bidangUrusan?->urusanPemerintahan?->nama,
                ),
                'children_count' => $program->kegiatan_count,
                'children_label' => $program->kegiatan_count.' kegiatan',
                'drilldown_url' => route('master.program-pemerintahan.index', [
                    'level' => 'kegiatan',
                    'program_id' => $program->id,
                    'periode_tahun_id' => $periodeTahunId,
                ]),
                'sort_key' => $this->sortKey($program->bidangUrusan?->urusanPemerintahan?->kode, $program->bidangUrusan?->kode, $program->kode),
                'search_text' => $this->searchText(
                    $program->kode,
                    $program->nama,
                    $program->bidangUrusan?->nama,
                    $program->bidangUrusan?->urusanPemerintahan?->nama,
                ),
            ]);
    }

    private function kegiatanRows(?int $programId, int $periodeTahunId): Collection
    {
        return KegiatanPemerintahan::query()
            ->with(['periodeTahun:id,tahun,nama', 'programPemerintahan.bidangUrusan.urusanPemerintahan:id,kode,nama'])
            ->withCount('subKegiatan')
            ->where('periode_tahun_id', $periodeTahunId)
            ->when($programId, fn ($query) => $query->where('program_pemerintahan_id', $programId))
            ->orderBy('kode')
            ->get()
            ->map(fn (KegiatanPemerintahan $kegiatan) => [
                'id' => $kegiatan->id,
                'type' => 'kegiatan',
                'level' => 'Kegiatan',
                'periode_tahun_id' => $kegiatan->periode_tahun_id,
                'periode_label' => $this->label((string) $kegiatan->periodeTahun?->tahun, $kegiatan->periodeTahun?->nama),
                'kode' => $kegiatan->kode,
                'nama' => $kegiatan->nama,
                'status' => $kegiatan->status,
                'parent_id' => $kegiatan->program_pemerintahan_id,
                'parent_label' => $this->label($kegiatan->programPemerintahan?->kode, $kegiatan->programPemerintahan?->nama),
                'bidang_label' => $this->label(
                    $kegiatan->programPemerintahan?->bidangUrusan?->kode,
                    $kegiatan->programPemerintahan?->bidangUrusan?->nama,
                ),
                'urusan_label' => $this->label(
                    $kegiatan->programPemerintahan?->bidangUrusan?->urusanPemerintahan?->kode,
                    $kegiatan->programPemerintahan?->bidangUrusan?->urusanPemerintahan?->nama,
                ),
                'children_count' => $kegiatan->sub_kegiatan_count,
                'children_label' => $kegiatan->sub_kegiatan_count.' sub kegiatan',
                'drilldown_url' => route('master.program-pemerintahan.index', [
                    'level' => 'sub_kegiatan',
                    'kegiatan_id' => $kegiatan->id,
                    'periode_tahun_id' => $kegiatan->periode_tahun_id,
                ]),
                'sort_key' => $this->sortKey(
                    $kegiatan->programPemerintahan?->bidangUrusan?->kode,
                    $kegiatan->programPemerintahan?->kode,
                    $kegiatan->kode,
                ),
                'search_text' => $this->searchText(
                    $kegiatan->kode,
                    $kegiatan->nama,
                    $kegiatan->programPemerintahan?->nama,
                    $kegiatan->programPemerintahan?->bidangUrusan?->nama,
                ),
            ]);
    }

    private function subKegiatanRows(?int $kegiatanId, int $periodeTahunId): Collection
    {
        return SubKegiatanPemerintahan::query()
            ->with(['periodeTahun:id,tahun,nama', 'kegiatanPemerintahan.programPemerintahan.bidangUrusan.urusanPemerintahan:id,kode,nama'])
            ->where('periode_tahun_id', $periodeTahunId)
            ->when($kegiatanId, fn ($query) => $query->where('kegiatan_pemerintahan_id', $kegiatanId))
            ->orderBy('kode')
            ->get()
            ->map(fn (SubKegiatanPemerintahan $subKegiatan) => [
                'id' => $subKegiatan->id,
                'type' => 'sub_kegiatan',
                'level' => 'Sub Kegiatan',
                'periode_tahun_id' => $subKegiatan->periode_tahun_id,
                'periode_label' => $this->label((string) $subKegiatan->periodeTahun?->tahun, $subKegiatan->periodeTahun?->nama),
                'kode' => $subKegiatan->kode,
                'nama' => $subKegiatan->nama,
                'status' => $subKegiatan->status,
                'parent_id' => $subKegiatan->kegiatan_pemerintahan_id,
                'parent_label' => $this->label($subKegiatan->kegiatanPemerintahan?->kode, $subKegiatan->kegiatanPemerintahan?->nama),
                'bidang_label' => $this->label(
                    $subKegiatan->kegiatanPemerintahan?->programPemerintahan?->bidangUrusan?->kode,
                    $subKegiatan->kegiatanPemerintahan?->programPemerintahan?->bidangUrusan?->nama,
                ),
                'urusan_label' => $this->label(
                    $subKegiatan->kegiatanPemerintahan?->programPemerintahan?->bidangUrusan?->urusanPemerintahan?->kode,
                    $subKegiatan->kegiatanPemerintahan?->programPemerintahan?->bidangUrusan?->urusanPemerintahan?->nama,
                ),
                'children_count' => 0,
                'children_label' => '-',
                'drilldown_url' => null,
                'sort_key' => $this->sortKey(
                    $subKegiatan->kegiatanPemerintahan?->programPemerintahan?->kode,
                    $subKegiatan->kegiatanPemerintahan?->kode,
                    $subKegiatan->kode,
                ),
                'search_text' => $this->searchText(
                    $subKegiatan->kode,
                    $subKegiatan->nama,
                    $subKegiatan->kegiatanPemerintahan?->nama,
                    $subKegiatan->kegiatanPemerintahan?->programPemerintahan?->nama,
                ),
            ]);
    }

    private function findReference(string $type, int $id): Model
    {
        return match ($type) {
            'program' => ProgramPemerintahan::findOrFail($id),
            'kegiatan' => KegiatanPemerintahan::findOrFail($id),
            'sub_kegiatan' => SubKegiatanPemerintahan::findOrFail($id),
            default => abort(404),
        };
    }

    private function hasChildren(Model $model): bool
    {
        return match (true) {
            $model instanceof ProgramPemerintahan => $model->kegiatan()->exists(),
            $model instanceof KegiatanPemerintahan => $model->subKegiatan()->exists(),
            default => false,
        };
    }

    private function bidangOptions(): array
    {
        return BidangUrusan::query()
            ->with('urusanPemerintahan:id,kode,nama')
            ->where('status', 'active')
            ->orderBy('kode')
            ->get(['id', 'urusan_pemerintahan_id', 'kode', 'nama'])
            ->map(fn (BidangUrusan $bidang) => [
                'id' => $bidang->id,
                'label' => $this->label($bidang->kode, $bidang->nama),
                'description' => $this->label($bidang->urusanPemerintahan?->kode, $bidang->urusanPemerintahan?->nama),
                'group' => $this->label($bidang->urusanPemerintahan?->kode, $bidang->urusanPemerintahan?->nama),
            ])
            ->all();
    }

    private function periodeOptions(): array
    {
        return PeriodeTahun::query()
            ->orderByDesc('tahun')
            ->get(['id', 'tahun', 'nama', 'status'])
            ->map(fn (PeriodeTahun $periode) => [
                'id' => $periode->id,
                'tahun' => $periode->tahun,
                'label' => "{$periode->tahun} - {$periode->nama}".($periode->status === 'active' ? ' (Aktif)' : ''),
            ])
            ->all();
    }

    private function programPeriodeOptions(): array
    {
        $existing = ProgramPemerintahan::query()
            ->select(['tahun_awal', 'tahun_akhir'])
            ->distinct()
            ->orderByDesc('tahun_awal')
            ->get()
            ->map(fn (ProgramPemerintahan $program) => [
                'id' => "{$program->tahun_awal}-{$program->tahun_akhir}",
                'label' => "RPJMD {$program->tahun_awal}-{$program->tahun_akhir}",
                'tahun_awal' => $program->tahun_awal,
                'tahun_akhir' => $program->tahun_akhir,
            ]);

        $rpjmd = Rpjmd::query()
            ->select(['tahun_awal', 'tahun_akhir'])
            ->distinct()
            ->orderByDesc('tahun_awal')
            ->get()
            ->map(fn (Rpjmd $rpjmd) => [
                'id' => "{$rpjmd->tahun_awal}-{$rpjmd->tahun_akhir}",
                'label' => "RPJMD {$rpjmd->tahun_awal}-{$rpjmd->tahun_akhir}",
                'tahun_awal' => $rpjmd->tahun_awal,
                'tahun_akhir' => $rpjmd->tahun_akhir,
            ]);

        return $existing
            ->merge($rpjmd)
            ->unique('id')
            ->values()
            ->all();
    }

    /**
     * @param  array{tahun_awal: int, tahun_akhir: int}  $programPeriod
     */
    private function programOptions(array $programPeriod): array
    {
        return ProgramPemerintahan::query()
            ->with('bidangUrusan.urusanPemerintahan:id,kode,nama')
            ->where('tahun_awal', $programPeriod['tahun_awal'])
            ->where('tahun_akhir', $programPeriod['tahun_akhir'])
            ->where('status', 'active')
            ->orderBy('kode')
            ->get(['id', 'bidang_urusan_id', 'kode', 'nama'])
            ->map(fn (ProgramPemerintahan $program) => [
                'id' => $program->id,
                'label' => $this->label($program->kode, $program->nama),
                'description' => $this->label($program->bidangUrusan?->kode, $program->bidangUrusan?->nama),
                'group' => $this->label(
                    $program->bidangUrusan?->urusanPemerintahan?->kode,
                    $program->bidangUrusan?->urusanPemerintahan?->nama,
                ),
            ])
            ->all();
    }

    private function kegiatanOptions(int $periodeTahunId): array
    {
        return KegiatanPemerintahan::query()
            ->with('programPemerintahan:id,kode,nama')
            ->where('periode_tahun_id', $periodeTahunId)
            ->where('status', 'active')
            ->orderBy('kode')
            ->get(['id', 'program_pemerintahan_id', 'kode', 'nama'])
            ->map(fn (KegiatanPemerintahan $kegiatan) => [
                'id' => $kegiatan->id,
                'label' => $this->label($kegiatan->kode, $kegiatan->nama),
                'description' => $this->label($kegiatan->programPemerintahan?->kode, $kegiatan->programPemerintahan?->nama),
                'group' => $this->label($kegiatan->programPemerintahan?->kode, $kegiatan->programPemerintahan?->nama),
            ])
            ->all();
    }

    private function currentLevel(Request $request): string
    {
        $level = (string) ($request->query('level') ?: $request->query('type') ?: 'program');

        return in_array($level, ['program', 'kegiatan', 'sub_kegiatan'], true) ? $level : 'program';
    }

    /**
     * @return array<string, mixed>
     */
    private function programContext(ProgramPemerintahan $program): array
    {
        return [
            'id' => $program->id,
            'periode_tahun_id' => null,
            'tahun_awal' => $program->tahun_awal,
            'tahun_akhir' => $program->tahun_akhir,
            'periode_label' => "{$program->tahun_awal}-{$program->tahun_akhir}",
            'kode' => $program->kode,
            'nama' => $program->nama,
            'label' => $this->label($program->kode, $program->nama),
            'bidang_label' => $this->label($program->bidangUrusan?->kode, $program->bidangUrusan?->nama),
            'urusan_label' => $this->label(
                $program->bidangUrusan?->urusanPemerintahan?->kode,
                $program->bidangUrusan?->urusanPemerintahan?->nama,
            ),
            'children_count' => (int) ($program->kegiatan_count ?? $program->kegiatan()->count()),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function kegiatanContext(KegiatanPemerintahan $kegiatan): array
    {
        $kegiatan->loadMissing('periodeTahun:id,tahun,nama');

        return [
            'id' => $kegiatan->id,
            'periode_tahun_id' => $kegiatan->periode_tahun_id,
            'periode_label' => $this->label((string) $kegiatan->periodeTahun?->tahun, $kegiatan->periodeTahun?->nama),
            'kode' => $kegiatan->kode,
            'nama' => $kegiatan->nama,
            'label' => $this->label($kegiatan->kode, $kegiatan->nama),
            'program_label' => $this->label(
                $kegiatan->programPemerintahan?->kode,
                $kegiatan->programPemerintahan?->nama,
            ),
            'children_count' => (int) ($kegiatan->sub_kegiatan_count ?? $kegiatan->subKegiatan()->count()),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    /**
     * @param  array{tahun_awal: int, tahun_akhir: int}  $programPeriod
     */
    private function summary(int $periodeTahunId, array $programPeriod, ?ProgramPemerintahan $program, ?KegiatanPemerintahan $kegiatan): array
    {
        if ($kegiatan) {
            return [
                'program_count' => 1,
                'kegiatan_count' => 1,
                'sub_kegiatan_count' => SubKegiatanPemerintahan::query()
                    ->where('kegiatan_pemerintahan_id', $kegiatan->id)
                    ->where('periode_tahun_id', $periodeTahunId)
                    ->count(),
            ];
        }

        if ($program) {
            return [
                'program_count' => 1,
                'kegiatan_count' => KegiatanPemerintahan::query()
                    ->where('program_pemerintahan_id', $program->id)
                    ->where('periode_tahun_id', $periodeTahunId)
                    ->count(),
                'sub_kegiatan_count' => SubKegiatanPemerintahan::query()
                    ->where('periode_tahun_id', $periodeTahunId)
                    ->whereHas('kegiatanPemerintahan', fn ($query) => $query->where('program_pemerintahan_id', $program->id))
                    ->count(),
            ];
        }

        return [
            'program_count' => ProgramPemerintahan::query()
                ->where('tahun_awal', $programPeriod['tahun_awal'])
                ->where('tahun_akhir', $programPeriod['tahun_akhir'])
                ->count(),
            'kegiatan_count' => KegiatanPemerintahan::query()
                ->where('periode_tahun_id', $periodeTahunId)
                ->whereHas('programPemerintahan', function ($query) use ($programPeriod) {
                    $query->where('tahun_awal', $programPeriod['tahun_awal'])
                        ->where('tahun_akhir', $programPeriod['tahun_akhir']);
                })
                ->count(),
            'sub_kegiatan_count' => SubKegiatanPemerintahan::query()
                ->where('periode_tahun_id', $periodeTahunId)
                ->whereHas('kegiatanPemerintahan.programPemerintahan', function ($query) use ($programPeriod) {
                    $query->where('tahun_awal', $programPeriod['tahun_awal'])
                        ->where('tahun_akhir', $programPeriod['tahun_akhir']);
                })
                ->count(),
        ];
    }

    private function redirectToContext(Request $request): RedirectResponse
    {
        $redirectTo = trim((string) $request->input('redirect_to'));

        if (str_starts_with($redirectTo, '/master/program-pemerintahan')) {
            return redirect()->to($redirectTo);
        }

        return redirect()->route('master.program-pemerintahan.index');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function resolvePeriodeTahunIdFromData(array $data): int
    {
        $periodeTahunId = match ($data['type']) {
            'sub_kegiatan' => KegiatanPemerintahan::query()
                ->whereKey($data['kegiatan_pemerintahan_id'] ?? null)
                ->value('periode_tahun_id'),
            default => $data['periode_tahun_id'] ?? null,
        };

        abort_if(blank($periodeTahunId), 422, 'Periode tahun referensi belum dipilih.');

        return (int) $periodeTahunId;
    }

    private function selectedPeriodeId(Request $request, ?KegiatanPemerintahan $kegiatan): int
    {
        if ($kegiatan?->periode_tahun_id) {
            return (int) $kegiatan->periode_tahun_id;
        }

        $requested = (int) $request->integer('periode_tahun_id');

        if ($requested && PeriodeTahun::query()->whereKey($requested)->exists()) {
            return $requested;
        }

        $periodeId = PeriodeTahun::query()
            ->where('status', 'active')
            ->orderByDesc('tahun')
            ->value('id')
            ?? PeriodeTahun::query()->orderByDesc('tahun')->value('id');

        abort_if(blank($periodeId), 422, 'Periode tahun belum tersedia.');

        return (int) $periodeId;
    }

    /**
     * @return array{tahun_awal: int, tahun_akhir: int}
     */
    private function selectedProgramPeriod(Request $request, ?ProgramPemerintahan $program): array
    {
        if ($program?->tahun_awal && $program?->tahun_akhir) {
            return [
                'tahun_awal' => (int) $program->tahun_awal,
                'tahun_akhir' => (int) $program->tahun_akhir,
            ];
        }

        $tahunAwal = (int) $request->integer('tahun_awal');
        $tahunAkhir = (int) $request->integer('tahun_akhir');

        if ($tahunAwal && $tahunAkhir && $tahunAkhir >= $tahunAwal) {
            return [
                'tahun_awal' => $tahunAwal,
                'tahun_akhir' => $tahunAkhir,
            ];
        }

        $rpjmd = Rpjmd::query()
            ->orderByDesc('tahun_awal')
            ->first(['tahun_awal', 'tahun_akhir']);

        if ($rpjmd) {
            return [
                'tahun_awal' => (int) $rpjmd->tahun_awal,
                'tahun_akhir' => (int) $rpjmd->tahun_akhir,
            ];
        }

        $tahun = PeriodeTahun::query()
            ->where('status', 'active')
            ->orderByDesc('tahun')
            ->value('tahun')
            ?? PeriodeTahun::query()->orderByDesc('tahun')->value('tahun');

        abort_if(blank($tahun), 422, 'Periode tahun belum tersedia.');

        return [
            'tahun_awal' => (int) $tahun,
            'tahun_akhir' => (int) $tahun + 4,
        ];
    }

    private function label(?string $kode, ?string $nama): string
    {
        return trim(collect([$kode, $nama])->filter()->implode(' - ')) ?: '-';
    }

    private function sortKey(?string ...$parts): string
    {
        return collect($parts)->map(fn (?string $part) => str_pad((string) ($part ?? '999'), 20, '0', STR_PAD_LEFT))->implode('.');
    }

    private function searchText(?string ...$parts): string
    {
        return collect($parts)->filter()->implode(' ');
    }

    /**
     * @return array{kode: string, nama: string}|null
     */
    private function parseBulkLine(string $line): ?array
    {
        $parts = preg_split('/\s*[|;\t,]\s*/', $line, 2);

        if (! is_array($parts) || count($parts) < 2) {
            return null;
        }

        $kode = trim($parts[0]);
        $nama = trim($parts[1]);

        if ($kode === '' || $nama === '') {
            return null;
        }

        return [
            'kode' => str($kode)->limit(80, '')->toString(),
            'nama' => str($nama)->limit(255, '')->toString(),
        ];
    }
}
