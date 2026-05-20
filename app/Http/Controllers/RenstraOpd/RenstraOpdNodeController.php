<?php

namespace App\Http\Controllers\RenstraOpd;

use App\Http\Controllers\Controller;
use App\Http\Requests\RenstraOpd\StoreRenstraOpdNodeRequest;
use App\Models\IndikatorOpdProgram;
use App\Models\IndikatorSasaranOpd;
use App\Models\IndikatorSubKegiatan;
use App\Models\IndikatorTujuanOpd;
use App\Models\OpdKegiatan;
use App\Models\OpdProgram;
use App\Models\OpdSubKegiatan;
use App\Models\RenstraOpd;
use App\Models\SasaranOpd;
use App\Models\TargetIndikatorOpdProgram;
use App\Models\TargetIndikatorSasaranOpd;
use App\Models\TargetIndikatorTujuanOpd;
use App\Models\TujuanOpd;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RenstraOpdNodeController extends Controller
{
    public function store(StoreRenstraOpdNodeRequest $request, RenstraOpd $renstraOpd): RedirectResponse
    {
        $this->authorize('update', $renstraOpd);

        $data = $request->validated();

        DB::transaction(fn () => $this->storeNode($renstraOpd, $data));

        return back()->with('success', 'Data cascading Renstra OPD berhasil disimpan.');
    }

    public function destroy(RenstraOpd $renstraOpd, string $type, int $id): RedirectResponse
    {
        $this->authorize('update', $renstraOpd);

        $model = $this->findNode($renstraOpd, $type, $id);
        $model->delete();

        return back()->with('success', 'Data cascading Renstra OPD berhasil dihapus.');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function storeNode(RenstraOpd $renstra, array $data): void
    {
        match ($data['type']) {
            'tujuan' => TujuanOpd::create([
                'renstra_opd_id' => $renstra->id,
                'tujuan_daerah_id' => $data['tujuan_daerah_id'] ?? null,
                'kode' => $data['kode'] ?? null,
                'tujuan' => $this->requiredText($data, 'uraian', 'Tujuan OPD wajib diisi.'),
                'urutan' => $data['urutan'] ?? 1,
            ]),
            'indikator_tujuan' => IndikatorTujuanOpd::create([
                'tujuan_opd_id' => $this->tujuan($renstra, $data['parent_id'] ?? null)->id,
                'indikator_tujuan_daerah_id' => $data['indikator_tujuan_daerah_id'] ?? null,
                'satuan_indikator_id' => $data['satuan_indikator_id'] ?? null,
                'kode' => $data['kode'] ?? null,
                'indikator' => $this->requiredText($data, 'indikator', 'Indikator tujuan OPD wajib diisi.'),
                'formula' => $data['formula'] ?? null,
                'sumber_data' => $data['sumber_data'] ?? null,
                'urutan' => $data['urutan'] ?? 1,
            ]),
            'target_tujuan' => TargetIndikatorTujuanOpd::updateOrCreate([
                'indikator_tujuan_opd_id' => $this->indikatorTujuan($renstra, $data['parent_id'] ?? null)->id,
                'periode_tahun_id' => $this->requiredInt($data, 'periode_tahun_id', 'Periode target wajib dipilih.'),
            ], [
                'target' => $data['target'] ?? null,
                'target_text' => $data['target_text'] ?? null,
            ]),
            'sasaran' => SasaranOpd::create([
                'tujuan_opd_id' => $this->tujuan($renstra, $data['parent_id'] ?? null)->id,
                'sasaran_daerah_id' => $data['sasaran_daerah_id'] ?? null,
                'kode' => $data['kode'] ?? null,
                'sasaran' => $this->requiredText($data, 'uraian', 'Sasaran OPD wajib diisi.'),
                'urutan' => $data['urutan'] ?? 1,
            ]),
            'indikator_sasaran' => IndikatorSasaranOpd::create([
                'sasaran_opd_id' => $this->sasaran($renstra, $data['parent_id'] ?? null)->id,
                'indikator_sasaran_daerah_id' => $data['indikator_sasaran_daerah_id'] ?? null,
                'satuan_indikator_id' => $data['satuan_indikator_id'] ?? null,
                'kode' => $data['kode'] ?? null,
                'indikator' => $this->requiredText($data, 'indikator', 'Indikator sasaran OPD wajib diisi.'),
                'formula' => $data['formula'] ?? null,
                'sumber_data' => $data['sumber_data'] ?? null,
                'urutan' => $data['urutan'] ?? 1,
            ]),
            'target_sasaran' => TargetIndikatorSasaranOpd::updateOrCreate([
                'indikator_sasaran_opd_id' => $this->indikatorSasaran($renstra, $data['parent_id'] ?? null)->id,
                'periode_tahun_id' => $this->requiredInt($data, 'periode_tahun_id', 'Periode target wajib dipilih.'),
            ], [
                'target' => $data['target'] ?? null,
                'target_text' => $data['target_text'] ?? null,
            ]),
            'program' => tap($this->sasaran($renstra, $data['parent_id'] ?? null), function (SasaranOpd $sasaran) use ($renstra, $data) {
                OpdProgram::create([
                    'renstra_opd_id' => $renstra->id,
                    'sasaran_opd_id' => $sasaran->id,
                    'program_rpjmd_id' => $data['program_rpjmd_id'] ?? null,
                    'kode' => $data['kode'] ?? null,
                    'nama' => $this->requiredText($data, 'uraian', 'Nama program OPD wajib diisi.'),
                    'pagu_indikatif' => $data['pagu_indikatif'] ?? null,
                    'status' => 'draft',
                    'urutan' => $data['urutan'] ?? 1,
                ]);
            }),
            'indikator_program' => IndikatorOpdProgram::create([
                'opd_program_id' => $this->program($renstra, $data['parent_id'] ?? null)->id,
                'indikator_program_rpjmd_id' => $data['indikator_program_rpjmd_id'] ?? null,
                'satuan_indikator_id' => $data['satuan_indikator_id'] ?? null,
                'kode' => $data['kode'] ?? null,
                'indikator' => $this->requiredText($data, 'indikator', 'Indikator program OPD wajib diisi.'),
                'formula' => $data['formula'] ?? null,
                'sumber_data' => $data['sumber_data'] ?? null,
                'urutan' => $data['urutan'] ?? 1,
            ]),
            'target_program' => TargetIndikatorOpdProgram::updateOrCreate([
                'indikator_opd_program_id' => $this->indikatorProgram($renstra, $data['parent_id'] ?? null)->id,
                'periode_tahun_id' => $this->requiredInt($data, 'periode_tahun_id', 'Periode target wajib dipilih.'),
            ], [
                'target' => $data['target'] ?? null,
                'target_text' => $data['target_text'] ?? null,
                'pagu' => $data['pagu'] ?? null,
            ]),
            'kegiatan' => OpdKegiatan::create([
                'opd_program_id' => $this->program($renstra, $data['parent_id'] ?? null)->id,
                'kode' => $data['kode'] ?? null,
                'nama' => $this->requiredText($data, 'uraian', 'Nama kegiatan OPD wajib diisi.'),
                'pagu_indikatif' => $data['pagu_indikatif'] ?? null,
                'urutan' => $data['urutan'] ?? 1,
            ]),
            'sub_kegiatan' => OpdSubKegiatan::create([
                'opd_kegiatan_id' => $this->kegiatan($renstra, $data['parent_id'] ?? null)->id,
                'kode' => $data['kode'] ?? null,
                'nama' => $this->requiredText($data, 'uraian', 'Nama sub kegiatan OPD wajib diisi.'),
                'pagu_indikatif' => $data['pagu_indikatif'] ?? null,
                'urutan' => $data['urutan'] ?? 1,
            ]),
            'indikator_sub_kegiatan' => IndikatorSubKegiatan::create([
                'opd_sub_kegiatan_id' => $this->subKegiatan($renstra, $data['parent_id'] ?? null)->id,
                'satuan_indikator_id' => $data['satuan_indikator_id'] ?? null,
                'kode' => $data['kode'] ?? null,
                'indikator' => $this->requiredText($data, 'indikator', 'Indikator sub kegiatan wajib diisi.'),
                'formula' => $data['formula'] ?? null,
                'sumber_data' => $data['sumber_data'] ?? null,
                'urutan' => $data['urutan'] ?? 1,
            ]),
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

    private function tujuan(RenstraOpd $renstra, mixed $id): TujuanOpd
    {
        return TujuanOpd::where('renstra_opd_id', $renstra->id)->findOrFail($id);
    }

    private function indikatorTujuan(RenstraOpd $renstra, mixed $id): IndikatorTujuanOpd
    {
        return IndikatorTujuanOpd::whereHas('tujuan', fn ($query) => $query->where('renstra_opd_id', $renstra->id))->findOrFail($id);
    }

    private function sasaran(RenstraOpd $renstra, mixed $id): SasaranOpd
    {
        return SasaranOpd::whereHas('tujuan', fn ($query) => $query->where('renstra_opd_id', $renstra->id))->findOrFail($id);
    }

    private function indikatorSasaran(RenstraOpd $renstra, mixed $id): IndikatorSasaranOpd
    {
        return IndikatorSasaranOpd::whereHas('sasaran.tujuan', fn ($query) => $query->where('renstra_opd_id', $renstra->id))->findOrFail($id);
    }

    private function program(RenstraOpd $renstra, mixed $id): OpdProgram
    {
        return OpdProgram::where('renstra_opd_id', $renstra->id)->findOrFail($id);
    }

    private function indikatorProgram(RenstraOpd $renstra, mixed $id): IndikatorOpdProgram
    {
        return IndikatorOpdProgram::whereHas('program', fn ($query) => $query->where('renstra_opd_id', $renstra->id))->findOrFail($id);
    }

    private function kegiatan(RenstraOpd $renstra, mixed $id): OpdKegiatan
    {
        return OpdKegiatan::whereHas('program', fn ($query) => $query->where('renstra_opd_id', $renstra->id))->findOrFail($id);
    }

    private function subKegiatan(RenstraOpd $renstra, mixed $id): OpdSubKegiatan
    {
        return OpdSubKegiatan::whereHas('kegiatan.program', fn ($query) => $query->where('renstra_opd_id', $renstra->id))->findOrFail($id);
    }

    private function findNode(RenstraOpd $renstra, string $type, int $id): object
    {
        return match ($type) {
            'tujuan' => $this->tujuan($renstra, $id),
            'indikator_tujuan' => $this->indikatorTujuan($renstra, $id),
            'target_tujuan' => TargetIndikatorTujuanOpd::whereHas('indikator.tujuan', fn ($query) => $query->where('renstra_opd_id', $renstra->id))->findOrFail($id),
            'sasaran' => $this->sasaran($renstra, $id),
            'indikator_sasaran' => $this->indikatorSasaran($renstra, $id),
            'target_sasaran' => TargetIndikatorSasaranOpd::whereHas('indikator.sasaran.tujuan', fn ($query) => $query->where('renstra_opd_id', $renstra->id))->findOrFail($id),
            'program' => $this->program($renstra, $id),
            'indikator_program' => $this->indikatorProgram($renstra, $id),
            'target_program' => TargetIndikatorOpdProgram::whereHas('indikator.program', fn ($query) => $query->where('renstra_opd_id', $renstra->id))->findOrFail($id),
            'kegiatan' => $this->kegiatan($renstra, $id),
            'sub_kegiatan' => $this->subKegiatan($renstra, $id),
            'indikator_sub_kegiatan' => IndikatorSubKegiatan::whereHas('subKegiatan.kegiatan.program', fn ($query) => $query->where('renstra_opd_id', $renstra->id))->findOrFail($id),
            default => abort(404),
        };
    }
}
