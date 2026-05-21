<?php

namespace App\Http\Controllers\Perencanaan;

use App\Http\Controllers\Controller;
use App\Http\Requests\Perencanaan\StoreTargetTriwulanIndikatorRequest;
use App\Models\IndikatorOpdProgram;
use App\Models\IndikatorProgramRpjmd;
use App\Models\IndikatorSasaranDaerah;
use App\Models\IndikatorSasaranOpd;
use App\Models\IndikatorSubKegiatan;
use App\Models\IndikatorTujuanDaerah;
use App\Models\IndikatorTujuanOpd;
use App\Models\TargetTriwulanIndikator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TargetTriwulanIndikatorController extends Controller
{
    public function store(StoreTargetTriwulanIndikatorRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $related = $this->relatedModel($data['related_table'], (int) $data['related_id']);
        $this->authorize('update', $this->ownerModel($related, $data['related_table']));

        TargetTriwulanIndikator::updateOrCreate([
            'related_table' => $data['related_table'],
            'related_id' => $related->getKey(),
            'periode_tahun_id' => $data['periode_tahun_id'],
            'triwulan' => $data['triwulan'],
        ], [
            'target_text' => $data['target_text'] ?? null,
            'target_angka' => $data['target_angka'] ?? null,
            'target_anggaran' => $data['target_anggaran'] ?? null,
        ]);

        return back()->with('success', 'Target triwulan indikator berhasil disimpan.');
    }

    public function destroy(Request $request, TargetTriwulanIndikator $target): RedirectResponse
    {
        $related = $this->relatedModel($target->related_table, (int) $target->related_id);
        $this->authorize('update', $this->ownerModel($related, $target->related_table));

        $target->delete();

        return back()->with('success', 'Target triwulan indikator berhasil dihapus.');
    }

    private function relatedModel(string $table, int $id): Model
    {
        $modelClass = match ($table) {
            'indikator_tujuan_daerah' => IndikatorTujuanDaerah::class,
            'indikator_sasaran_daerah' => IndikatorSasaranDaerah::class,
            'indikator_program_rpjmd' => IndikatorProgramRpjmd::class,
            'indikator_tujuan_opd' => IndikatorTujuanOpd::class,
            'indikator_sasaran_opd' => IndikatorSasaranOpd::class,
            'indikator_opd_program' => IndikatorOpdProgram::class,
            'indikator_sub_kegiatan' => IndikatorSubKegiatan::class,
            default => abort(404),
        };

        return $modelClass::query()->findOrFail($id);
    }

    private function ownerModel(Model $related, string $table): Model
    {
        return match ($table) {
            'indikator_tujuan_daerah' => $related->tujuan->misi->rpjmd,
            'indikator_sasaran_daerah' => $related->sasaran->tujuan->misi->rpjmd,
            'indikator_program_rpjmd' => $related->program->strategi?->sasaran?->tujuan?->misi?->rpjmd
                ?? $related->program->sasaran->tujuan->misi->rpjmd,
            'indikator_tujuan_opd' => $related->tujuan->renstra,
            'indikator_sasaran_opd' => $related->sasaran->tujuan->renstra,
            'indikator_opd_program' => $related->program->renstra,
            'indikator_sub_kegiatan' => $related->subKegiatan->kegiatan->program->renstra,
            default => abort(404),
        };
    }
}
