<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Http\Requests\Master\StoreBidangUrusanRequest;
use App\Http\Requests\Master\StoreUrusanPemerintahanRequest;
use App\Http\Requests\Master\UpdateBidangUrusanRequest;
use App\Http\Requests\Master\UpdateUrusanPemerintahanRequest;
use App\Models\BidangUrusan;
use App\Models\UrusanPemerintahan;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class UrusanPemerintahanController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()->hasPermission('urusan.view'), 403);

        $filters = $request->only(['search', 'status']);

        $items = UrusanPemerintahan::query()
            ->with(['bidangUrusan' => fn ($query) => $query->withCount('program')->orderBy('kode')])
            ->withCount('opds')
            ->when($filters['search'] ?? null, function (Builder $query, string $search) {
                $query->where(function (Builder $query) use ($search) {
                    $query->where('kode', 'ilike', "%{$search}%")
                        ->orWhere('nama', 'ilike', "%{$search}%")
                        ->orWhere('deskripsi', 'ilike', "%{$search}%")
                        ->orWhereHas('bidangUrusan', function (Builder $query) use ($search) {
                            $query->where('kode', 'ilike', "%{$search}%")
                                ->orWhere('nama', 'ilike', "%{$search}%");
                        });
                });
            })
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->orderBy('kode')
            ->paginate(8)
            ->withQueryString()
            ->through(fn (UrusanPemerintahan $urusan) => $this->serialize($urusan));

        return Inertia::render('Master/UrusanPemerintahan/Index', [
            'items' => $items,
            'filters' => $filters,
            'options' => [
                'urusan' => $this->urusanOptions(),
            ],
            'can' => [
                'manage' => $request->user()->hasPermission('urusan.manage'),
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        abort_unless($request->user()->hasPermission('urusan.manage'), 403);

        return Inertia::render('Master/UrusanPemerintahan/Form', [
            'mode' => 'create',
            'item' => null,
        ]);
    }

    public function store(StoreUrusanPemerintahanRequest $request): RedirectResponse
    {
        UrusanPemerintahan::create($request->validated());

        return redirect()->route('master.urusan-pemerintahan.index')->with('success', 'Urusan pemerintahan berhasil ditambahkan.');
    }

    public function storeBidang(StoreBidangUrusanRequest $request): RedirectResponse
    {
        BidangUrusan::create($request->validated());

        return back()->with('success', 'Bidang urusan berhasil ditambahkan.');
    }

    public function edit(Request $request, UrusanPemerintahan $urusanPemerintahan): Response
    {
        abort_unless($request->user()->hasPermission('urusan.manage'), 403);

        return Inertia::render('Master/UrusanPemerintahan/Form', [
            'mode' => 'edit',
            'item' => $this->serialize($urusanPemerintahan),
        ]);
    }

    public function update(UpdateUrusanPemerintahanRequest $request, UrusanPemerintahan $urusanPemerintahan): RedirectResponse
    {
        $urusanPemerintahan->update($request->validated());

        return redirect()->route('master.urusan-pemerintahan.index')->with('success', 'Urusan pemerintahan berhasil diperbarui.');
    }

    public function updateBidang(UpdateBidangUrusanRequest $request, BidangUrusan $bidangUrusan): RedirectResponse
    {
        $bidangUrusan->update($request->validated());

        return back()->with('success', 'Bidang urusan berhasil diperbarui.');
    }

    public function destroy(Request $request, UrusanPemerintahan $urusanPemerintahan): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('urusan.manage'), 403);

        $urusanPemerintahan->delete();

        return redirect()->route('master.urusan-pemerintahan.index')->with('success', 'Urusan pemerintahan berhasil dihapus.');
    }

    public function destroyBidang(Request $request, BidangUrusan $bidangUrusan): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('urusan.manage'), 403);

        if ($bidangUrusan->program()->exists()) {
            return back()->with('error', 'Bidang urusan masih memiliki program. Hapus atau nonaktifkan programnya terlebih dahulu.');
        }

        $bidangUrusan->delete();

        return back()->with('success', 'Bidang urusan berhasil dihapus.');
    }

    public function export(Request $request): StreamedResponse
    {
        abort_unless($request->user()->hasPermission('urusan.view'), 403);
        abort(404, 'Export urusan pemerintahan sementara dinonaktifkan.');
    }

    private function serialize(UrusanPemerintahan $urusan): array
    {
        return [
            'id' => $urusan->id,
            'kode' => $urusan->kode,
            'nama' => $urusan->nama,
            'deskripsi' => $urusan->deskripsi,
            'status' => $urusan->status,
            'opds_count' => $urusan->opds_count ?? null,
            'bidang_count' => $urusan->bidangUrusan->count(),
            'program_count' => $urusan->bidangUrusan->sum('program_count'),
            'bidang_urusan' => $urusan->bidangUrusan
                ->map(fn (BidangUrusan $bidang) => [
                    'id' => $bidang->id,
                    'urusan_pemerintahan_id' => $bidang->urusan_pemerintahan_id,
                    'kode' => $bidang->kode,
                    'nama' => $bidang->nama,
                    'status' => $bidang->status,
                    'program_count' => $bidang->program_count ?? 0,
                ])
                ->values()
                ->all(),
        ];
    }

    private function urusanOptions(): array
    {
        return UrusanPemerintahan::query()
            ->where('status', 'active')
            ->orderBy('kode')
            ->get(['id', 'kode', 'nama'])
            ->map(fn (UrusanPemerintahan $urusan) => [
                'id' => $urusan->id,
                'label' => trim($urusan->kode.' - '.$urusan->nama),
            ])
            ->all();
    }
}
