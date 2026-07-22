<?php

namespace App\Http\Controllers\RenstraOpd;

use App\Http\Controllers\Controller;
use App\Http\Requests\RenstraOpd\StoreRenstraOpdNodeRequest;
use App\Models\IndikatorOpdProgram;
use App\Models\IndikatorProgramRpjmd;
use App\Models\IndikatorSasaranOpd;
use App\Models\IndikatorSubKegiatan;
use App\Models\IndikatorTujuanOpd;
use App\Models\KegiatanPemerintahan;
use App\Models\OpdKegiatan;
use App\Models\OpdProgram;
use App\Models\OpdSubKegiatan;
use App\Models\OpdUnit;
use App\Models\ProgramPemerintahan;
use App\Models\ProgramRpjmd;
use App\Models\RenstraOpd;
use App\Models\SasaranOpd;
use App\Models\SubKegiatanPemerintahan;
use App\Models\TargetIndikatorOpdProgram;
use App\Models\TargetIndikatorSasaranOpd;
use App\Models\TargetIndikatorTujuanOpd;
use App\Models\TujuanOpd;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
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

    public function autosaveStore(StoreRenstraOpdNodeRequest $request, RenstraOpd $renstraOpd): JsonResponse
    {
        $this->authorize('update', $renstraOpd);

        $data = $request->validated();

        $model = DB::transaction(fn () => $this->storeNode($renstraOpd, $data));

        return response()->json([
            'status' => 'saved',
            'id' => $model->getKey(),
            'type' => $data['type'],
            'message' => 'Data cascading Renstra OPD baru tersimpan otomatis.',
            'saved_at' => now()->toIso8601String(),
        ], 201);
    }

    public function update(StoreRenstraOpdNodeRequest $request, RenstraOpd $renstraOpd, string $type, int $id): RedirectResponse
    {
        $this->authorize('update', $renstraOpd);

        $data = $request->validated();

        if (($data['type'] ?? null) !== $type) {
            throw ValidationException::withMessages(['type' => 'Jenis data tidak sesuai dengan node yang diedit.']);
        }

        DB::transaction(fn () => $this->updateNode($renstraOpd, $type, $id, $data));

        return back()->with('success', 'Data cascading Renstra OPD berhasil diperbarui.');
    }

    public function autosave(StoreRenstraOpdNodeRequest $request, RenstraOpd $renstraOpd, string $type, int $id): JsonResponse
    {
        $this->authorize('update', $renstraOpd);

        $data = $request->validated();

        if (($data['type'] ?? null) !== $type) {
            throw ValidationException::withMessages(['type' => 'Jenis data tidak sesuai dengan node yang diedit.']);
        }

        DB::transaction(fn () => $this->updateNode($renstraOpd, $type, $id, $data));

        return response()->json([
            'status' => 'saved',
            'message' => 'Data cascading Renstra OPD tersimpan otomatis.',
            'saved_at' => now()->toIso8601String(),
        ]);
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
    private function storeNode(RenstraOpd $renstra, array $data): Model
    {
        return match ($data['type']) {
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
                'tipe_indikator' => $data['tipe_indikator'] ?? 'positif',
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
                'tipe_indikator' => $data['tipe_indikator'] ?? 'positif',
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
            'program' => OpdProgram::create([
                'renstra_opd_id' => $renstra->id,
                'sasaran_opd_id' => $this->sasaran($renstra, $data['parent_id'] ?? null)->id,
                'status' => 'draft',
                ...$this->programPayload($renstra, $data),
            ]),
            'indikator_program' => $this->createIndikatorProgram($renstra, $data),
            'target_program' => TargetIndikatorOpdProgram::updateOrCreate([
                'indikator_opd_program_id' => $this->indikatorProgram($renstra, $data['parent_id'] ?? null)->id,
                'periode_tahun_id' => $this->requiredInt($data, 'periode_tahun_id', 'Periode target wajib dipilih.'),
            ], [
                'target' => $data['target'] ?? null,
                'target_text' => $data['target_text'] ?? null,
                'pagu' => $data['pagu'] ?? null,
            ]),
            'kegiatan' => $this->createKegiatan($renstra, $data),
            'sub_kegiatan' => $this->createSubKegiatan($renstra, $data),
            'indikator_sub_kegiatan' => IndikatorSubKegiatan::create([
                'opd_sub_kegiatan_id' => $this->subKegiatan($renstra, $data['parent_id'] ?? null)->id,
                'satuan_indikator_id' => $data['satuan_indikator_id'] ?? null,
                'kode' => $data['kode'] ?? null,
                'indikator' => $this->requiredText($data, 'indikator', 'Indikator sub kegiatan wajib diisi.'),
                'tipe_indikator' => $data['tipe_indikator'] ?? 'positif',
                'formula' => $data['formula'] ?? null,
                'sumber_data' => $data['sumber_data'] ?? null,
                'urutan' => $data['urutan'] ?? 1,
            ]),
        };
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function updateNode(RenstraOpd $renstra, string $type, int $id, array $data): void
    {
        match ($type) {
            'tujuan' => $this->tujuan($renstra, $id)->update([
                'tujuan_daerah_id' => $data['tujuan_daerah_id'] ?? null,
                'kode' => $data['kode'] ?? null,
                'tujuan' => $this->requiredText($data, 'uraian', 'Tujuan OPD wajib diisi.'),
                'urutan' => $data['urutan'] ?? 1,
            ]),
            'indikator_tujuan' => tap($this->indikatorTujuan($renstra, $id), function (IndikatorTujuanOpd $indikator) use ($renstra, $data) {
                $indikator->update([
                    'tujuan_opd_id' => filled($data['parent_id'] ?? null) ? $this->tujuan($renstra, $data['parent_id'])->id : $indikator->tujuan_opd_id,
                    'indikator_tujuan_daerah_id' => $data['indikator_tujuan_daerah_id'] ?? null,
                    'satuan_indikator_id' => $data['satuan_indikator_id'] ?? null,
                    'kode' => $data['kode'] ?? null,
                    'indikator' => $this->requiredText($data, 'indikator', 'Indikator tujuan OPD wajib diisi.'),
                    'tipe_indikator' => $data['tipe_indikator'] ?? 'positif',
                    'formula' => $data['formula'] ?? null,
                    'sumber_data' => $data['sumber_data'] ?? null,
                    'urutan' => $data['urutan'] ?? 1,
                ]);
            }),
            'target_tujuan' => tap($this->findNode($renstra, $type, $id), function (TargetIndikatorTujuanOpd $target) use ($renstra, $data) {
                $target->update([
                    'indikator_tujuan_opd_id' => filled($data['parent_id'] ?? null) ? $this->indikatorTujuan($renstra, $data['parent_id'])->id : $target->indikator_tujuan_opd_id,
                    'periode_tahun_id' => filled($data['periode_tahun_id'] ?? null) ? (int) $data['periode_tahun_id'] : $target->periode_tahun_id,
                    'target' => $data['target'] ?? null,
                    'target_text' => $data['target_text'] ?? null,
                ]);
            }),
            'sasaran' => tap($this->sasaran($renstra, $id), function (SasaranOpd $sasaran) use ($renstra, $data) {
                $sasaran->update([
                    'tujuan_opd_id' => filled($data['parent_id'] ?? null) ? $this->tujuan($renstra, $data['parent_id'])->id : $sasaran->tujuan_opd_id,
                    'sasaran_daerah_id' => $data['sasaran_daerah_id'] ?? null,
                    'kode' => $data['kode'] ?? null,
                    'sasaran' => $this->requiredText($data, 'uraian', 'Sasaran OPD wajib diisi.'),
                    'urutan' => $data['urutan'] ?? 1,
                ]);
            }),
            'indikator_sasaran' => tap($this->indikatorSasaran($renstra, $id), function (IndikatorSasaranOpd $indikator) use ($renstra, $data) {
                $indikator->update([
                    'sasaran_opd_id' => filled($data['parent_id'] ?? null) ? $this->sasaran($renstra, $data['parent_id'])->id : $indikator->sasaran_opd_id,
                    'indikator_sasaran_daerah_id' => $data['indikator_sasaran_daerah_id'] ?? null,
                    'satuan_indikator_id' => $data['satuan_indikator_id'] ?? null,
                    'kode' => $data['kode'] ?? null,
                    'indikator' => $this->requiredText($data, 'indikator', 'Indikator sasaran OPD wajib diisi.'),
                    'tipe_indikator' => $data['tipe_indikator'] ?? 'positif',
                    'formula' => $data['formula'] ?? null,
                    'sumber_data' => $data['sumber_data'] ?? null,
                    'urutan' => $data['urutan'] ?? 1,
                ]);
            }),
            'target_sasaran' => tap($this->findNode($renstra, $type, $id), function (TargetIndikatorSasaranOpd $target) use ($renstra, $data) {
                $target->update([
                    'indikator_sasaran_opd_id' => filled($data['parent_id'] ?? null) ? $this->indikatorSasaran($renstra, $data['parent_id'])->id : $target->indikator_sasaran_opd_id,
                    'periode_tahun_id' => filled($data['periode_tahun_id'] ?? null) ? (int) $data['periode_tahun_id'] : $target->periode_tahun_id,
                    'target' => $data['target'] ?? null,
                    'target_text' => $data['target_text'] ?? null,
                ]);
            }),
            'program' => tap($this->program($renstra, $id), function (OpdProgram $program) use ($renstra, $data) {
                $program->update([
                    'sasaran_opd_id' => filled($data['parent_id'] ?? null) ? $this->sasaran($renstra, $data['parent_id'])->id : $program->sasaran_opd_id,
                    ...$this->programPayload($renstra, $data),
                ]);
            }),
            'indikator_program' => tap($this->indikatorProgram($renstra, $id), function (IndikatorOpdProgram $indikator) use ($renstra, $data) {
                $program = filled($data['parent_id'] ?? null) ? $this->program($renstra, $data['parent_id']) : $indikator->program;

                $indikator->update([
                    'opd_program_id' => $program->id,
                    ...$this->indikatorProgramPayload($renstra, $program, $data),
                ]);
            }),
            'target_program' => tap($this->findNode($renstra, $type, $id), function (TargetIndikatorOpdProgram $target) use ($renstra, $data) {
                $target->update([
                    'indikator_opd_program_id' => filled($data['parent_id'] ?? null) ? $this->indikatorProgram($renstra, $data['parent_id'])->id : $target->indikator_opd_program_id,
                    'periode_tahun_id' => filled($data['periode_tahun_id'] ?? null) ? (int) $data['periode_tahun_id'] : $target->periode_tahun_id,
                    'target' => $data['target'] ?? null,
                    'target_text' => $data['target_text'] ?? null,
                    'pagu' => $data['pagu'] ?? null,
                ]);
            }),
            'kegiatan' => tap($this->kegiatan($renstra, $id), function (OpdKegiatan $kegiatan) use ($renstra, $data) {
                $program = filled($data['parent_id'] ?? null) ? $this->program($renstra, $data['parent_id']) : $kegiatan->program;

                $kegiatan->update([
                    'opd_program_id' => $program->id,
                    ...$this->kegiatanPayload($program, $data),
                ]);
            }),
            'sub_kegiatan' => tap($this->subKegiatan($renstra, $id), function (OpdSubKegiatan $subKegiatan) use ($renstra, $data) {
                $kegiatan = filled($data['parent_id'] ?? null) ? $this->kegiatan($renstra, $data['parent_id']) : $subKegiatan->kegiatan;

                $subKegiatan->update([
                    'opd_kegiatan_id' => $kegiatan->id,
                    ...$this->subKegiatanPayload($renstra, $kegiatan, $data),
                ]);
            }),
            'indikator_sub_kegiatan' => tap($this->findNode($renstra, $type, $id), function (IndikatorSubKegiatan $indikator) use ($renstra, $data) {
                $indikator->update([
                    'opd_sub_kegiatan_id' => filled($data['parent_id'] ?? null) ? $this->subKegiatan($renstra, $data['parent_id'])->id : $indikator->opd_sub_kegiatan_id,
                    'satuan_indikator_id' => $data['satuan_indikator_id'] ?? null,
                    'kode' => $data['kode'] ?? null,
                    'indikator' => $this->requiredText($data, 'indikator', 'Indikator sub kegiatan wajib diisi.'),
                    'tipe_indikator' => $data['tipe_indikator'] ?? 'positif',
                    'formula' => $data['formula'] ?? null,
                    'sumber_data' => $data['sumber_data'] ?? null,
                    'urutan' => $data['urutan'] ?? 1,
                ]);
            }),
        };
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function createKegiatan(RenstraOpd $renstra, array $data): OpdKegiatan
    {
        $program = $this->program($renstra, $data['parent_id'] ?? null);

        return $program->kegiatan()->create($this->kegiatanPayload($program, $data));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function createSubKegiatan(RenstraOpd $renstra, array $data): OpdSubKegiatan
    {
        $kegiatan = $this->kegiatan($renstra, $data['parent_id'] ?? null);

        return $kegiatan->subKegiatan()->create($this->subKegiatanPayload($renstra, $kegiatan, $data));
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function programPayload(RenstraOpd $renstra, array $data): array
    {
        $programRpjmd = filled($data['program_rpjmd_id'] ?? null)
            ? $this->programRpjmdReference($renstra, $data['program_rpjmd_id'])
            : null;
        $programRpjmd?->loadMissing('programPemerintahanReferences');

        $programRpjmdProgramIds = $programRpjmd?->programPemerintahanReferenceIds() ?? [];
        $programPemerintahanId = $data['program_pemerintahan_id']
            ?? ($programRpjmdProgramIds[0] ?? $programRpjmd?->program_pemerintahan_id);
        $reference = filled($programPemerintahanId)
            ? ProgramPemerintahan::query()->findOrFail($programPemerintahanId)
            : null;

        if ($programRpjmd && $reference && $programRpjmdProgramIds !== [] && ! in_array((int) $reference->id, $programRpjmdProgramIds, true)) {
            throw ValidationException::withMessages([
                'program_pemerintahan_id' => 'Program master tidak sesuai dengan Program RPJMD yang dipilih.',
            ]);
        }

        return [
            'program_rpjmd_id' => $programRpjmd?->id,
            'program_pemerintahan_id' => $reference?->id,
            'kode' => $reference?->kode ?? ($data['kode'] ?? null),
            'nama' => $reference?->nama ?? $this->requiredText($data, 'uraian', 'Nama program OPD wajib diisi.'),
            'pagu_indikatif' => $data['pagu_indikatif'] ?? null,
            'urutan' => $data['urutan'] ?? 1,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function createIndikatorProgram(RenstraOpd $renstra, array $data): IndikatorOpdProgram
    {
        $program = $this->program($renstra, $data['parent_id'] ?? null);

        return $program->indikator()->create($this->indikatorProgramPayload($renstra, $program, $data));
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function indikatorProgramPayload(RenstraOpd $renstra, OpdProgram $program, array $data): array
    {
        $indikatorProgramRpjmd = filled($data['indikator_program_rpjmd_id'] ?? null)
            ? $this->indikatorProgramRpjmdReference($renstra, $program, $data['indikator_program_rpjmd_id'])
            : null;

        return [
            'indikator_program_rpjmd_id' => $indikatorProgramRpjmd?->id,
            'satuan_indikator_id' => $data['satuan_indikator_id'] ?? null,
            'kode' => $data['kode'] ?? null,
            'indikator' => $this->requiredText($data, 'indikator', 'Indikator program OPD wajib diisi.'),
            'tipe_indikator' => $data['tipe_indikator'] ?? 'positif',
            'formula' => $data['formula'] ?? null,
            'sumber_data' => $data['sumber_data'] ?? null,
            'urutan' => $data['urutan'] ?? 1,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function kegiatanPayload(OpdProgram $program, array $data): array
    {
        $reference = filled($data['kegiatan_pemerintahan_id'] ?? null)
            ? KegiatanPemerintahan::query()->findOrFail($data['kegiatan_pemerintahan_id'])
            : null;

        if ($reference && $program->program_pemerintahan_id && (int) $reference->program_pemerintahan_id !== (int) $program->program_pemerintahan_id) {
            throw ValidationException::withMessages([
                'kegiatan_pemerintahan_id' => 'Kegiatan master harus berada di bawah program master induk.',
            ]);
        }

        return [
            'kegiatan_pemerintahan_id' => $reference?->id,
            'kode' => $reference?->kode ?? ($data['kode'] ?? null),
            'nama' => $reference?->nama ?? $this->requiredText($data, 'uraian', 'Nama kegiatan OPD wajib diisi.'),
            'pagu_indikatif' => $data['pagu_indikatif'] ?? null,
            'urutan' => $data['urutan'] ?? 1,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function subKegiatanPayload(RenstraOpd $renstra, OpdKegiatan $kegiatan, array $data): array
    {
        $reference = filled($data['sub_kegiatan_pemerintahan_id'] ?? null)
            ? SubKegiatanPemerintahan::query()->findOrFail($data['sub_kegiatan_pemerintahan_id'])
            : null;

        if ($reference && $kegiatan->kegiatan_pemerintahan_id && (int) $reference->kegiatan_pemerintahan_id !== (int) $kegiatan->kegiatan_pemerintahan_id) {
            throw ValidationException::withMessages([
                'sub_kegiatan_pemerintahan_id' => 'Sub kegiatan master harus berada di bawah kegiatan master induk.',
            ]);
        }

        return [
            'sub_kegiatan_pemerintahan_id' => $reference?->id,
            'opd_unit_id' => $this->validatedOpdUnitId($renstra, $data['opd_unit_id'] ?? null),
            'kode' => $reference?->kode ?? ($data['kode'] ?? null),
            'nama' => $reference?->nama ?? $this->requiredText($data, 'uraian', 'Nama sub kegiatan OPD wajib diisi.'),
            'pagu_indikatif' => $data['pagu_indikatif'] ?? null,
            'urutan' => $data['urutan'] ?? 1,
        ];
    }

    private function validatedOpdUnitId(RenstraOpd $renstra, mixed $id): ?int
    {
        if (blank($id)) {
            return null;
        }

        $exists = OpdUnit::query()
            ->where('opd_id', $renstra->opd_id)
            ->whereKey($id)
            ->exists();

        throw_if(! $exists, ValidationException::withMessages([
            'opd_unit_id' => 'Unit OPD harus berada di bawah OPD Renstra ini.',
        ]));

        return (int) $id;
    }

    private function programRpjmdReference(RenstraOpd $renstra, mixed $id): ProgramRpjmd
    {
        $program = $this->accessibleProgramRpjmdQuery($renstra)
            ->whereKey($id)
            ->first();

        throw_if(! $program, ValidationException::withMessages([
            'program_rpjmd_id' => 'Program RPJMD tidak tersedia untuk OPD Renstra ini.',
        ]));

        return $program;
    }

    private function indikatorProgramRpjmdReference(RenstraOpd $renstra, OpdProgram $program, mixed $id): IndikatorProgramRpjmd
    {
        $indikator = IndikatorProgramRpjmd::query()
            ->whereKey($id)
            ->whereHas('program', fn (Builder $query) => $query
                ->whereIn('id', $this->accessibleProgramRpjmdQuery($renstra)->select('program_rpjmd.id')))
            ->first();

        throw_if(! $indikator, ValidationException::withMessages([
            'indikator_program_rpjmd_id' => 'Indikator program RPJMD tidak tersedia untuk OPD Renstra ini.',
        ]));

        if ($program->program_rpjmd_id && (int) $indikator->program_rpjmd_id !== (int) $program->program_rpjmd_id) {
            throw ValidationException::withMessages([
                'indikator_program_rpjmd_id' => 'Indikator program RPJMD harus berada pada Program RPJMD yang sama.',
            ]);
        }

        return $indikator;
    }

    private function accessibleProgramRpjmdQuery(RenstraOpd $renstra): Builder
    {
        return ProgramRpjmd::query()
            ->forRpjmd($renstra->rpjmd_id)
            ->when($this->shouldRestrictRpjmdProgramReferences($renstra), fn (Builder $query) => $query
                ->whereHas('opdPenanggungJawab', fn (Builder $query) => $query->whereKey($renstra->opd_id)));
    }

    private function shouldRestrictRpjmdProgramReferences(RenstraOpd $renstra): bool
    {
        $user = request()->user();

        return $user?->hasRole('admin_opd') && filled($renstra->opd_id);
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
