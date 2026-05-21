<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Http\Requests\Master\StoreUrusanPemerintahanRequest;
use App\Http\Requests\Master\UpdateUrusanPemerintahanRequest;
use App\Models\UrusanPemerintahan;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class UrusanPemerintahanController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()->hasPermission('urusan.view'), 403);

        $filters = $request->only(['search', 'status']);

        $items = UrusanPemerintahan::query()
            ->withCount('opds')
            ->when($filters['search'] ?? null, function (Builder $query, string $search) {
                $query->where(function (Builder $query) use ($search) {
                    $query->where('kode', 'ilike', "%{$search}%")
                        ->orWhere('nama', 'ilike', "%{$search}%")
                        ->orWhere('deskripsi', 'ilike', "%{$search}%");
                });
            })
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->orderBy('kode')
            ->paginate(10)
            ->withQueryString()
            ->through(fn (UrusanPemerintahan $urusan) => $this->serialize($urusan));

        return Inertia::render('Master/UrusanPemerintahan/Index', [
            'items' => $items,
            'filters' => $filters,
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

    public function destroy(Request $request, UrusanPemerintahan $urusanPemerintahan): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('urusan.manage'), 403);

        $urusanPemerintahan->delete();

        return redirect()->route('master.urusan-pemerintahan.index')->with('success', 'Urusan pemerintahan berhasil dihapus.');
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
        ];
    }
}
