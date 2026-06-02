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
use App\Services\Kinerja\CapaianKinerjaService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;

class RealisasiProgramController extends Controller
{
    public function store(StoreRealisasiProgramRequest $request, RealisasiKinerja $realisasiKinerja, CapaianKinerjaService $capaianService): RedirectResponse
    {
        $this->authorize('update', $realisasiKinerja);

        $data = $request->validated();
        $this->assertRelationsBelongToOpd($data, (int) $realisasiKinerja->opd_id);
        $data = $this->withCalculatedMetrics($data, $capaianService);

        $realisasiKinerja->programs()->create($data);
        $capaianService->syncRealisasiKinerjaSummary($realisasiKinerja);

        return back()->with('success', 'Realisasi indikator berhasil ditambahkan.');
    }

    public function update(StoreRealisasiProgramRequest $request, RealisasiKinerja $realisasiKinerja, RealisasiProgram $program, CapaianKinerjaService $capaianService): RedirectResponse
    {
        $this->authorize('update', $realisasiKinerja);
        abort_unless((int) $program->realisasi_kinerja_id === (int) $realisasiKinerja->id, 404);

        $data = $request->validated();
        $this->assertRelationsBelongToOpd($data, (int) $realisasiKinerja->opd_id);
        $data = $this->withCalculatedMetrics($data, $capaianService);

        $program->update($data);
        $capaianService->syncRealisasiKinerjaSummary($realisasiKinerja);

        return back()->with('success', 'Realisasi indikator berhasil diperbarui.');
    }

    public function destroy(RealisasiKinerja $realisasiKinerja, RealisasiProgram $program, CapaianKinerjaService $capaianService): RedirectResponse
    {
        $this->authorize('update', $realisasiKinerja);
        abort_unless((int) $program->realisasi_kinerja_id === (int) $realisasiKinerja->id, 404);

        $program->delete();
        $capaianService->syncRealisasiKinerjaSummary($realisasiKinerja);

        return back()->with('success', 'Realisasi indikator berhasil dihapus.');
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function withCalculatedMetrics(array $data, CapaianKinerjaService $capaianService): array
    {
        $tipeIndikator = $data['tipe_indikator'] ?? $this->tipeIndikatorFromReference($data['indikator_opd_program_id'] ?? null);
        $capaianPersen = $capaianService->calculateCapaian($data['target'] ?? null, $data['realisasi'] ?? null, $tipeIndikator)
            ?? ($data['capaian_persen'] ?? null);
        $serapanAnggaran = $capaianService->calculateSerapanAnggaran($data['anggaran'] ?? null, $data['realisasi_anggaran'] ?? null)
            ?? ($data['serapan_anggaran_persen'] ?? null);

        return [
            ...$data,
            'tipe_indikator' => $tipeIndikator,
            'capaian_persen' => $capaianPersen,
            'status_capaian' => $capaianService->determineStatusCapaian($capaianPersen) ?? ($data['status_capaian'] ?? null),
            'serapan_anggaran_persen' => $serapanAnggaran,
            'status_efisiensi' => $capaianService->determineEfisiensi($capaianPersen, $serapanAnggaran) ?? ($data['status_efisiensi'] ?? null),
        ];
    }

    private function tipeIndikatorFromReference(mixed $indikatorOpdProgramId): string
    {
        if (blank($indikatorOpdProgramId)) {
            return 'positif';
        }

        return (string) (IndikatorOpdProgram::query()->whereKey($indikatorOpdProgramId)->value('tipe_indikator') ?: 'positif');
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
