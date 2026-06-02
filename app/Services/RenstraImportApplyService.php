<?php

namespace App\Services;

use App\Models\ImportBatch;
use App\Models\ImportBatchRow;
use App\Models\IndikatorOpdProgram;
use App\Models\IndikatorProgramRpjmd;
use App\Models\IndikatorSasaranDaerah;
use App\Models\IndikatorSasaranOpd;
use App\Models\IndikatorSubKegiatan;
use App\Models\IndikatorTujuanDaerah;
use App\Models\IndikatorTujuanOpd;
use App\Models\Opd;
use App\Models\OpdKegiatan;
use App\Models\OpdProgram;
use App\Models\OpdSubKegiatan;
use App\Models\PeriodeTahun;
use App\Models\ProgramRpjmd;
use App\Models\RenstraOpd;
use App\Models\Rpjmd;
use App\Models\SasaranDaerah;
use App\Models\SasaranOpd;
use App\Models\TargetIndikatorOpdProgram;
use App\Models\TargetIndikatorSasaranOpd;
use App\Models\TargetIndikatorTujuanOpd;
use App\Models\TargetTriwulanIndikator;
use App\Models\TujuanDaerah;
use App\Models\TujuanOpd;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class RenstraImportApplyService
{
    /**
     * @var array<string, Model>
     */
    private array $context = [];

    public function apply(ImportBatch $batch, User $user): ImportBatch
    {
        if ($batch->module !== 'renstra_opd') {
            throw ValidationException::withMessages([
                'import_batch_id' => 'Batch import tidak sesuai modul Renstra OPD.',
            ]);
        }

        if ($batch->status !== 'previewed') {
            throw ValidationException::withMessages([
                'import_batch_id' => 'Batch import hanya dapat diterapkan setelah status previewed.',
            ]);
        }

        $summary = [
            'imported_rows' => 0,
            'failed_rows' => 0,
            'skipped_rows' => 0,
        ];
        $results = [];

        $batch->update(['status' => 'processing', 'error_message' => null]);

        DB::beginTransaction();

        try {
            $batch->rows()->orderBy('row_number')->get()->each(function (ImportBatchRow $row) use ($user, &$summary, &$results) {
                if ((bool) ($row->normalized_data['is_header'] ?? false)) {
                    $results[$row->id] = ['status' => 'skipped', 'result' => ['reason' => 'header'], 'error' => null];
                    $summary['skipped_rows']++;

                    return;
                }

                try {
                    $result = $this->applyRow($row->normalized_data['mapped'] ?? [], $user);
                    $results[$row->id] = ['status' => 'imported', 'result' => $result, 'error' => null];
                    $summary['imported_rows']++;
                } catch (Throwable $exception) {
                    $results[$row->id] = ['status' => 'failed', 'result' => ['error' => $exception->getMessage()], 'error' => $exception->getMessage()];
                    $summary['failed_rows']++;
                }
            });

            if ($summary['failed_rows'] > 0 || $summary['imported_rows'] === 0) {
                DB::rollBack();

                $this->persistRowResults($batch, $results);
                $batch->update([
                    'status' => 'failed',
                    'error_message' => $summary['imported_rows'] === 0
                        ? 'Tidak ada baris yang berhasil divalidasi untuk import Renstra OPD.'
                        : 'Import Renstra OPD dibatalkan. Perbaiki baris gagal lalu upload ulang agar data tidak masuk sebagian.',
                    'metadata' => [
                        ...($batch->metadata ?? []),
                        'applied' => [
                            ...$summary,
                            'rolled_back' => true,
                            'applied_by' => $user->id,
                            'applied_at' => now()->toISOString(),
                        ],
                    ],
                ]);

                return $batch->fresh(['uploadedBy:id,name', 'rows']);
            }

            DB::commit();
        } catch (Throwable $exception) {
            DB::rollBack();

            $batch->update([
                'status' => 'failed',
                'error_message' => $exception->getMessage(),
            ]);

            return $batch->fresh(['uploadedBy:id,name', 'rows']);
        }

        $this->persistRowResults($batch, $results);
        $batch->update([
            'status' => 'imported',
            'metadata' => [
                ...($batch->metadata ?? []),
                'applied' => [
                    ...$summary,
                    'rolled_back' => false,
                    'applied_by' => $user->id,
                    'applied_at' => now()->toISOString(),
                    'renstra_opd_id' => $this->context['renstra']->getKey() ?? null,
                ],
            ],
        ]);

        return $batch->fresh(['uploadedBy:id,name', 'rows']);
    }

    /**
     * @param  array<string, mixed>  $mapped
     * @return array<string, mixed>
     */
    private function applyRow(array $mapped, User $user): array
    {
        $level = $this->normalizeLevel($this->text($mapped, ['level', 'jenis', 'tipe', 'node_type']));

        return match ($level) {
            'renstra' => $this->applyRenstra($mapped, $user),
            'tujuan' => $this->applyTujuan($mapped, $user),
            'indikator_tujuan' => $this->applyIndikatorTujuan($mapped, $user),
            'target_tujuan' => $this->applyTargetTujuan($mapped, $user),
            'sasaran' => $this->applySasaran($mapped, $user),
            'indikator_sasaran' => $this->applyIndikatorSasaran($mapped, $user),
            'target_sasaran' => $this->applyTargetSasaran($mapped, $user),
            'program' => $this->applyProgram($mapped, $user),
            'indikator_program' => $this->applyIndikatorProgram($mapped, $user),
            'target_program' => $this->applyTargetProgram($mapped, $user),
            'kegiatan' => $this->applyKegiatan($mapped, $user),
            'sub_kegiatan' => $this->applySubKegiatan($mapped, $user),
            'indikator_sub_kegiatan' => $this->applyIndikatorSubKegiatan($mapped, $user),
            'target_triwulan' => $this->applyTargetTriwulan($mapped, $user),
            default => throw new RuntimeException('Level import Renstra tidak dikenali. Gunakan level renstra, tujuan, indikator_tujuan, target_tujuan, sasaran, indikator_sasaran, target_sasaran, program, indikator_program, target_program, kegiatan, sub_kegiatan, indikator_sub_kegiatan, atau target_triwulan.'),
        };
    }

    /**
     * @param  array<string, mixed>  $mapped
     * @return array<string, mixed>
     */
    private function applyRenstra(array $mapped, User $user): array
    {
        $renstra = $this->upsertRenstra($mapped);
        $this->ensureCanUpdate($user, $renstra);
        $this->setContext('renstra', $renstra);

        return $this->result($renstra);
    }

    private function applyTujuan(array $mapped, User $user): array
    {
        $renstra = $this->resolveRenstra($mapped, $user);
        $text = $this->requiredText($mapped, ['tujuan', 'uraian', 'nama'], 'Tujuan OPD');
        $kode = $this->text($mapped, ['kode', 'kode_tujuan']);
        $identity = $kode ? ['renstra_opd_id' => $renstra->id, 'kode' => $kode] : ['renstra_opd_id' => $renstra->id, 'tujuan' => $text];

        $tujuan = TujuanOpd::updateOrCreate($identity, [
            'tujuan_daerah_id' => $this->resolveTujuanDaerah($mapped, $renstra)?->id,
            'tujuan' => $text,
            'urutan' => $this->order($mapped),
        ]);

        $this->setContext('tujuan', $tujuan);

        return $this->result($tujuan);
    }

    private function applyIndikatorTujuan(array $mapped, User $user): array
    {
        $this->resolveRenstra($mapped, $user);
        /** @var TujuanOpd $tujuan */
        $tujuan = $this->requiredContext('tujuan', 'Indikator tujuan OPD harus berada setelah baris tujuan.');
        $text = $this->requiredText($mapped, ['indikator_tujuan', 'indikator', 'uraian', 'nama'], 'Indikator tujuan OPD');
        $kode = $this->text($mapped, ['kode', 'kode_indikator', 'kode_indikator_tujuan']);
        $identity = $kode ? ['tujuan_opd_id' => $tujuan->id, 'kode' => $kode] : ['tujuan_opd_id' => $tujuan->id, 'indikator' => $text];

        $indikator = IndikatorTujuanOpd::updateOrCreate($identity, [
            'indikator_tujuan_daerah_id' => $this->resolveIndikatorTujuanDaerah($mapped, $tujuan->renstra)?->id,
            'indikator' => $text,
            'tipe_indikator' => $this->tipeIndikator($mapped),
            'formula' => $this->text($mapped, ['formula', 'rumus']),
            'sumber_data' => $this->text($mapped, ['sumber_data']),
            'urutan' => $this->order($mapped),
        ]);

        $this->setContext('indikator_tujuan', $indikator);
        $this->maybeApplyTargetTujuan($mapped, $indikator);

        return $this->result($indikator);
    }

    private function applyTargetTujuan(array $mapped, User $user): array
    {
        $this->resolveRenstra($mapped, $user);
        /** @var IndikatorTujuanOpd $indikator */
        $indikator = $this->requiredContext('indikator_tujuan', 'Target tujuan harus berada setelah baris indikator tujuan OPD.');

        return $this->result($this->upsertTargetTujuan($mapped, $indikator));
    }

    private function applySasaran(array $mapped, User $user): array
    {
        $this->resolveRenstra($mapped, $user);
        /** @var TujuanOpd $tujuan */
        $tujuan = $this->requiredContext('tujuan', 'Sasaran OPD harus berada setelah baris tujuan.');
        $text = $this->requiredText($mapped, ['sasaran', 'uraian', 'nama'], 'Sasaran OPD');
        $kode = $this->text($mapped, ['kode', 'kode_sasaran']);
        $identity = $kode ? ['tujuan_opd_id' => $tujuan->id, 'kode' => $kode] : ['tujuan_opd_id' => $tujuan->id, 'sasaran' => $text];

        $sasaran = SasaranOpd::updateOrCreate($identity, [
            'sasaran_daerah_id' => $this->resolveSasaranDaerah($mapped, $tujuan->renstra)?->id,
            'sasaran' => $text,
            'urutan' => $this->order($mapped),
        ]);

        $this->setContext('sasaran', $sasaran);

        return $this->result($sasaran);
    }

    private function applyIndikatorSasaran(array $mapped, User $user): array
    {
        $this->resolveRenstra($mapped, $user);
        /** @var SasaranOpd $sasaran */
        $sasaran = $this->requiredContext('sasaran', 'Indikator sasaran OPD harus berada setelah baris sasaran.');
        $text = $this->requiredText($mapped, ['indikator_sasaran', 'indikator', 'uraian', 'nama'], 'Indikator sasaran OPD');
        $kode = $this->text($mapped, ['kode', 'kode_indikator', 'kode_indikator_sasaran']);
        $identity = $kode ? ['sasaran_opd_id' => $sasaran->id, 'kode' => $kode] : ['sasaran_opd_id' => $sasaran->id, 'indikator' => $text];

        $indikator = IndikatorSasaranOpd::updateOrCreate($identity, [
            'indikator_sasaran_daerah_id' => $this->resolveIndikatorSasaranDaerah($mapped, $sasaran->tujuan->renstra)?->id,
            'indikator' => $text,
            'tipe_indikator' => $this->tipeIndikator($mapped),
            'formula' => $this->text($mapped, ['formula', 'rumus']),
            'sumber_data' => $this->text($mapped, ['sumber_data']),
            'urutan' => $this->order($mapped),
        ]);

        $this->setContext('indikator_sasaran', $indikator);
        $this->maybeApplyTargetSasaran($mapped, $indikator);

        return $this->result($indikator);
    }

    private function applyTargetSasaran(array $mapped, User $user): array
    {
        $this->resolveRenstra($mapped, $user);
        /** @var IndikatorSasaranOpd $indikator */
        $indikator = $this->requiredContext('indikator_sasaran', 'Target sasaran harus berada setelah baris indikator sasaran OPD.');

        return $this->result($this->upsertTargetSasaran($mapped, $indikator));
    }

    private function applyProgram(array $mapped, User $user): array
    {
        $this->resolveRenstra($mapped, $user);
        /** @var SasaranOpd $sasaran */
        $sasaran = $this->requiredContext('sasaran', 'Program OPD harus berada setelah baris sasaran.');
        $text = $this->requiredText($mapped, ['program', 'nama_program', 'uraian', 'nama'], 'Program OPD');
        $kode = $this->text($mapped, ['kode', 'kode_program']);
        $identity = $kode ? ['renstra_opd_id' => $sasaran->tujuan->renstra_opd_id, 'kode' => $kode] : ['renstra_opd_id' => $sasaran->tujuan->renstra_opd_id, 'nama' => $text];

        $program = OpdProgram::updateOrCreate($identity, [
            'sasaran_opd_id' => $sasaran->id,
            'program_rpjmd_id' => $this->resolveProgramRpjmd($mapped, $sasaran->tujuan->renstra)?->id,
            'nama' => $text,
            'pagu_indikatif' => $this->number($mapped, ['pagu', 'pagu_program', 'pagu_indikatif']),
            'status' => 'draft',
            'urutan' => $this->order($mapped),
        ]);

        $this->setContext('program', $program);

        return $this->result($program);
    }

    private function applyIndikatorProgram(array $mapped, User $user): array
    {
        $this->resolveRenstra($mapped, $user);
        /** @var OpdProgram $program */
        $program = $this->requiredContext('program', 'Indikator program OPD harus berada setelah baris program.');
        $text = $this->requiredText($mapped, ['indikator_program', 'indikator', 'uraian', 'nama'], 'Indikator program OPD');
        $kode = $this->text($mapped, ['kode', 'kode_indikator', 'kode_indikator_program']);
        $identity = $kode ? ['opd_program_id' => $program->id, 'kode' => $kode] : ['opd_program_id' => $program->id, 'indikator' => $text];

        $indikator = IndikatorOpdProgram::updateOrCreate($identity, [
            'indikator_program_rpjmd_id' => $this->resolveIndikatorProgramRpjmd($mapped, $program->renstra)?->id,
            'indikator' => $text,
            'tipe_indikator' => $this->tipeIndikator($mapped),
            'formula' => $this->text($mapped, ['formula', 'rumus']),
            'sumber_data' => $this->text($mapped, ['sumber_data']),
            'urutan' => $this->order($mapped),
        ]);

        $this->setContext('indikator_program', $indikator);
        $this->maybeApplyTargetProgram($mapped, $indikator);

        return $this->result($indikator);
    }

    private function applyTargetProgram(array $mapped, User $user): array
    {
        $this->resolveRenstra($mapped, $user);
        /** @var IndikatorOpdProgram $indikator */
        $indikator = $this->requiredContext('indikator_program', 'Target program harus berada setelah baris indikator program OPD.');

        return $this->result($this->upsertTargetProgram($mapped, $indikator));
    }

    private function applyKegiatan(array $mapped, User $user): array
    {
        $this->resolveRenstra($mapped, $user);
        /** @var OpdProgram $program */
        $program = $this->requiredContext('program', 'Kegiatan OPD harus berada setelah baris program.');
        $text = $this->requiredText($mapped, ['kegiatan', 'nama_kegiatan', 'uraian', 'nama'], 'Kegiatan OPD');
        $kode = $this->text($mapped, ['kode', 'kode_kegiatan']);

        $kegiatan = OpdKegiatan::updateOrCreate($kode ? ['opd_program_id' => $program->id, 'kode' => $kode] : ['opd_program_id' => $program->id, 'nama' => $text], [
            'nama' => $text,
            'pagu_indikatif' => $this->number($mapped, ['pagu', 'pagu_kegiatan', 'pagu_indikatif']),
            'urutan' => $this->order($mapped),
        ]);

        $this->setContext('kegiatan', $kegiatan);

        return $this->result($kegiatan);
    }

    private function applySubKegiatan(array $mapped, User $user): array
    {
        $this->resolveRenstra($mapped, $user);
        /** @var OpdKegiatan $kegiatan */
        $kegiatan = $this->requiredContext('kegiatan', 'Sub kegiatan OPD harus berada setelah baris kegiatan.');
        $text = $this->requiredText($mapped, ['sub_kegiatan', 'nama_sub_kegiatan', 'uraian', 'nama'], 'Sub kegiatan OPD');
        $kode = $this->text($mapped, ['kode', 'kode_sub_kegiatan']);

        $subKegiatan = OpdSubKegiatan::updateOrCreate($kode ? ['opd_kegiatan_id' => $kegiatan->id, 'kode' => $kode] : ['opd_kegiatan_id' => $kegiatan->id, 'nama' => $text], [
            'nama' => $text,
            'pagu_indikatif' => $this->number($mapped, ['pagu', 'pagu_sub_kegiatan', 'pagu_indikatif']),
            'urutan' => $this->order($mapped),
        ]);

        $this->setContext('sub_kegiatan', $subKegiatan);

        return $this->result($subKegiatan);
    }

    private function applyIndikatorSubKegiatan(array $mapped, User $user): array
    {
        $this->resolveRenstra($mapped, $user);
        /** @var OpdSubKegiatan $subKegiatan */
        $subKegiatan = $this->requiredContext('sub_kegiatan', 'Indikator sub kegiatan harus berada setelah baris sub kegiatan.');
        $text = $this->requiredText($mapped, ['indikator_sub_kegiatan', 'indikator', 'uraian', 'nama'], 'Indikator sub kegiatan');
        $kode = $this->text($mapped, ['kode', 'kode_indikator']);

        $indikator = IndikatorSubKegiatan::updateOrCreate($kode ? ['opd_sub_kegiatan_id' => $subKegiatan->id, 'kode' => $kode] : ['opd_sub_kegiatan_id' => $subKegiatan->id, 'indikator' => $text], [
            'indikator' => $text,
            'tipe_indikator' => $this->tipeIndikator($mapped),
            'formula' => $this->text($mapped, ['formula', 'rumus']),
            'sumber_data' => $this->text($mapped, ['sumber_data']),
            'urutan' => $this->order($mapped),
        ]);

        $this->setContext('indikator_sub_kegiatan', $indikator);

        return $this->result($indikator);
    }

    private function applyTargetTriwulan(array $mapped, User $user): array
    {
        $this->resolveRenstra($mapped, $user);
        [$table, $indicator] = $this->currentIndicatorForTriwulan();
        $periode = $this->periodeTarget($mapped);
        $triwulan = $this->triwulan($mapped);

        $target = TargetTriwulanIndikator::updateOrCreate([
            'related_table' => $table,
            'related_id' => $indicator->getKey(),
            'periode_tahun_id' => $periode->id,
            'triwulan' => $triwulan,
        ], [
            'target_text' => $this->text($mapped, ['target_text', 'target_teks']),
            'target_angka' => $this->number($mapped, ['target', 'target_angka']),
            'target_anggaran' => $this->number($mapped, ['target_anggaran', 'anggaran']),
        ]);

        return $this->result($target);
    }

    /**
     * @param  array<string, mixed>  $mapped
     */
    private function upsertRenstra(array $mapped): RenstraOpd
    {
        if ($id = $this->text($mapped, ['renstra_opd_id', 'renstra_id'])) {
            $renstra = RenstraOpd::find($id);

            if (! $renstra) {
                throw new RuntimeException("Renstra OPD ID {$id} tidak ditemukan.");
            }

            return $renstra;
        }

        $opd = $this->resolveOpd($mapped);
        $rpjmd = $this->resolveRpjmd($mapped);
        $judul = $this->requiredText($mapped, ['renstra_judul', 'judul', 'nama'], 'Judul Renstra OPD');
        $tahunAwal = $this->integer($mapped, ['tahun_awal', 'awal_periode']) ?? $rpjmd->tahun_awal;
        $tahunAkhir = $this->integer($mapped, ['tahun_akhir', 'akhir_periode']) ?? $rpjmd->tahun_akhir;
        $periode = PeriodeTahun::query()->where('tahun', $tahunAwal)->first();

        return RenstraOpd::updateOrCreate([
            'opd_id' => $opd->id,
            'rpjmd_id' => $rpjmd->id,
            'tahun_awal' => $tahunAwal,
            'tahun_akhir' => $tahunAkhir,
        ], [
            'periode_tahun_id' => $periode?->id,
            'judul' => $judul,
            'nomor_dokumen' => $this->text($mapped, ['nomor_dokumen', 'nomor']),
            'status' => $this->status($mapped, 'draft'),
            'keterangan' => $this->text($mapped, ['keterangan', 'catatan']),
        ]);
    }

    /**
     * @param  array<string, mixed>  $mapped
     */
    private function resolveRenstra(array $mapped, User $user): RenstraOpd
    {
        if (($this->context['renstra'] ?? null) instanceof RenstraOpd) {
            $renstra = $this->context['renstra'];
        } else {
            $renstra = $this->upsertRenstra($mapped);
            $this->setContext('renstra', $renstra);
        }

        $this->ensureCanUpdate($user, $renstra);

        return $renstra;
    }

    private function ensureCanUpdate(User $user, RenstraOpd $renstra): void
    {
        if (! Gate::forUser($user)->allows('update', $renstra)) {
            throw new RuntimeException('User tidak berwenang mengubah Renstra OPD ini atau status dokumen tidak dalam tahap revisi/draft.');
        }
    }

    /**
     * @param  array<string, mixed>  $mapped
     */
    private function resolveOpd(array $mapped): Opd
    {
        $query = Opd::query();

        if ($id = $this->text($mapped, ['opd_id'])) {
            $query->whereKey($id);
        } elseif ($kode = $this->text($mapped, ['opd_kode', 'kode_opd'])) {
            $query->where('kode', $kode);
        } elseif ($nama = $this->text($mapped, ['opd', 'opd_nama', 'nama_opd'])) {
            $query->where('nama', 'ilike', $nama)->orWhere('singkatan', 'ilike', $nama);
        } else {
            throw new RuntimeException('Renstra membutuhkan opd_id, opd_kode, atau opd_nama.');
        }

        $opd = $query->first();

        if (! $opd) {
            throw new RuntimeException('OPD tidak ditemukan di master OPD.');
        }

        return $opd;
    }

    /**
     * @param  array<string, mixed>  $mapped
     */
    private function resolveRpjmd(array $mapped): Rpjmd
    {
        if ($id = $this->text($mapped, ['rpjmd_id'])) {
            $rpjmd = Rpjmd::find($id);

            if ($rpjmd) {
                return $rpjmd;
            }
        }

        $judul = $this->text($mapped, ['rpjmd_judul', 'judul_rpjmd']);

        if ($judul) {
            $rpjmd = Rpjmd::query()->where('judul', 'ilike', $judul)->first();

            if ($rpjmd) {
                return $rpjmd;
            }
        }

        throw new RuntimeException('RPJMD referensi tidak ditemukan. Isi rpjmd_id atau rpjmd_judul.');
    }

    private function resolveTujuanDaerah(array $mapped, RenstraOpd $renstra): ?TujuanDaerah
    {
        return $this->referenceByIdOrKode(TujuanDaerah::query()->whereHas('misi', fn ($query) => $query->where('rpjmd_id', $renstra->rpjmd_id)), $mapped, ['tujuan_daerah_id'], ['tujuan_daerah_kode', 'kode_tujuan_daerah']);
    }

    private function resolveIndikatorTujuanDaerah(array $mapped, RenstraOpd $renstra): ?IndikatorTujuanDaerah
    {
        return $this->referenceByIdOrKode(IndikatorTujuanDaerah::query()->whereHas('tujuan.misi', fn ($query) => $query->where('rpjmd_id', $renstra->rpjmd_id)), $mapped, ['indikator_tujuan_daerah_id'], ['indikator_tujuan_daerah_kode']);
    }

    private function resolveSasaranDaerah(array $mapped, RenstraOpd $renstra): ?SasaranDaerah
    {
        return $this->referenceByIdOrKode(SasaranDaerah::query()->whereHas('tujuan.misi', fn ($query) => $query->where('rpjmd_id', $renstra->rpjmd_id)), $mapped, ['sasaran_daerah_id'], ['sasaran_daerah_kode', 'kode_sasaran_daerah']);
    }

    private function resolveIndikatorSasaranDaerah(array $mapped, RenstraOpd $renstra): ?IndikatorSasaranDaerah
    {
        return $this->referenceByIdOrKode(IndikatorSasaranDaerah::query()->whereHas('sasaran.tujuan.misi', fn ($query) => $query->where('rpjmd_id', $renstra->rpjmd_id)), $mapped, ['indikator_sasaran_daerah_id'], ['indikator_sasaran_daerah_kode']);
    }

    private function resolveProgramRpjmd(array $mapped, RenstraOpd $renstra): ?ProgramRpjmd
    {
        return $this->referenceByIdOrKode(ProgramRpjmd::query()->whereHas('strategi.sasaran.tujuan.misi', fn ($query) => $query->where('rpjmd_id', $renstra->rpjmd_id)), $mapped, ['program_rpjmd_id'], ['program_rpjmd_kode', 'kode_program_rpjmd']);
    }

    private function resolveIndikatorProgramRpjmd(array $mapped, RenstraOpd $renstra): ?IndikatorProgramRpjmd
    {
        return $this->referenceByIdOrKode(IndikatorProgramRpjmd::query()->whereHas('program.strategi.sasaran.tujuan.misi', fn ($query) => $query->where('rpjmd_id', $renstra->rpjmd_id)), $mapped, ['indikator_program_rpjmd_id'], ['indikator_program_rpjmd_kode']);
    }

    /**
     * @param  array<string, mixed>  $mapped
     * @param  array<int, string>  $idKeys
     * @param  array<int, string>  $kodeKeys
     */
    private function referenceByIdOrKode($query, array $mapped, array $idKeys, array $kodeKeys): ?Model
    {
        if ($id = $this->text($mapped, $idKeys)) {
            return $query->clone()->whereKey($id)->first();
        }

        if ($kode = $this->text($mapped, $kodeKeys)) {
            return $query->clone()->where('kode', $kode)->first();
        }

        return null;
    }

    private function maybeApplyTargetTujuan(array $mapped, IndikatorTujuanOpd $indikator): void
    {
        if ($this->hasTargetData($mapped)) {
            $this->upsertTargetTujuan($mapped, $indikator);
        }
    }

    private function maybeApplyTargetSasaran(array $mapped, IndikatorSasaranOpd $indikator): void
    {
        if ($this->hasTargetData($mapped)) {
            $this->upsertTargetSasaran($mapped, $indikator);
        }
    }

    private function maybeApplyTargetProgram(array $mapped, IndikatorOpdProgram $indikator): void
    {
        if ($this->hasTargetData($mapped)) {
            $this->upsertTargetProgram($mapped, $indikator);
        }
    }

    private function upsertTargetTujuan(array $mapped, IndikatorTujuanOpd $indikator): TargetIndikatorTujuanOpd
    {
        $periode = $this->periodeTarget($mapped);

        return TargetIndikatorTujuanOpd::updateOrCreate([
            'indikator_tujuan_opd_id' => $indikator->id,
            'periode_tahun_id' => $periode->id,
        ], [
            'target' => $this->number($mapped, ['target', 'target_tujuan', 'target_angka']),
            'target_text' => $this->text($mapped, ['target_text', 'target_teks', 'target_tujuan_text']),
        ]);
    }

    private function upsertTargetSasaran(array $mapped, IndikatorSasaranOpd $indikator): TargetIndikatorSasaranOpd
    {
        $periode = $this->periodeTarget($mapped);

        return TargetIndikatorSasaranOpd::updateOrCreate([
            'indikator_sasaran_opd_id' => $indikator->id,
            'periode_tahun_id' => $periode->id,
        ], [
            'target' => $this->number($mapped, ['target', 'target_sasaran', 'target_angka']),
            'target_text' => $this->text($mapped, ['target_text', 'target_teks', 'target_sasaran_text']),
        ]);
    }

    private function upsertTargetProgram(array $mapped, IndikatorOpdProgram $indikator): TargetIndikatorOpdProgram
    {
        $periode = $this->periodeTarget($mapped);

        return TargetIndikatorOpdProgram::updateOrCreate([
            'indikator_opd_program_id' => $indikator->id,
            'periode_tahun_id' => $periode->id,
        ], [
            'target' => $this->number($mapped, ['target', 'target_program', 'target_angka']),
            'target_text' => $this->text($mapped, ['target_text', 'target_teks', 'target_program_text']),
            'pagu' => $this->number($mapped, ['pagu', 'pagu_program', 'pagu_target']),
        ]);
    }

    private function currentIndicatorForTriwulan(): array
    {
        foreach ([
            'indikator_sub_kegiatan' => 'indikator_sub_kegiatan',
            'indikator_program' => 'indikator_opd_program',
            'indikator_sasaran' => 'indikator_sasaran_opd',
            'indikator_tujuan' => 'indikator_tujuan_opd',
        ] as $contextKey => $table) {
            if (($this->context[$contextKey] ?? null) instanceof Model) {
                return [$table, $this->context[$contextKey]];
            }
        }

        throw new RuntimeException('Target triwulan harus berada setelah baris indikator.');
    }

    private function periodeTarget(array $mapped): PeriodeTahun
    {
        if ($id = $this->text($mapped, ['periode_tahun_id'])) {
            $periode = PeriodeTahun::find($id);

            if ($periode) {
                return $periode;
            }
        }

        $tahun = $this->integer($mapped, ['tahun_target', 'tahun', 'periode_tahun', 'target_tahun']);

        if (! $tahun) {
            throw new RuntimeException('Target indikator membutuhkan tahun_target atau periode_tahun_id.');
        }

        $periode = PeriodeTahun::query()->where('tahun', $tahun)->first();

        if (! $periode) {
            throw new RuntimeException("Periode tahun {$tahun} belum tersedia di master periode.");
        }

        return $periode;
    }

    private function hasTargetData(array $mapped): bool
    {
        return $this->text($mapped, ['target', 'target_angka', 'target_text', 'target_teks', 'target_tujuan', 'target_sasaran', 'target_program', 'pagu']) !== null;
    }

    private function triwulan(array $mapped): string
    {
        $triwulan = str($this->text($mapped, ['triwulan', 'tw']) ?? '')
            ->lower()
            ->replace('triwulan', 'tw')
            ->replace(' ', '')
            ->toString();

        $triwulan = match ($triwulan) {
            '1', 'i', 'twi' => 'tw1',
            '2', 'ii', 'twii' => 'tw2',
            '3', 'iii', 'twiii' => 'tw3',
            '4', 'iv', 'twiv' => 'tw4',
            default => $triwulan,
        };

        if (! in_array($triwulan, ['tw1', 'tw2', 'tw3', 'tw4'], true)) {
            throw new RuntimeException('Target triwulan membutuhkan nilai tw1, tw2, tw3, atau tw4.');
        }

        return $triwulan;
    }

    private function normalizeLevel(?string $level): string
    {
        $level = str((string) $level)
            ->lower()
            ->replaceMatches('/[^a-z0-9]+/', '_')
            ->trim('_')
            ->toString();

        return match ($level) {
            'renstra', 'renstra_opd' => 'renstra',
            'tujuan', 'tujuan_opd' => 'tujuan',
            'indikator_tujuan', 'indikator_tujuan_opd' => 'indikator_tujuan',
            'target_tujuan', 'target_indikator_tujuan', 'target_indikator_tujuan_opd' => 'target_tujuan',
            'sasaran', 'sasaran_opd' => 'sasaran',
            'indikator_sasaran', 'indikator_sasaran_opd' => 'indikator_sasaran',
            'target_sasaran', 'target_indikator_sasaran', 'target_indikator_sasaran_opd' => 'target_sasaran',
            'program', 'program_opd', 'opd_program' => 'program',
            'indikator_program', 'indikator_opd_program' => 'indikator_program',
            'target_program', 'target_indikator_program', 'target_indikator_opd_program' => 'target_program',
            'kegiatan', 'opd_kegiatan' => 'kegiatan',
            'sub_kegiatan', 'opd_sub_kegiatan' => 'sub_kegiatan',
            'indikator_sub_kegiatan' => 'indikator_sub_kegiatan',
            'target_triwulan', 'target_tw', 'triwulan' => 'target_triwulan',
            default => $level,
        };
    }

    private function requiredText(array $mapped, array $keys, string $label): string
    {
        $value = $this->text($mapped, $keys);

        if ($value === null) {
            throw new RuntimeException("{$label} wajib diisi.");
        }

        return $value;
    }

    private function text(array $mapped, array $keys): ?string
    {
        foreach ($keys as $key) {
            $value = $mapped[$key] ?? null;

            if (filled($value)) {
                return trim((string) $value);
            }
        }

        return null;
    }

    private function integer(array $mapped, array $keys): ?int
    {
        $value = $this->text($mapped, $keys);

        return $value === null ? null : (int) $value;
    }

    private function number(array $mapped, array $keys): ?float
    {
        $value = $this->text($mapped, $keys);

        if ($value === null) {
            return null;
        }

        $value = str_replace(' ', '', $value);

        if (str_contains($value, ',') && str_contains($value, '.')) {
            $value = str_replace('.', '', $value);
            $value = str_replace(',', '.', $value);
        } else {
            $value = str_replace(',', '.', $value);
        }

        return is_numeric($value) ? (float) $value : null;
    }

    private function status(array $mapped, string $default): string
    {
        $status = $this->text($mapped, ['status']);

        return in_array($status, ['draft', 'submitted', 'revision', 'verified', 'approved', 'rejected', 'locked'], true)
            ? $status
            : $default;
    }

    private function tipeIndikator(array $mapped): string
    {
        $tipe = $this->text($mapped, ['tipe_indikator', 'tipe']);

        return $tipe === 'negatif' ? 'negatif' : 'positif';
    }

    private function order(array $mapped): int
    {
        return $this->integer($mapped, ['urutan', 'order', 'no']) ?? 1;
    }

    private function requiredContext(string $key, string $message): Model
    {
        $model = $this->context[$key] ?? null;

        if (! $model instanceof Model) {
            throw new RuntimeException($message);
        }

        return $model;
    }

    private function setContext(string $key, Model $model): void
    {
        $order = ['renstra', 'tujuan', 'indikator_tujuan', 'sasaran', 'indikator_sasaran', 'program', 'indikator_program', 'kegiatan', 'sub_kegiatan', 'indikator_sub_kegiatan'];
        $index = array_search($key, $order, true);

        if ($index !== false) {
            foreach (array_slice($order, $index + 1) as $childKey) {
                unset($this->context[$childKey]);
            }
        }

        $this->context[$key] = $model;
    }

    /**
     * @return array<string, mixed>
     */
    private function result(Model $model): array
    {
        return [
            'table' => $model->getTable(),
            'id' => $model->getKey(),
        ];
    }

    /**
     * @param  array<int, array{status: string, result: array<string, mixed>, error: string|null}>  $results
     */
    private function persistRowResults(ImportBatch $batch, array $results): void
    {
        $batch->rows()->get()->each(function (ImportBatchRow $row) use ($results) {
            $result = $results[$row->id] ?? null;

            if (! $result) {
                return;
            }

            $row->update([
                'status' => $result['status'],
                'normalized_data' => [
                    ...($row->normalized_data ?? []),
                    'result' => $result['result'],
                ],
                'error_message' => $result['error'],
            ]);
        });
    }
}
