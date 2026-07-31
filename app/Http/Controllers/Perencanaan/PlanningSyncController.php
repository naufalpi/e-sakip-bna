<?php

namespace App\Http\Controllers\Perencanaan;

use App\Http\Controllers\Controller;
use App\Models\PlanningSyncBatch;
use App\Models\RenjaOpd;
use App\Models\Rkpd;
use App\Services\Perencanaan\PlanningSyncService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PlanningSyncController extends Controller
{
    public function previewRenjaToRkpd(Request $request, Rkpd $rkpd, PlanningSyncService $service): RedirectResponse
    {
        $this->authorize('update', $rkpd);

        $batch = $service->previewRenjaToRkpd($rkpd, $request->user(), $request->only(['opd_id', 'renja_statuses']));

        return redirect()
            ->route('rkpd.show', [
                'rkpd' => $rkpd->id,
                'sync_batch' => $batch->id,
                'sync_panel' => 1,
            ])
            ->with('success', 'Preview sinkronisasi RENJA ke RKPD berhasil dibuat.');
    }

    public function applyRenjaToRkpd(Request $request, Rkpd $rkpd, PlanningSyncBatch $batch, PlanningSyncService $service): RedirectResponse
    {
        $this->authorize('update', $rkpd);
        abort_unless($batch->source_module === 'renja_opd' && $batch->target_module === 'rkpd' && (int) $batch->target_id === (int) $rkpd->id, 404);

        $validated = $request->validate([
            'selected_rows' => ['required', 'array', 'min:1'],
            'selected_rows.*' => ['integer'],
        ]);

        $result = $service->apply($batch, $request->user(), array_map('intval', $validated['selected_rows']));

        return redirect()
            ->route('rkpd.show', $rkpd)
            ->with('success', "Sinkronisasi diterapkan: {$result['applied']} baris, {$result['skipped']} dilewati.");
    }

    public function previewRkpdToRenja(Request $request, RenjaOpd $renjaOpd, PlanningSyncService $service): RedirectResponse
    {
        $this->authorize('update', $renjaOpd);

        $batch = $service->previewRkpdToRenja($renjaOpd, $request->user());

        return redirect()
            ->route('renja-opd.show', [
                'renja_opd' => $renjaOpd->id,
                'sync_batch' => $batch->id,
                'sync_panel' => 1,
            ])
            ->with('success', 'Preview sinkronisasi RKPD ke Renja berhasil dibuat.');
    }

    public function applyRkpdToRenja(Request $request, RenjaOpd $renjaOpd, PlanningSyncBatch $batch, PlanningSyncService $service): RedirectResponse
    {
        $this->authorize('update', $renjaOpd);
        abort_unless($batch->source_module === 'rkpd' && $batch->target_module === 'renja_opd' && (int) $batch->target_id === (int) $renjaOpd->id, 404);

        $validated = $request->validate([
            'selected_rows' => ['required', 'array', 'min:1'],
            'selected_rows.*' => ['integer'],
        ]);

        $result = $service->apply($batch, $request->user(), array_map('intval', $validated['selected_rows']));

        return redirect()
            ->route('renja-opd.show', $renjaOpd)
            ->with('success', "Sinkronisasi diterapkan: {$result['applied']} baris, {$result['skipped']} dilewati.");
    }
}
