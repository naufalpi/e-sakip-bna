<?php

namespace App\Http\Controllers\Rpjmd;

use App\Http\Controllers\Controller;
use App\Http\Requests\Rpjmd\StoreRpjmdNodeBulkRequest;
use App\Http\Requests\Rpjmd\StoreRpjmdNodeRequest;
use App\Models\IndikatorProgramRpjmd;
use App\Models\IndikatorSasaranDaerah;
use App\Models\IndikatorTujuanDaerah;
use App\Models\PeriodeTahun;
use App\Models\ProgramPemerintahan;
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
use App\Services\Rpjmd\RpjmdProgramPengampuResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RpjmdNodeController extends Controller
{
    public function __construct(private readonly RpjmdProgramPengampuResolver $pengampuResolver) {}

    public function store(StoreRpjmdNodeRequest $request, Rpjmd $rpjmd): RedirectResponse
    {
        $this->authorize('update', $rpjmd);

        $data = $request->validated();

        DB::transaction(fn () => $this->storeNode($rpjmd, $data));

        return back()->with('success', 'Data cascading RPJMD berhasil disimpan.');
    }

    public function bulkStore(StoreRpjmdNodeBulkRequest $request, Rpjmd $rpjmd): RedirectResponse|JsonResponse
    {
        $this->authorize('update', $rpjmd);

        $data = $request->validated();
        $rows = collect($data['rows'] ?? [])
            ->map(fn (array $row, int $index) => $this->normalizeBulkRow($data, $row, $index))
            ->filter(fn (array $row) => $this->bulkRowHasContent($row))
            ->values();

        if ($rows->isEmpty()) {
            throw ValidationException::withMessages(['rows' => 'Minimal satu baris bulk harus diisi.']);
        }

        DB::transaction(function () use ($rpjmd, $rows): void {
            $rows->each(fn (array $row) => $this->storeNode($rpjmd, $row));
        });

        if ($request->expectsJson()) {
            return response()->json([
                'count' => $rows->count(),
                'message' => "{$rows->count()} data cascading RPJMD berhasil disimpan.",
            ]);
        }

        return back()->with('success', "{$rows->count()} data cascading RPJMD berhasil disimpan.");
    }

    public function update(StoreRpjmdNodeRequest $request, Rpjmd $rpjmd, string $type, int $id): RedirectResponse|JsonResponse
    {
        $this->authorize('update', $rpjmd);

        $data = $request->validated();

        if (($data['type'] ?? null) !== $type) {
            throw ValidationException::withMessages(['type' => 'Jenis data tidak sesuai dengan node yang diedit.']);
        }

        DB::transaction(fn () => $this->updateNode($rpjmd, $type, $id, $data));

        if ($request->expectsJson()) {
            return response()->json([
                'id' => $id,
                'type' => $type,
                'message' => 'Data cascading RPJMD berhasil diperbarui.',
            ]);
        }

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
            'tujuan' => tap(TujuanDaerah::create([
                'rpjmd_visi_id' => $this->visi($rpjmd, $data['parent_id'] ?? null)->id,
                'rpjmd_misi_id' => null,
                'kode' => $data['kode'] ?? null,
                'tujuan' => $this->requiredText($data, 'uraian', 'Tujuan daerah wajib diisi.'),
                'urutan' => $data['urutan'] ?? 1,
            ]), fn (TujuanDaerah $tujuan) => $this->syncTujuanMisi($rpjmd, $tujuan, $data)),
            'indikator_tujuan' => IndikatorTujuanDaerah::create([
                'tujuan_daerah_id' => $this->tujuan($rpjmd, $data['parent_id'] ?? null)->id,
                ...$this->indicatorPayload($data, 'Indikator tujuan wajib diisi.'),
            ]),
            'target_tujuan' => TargetIndikatorTujuanDaerah::updateOrCreate([
                'indikator_tujuan_daerah_id' => $this->indikatorTujuan($rpjmd, $data['parent_id'] ?? null)->id,
                'periode_tahun_id' => $this->requiredInt($data, 'periode_tahun_id', 'Periode target wajib dipilih.'),
            ], [
                'jenis_target' => $this->jenisTarget($rpjmd, (int) $data['periode_tahun_id']),
                'target' => $data['target'] ?? null,
                'target_text' => $data['target_text'] ?? null,
            ]),
            'sasaran' => tap(SasaranDaerah::create([
                'tujuan_daerah_id' => $this->tujuan($rpjmd, $data['parent_id'] ?? null)->id,
                'kode' => $data['kode'] ?? null,
                'sasaran' => $this->requiredText($data, 'uraian', 'Sasaran daerah wajib diisi.'),
                'urutan' => $data['urutan'] ?? 1,
            ]), fn (SasaranDaerah $sasaran) => $this->syncSasaranIndikatorTujuan($rpjmd, $sasaran, $data)),
            'indikator_sasaran' => IndikatorSasaranDaerah::create([
                'sasaran_daerah_id' => $this->sasaran($rpjmd, $data['parent_id'] ?? null)->id,
                ...$this->indicatorPayload($data, 'Indikator sasaran wajib diisi.'),
            ]),
            'target_sasaran' => TargetIndikatorSasaranDaerah::updateOrCreate([
                'indikator_sasaran_daerah_id' => $this->indikatorSasaran($rpjmd, $data['parent_id'] ?? null)->id,
                'periode_tahun_id' => $this->requiredInt($data, 'periode_tahun_id', 'Periode target wajib dipilih.'),
            ], [
                'jenis_target' => $this->jenisTarget($rpjmd, (int) $data['periode_tahun_id']),
                'target' => $data['target'] ?? null,
                'target_text' => $data['target_text'] ?? null,
            ]),
            'program' => tap($this->sasaran($rpjmd, $data['parent_id'] ?? null), function (SasaranDaerah $sasaran) use ($rpjmd, $data) {
                $strategi = $this->optionalStrategiProgram($data);
                $programPemerintahan = $this->optionalProgramPemerintahan($data, null, $rpjmd);

                $program = ProgramRpjmd::create([
                    'strategi_daerah_id' => $strategi?->id,
                    'sasaran_daerah_id' => $sasaran->id,
                    'indikator_sasaran_daerah_id' => null,
                    'program_pemerintahan_id' => $programPemerintahan?->id,
                    'urusan_pemerintahan_id' => $programPemerintahan?->bidangUrusan?->urusan_pemerintahan_id ?? ($data['urusan_pemerintahan_id'] ?? null),
                    'kode' => $programPemerintahan?->kode ?? ($data['kode'] ?? null),
                    'nama' => $programPemerintahan?->nama ?? $this->requiredText($data, 'uraian', 'Nama program wajib diisi.'),
                    'status' => 'draft',
                    'is_penanggung_jawab_manual' => $this->penanggungJawabManual($data),
                    'urutan' => $data['urutan'] ?? 1,
                ]);

                $this->syncProgramPemerintahanReferences($program, $programPemerintahan, $data);
                $program = $program->refresh();
                $this->syncProgramOpdPenanggungJawab($program, $data);
                $this->pengampuResolver->syncForProgramIndicators($program);
            }),
            'indikator_program' => tap(IndikatorProgramRpjmd::create([
                'program_rpjmd_id' => $this->program($rpjmd, $data['parent_id'] ?? null)->id,
                ...$this->indicatorProgramPayload($data, 'Indikator program wajib diisi.'),
            ]), fn (IndikatorProgramRpjmd $indikator) => $this->syncIndikatorProgramOpdPengampu($indikator, $data)),
            'target_program' => TargetIndikatorProgramRpjmd::updateOrCreate([
                'indikator_program_rpjmd_id' => $this->indikatorProgram($rpjmd, $data['parent_id'] ?? null)->id,
                'periode_tahun_id' => $this->requiredInt($data, 'periode_tahun_id', 'Periode target wajib dipilih.'),
            ], [
                'jenis_target' => $this->jenisTarget($rpjmd, (int) $data['periode_tahun_id']),
                'target' => $data['target'] ?? null,
                'target_text' => $data['target_text'] ?? null,
            ]),
            'program_opd' => tap($this->program($rpjmd, $data['parent_id'] ?? null), function (ProgramRpjmd $program) use ($data) {
                $program->update(['is_penanggung_jawab_manual' => true]);

                ProgramRpjmdOpdPenanggungJawab::updateOrCreate([
                    'program_rpjmd_id' => $program->id,
                    'opd_id' => $this->requiredInt($data, 'opd_id', 'OPD penanggung jawab wajib dipilih.'),
                    'peran' => $data['peran'] ?? 'penanggung_jawab',
                ], [
                    'is_utama' => (bool) ($data['is_utama'] ?? true),
                ]);
            }),
        };
    }

    /**
     * @param  array<string, mixed>  $bulk
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function normalizeBulkRow(array $bulk, array $row, int $index): array
    {
        $inheritKeys = [
            'parent_id',
            'misi_ids',
            'indikator_tujuan_ids',
            'periode_tahun_id',
            'satuan_indikator_id',
            'opd_ids',
            'cakupan_pengampu',
            'urusan_pemerintahan_id',
            'strategi_daerah_id',
            'program_pemerintahan_id',
            'program_pemerintahan_ids',
            'peran',
            'is_utama',
            'is_penanggung_jawab_manual',
        ];

        foreach ($inheritKeys as $key) {
            if ($key === 'is_utama') {
                if (! array_key_exists($key, $row) && array_key_exists($key, $bulk)) {
                    $row[$key] = $bulk[$key];
                }

                continue;
            }

            if (blank($row[$key] ?? null) && array_key_exists($key, $bulk)) {
                $row[$key] = $bulk[$key];
            }
        }

        $row['type'] = $bulk['type'];
        $row['urutan'] = filled($row['urutan'] ?? null) ? $row['urutan'] : $index + 1;

        return $row;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function bulkRowHasContent(array $row): bool
    {
        return match ($row['type']) {
            'indikator_tujuan', 'indikator_sasaran', 'indikator_program' => filled($row['indikator'] ?? null),
            'program' => filled($row['program_pemerintahan_id'] ?? null) || filled($row['uraian'] ?? null),
            'target_tujuan', 'target_sasaran', 'target_program' => filled($row['target'] ?? null)
                || filled($row['target_text'] ?? null)
                || filled($row['existing_target_id'] ?? null),
            'program_opd' => filled($row['opd_id'] ?? null),
            default => filled($row['uraian'] ?? null),
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
                    'rpjmd_visi_id' => filled($data['parent_id'] ?? null) ? $this->visi($rpjmd, $data['parent_id'])->id : $tujuan->rpjmd_visi_id,
                    'rpjmd_misi_id' => filled($data['parent_id'] ?? null) ? null : $tujuan->rpjmd_misi_id,
                    'kode' => $data['kode'] ?? null,
                    'tujuan' => $this->requiredText($data, 'uraian', 'Tujuan daerah wajib diisi.'),
                    'urutan' => $data['urutan'] ?? 1,
                ]);

                $this->syncTujuanMisi($rpjmd, $tujuan->refresh(), $data);
            }),
            'indikator_tujuan' => tap($this->indikatorTujuan($rpjmd, $id), function (IndikatorTujuanDaerah $indikator) use ($rpjmd, $data) {
                $indikator->update([
                    'tujuan_daerah_id' => filled($data['parent_id'] ?? null) ? $this->tujuan($rpjmd, $data['parent_id'])->id : $indikator->tujuan_daerah_id,
                    ...$this->indicatorPayload($data, 'Indikator tujuan wajib diisi.'),
                ]);
            }),
            'target_tujuan' => tap($this->findNode($rpjmd, $type, $id), function (TargetIndikatorTujuanDaerah $target) use ($rpjmd, $data) {
                $periodeTahunId = filled($data['periode_tahun_id'] ?? null) ? (int) $data['periode_tahun_id'] : $target->periode_tahun_id;

                $target->update([
                    'indikator_tujuan_daerah_id' => filled($data['parent_id'] ?? null) ? $this->indikatorTujuan($rpjmd, $data['parent_id'])->id : $target->indikator_tujuan_daerah_id,
                    'periode_tahun_id' => $periodeTahunId,
                    'jenis_target' => $this->jenisTarget($rpjmd, $periodeTahunId),
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

                $this->syncSasaranIndikatorTujuan($rpjmd, $sasaran->refresh(), $data);
            }),
            'indikator_sasaran' => tap($this->indikatorSasaran($rpjmd, $id), function (IndikatorSasaranDaerah $indikator) use ($rpjmd, $data) {
                $indikator->update([
                    'sasaran_daerah_id' => filled($data['parent_id'] ?? null) ? $this->sasaran($rpjmd, $data['parent_id'])->id : $indikator->sasaran_daerah_id,
                    ...$this->indicatorPayload($data, 'Indikator sasaran wajib diisi.'),
                ]);
            }),
            'target_sasaran' => tap($this->findNode($rpjmd, $type, $id), function (TargetIndikatorSasaranDaerah $target) use ($rpjmd, $data) {
                $periodeTahunId = filled($data['periode_tahun_id'] ?? null) ? (int) $data['periode_tahun_id'] : $target->periode_tahun_id;

                $target->update([
                    'indikator_sasaran_daerah_id' => filled($data['parent_id'] ?? null) ? $this->indikatorSasaran($rpjmd, $data['parent_id'])->id : $target->indikator_sasaran_daerah_id,
                    'periode_tahun_id' => $periodeTahunId,
                    'jenis_target' => $this->jenisTarget($rpjmd, $periodeTahunId),
                    'target' => $data['target'] ?? null,
                    'target_text' => $data['target_text'] ?? null,
                ]);
            }),
            'program' => tap($this->program($rpjmd, $id), function (ProgramRpjmd $program) use ($rpjmd, $data) {
                $sasaran = filled($data['parent_id'] ?? null)
                    ? $this->sasaran($rpjmd, $data['parent_id'])
                    : $program->sasaran;
                $strategi = $this->optionalStrategiProgram($data, $program->strategi_daerah_id);
                $programPemerintahan = $this->optionalProgramPemerintahan($data, $program->program_pemerintahan_id, $rpjmd);

                $program->update([
                    'strategi_daerah_id' => $strategi?->id,
                    'sasaran_daerah_id' => $sasaran?->id ?? $program->sasaran_daerah_id,
                    'indikator_sasaran_daerah_id' => null,
                    'program_pemerintahan_id' => $programPemerintahan?->id,
                    'urusan_pemerintahan_id' => $programPemerintahan?->bidangUrusan?->urusan_pemerintahan_id ?? ($data['urusan_pemerintahan_id'] ?? null),
                    'kode' => $programPemerintahan?->kode ?? ($data['kode'] ?? null),
                    'nama' => $programPemerintahan?->nama ?? $this->requiredText($data, 'uraian', 'Nama program wajib diisi.'),
                    'is_penanggung_jawab_manual' => $this->penanggungJawabManual($data, (bool) $program->is_penanggung_jawab_manual),
                    'urutan' => $data['urutan'] ?? 1,
                ]);

                $this->syncProgramPemerintahanReferences($program, $programPemerintahan, $data);
                $program = $program->refresh();
                $this->syncProgramOpdPenanggungJawab($program, $data);
                $this->pengampuResolver->syncForProgramIndicators($program);
            }),
            'indikator_program' => tap($this->indikatorProgram($rpjmd, $id), function (IndikatorProgramRpjmd $indikator) use ($rpjmd, $data) {
                $indikator->update([
                    'program_rpjmd_id' => filled($data['parent_id'] ?? null) ? $this->program($rpjmd, $data['parent_id'])->id : $indikator->program_rpjmd_id,
                    ...$this->indicatorProgramPayload($data, 'Indikator program wajib diisi.'),
                ]);

                $this->syncIndikatorProgramOpdPengampu($indikator->refresh(), $data);
            }),
            'target_program' => tap($this->findNode($rpjmd, $type, $id), function (TargetIndikatorProgramRpjmd $target) use ($rpjmd, $data) {
                $periodeTahunId = filled($data['periode_tahun_id'] ?? null) ? (int) $data['periode_tahun_id'] : $target->periode_tahun_id;

                $target->update([
                    'indikator_program_rpjmd_id' => filled($data['parent_id'] ?? null) ? $this->indikatorProgram($rpjmd, $data['parent_id'])->id : $target->indikator_program_rpjmd_id,
                    'periode_tahun_id' => $periodeTahunId,
                    'jenis_target' => $this->jenisTarget($rpjmd, $periodeTahunId),
                    'target' => $data['target'] ?? null,
                    'target_text' => $data['target_text'] ?? null,
                ]);
            }),
            'program_opd' => tap($this->findNode($rpjmd, $type, $id), function (ProgramRpjmdOpdPenanggungJawab $pivot) use ($rpjmd, $data) {
                $program = filled($data['parent_id'] ?? null)
                    ? $this->program($rpjmd, $data['parent_id'])
                    : $pivot->program;

                $program->update(['is_penanggung_jawab_manual' => true]);

                $pivot->update([
                    'program_rpjmd_id' => $program->id,
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

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function indicatorPayload(array $data, string $requiredMessage): array
    {
        return [
            'satuan_indikator_id' => $data['satuan_indikator_id'] ?? null,
            'opd_id' => $data['opd_id'] ?? null,
            'kode' => $data['kode'] ?? null,
            'indikator' => $this->requiredText($data, 'indikator', $requiredMessage),
            'definisi_operasional' => $data['definisi_operasional'] ?? null,
            'alasan_pemilihan' => $data['alasan_pemilihan'] ?? null,
            'formulasi_pengukuran' => $data['formulasi_pengukuran'] ?? null,
            'tipe_perhitungan' => $data['tipe_perhitungan'] ?? 'non_kumulatif',
            'sumber_data' => $data['sumber_data'] ?? null,
            'urutan' => $data['urutan'] ?? 1,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function indicatorProgramPayload(array $data, string $requiredMessage): array
    {
        $payload = $this->indicatorPayload($data, $requiredMessage);
        $cakupanPengampu = in_array($data['cakupan_pengampu'] ?? null, ['opd_tertentu', 'semua_opd'], true)
            ? $data['cakupan_pengampu']
            : 'opd_tertentu';

        return [
            ...$payload,
            'cakupan_pengampu' => $cakupanPengampu,
            'opd_id' => null,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function syncIndikatorProgramOpdPengampu(IndikatorProgramRpjmd $indikator, array $data): void
    {
        $this->pengampuResolver->syncForIndikator(
            $indikator,
            array_key_exists('opd_ids', $data) ? $this->normalizeIds($data['opd_ids'] ?? []) : null,
            $data['cakupan_pengampu'] ?? null,
        );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function syncTujuanMisi(Rpjmd $rpjmd, TujuanDaerah $tujuan, array $data): void
    {
        if (! array_key_exists('misi_ids', $data)) {
            return;
        }

        $ids = $this->validMisiIds($rpjmd, $tujuan, $data['misi_ids'] ?? []);

        $tujuan->misiTerkait()->sync($this->orderedSyncPayload($ids));
        $tujuan->forceFill(['rpjmd_misi_id' => $ids[0] ?? null])->save();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function syncSasaranIndikatorTujuan(Rpjmd $rpjmd, SasaranDaerah $sasaran, array $data): void
    {
        if (! array_key_exists('indikator_tujuan_ids', $data)) {
            return;
        }

        $ids = $this->validIndikatorTujuanIds($rpjmd, $sasaran, $data['indikator_tujuan_ids'] ?? []);

        $sasaran->indikatorTujuanTerkait()->sync($ids);
    }

    /**
     * @return array<int, int>
     */
    private function validMisiIds(Rpjmd $rpjmd, TujuanDaerah $tujuan, mixed $rawIds): array
    {
        $ids = $this->normalizeIds($rawIds);

        if ($ids === []) {
            return [];
        }

        $validIds = RpjmdMisi::query()
            ->where('rpjmd_id', $rpjmd->id)
            ->when($tujuan->rpjmd_visi_id, fn ($query) => $query->where('rpjmd_visi_id', $tujuan->rpjmd_visi_id))
            ->whereIn('id', $ids)
            ->pluck('id')
            ->all();

        if (count($validIds) !== count($ids)) {
            throw ValidationException::withMessages([
                'misi_ids' => 'Misi terkait harus berada pada visi dan RPJMD yang sama.',
            ]);
        }

        return array_values(array_filter($ids, fn (int $id) => in_array($id, $validIds, true)));
    }

    /**
     * @return array<int, int>
     */
    private function validIndikatorTujuanIds(Rpjmd $rpjmd, SasaranDaerah $sasaran, mixed $rawIds): array
    {
        $ids = $this->normalizeIds($rawIds);

        if ($ids === []) {
            return [];
        }

        $validIds = IndikatorTujuanDaerah::query()
            ->where('tujuan_daerah_id', $sasaran->tujuan_daerah_id)
            ->whereHas('tujuan', fn ($query) => $query->forRpjmd($rpjmd->id))
            ->whereIn('id', $ids)
            ->pluck('id')
            ->all();

        if (count($validIds) !== count($ids)) {
            throw ValidationException::withMessages([
                'indikator_tujuan_ids' => 'Indikator tujuan terkait harus berada pada tujuan daerah yang sama.',
            ]);
        }

        return array_values(array_filter($ids, fn (int $id) => in_array($id, $validIds, true)));
    }

    /**
     * @return array<int, int>
     */
    private function normalizeIds(mixed $rawIds): array
    {
        return collect(is_array($rawIds) ? $rawIds : [])
            ->filter(fn ($id) => filled($id))
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  array<int, int>  $ids
     * @return array<int, array<string, int>>
     */
    private function orderedSyncPayload(array $ids): array
    {
        return collect($ids)
            ->values()
            ->mapWithKeys(fn (int $id, int $index) => [$id => ['urutan' => $index + 1]])
            ->all();
    }

    private function requiredInt(array $data, string $key, string $message): int
    {
        throw_if(blank($data[$key] ?? null), ValidationException::withMessages([$key => $message]));

        return (int) $data[$key];
    }

    private function jenisTarget(Rpjmd $rpjmd, int $periodeTahunId): string
    {
        $tahun = PeriodeTahun::query()->whereKey($periodeTahunId)->value('tahun');

        return $tahun && (int) $tahun > (int) $rpjmd->tahun_akhir ? 'prakiraan_maju' : 'tahunan';
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
        return TujuanDaerah::forRpjmd($rpjmd->id)->findOrFail($id);
    }

    private function indikatorTujuan(Rpjmd $rpjmd, mixed $id): IndikatorTujuanDaerah
    {
        return IndikatorTujuanDaerah::whereHas('tujuan', fn ($query) => $query->forRpjmd($rpjmd->id))->findOrFail($id);
    }

    private function sasaran(Rpjmd $rpjmd, mixed $id): SasaranDaerah
    {
        return SasaranDaerah::whereHas('tujuan', fn ($query) => $query->forRpjmd($rpjmd->id))->findOrFail($id);
    }

    private function indikatorSasaran(Rpjmd $rpjmd, mixed $id): IndikatorSasaranDaerah
    {
        return IndikatorSasaranDaerah::whereHas('sasaran.tujuan', fn ($query) => $query->forRpjmd($rpjmd->id))->findOrFail($id);
    }

    private function optionalStrategiProgram(
        array $data,
        mixed $fallbackId = null,
    ): ?StrategiDaerah {
        $id = $data['strategi_daerah_id'] ?? $fallbackId;

        if (blank($id)) {
            return null;
        }

        return StrategiDaerah::query()->findOrFail($id);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function syncProgramPemerintahanReferences(
        ProgramRpjmd $program,
        ?ProgramPemerintahan $programPemerintahan,
        array $data,
    ): void {
        $ids = $this->programPemerintahanReferenceIds($programPemerintahan, $data['program_pemerintahan_ids'] ?? null);

        $program->programPemerintahanReferences()->sync($ids);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function syncProgramOpdPenanggungJawab(ProgramRpjmd $program, array $data): void
    {
        $opdIds = $program->is_penanggung_jawab_manual
            ? $this->normalizeIds($data['opd_ids'] ?? [])
            : $this->pengampuResolver->resolveOpdPenanggungJawabIds($program);

        $this->pengampuResolver->syncProgramPenanggungJawab($program, $opdIds);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function penanggungJawabManual(array $data, bool $fallback = false): bool
    {
        if (! array_key_exists('is_penanggung_jawab_manual', $data)) {
            return $fallback;
        }

        return filter_var($data['is_penanggung_jawab_manual'], FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * @return array<int, int>
     */
    private function programPemerintahanReferenceIds(?ProgramPemerintahan $programPemerintahan, mixed $rawIds): array
    {
        $ids = $this->normalizeIds($rawIds);

        if ($ids !== []) {
            $validIds = ProgramPemerintahan::query()
                ->whereIn('id', $ids)
                ->when($programPemerintahan, fn ($query) => $query
                    ->where('tahun_awal', $programPemerintahan->tahun_awal)
                    ->where('tahun_akhir', $programPemerintahan->tahun_akhir))
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();

            if (count($validIds) !== count($ids)) {
                throw ValidationException::withMessages([
                    'program_pemerintahan_ids' => 'Daftar program master tidak valid.',
                ]);
            }

            return array_values(array_filter($ids, fn (int $id) => in_array($id, $validIds, true)));
        }

        if (! $programPemerintahan) {
            return [];
        }

        $name = $this->normalizeProgramName($programPemerintahan->nama);

        return ProgramPemerintahan::query()
            ->where('tahun_awal', $programPemerintahan->tahun_awal)
            ->where('tahun_akhir', $programPemerintahan->tahun_akhir)
            ->where('status', 'active')
            ->orderBy('kode')
            ->get(['id', 'nama'])
            ->filter(fn (ProgramPemerintahan $program) => $this->normalizeProgramName($program->nama) === $name)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }

    private function normalizeProgramName(?string $name): string
    {
        return strtolower((string) preg_replace('/\s+/', ' ', trim((string) $name)));
    }

    private function optionalProgramPemerintahan(
        array $data,
        mixed $fallbackId = null,
        ?Rpjmd $rpjmd = null,
    ): ?ProgramPemerintahan {
        $id = $data['program_pemerintahan_id'] ?? $fallbackId;

        if (blank($id)) {
            return null;
        }

        return ProgramPemerintahan::query()
            ->with('bidangUrusan:id,urusan_pemerintahan_id')
            ->when($rpjmd, fn ($query) => $query
                ->where('tahun_awal', $rpjmd->tahun_awal)
                ->where('tahun_akhir', $rpjmd->tahun_akhir))
            ->findOrFail($id);
    }

    private function program(Rpjmd $rpjmd, mixed $id): ProgramRpjmd
    {
        return ProgramRpjmd::query()->forRpjmd($rpjmd->id)->findOrFail($id);
    }

    private function indikatorProgram(Rpjmd $rpjmd, mixed $id): IndikatorProgramRpjmd
    {
        return IndikatorProgramRpjmd::whereHas('program', fn ($query) => $query->forRpjmd($rpjmd->id))->findOrFail($id);
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
            'program' => $this->program($rpjmd, $id),
            'indikator_program' => $this->indikatorProgram($rpjmd, $id),
            'target_tujuan' => TargetIndikatorTujuanDaerah::whereHas('indikator.tujuan', fn ($query) => $query->forRpjmd($rpjmd->id))->findOrFail($id),
            'target_sasaran' => TargetIndikatorSasaranDaerah::whereHas('indikator.sasaran.tujuan', fn ($query) => $query->forRpjmd($rpjmd->id))->findOrFail($id),
            'target_program' => TargetIndikatorProgramRpjmd::whereHas('indikator.program', fn ($query) => $query->forRpjmd($rpjmd->id))->findOrFail($id),
            'program_opd' => ProgramRpjmdOpdPenanggungJawab::whereHas('program', fn ($query) => $query->forRpjmd($rpjmd->id))->findOrFail($id),
            default => abort(404),
        };
    }
}
