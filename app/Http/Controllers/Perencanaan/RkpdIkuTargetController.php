<?php

namespace App\Http\Controllers\Perencanaan;

use App\Http\Controllers\Controller;
use App\Http\Requests\Perencanaan\StoreRkpdIkuTargetRequest;
use App\Models\IndikatorSasaranDaerah;
use App\Models\IndikatorTujuanDaerah;
use App\Models\Rkpd;
use App\Models\RkpdIkuTarget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class RkpdIkuTargetController extends Controller
{
    public function store(StoreRkpdIkuTargetRequest $request, Rkpd $rkpd): RedirectResponse
    {
        $data = $request->validated();

        $this->abortIfIndicatorOutsideRpjmd($rkpd, $data['indikator_type'], (int) $data['indikator_id']);

        $target = RkpdIkuTarget::withTrashed()->firstOrNew([
            'rkpd_id' => $rkpd->id,
            'indikator_type' => $data['indikator_type'],
            'indikator_id' => (int) $data['indikator_id'],
        ]);

        $target->fill([
            'periode_tahun_id' => $rkpd->periode_tahun_id,
            'target_rkpd' => $data['target_rkpd'] ?? null,
            'updated_by' => $request->user()?->id,
        ]);

        if ($target->trashed()) {
            $target->restore();
        }

        $target->save();

        return back()->with('success', 'Target IKU RKPD berhasil disimpan.');
    }

    public function update(StoreRkpdIkuTargetRequest $request, Rkpd $rkpd, RkpdIkuTarget $target): RedirectResponse
    {
        abort_unless((int) $target->rkpd_id === (int) $rkpd->id, 404);

        $data = $request->validated();

        $this->abortIfIndicatorOutsideRpjmd($rkpd, $data['indikator_type'], (int) $data['indikator_id']);

        $target->update([
            'periode_tahun_id' => $rkpd->periode_tahun_id,
            'indikator_type' => $data['indikator_type'],
            'indikator_id' => (int) $data['indikator_id'],
            'target_rkpd' => $data['target_rkpd'] ?? null,
            'updated_by' => $request->user()?->id,
        ]);

        return back()->with('success', 'Target IKU RKPD berhasil diperbarui.');
    }

    public function destroy(Request $request, Rkpd $rkpd, RkpdIkuTarget $target): RedirectResponse
    {
        abort_unless($request->user()?->can('update', $rkpd), 403);
        abort_unless((int) $target->rkpd_id === (int) $rkpd->id, 404);

        $target->delete();

        return back()->with('success', 'Target IKU RKPD berhasil dihapus.');
    }

    private function abortIfIndicatorOutsideRpjmd(Rkpd $rkpd, string $type, int $indicatorId): void
    {
        if ($type === 'indikator_tujuan_daerah') {
            $exists = IndikatorTujuanDaerah::query()
                ->whereKey($indicatorId)
                ->whereHas('tujuan', fn (Builder $query) => $query->forRpjmd((int) $rkpd->rpjmd_id))
                ->exists();

            abort_unless($exists, 422, 'Indikator tujuan tidak terhubung dengan RPJMD RKPD ini.');

            return;
        }

        $exists = IndikatorSasaranDaerah::query()
            ->whereKey($indicatorId)
            ->whereHas('sasaran.tujuan', fn (Builder $query) => $query->forRpjmd((int) $rkpd->rpjmd_id))
            ->exists();

        abort_unless($exists, 422, 'Indikator sasaran tidak terhubung dengan RPJMD RKPD ini.');
    }
}
