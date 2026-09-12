<?php

namespace App\Http\Controllers\Perencanaan;

use App\Http\Controllers\Controller;
use App\Http\Requests\Perencanaan\UpdateRenjaOpdAnnualTargetsRequest;
use App\Models\RenjaOpd;
use App\Models\RenjaOpdAnnualTarget;
use App\Services\Perencanaan\RenjaAnnualTargetService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RenjaOpdAnnualTargetController extends Controller
{
    public function __construct(private readonly RenjaAnnualTargetService $service) {}

    public function index(Request $request, RenjaOpd $renjaOpd): Response
    {
        abort_unless($this->service->available(), 404);
        $this->authorize('view', $renjaOpd);

        $this->service->bootstrap(
            $renjaOpd,
            $renjaOpd->isOfficialVersion() ? 'legacy_backfill' : 'renstra_initial',
        );

        $filters = $request->only(['search', 'level', 'status']);
        $allowedLevels = ['tujuan_opd', 'sasaran_opd', 'program_opd', 'kegiatan_opd'];
        $allowedStatuses = ['complete', 'following', 'adjusted', 'missing'];
        $filters['level'] = in_array($filters['level'] ?? null, $allowedLevels, true) ? $filters['level'] : null;
        $filters['status'] = in_array($filters['status'] ?? null, $allowedStatuses, true) ? $filters['status'] : null;

        $query = $renjaOpd->annualTargets()
            ->reorder()
            ->when($filters['level'], fn (Builder $query, string $level) => $query->where('level', $level))
            ->when($filters['search'] ?? null, function (Builder $query, string $search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query->where('kode_snapshot', 'ilike', "%{$search}%")
                        ->orWhere('uraian_snapshot', 'ilike', "%{$search}%")
                        ->orWhere('indikator_snapshot', 'ilike', "%{$search}%");
                });
            })
            ->when($filters['status'] === 'adjusted', fn (Builder $query) => $query->where('is_adjusted', true))
            ->when($filters['status'] === 'following', fn (Builder $query) => $query
                ->where('is_adjusted', false)
                ->where(fn (Builder $query) => $query->whereNotNull('target_renja')->orWhereNotNull('target_renja_text')))
            ->when($filters['status'] === 'complete', fn (Builder $query) => $query
                ->where(fn (Builder $query) => $query->whereNotNull('target_renja')->orWhereNotNull('target_renja_text')))
            ->when($filters['status'] === 'missing', fn (Builder $query) => $query
                ->whereNull('target_renja')->whereNull('target_renja_text'));

        $items = $query->orderBy('urutan')->orderBy('id')->paginate(50)->withQueryString()
            ->through(fn (RenjaOpdAnnualTarget $target) => [
                'id' => $target->id,
                'level' => $target->level,
                'kode' => $target->kode_snapshot,
                'uraian' => $target->uraian_snapshot,
                'indikator' => $target->indikator_snapshot,
                'satuan' => $target->satuan_snapshot,
                'formula' => $target->formula_snapshot,
                'hierarchy' => $target->hierarchy_snapshot ?? [],
                'target_renstra' => $this->service->targetDisplay($target->target_renstra_text, $target->target_renstra),
                'target_renja' => $this->service->targetDisplay($target->target_renja_text, $target->target_renja),
                'is_adjusted' => $target->is_adjusted,
                'alasan_penyesuaian' => $target->alasan_penyesuaian,
                'bootstrap_source' => $target->bootstrap_source,
            ]);

        $levelCounts = $renjaOpd->annualTargets()
            ->reorder()
            ->selectRaw('level, COUNT(*) AS aggregate')
            ->groupBy('level')
            ->pluck('aggregate', 'level');

        $renjaOpd->load(['opd:id,kode,nama,singkatan', 'periodeTahun:id,tahun,nama']);

        return Inertia::render('RenjaOpd/AnnualTargets', [
            'renja' => [
                'id' => $renjaOpd->id,
                'judul' => $renjaOpd->judul,
                'tahun' => $renjaOpd->tahun,
                'status' => $renjaOpd->status,
                'version_label' => $renjaOpd->versionLabel(),
                'opd' => $renjaOpd->opd ? [
                    'nama' => $renjaOpd->opd->nama,
                    'singkatan' => $renjaOpd->opd->singkatan,
                ] : null,
            ],
            'items' => $items,
            'summary' => $this->service->summary($renjaOpd),
            'levelCounts' => collect($allowedLevels)->mapWithKeys(fn (string $level) => [$level => (int) $levelCounts->get($level, 0)]),
            'filters' => $filters,
            'can' => ['manage' => $request->user()->can('update', $renjaOpd)],
        ]);
    }

    public function update(UpdateRenjaOpdAnnualTargetsRequest $request, RenjaOpd $renjaOpd): RedirectResponse
    {
        abort_unless($this->service->available(), 404);
        $this->service->updateTargets($renjaOpd, $request->validated()['targets']);

        return back()->with('success', 'Target kinerja tahunan RENJA berhasil disimpan.');
    }
}
