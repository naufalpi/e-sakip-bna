<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Http\Requests\Master\StoreStrategiDaerahRequest;
use App\Http\Requests\Master\UpdateStrategiDaerahRequest;
use App\Models\StrategiDaerah;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class StrategiDaerahController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()->hasPermission('strategi.view'), 403);

        $filters = $request->only(['search', 'status']);
        $items = StrategiDaerah::query()
            ->withCount('programs')
            ->when($filters['search'] ?? null, function (Builder $query, string $search) {
                $query->where(function (Builder $query) use ($search) {
                    $query->where('kode', 'ilike', "%{$search}%")
                        ->orWhere('strategi', 'ilike', "%{$search}%");
                });
            })
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->orderBy('strategi')
            ->paginate(10)
            ->withQueryString()
            ->through(fn (StrategiDaerah $strategi) => $this->serialize($strategi));

        return Inertia::render('Master/StrategiDaerah/Index', [
            'items' => $items,
            'filters' => $filters,
            'can' => [
                'manage' => $request->user()->hasPermission('strategi.manage'),
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        abort_unless($request->user()->hasPermission('strategi.manage'), 403);

        return Inertia::render('Master/StrategiDaerah/Form', [
            'mode' => 'create',
            'item' => null,
        ]);
    }

    public function store(StoreStrategiDaerahRequest $request): RedirectResponse
    {
        StrategiDaerah::create($request->validated());

        return redirect()->route('master.strategi-daerah.index')->with('success', 'Strategi daerah berhasil ditambahkan.');
    }

    public function edit(Request $request, StrategiDaerah $strategiDaerah): Response
    {
        abort_unless($request->user()->hasPermission('strategi.manage'), 403);

        return Inertia::render('Master/StrategiDaerah/Form', [
            'mode' => 'edit',
            'item' => $this->serialize($strategiDaerah),
        ]);
    }

    public function update(UpdateStrategiDaerahRequest $request, StrategiDaerah $strategiDaerah): RedirectResponse
    {
        $strategiDaerah->update($request->validated());

        return redirect()->route('master.strategi-daerah.index')->with('success', 'Strategi daerah berhasil diperbarui.');
    }

    public function destroy(Request $request, StrategiDaerah $strategiDaerah): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('strategi.manage'), 403);

        if ($strategiDaerah->programs()->exists()) {
            throw ValidationException::withMessages([
                'strategi' => 'Strategi masih digunakan oleh Program RPJMD. Nonaktifkan strategi jika tidak lagi digunakan.',
            ]);
        }

        $strategiDaerah->delete();

        return redirect()->route('master.strategi-daerah.index')->with('success', 'Strategi daerah berhasil dihapus.');
    }

    private function serialize(StrategiDaerah $strategi): array
    {
        return [
            'id' => $strategi->id,
            'kode' => $strategi->kode,
            'strategi' => $strategi->strategi,
            'status' => $strategi->status,
            'programs_count' => $strategi->programs_count ?? null,
        ];
    }
}
