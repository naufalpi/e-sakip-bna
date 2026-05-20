<?php

namespace App\Http\Controllers\Kinerja;

use App\Http\Controllers\Controller;
use App\Http\Requests\Kinerja\StoreRealisasiProgramRequest;
use App\Models\IndikatorOpdProgram;
use App\Models\OpdProgram;
use App\Models\PerjanjianKinerjaItem;
use App\Models\RealisasiKinerja;
use App\Models\RealisasiProgram;
use App\Models\RencanaAksiItem;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;

class RealisasiProgramController extends Controller
{
    public function store(StoreRealisasiProgramRequest $request, RealisasiKinerja $realisasiKinerja): RedirectResponse
    {
        $data = $request->validated();
        $this->assertRelationsBelongToOpd($data, (int) $realisasiKinerja->opd_id);

        $realisasiKinerja->programs()->create($data);

        return back()->with('success', 'Realisasi indikator berhasil ditambahkan.');
    }

    public function destroy(RealisasiKinerja $realisasiKinerja, RealisasiProgram $program): RedirectResponse
    {
        $this->authorize('update', $realisasiKinerja);
        abort_unless((int) $program->realisasi_kinerja_id === (int) $realisasiKinerja->id, 404);

        $program->delete();

        return back()->with('success', 'Realisasi indikator berhasil dihapus.');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function assertRelationsBelongToOpd(array $data, int $opdId): void
    {
        if (($data['perjanjian_kinerja_item_id'] ?? null)
            && ! PerjanjianKinerjaItem::query()
                ->whereKey($data['perjanjian_kinerja_item_id'])
                ->whereHas('perjanjianKinerja', fn (Builder $query) => $query->where('opd_id', $opdId))
                ->exists()) {
            throw ValidationException::withMessages(['perjanjian_kinerja_item_id' => 'Item Perjanjian Kinerja tidak sesuai OPD Realisasi.']);
        }

        if (($data['rencana_aksi_item_id'] ?? null)
            && ! RencanaAksiItem::query()
                ->whereKey($data['rencana_aksi_item_id'])
                ->whereHas('rencanaAksi', fn (Builder $query) => $query->where('opd_id', $opdId))
                ->exists()) {
            throw ValidationException::withMessages(['rencana_aksi_item_id' => 'Item Rencana Aksi tidak sesuai OPD Realisasi.']);
        }

        if (($data['opd_program_id'] ?? null)
            && ! OpdProgram::query()
                ->whereKey($data['opd_program_id'])
                ->whereHas('renstra', fn (Builder $query) => $query->where('opd_id', $opdId))
                ->exists()) {
            throw ValidationException::withMessages(['opd_program_id' => 'Program OPD tidak sesuai OPD Realisasi.']);
        }

        if (($data['indikator_opd_program_id'] ?? null)
            && ! IndikatorOpdProgram::query()
                ->whereKey($data['indikator_opd_program_id'])
                ->whereHas('program.renstra', fn (Builder $query) => $query->where('opd_id', $opdId))
                ->exists()) {
            throw ValidationException::withMessages(['indikator_opd_program_id' => 'Indikator program OPD tidak sesuai OPD Realisasi.']);
        }
    }
}
