<?php

namespace App\Http\Controllers\Rpjmd;

use App\Http\Controllers\Controller;
use App\Http\Requests\Rpjmd\StoreRpjmdNodeRequest;
use App\Models\IndikatorProgramRpjmd;
use App\Models\IndikatorSasaranDaerah;
use App\Models\IndikatorTujuanDaerah;
use App\Models\ProgramRpjmd;
use App\Models\ProgramRpjmdOpdPenanggungJawab;
use App\Models\Rpjmd;
use App\Models\RpjmdMisi;
use App\Models\RpjmdVisi;
use App\Models\SasaranDaerah;
use App\Models\StrategiDaerah;
use App\Models\TargetIndikatorProgramRpjmd;
use App\Models\TargetIndikatorSasaranDaerah;
use App\Models\TargetIndikatorTujuanDaerah;
use App\Models\TujuanDaerah;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RpjmdNodeController extends Controller
{
    public function store(StoreRpjmdNodeRequest $request, Rpjmd $rpjmd): RedirectResponse
    {
        $this->authorize('update', $rpjmd);

        $data = $request->validated();

        DB::transaction(fn () => $this->storeNode($rpjmd, $data));

        return back()->with('success', 'Data cascading RPJMD berhasil disimpan.');
    }

    public function update(StoreRpjmdNodeRequest $request, Rpjmd $rpjmd, string $type, int $id): RedirectResponse
    {
        $this->authorize('update', $rpjmd);

        $data = $request->validated();

        if (($data['type'] ?? null) !== $type) {
            throw ValidationException::withMessages(['type' => 'Jenis data tidak sesuai dengan node yang diedit.']);
        }

        DB::transaction(fn () => $this->updateNode($rpjmd, $type, $id, $data));

        return back()->with('success', 'Data cascading RPJMD berhasil diperbarui.');
    }

    public function destroy(Rpjmd $rpjmd, string $type, int $id): RedirectResponse
    {
        $this->authorize('update', $rpjmd);

        $model = $this->findNode($rpjmd, $type, $id);
        $model->delete();

        return back()->with('success', 'Data cascading RPJMD berhasil dihapus.');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function storeNode(Rpjmd $rpjmd, array $data): void
    {
        match ($data['type']) {
            'visi' => RpjmdVisi::create([
                'rpjmd_id' => $rpjmd->id,
                'visi' => $this->requiredText($data, 'uraian', 'Visi wajib diisi.'),
                'urutan' => $data['urutan'] ?? 1,
            ]),
            'misi' => RpjmdMisi::create([
                'rpjmd_id' => $rpjmd->id,
                'rpjmd_visi_id' => $this->visi($rpjmd, $data['parent_id'] ?? null)->id,
                'kode' => $data['kode'] ?? null,
                'misi' => $this->requiredText($data, 'uraian', 'Misi wajib diisi.'),
                'urutan' => $data['urutan'] ?? 1,
            ]),
            'tujuan' => TujuanDaerah::create([
                'rpjmd_misi_id' => $this->misi($rpjmd, $data['parent_id'] ?? null)->id,
                'kode' => $data['kode'] ?? null,
                'tujuan' => $this->requiredText($data, 'uraian', 'Tujuan daerah wajib diisi.'),
                'urutan' => $data['urutan'] ?? 1,
            ]),
            'indikator_tujuan' => IndikatorTujuanDaerah::create([
                'tujuan_daerah_id' => $this->tujuan($rpjmd, $data['parent_id'] ?? null)->id,
                'satuan_indikator_id' => $data['satuan_indikator_id'] ?? null,
                'kode' => $data['kode'] ?? null,
                'indikator' => $this->requiredText($data, 'indikator', 'Indikator tujuan wajib diisi.'),
                'tipe_indikator' => $data['tipe_indikator'] ?? 'positif',
                'formula' => $data['formula'] ?? null,
                'sumber_data' => $data['sumber_data'] ?? null,
                'urutan' => $data['urutan'] ?? 1,
            ]),
            'target_tujuan' => TargetIndikatorTujuanDaerah::updateOrCreate([
                'indikator_tujuan_daerah_id' => $this->indikatorTujuan($rpjmd, $data['parent_id'] ?? null)->id,
                'periode_tahun_id' => $this->requiredInt($data, 'periode_tahun_id', 'Periode target wajib dipilih.'),
            ], [
                'target' => $data['target'] ?? null,
                'target_text' => $data['target_text'] ?? null,
            ]),
            'sasaran' => SasaranDaerah::create([
                'tujuan_daerah_id' => $this->tujuan($rpjmd, $data['parent_id'] ?? null)->id,
                'kode' => $data['kode'] ?? null,
                'sasaran' => $this->requiredText($data, 'uraian', 'Sasaran daerah wajib diisi.'),
                'urutan' => $data['urutan'] ?? 1,
            ]),
            'indikator_sasaran' => IndikatorSasaranDaerah::create([
                'sasaran_daerah_id' => $this->sasaran($rpjmd, $data['parent_id'] ?? null)->id,
                'satuan_indikator_id' => $data['satuan_indikator_id'] ?? null,
                'kode' => $data['kode'] ?? null,
                'indikator' => $this->requiredText($data, 'indikator', 'Indikator sasaran wajib diisi.'),
                'tipe_indikator' => $data['tipe_indikator'] ?? 'positif',
                'formula' => $data['formula'] ?? null,
                'sumber_data' => $data['sumber_data'] ?? null,
                'urutan' => $data['urutan'] ?? 1,
            ]),
            'target_sasaran' => TargetIndikatorSasaranDaerah::updateOrCreate([
                'indikator_sasaran_daerah_id' => $this->indikatorSasaran($rpjmd, $data['parent_id'] ?? null)->id,
                'periode_tahun_id' => $this->requiredInt($data, 'periode_tahun_id', 'Periode target wajib dipilih.'),
            ], [
                'target' => $data['target'] ?? null,
                'target_text' => $data['target_text'] ?? null,
            ]),
            'strategi' => StrategiDaerah::create([
                'sasaran_daerah_id' => $this->sasaran($rpjmd, $data['parent_id'] ?? null)->id,
                'kode' => $data['kode'] ?? null,
                'strategi' => $this->requiredText($data, 'uraian', 'Strategi daerah wajib diisi.'),
                'arah_kebijakan' => $data['arah_kebijakan'] ?? null,
                'urutan' => $data['urutan'] ?? 1,
            ]),
            'program' => tap($this->strategi($rpjmd, $data['parent_id'] ?? null), function (StrategiDaerah $strategi) use ($data) {
                ProgramRpjmd::create([
                    'strategi_daerah_id' => $strategi->id,
                    'sasaran_daerah_id' => $strategi->sasaran_daerah_id,
                    'urusan_pemerintahan_id' => $data['urusan_pemerintahan_id'] ?? null,
                    'kode' => $data['kode'] ?? null,
                    'nama' => $this->requiredText($data, 'uraian', 'Nama program wajib diisi.'),
                    'pagu_indikatif' => $data['pagu_indikatif'] ?? null,
                    'status' => 'draft',
                    'urutan' => $data['urutan'] ?? 1,
                ]);
            }),
            'indikator_program' => IndikatorProgramRpjmd::create([
                'program_rpjmd_id' => $this->program($rpjmd, $data['parent_id'] ?? null)->id,
                'satuan_indikator_id' => $data['satuan_indikator_id'] ?? null,
                'kode' => $data['kode'] ?? null,
                'indikator' => $this->requiredText($data, 'indikator', 'Indikator program wajib diisi.'),
                'tipe_indikator' => $data['tipe_indikator'] ?? 'positif',
                'formula' => $data['formula'] ?? null,
                'sumber_data' => $data['sumber_data'] ?? null,
                'urutan' => $data['urutan'] ?? 1,
            ]),
            'target_program' => TargetIndikatorProgramRpjmd::updateOrCreate([
                'indikator_program_rpjmd_id' => $this->indikatorProgram($rpjmd, $data['parent_id'] ?? null)->id,
                'periode_tahun_id' => $this->requiredInt($data, 'periode_tahun_id', 'Periode target wajib dipilih.'),
            ], [
                'target' => $data['target'] ?? null,
                'target_text' => $data['target_text'] ?? null,
                'pagu' => $data['pagu'] ?? null,
            ]),
            'program_opd' => ProgramRpjmdOpdPenanggungJawab::updateOrCreate([
                'program_rpjmd_id' => $this->program($rpjmd, $data['parent_id'] ?? null)->id,
                'opd_id' => $this->requiredInt($data, 'opd_id', 'OPD penanggung jawab wajib dipilih.'),
                'peran' => $data['peran'] ?? 'penanggung_jawab',
            ], [
                'is_utama' => (bool) ($data['is_utama'] ?? true),
            ]),
        };
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function updateNode(Rpjmd $rpjmd, string $type, int $id, array $data): void
    {
        match ($type) {
            'visi' => $this->visi($rpjmd, $id)->update([
                'visi' => $this->requiredText($data, 'uraian', 'Visi wajib diisi.'),
                'urutan' => $data['urutan'] ?? 1,
            ]),
            'misi' => tap($this->misi($rpjmd, $id), function (RpjmdMisi $misi) use ($rpjmd, $data) {
                $misi->update([
                    'rpjmd_visi_id' => filled($data['parent_id'] ?? null) ? $this->visi($rpjmd, $data['parent_id'])->id : $misi->rpjmd_visi_id,
                    'kode' => $data['kode'] ?? null,
                    'misi' => $this->requiredText($data, 'uraian', 'Misi wajib diisi.'),
                    'urutan' => $data['urutan'] ?? 1,
                ]);
            }),
            'tujuan' => tap($this->tujuan($rpjmd, $id), function (TujuanDaerah $tujuan) use ($rpjmd, $data) {
                $tujuan->update([
                    'rpjmd_misi_id' => filled($data['parent_id'] ?? null) ? $this->misi($rpjmd, $data['parent_id'])->id : $tujuan->rpjmd_misi_id,
                    'kode' => $data['kode'] ?? null,
                    'tujuan' => $this->requiredText($data, 'uraian', 'Tujuan daerah wajib diisi.'),
                    'urutan' => $data['urutan'] ?? 1,
                ]);
            }),
            'indikator_tujuan' => tap($this->indikatorTujuan($rpjmd, $id), function (IndikatorTujuanDaerah $indikator) use ($rpjmd, $data) {
                $indikator->update([
                    'tujuan_daerah_id' => filled($data['parent_id'] ?? null) ? $this->tujuan($rpjmd, $data['parent_id'])->id : $indikator->tujuan_daerah_id,
                    'satuan_indikator_id' => $data['satuan_indikator_id'] ?? null,
                    'kode' => $data['kode'] ?? null,
                    'indikator' => $this->requiredText($data, 'indikator', 'Indikator tujuan wajib diisi.'),
                    'tipe_indikator' => $data['tipe_indikator'] ?? 'positif',
                    'formula' => $data['formula'] ?? null,
                    'sumber_data' => $data['sumber_data'] ?? null,
                    'urutan' => $data['urutan'] ?? 1,
                ]);
            }),
            'target_tujuan' => tap($this->findNode($rpjmd, $type, $id), function (TargetIndikatorTujuanDaerah $target) use ($rpjmd, $data) {
                $target->update([
                    'indikator_tujuan_daerah_id' => filled($data['parent_id'] ?? null) ? $this->indikatorTujuan($rpjmd, $data['parent_id'])->id : $target->indikator_tujuan_daerah_id,
                    'periode_tahun_id' => filled($data['periode_tahun_id'] ?? null) ? (int) $data['periode_tahun_id'] : $target->periode_tahun_id,
                    'target' => $data['target'] ?? null,
                    'target_text' => $data['target_text'] ?? null,
                ]);
            }),
            'sasaran' => tap($this->sasaran($rpjmd, $id), function (SasaranDaerah $sasaran) use ($rpjmd, $data) {
                $sasaran->update([
                    'tujuan_daerah_id' => filled($data['parent_id'] ?? null) ? $this->tujuan($rpjmd, $data['parent_id'])->id : $sasaran->tujuan_daerah_id,
                    'kode' => $data['kode'] ?? null,
                    'sasaran' => $this->requiredText($data, 'uraian', 'Sasaran daerah wajib diisi.'),
                    'urutan' => $data['urutan'] ?? 1,
                ]);
            }),
            'indikator_sasaran' => tap($this->indikatorSasaran($rpjmd, $id), function (IndikatorSasaranDaerah $indikator) use ($rpjmd, $data) {
                $indikator->update([
                    'sasaran_daerah_id' => filled($data['parent_id'] ?? null) ? $this->sasaran($rpjmd, $data['parent_id'])->id : $indikator->sasaran_daerah_id,
                    'satuan_indikator_id' => $data['satuan_indikator_id'] ?? null,
                    'kode' => $data['kode'] ?? null,
                    'indikator' => $this->requiredText($data, 'indikator', 'Indikator sasaran wajib diisi.'),
                    'tipe_indikator' => $data['tipe_indikator'] ?? 'positif',
                    'formula' => $data['formula'] ?? null,
                    'sumber_data' => $data['sumber_data'] ?? null,
                    'urutan' => $data['urutan'] ?? 1,
                ]);
            }),
            'target_sasaran' => tap($this->findNode($rpjmd, $type, $id), function (TargetIndikatorSasaranDaerah $target) use ($rpjmd, $data) {
                $target->update([
                    'indikator_sasaran_daerah_id' => filled($data['parent_id'] ?? null) ? $this->indikatorSasaran($rpjmd, $data['parent_id'])->id : $target->indikator_sasaran_daerah_id,
                    'periode_tahun_id' => filled($data['periode_tahun_id'] ?? null) ? (int) $data['periode_tahun_id'] : $target->periode_tahun_id,
                    'target' => $data['target'] ?? null,
                    'target_text' => $data['target_text'] ?? null,
                ]);
            }),
            'strategi' => tap($this->strategi($rpjmd, $id), function (StrategiDaerah $strategi) use ($rpjmd, $data) {
                $strategi->update([
                    'sasaran_daerah_id' => filled($data['parent_id'] ?? null) ? $this->sasaran($rpjmd, $data['parent_id'])->id : $strategi->sasaran_daerah_id,
                    'kode' => $data['kode'] ?? null,
                    'strategi' => $this->requiredText($data, 'uraian', 'Strategi daerah wajib diisi.'),
                    'arah_kebijakan' => $data['arah_kebijakan'] ?? null,
                    'urutan' => $data['urutan'] ?? 1,
                ]);
            }),
            'program' => tap($this->program($rpjmd, $id), function (ProgramRpjmd $program) use ($rpjmd, $data) {
                $strategi = filled($data['parent_id'] ?? null) ? $this->strategi($rpjmd, $data['parent_id']) : null;

                $program->update([
                    'strategi_daerah_id' => $strategi?->id ?? $program->strategi_daerah_id,
                    'sasaran_daerah_id' => $strategi?->sasaran_daerah_id ?? $program->sasaran_daerah_id,
                    'urusan_pemerintahan_id' => $data['urusan_pemerintahan_id'] ?? null,
                    'kode' => $data['kode'] ?? null,
                    'nama' => $this->requiredText($data, 'uraian', 'Nama program wajib diisi.'),
                    'pagu_indikatif' => $data['pagu_indikatif'] ?? null,
                    'urutan' => $data['urutan'] ?? 1,
                ]);
            }),
            'indikator_program' => tap($this->indikatorProgram($rpjmd, $id), function (IndikatorProgramRpjmd $indikator) use ($rpjmd, $data) {
                $indikator->update([
                    'program_rpjmd_id' => filled($data['parent_id'] ?? null) ? $this->program($rpjmd, $data['parent_id'])->id : $indikator->program_rpjmd_id,
                    'satuan_indikator_id' => $data['satuan_indikator_id'] ?? null,
                    'kode' => $data['kode'] ?? null,
                    'indikator' => $this->requiredText($data, 'indikator', 'Indikator program wajib diisi.'),
                    'tipe_indikator' => $data['tipe_indikator'] ?? 'positif',
                    'formula' => $data['formula'] ?? null,
                    'sumber_data' => $data['sumber_data'] ?? null,
                    'urutan' => $data['urutan'] ?? 1,
                ]);
            }),
            'target_program' => tap($this->findNode($rpjmd, $type, $id), function (TargetIndikatorProgramRpjmd $target) use ($rpjmd, $data) {
                $target->update([
                    'indikator_program_rpjmd_id' => filled($data['parent_id'] ?? null) ? $this->indikatorProgram($rpjmd, $data['parent_id'])->id : $target->indikator_program_rpjmd_id,
                    'periode_tahun_id' => filled($data['periode_tahun_id'] ?? null) ? (int) $data['periode_tahun_id'] : $target->periode_tahun_id,
                    'target' => $data['target'] ?? null,
                    'target_text' => $data['target_text'] ?? null,
                    'pagu' => $data['pagu'] ?? null,
                ]);
            }),
            'program_opd' => tap($this->findNode($rpjmd, $type, $id), function (ProgramRpjmdOpdPenanggungJawab $pivot) use ($rpjmd, $data) {
                $pivot->update([
                    'program_rpjmd_id' => filled($data['parent_id'] ?? null) ? $this->program($rpjmd, $data['parent_id'])->id : $pivot->program_rpjmd_id,
                    'opd_id' => filled($data['opd_id'] ?? null) ? (int) $data['opd_id'] : $pivot->opd_id,
                    'peran' => $data['peran'] ?? $pivot->peran,
                    'is_utama' => array_key_exists('is_utama', $data) ? (bool) $data['is_utama'] : (bool) $pivot->is_utama,
                ]);
            }),
        };
    }

    private function requiredText(array $data, string $key, string $message): string
    {
        $value = trim((string) ($data[$key] ?? ''));

        throw_if($value === '', ValidationException::withMessages([$key => $message]));

        return $value;
    }

    private function requiredInt(array $data, string $key, string $message): int
    {
        throw_if(blank($data[$key] ?? null), ValidationException::withMessages([$key => $message]));

        return (int) $data[$key];
    }

    private function visi(Rpjmd $rpjmd, mixed $id): RpjmdVisi
    {
        return RpjmdVisi::where('rpjmd_id', $rpjmd->id)->findOrFail($id);
    }

    private function misi(Rpjmd $rpjmd, mixed $id): RpjmdMisi
    {
        return RpjmdMisi::where('rpjmd_id', $rpjmd->id)->findOrFail($id);
    }

    private function tujuan(Rpjmd $rpjmd, mixed $id): TujuanDaerah
    {
        return TujuanDaerah::whereHas('misi', fn ($query) => $query->where('rpjmd_id', $rpjmd->id))->findOrFail($id);
    }

    private function indikatorTujuan(Rpjmd $rpjmd, mixed $id): IndikatorTujuanDaerah
    {
        return IndikatorTujuanDaerah::whereHas('tujuan.misi', fn ($query) => $query->where('rpjmd_id', $rpjmd->id))->findOrFail($id);
    }

    private function sasaran(Rpjmd $rpjmd, mixed $id): SasaranDaerah
    {
        return SasaranDaerah::whereHas('tujuan.misi', fn ($query) => $query->where('rpjmd_id', $rpjmd->id))->findOrFail($id);
    }

    private function indikatorSasaran(Rpjmd $rpjmd, mixed $id): IndikatorSasaranDaerah
    {
        return IndikatorSasaranDaerah::whereHas('sasaran.tujuan.misi', fn ($query) => $query->where('rpjmd_id', $rpjmd->id))->findOrFail($id);
    }

    private function strategi(Rpjmd $rpjmd, mixed $id): StrategiDaerah
    {
        return StrategiDaerah::whereHas('sasaran.tujuan.misi', fn ($query) => $query->where('rpjmd_id', $rpjmd->id))->findOrFail($id);
    }

    private function program(Rpjmd $rpjmd, mixed $id): ProgramRpjmd
    {
        return ProgramRpjmd::whereHas('strategi.sasaran.tujuan.misi', fn ($query) => $query->where('rpjmd_id', $rpjmd->id))->findOrFail($id);
    }

    private function indikatorProgram(Rpjmd $rpjmd, mixed $id): IndikatorProgramRpjmd
    {
        return IndikatorProgramRpjmd::whereHas('program.strategi.sasaran.tujuan.misi', fn ($query) => $query->where('rpjmd_id', $rpjmd->id))->findOrFail($id);
    }

    private function findNode(Rpjmd $rpjmd, string $type, int $id): object
    {
        return match ($type) {
            'visi' => $this->visi($rpjmd, $id),
            'misi' => $this->misi($rpjmd, $id),
            'tujuan' => $this->tujuan($rpjmd, $id),
            'indikator_tujuan' => $this->indikatorTujuan($rpjmd, $id),
            'sasaran' => $this->sasaran($rpjmd, $id),
            'indikator_sasaran' => $this->indikatorSasaran($rpjmd, $id),
            'strategi' => $this->strategi($rpjmd, $id),
            'program' => $this->program($rpjmd, $id),
            'indikator_program' => $this->indikatorProgram($rpjmd, $id),
            'target_tujuan' => TargetIndikatorTujuanDaerah::whereHas('indikator.tujuan.misi', fn ($query) => $query->where('rpjmd_id', $rpjmd->id))->findOrFail($id),
            'target_sasaran' => TargetIndikatorSasaranDaerah::whereHas('indikator.sasaran.tujuan.misi', fn ($query) => $query->where('rpjmd_id', $rpjmd->id))->findOrFail($id),
            'target_program' => TargetIndikatorProgramRpjmd::whereHas('indikator.program.strategi.sasaran.tujuan.misi', fn ($query) => $query->where('rpjmd_id', $rpjmd->id))->findOrFail($id),
            'program_opd' => ProgramRpjmdOpdPenanggungJawab::whereHas('program.strategi.sasaran.tujuan.misi', fn ($query) => $query->where('rpjmd_id', $rpjmd->id))->findOrFail($id),
            default => abort(404),
        };
    }
}
