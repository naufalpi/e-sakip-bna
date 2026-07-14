<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Http\Requests\Master\StoreProgramPemerintahanReferenceRequest;
use App\Http\Requests\Master\UpdateProgramPemerintahanReferenceRequest;
use App\Models\BidangUrusan;
use App\Models\KegiatanPemerintahan;
use App\Models\ProgramPemerintahan;
use App\Models\SubKegiatanPemerintahan;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
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

        $filters = $request->only(['search', 'status', 'bidang_urusan_id']);
        $items = $this->paginatedItems(
            $filters,
            (int) $request->integer('page', 1),
            $level,
            $program?->id,
            $kegiatan?->id,
        );

        return Inertia::render('Master/ProgramPemerintahan/Index', [
            'items' => $items,
            'filters' => $filters,
            'level' => $level,
            'context' => [
                'program' => $program ? $this->programContext($program) : null,
                'kegiatan' => $kegiatan ? $this->kegiatanContext($kegiatan) : null,
            ],
            'summary' => $this->summary($program, $kegiatan),
            'options' => [
                'bidang' => $this->bidangOptions(),
                'program' => $this->programOptions(),
                'kegiatan' => $this->kegiatanOptions(),
            ],
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
                'kode' => $data['kode'],
                'nama' => $data['nama'],
                'status' => $data['status'],
            ]),
            'kegiatan' => KegiatanPemerintahan::create([
                'program_pemerintahan_id' => $data['program_pemerintahan_id'],
                'kode' => $data['kode'],
                'nama' => $data['nama'],
                'status' => $data['status'],
            ]),
            'sub_kegiatan' => SubKegiatanPemerintahan::create([
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

        DB::transaction(function () use ($data, $rows) {
            foreach ($rows as $row) {
                match ($data['type']) {
                    'program' => ProgramPemerintahan::updateOrCreate(
                        ['bidang_urusan_id' => $data['bidang_urusan_id'], 'kode' => $row['kode']],
                        ['nama' => $row['nama'], 'status' => $data['status']],
                    ),
                    'kegiatan' => KegiatanPemerintahan::updateOrCreate(
                        ['program_pemerintahan_id' => $data['program_pemerintahan_id'], 'kode' => $row['kode']],
                        ['nama' => $row['nama'], 'status' => $data['status']],
                    ),
                    'sub_kegiatan' => SubKegiatanPemerintahan::updateOrCreate(
                        ['kegiatan_pemerintahan_id' => $data['kegiatan_pemerintahan_id'], 'kode' => $row['kode']],
                        ['nama' => $row['nama'], 'status' => $data['status']],
                    ),
                };
            }
        });

        return $this->redirectToContext($request)->with('success', $rows->count().' baris referensi berhasil disimpan.');
    }

    public function update(UpdateProgramPemerintahanReferenceRequest $request, string $type, int $id): RedirectResponse
    {
        $data = $request->validated();
        $model = $this->findReference($type, $id);

        match ($type) {
            'program' => $model->update([
                'bidang_urusan_id' => $data['bidang_urusan_id'],
                'kode' => $data['kode'],
                'nama' => $data['nama'],
                'status' => $data['status'],
            ]),
            'kegiatan' => $model->update([
                'program_pemerintahan_id' => $data['program_pemerintahan_id'],
                'kode' => $data['kode'],
                'nama' => $data['nama'],
                'status' => $data['status'],
            ]),
            'sub_kegiatan' => $model->update([
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
    private function paginatedItems(array $filters, int $page, string $level, ?int $programId, ?int $kegiatanId): LengthAwarePaginator
    {
        $items = $this->allItems($filters, $level, $programId, $kegiatanId);
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
    private function allItems(array $filters, string $level = 'program', ?int $programId = null, ?int $kegiatanId = null): Collection
    {
        $items = match ($level) {
            'kegiatan' => $this->kegiatanRows($programId),
            'sub_kegiatan' => $this->subKegiatanRows($kegiatanId),
            default => $this->programRows($filters['bidang_urusan_id'] ?? null),
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

    private function programRows(mixed $bidangUrusanId = null): Collection
    {
        return ProgramPemerintahan::query()
            ->with('bidangUrusan.urusanPemerintahan:id,kode,nama')
            ->withCount('kegiatan')
            ->when($bidangUrusanId, fn ($query) => $query->where('bidang_urusan_id', $bidangUrusanId))
            ->orderBy('kode')
            ->get()
            ->map(fn (ProgramPemerintahan $program) => [
                'id' => $program->id,
                'type' => 'program',
                'level' => 'Program',
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

    private function kegiatanRows(?int $programId = null): Collection
    {
        return KegiatanPemerintahan::query()
            ->with('programPemerintahan.bidangUrusan.urusanPemerintahan:id,kode,nama')
            ->withCount('subKegiatan')
            ->when($programId, fn ($query) => $query->where('program_pemerintahan_id', $programId))
            ->orderBy('kode')
            ->get()
            ->map(fn (KegiatanPemerintahan $kegiatan) => [
                'id' => $kegiatan->id,
                'type' => 'kegiatan',
                'level' => 'Kegiatan',
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

    private function subKegiatanRows(?int $kegiatanId = null): Collection
    {
        return SubKegiatanPemerintahan::query()
            ->with('kegiatanPemerintahan.programPemerintahan.bidangUrusan.urusanPemerintahan:id,kode,nama')
            ->when($kegiatanId, fn ($query) => $query->where('kegiatan_pemerintahan_id', $kegiatanId))
            ->orderBy('kode')
            ->get()
            ->map(fn (SubKegiatanPemerintahan $subKegiatan) => [
                'id' => $subKegiatan->id,
                'type' => 'sub_kegiatan',
                'level' => 'Sub Kegiatan',
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

    private function programOptions(): array
    {
        return ProgramPemerintahan::query()
            ->with('bidangUrusan.urusanPemerintahan:id,kode,nama')
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

    private function kegiatanOptions(): array
    {
        return KegiatanPemerintahan::query()
            ->with('programPemerintahan:id,kode,nama')
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
        return [
            'id' => $kegiatan->id,
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
    private function summary(?ProgramPemerintahan $program, ?KegiatanPemerintahan $kegiatan): array
    {
        return [
            'program_count' => ProgramPemerintahan::query()->count(),
            'kegiatan_count' => $program
                ? KegiatanPemerintahan::query()->where('program_pemerintahan_id', $program->id)->count()
                : KegiatanPemerintahan::query()->count(),
            'sub_kegiatan_count' => $kegiatan
                ? SubKegiatanPemerintahan::query()->where('kegiatan_pemerintahan_id', $kegiatan->id)->count()
                : SubKegiatanPemerintahan::query()->count(),
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
