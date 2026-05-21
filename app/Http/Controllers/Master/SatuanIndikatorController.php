<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Http\Requests\Master\StoreSatuanIndikatorRequest;
use App\Http\Requests\Master\UpdateSatuanIndikatorRequest;
use App\Models\SatuanIndikator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SatuanIndikatorController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()->hasPermission('satuan.view'), 403);

        $filters = $request->only(['search', 'status', 'jenis']);

        $items = SatuanIndikator::query()
            ->when($filters['search'] ?? null, function (Builder $query, string $search) {
                $query->where(function (Builder $query) use ($search) {
                    $query->where('nama', 'ilike', "%{$search}%")
                        ->orWhere('simbol', 'ilike', "%{$search}%")
                        ->orWhere('deskripsi', 'ilike', "%{$search}%");
                });
            })
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($filters['jenis'] ?? null, fn (Builder $query, string $jenis) => $query->where('jenis', $jenis))
            ->orderBy('nama')
            ->paginate(10)
            ->withQueryString()
            ->through(fn (SatuanIndikator $satuan) => $this->serialize($satuan));

        return Inertia::render('Master/SatuanIndikator/Index', [
            'items' => $items,
            'filters' => $filters,
            'jenisOptions' => $this->jenisOptions(),
            'can' => [
                'manage' => $request->user()->hasPermission('satuan.manage'),
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        abort_unless($request->user()->hasPermission('satuan.manage'), 403);

        return Inertia::render('Master/SatuanIndikator/Form', [
            'mode' => 'create',
            'item' => null,
            'jenisOptions' => $this->jenisOptions(),
        ]);
    }

    public function store(StoreSatuanIndikatorRequest $request): RedirectResponse
    {
        SatuanIndikator::create($request->validated());

        return redirect()->route('master.satuan-indikator.index')->with('success', 'Satuan indikator berhasil ditambahkan.');
    }

    public function edit(Request $request, SatuanIndikator $satuanIndikator): Response
    {
        abort_unless($request->user()->hasPermission('satuan.manage'), 403);

        return Inertia::render('Master/SatuanIndikator/Form', [
            'mode' => 'edit',
            'item' => $this->serialize($satuanIndikator),
            'jenisOptions' => $this->jenisOptions(),
        ]);
    }

    public function update(UpdateSatuanIndikatorRequest $request, SatuanIndikator $satuanIndikator): RedirectResponse
    {
        $satuanIndikator->update($request->validated());

        return redirect()->route('master.satuan-indikator.index')->with('success', 'Satuan indikator berhasil diperbarui.');
    }

    public function destroy(Request $request, SatuanIndikator $satuanIndikator): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('satuan.manage'), 403);

        $satuanIndikator->delete();

        return redirect()->route('master.satuan-indikator.index')->with('success', 'Satuan indikator berhasil dihapus.');
    }

    private function serialize(SatuanIndikator $satuan): array
    {
        return [
            'id' => $satuan->id,
            'nama' => $satuan->nama,
            'simbol' => $satuan->simbol,
            'jenis' => $satuan->jenis,
            'deskripsi' => $satuan->deskripsi,
            'status' => $satuan->status,
        ];
    }

    private function jenisOptions(): array
    {
        return [
            ['value' => 'persentase', 'label' => 'Persentase'],
            ['value' => 'angka', 'label' => 'Angka'],
            ['value' => 'jumlah', 'label' => 'Jumlah'],
            ['value' => 'mata_uang', 'label' => 'Mata Uang'],
            ['value' => 'rasio', 'label' => 'Rasio'],
            ['value' => 'indeks', 'label' => 'Indeks'],
            ['value' => 'lainnya', 'label' => 'Lainnya'],
        ];
    }
}
