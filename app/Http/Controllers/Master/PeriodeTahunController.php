<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Http\Requests\Master\StorePeriodeTahunRequest;
use App\Http\Requests\Master\UpdatePeriodeTahunRequest;
use App\Models\PeriodeTahun;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PeriodeTahunController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()->hasPermission('periode.view'), 403);

        $filters = $request->only(['search', 'status']);

        $items = PeriodeTahun::query()
            ->when($filters['search'] ?? null, function (Builder $query, string $search) {
                $query->where(function (Builder $query) use ($search) {
                    $query->where('nama', 'ilike', "%{$search}%")
                        ->orWhere('tahun', (int) $search);
                });
            })
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->orderByDesc('tahun')
            ->paginate(10)
            ->withQueryString()
            ->through(fn (PeriodeTahun $periode) => $this->serialize($periode));

        return Inertia::render('Master/PeriodeTahun/Index', [
            'items' => $items,
            'filters' => $filters,
            'can' => [
                'manage' => $request->user()->hasPermission('periode.manage'),
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        abort_unless($request->user()->hasPermission('periode.manage'), 403);

        return Inertia::render('Master/PeriodeTahun/Form', [
            'mode' => 'create',
            'item' => null,
        ]);
    }

    public function store(StorePeriodeTahunRequest $request): RedirectResponse
    {
        PeriodeTahun::create($request->validated());

        return redirect()->route('master.periode-tahun.index')->with('success', 'Periode tahun berhasil ditambahkan.');
    }

    public function edit(Request $request, PeriodeTahun $periodeTahun): Response
    {
        abort_unless($request->user()->hasPermission('periode.manage'), 403);

        return Inertia::render('Master/PeriodeTahun/Form', [
            'mode' => 'edit',
            'item' => $this->serialize($periodeTahun),
        ]);
    }

    public function update(UpdatePeriodeTahunRequest $request, PeriodeTahun $periodeTahun): RedirectResponse
    {
        $periodeTahun->update($request->validated());

        return redirect()->route('master.periode-tahun.index')->with('success', 'Periode tahun berhasil diperbarui.');
    }

    public function destroy(Request $request, PeriodeTahun $periodeTahun): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('periode.manage'), 403);

        $periodeTahun->delete();

        return redirect()->route('master.periode-tahun.index')->with('success', 'Periode tahun berhasil dihapus.');
    }

    private function serialize(PeriodeTahun $periode): array
    {
        return [
            'id' => $periode->id,
            'tahun' => $periode->tahun,
            'nama' => $periode->nama,
            'tanggal_mulai' => $periode->tanggal_mulai?->toDateString(),
            'tanggal_selesai' => $periode->tanggal_selesai?->toDateString(),
            'status' => $periode->status,
        ];
    }
}
