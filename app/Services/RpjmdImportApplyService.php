<?php

namespace App\Services;

use App\Models\ImportBatch;
use App\Models\ImportBatchRow;
use App\Models\IndikatorProgramRpjmd;
use App\Models\IndikatorSasaranDaerah;
use App\Models\IndikatorTujuanDaerah;
use App\Models\Opd;
use App\Models\PeriodeTahun;
use App\Models\ProgramRpjmd;
use App\Models\ProgramRpjmdOpdPenanggungJawab;
use App\Models\Rpjmd;
use App\Models\RpjmdMisi;
use App\Models\RpjmdVisi;
use App\Models\SasaranDaerah;
use App\Models\SatuanIndikator;
use App\Models\StrategiDaerah;
use App\Models\TargetIndikatorProgramRpjmd;
use App\Models\TargetIndikatorSasaranDaerah;
use App\Models\TargetIndikatorTujuanDaerah;
use App\Models\TujuanDaerah;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class RpjmdImportApplyService
{
    /**
     * @var array<string, Model>
     */
    private array $context = [];

    private ?User $actor = null;

    public function apply(ImportBatch $batch, User $user): ImportBatch
    {
        if ($batch->module !== 'rpjmd') {
            throw ValidationException::withMessages([
                'import_batch_id' => 'Batch import tidak sesuai modul RPJMD.',
            ]);
        }

        if ($batch->status !== 'previewed') {
            throw ValidationException::withMessages([
                'import_batch_id' => 'Batch import hanya dapat diterapkan setelah status previewed.',
            ]);
        }

        $this->actor = $user;
        $this->context = [];

        $summary = [
            'imported_rows' => 0,
            'failed_rows' => 0,
            'skipped_rows' => 0,
        ];

        DB::transaction(function () use ($batch, $user, &$summary) {
            $batch->update(['status' => 'processing']);

            $batch->rows()->orderBy('row_number')->get()->each(function (ImportBatchRow $row) use (&$summary) {
                if ((bool) ($row->normalized_data['is_header'] ?? false)) {
                    $this->markRow($row, 'skipped', ['reason' => 'header']);
                    $summary['skipped_rows']++;

                    return;
                }

                try {
                    $result = $this->applyRow($row->normalized_data['mapped'] ?? []);
                    $this->markRow($row, 'imported', $result);
                    $summary['imported_rows']++;
                } catch (Throwable $exception) {
                    $this->markRow($row, 'failed', ['error' => $exception->getMessage()], $exception->getMessage());
                    $summary['failed_rows']++;
                }
            });

            $status = match (true) {
                $summary['imported_rows'] > 0 && $summary['failed_rows'] > 0 => 'imported_with_errors',
                $summary['imported_rows'] > 0 => 'imported',
                default => 'failed',
            };

            $batch->update([
                'status' => $status,
                'error_message' => $status === 'failed' ? 'Tidak ada baris yang berhasil dimasukkan ke cascading RPJMD.' : null,
                'metadata' => [
                    ...($batch->metadata ?? []),
                    'applied' => [
                        ...$summary,
                        'applied_by' => $user->id,
                        'applied_at' => now()->toISOString(),
                        'rpjmd_id' => ($this->context['rpjmd'] ?? null)?->getKey(),
                    ],
                ],
            ]);
        });

        return $batch->fresh(['uploadedBy:id,name', 'rows']);
    }

    /**
     * @param  array<string, mixed>  $mapped
     * @return array<string, mixed>
     */
    private function applyRow(array $mapped): array
    {
        $level = $this->normalizeLevel($this->text($mapped, ['level', 'jenis', 'tipe', 'node_type']));

        return match ($level) {
            'rpjmd' => $this->applyRpjmd($mapped),
            'visi' => $this->applyVisi($mapped),
            'misi' => $this->applyMisi($mapped),
            'tujuan' => $this->applyTujuan($mapped),
            'indikator_tujuan' => $this->applyIndikatorTujuan($mapped),
            'target_tujuan' => $this->applyTargetTujuan($mapped),
            'sasaran' => $this->applySasaran($mapped),
            'indikator_sasaran' => $this->applyIndikatorSasaran($mapped),
            'target_sasaran' => $this->applyTargetSasaran($mapped),
            'program' => $this->applyProgram($mapped),
            'indikator_program' => $this->applyIndikatorProgram($mapped),
            'target_program' => $this->applyTargetProgram($mapped),
            'opd_penanggung_jawab' => $this->applyOpdPenanggungJawab($mapped),
            default => throw new RuntimeException('Level import tidak dikenali. Gunakan level rpjmd, visi, misi, tujuan, indikator_tujuan, sasaran, program, indikator_program, target_program, atau opd_penanggung_jawab.'),
        };
    }

    /**
     * @param  array<string, mixed>  $mapped
     * @return array<string, mixed>
     */
    private function applyRpjmd(array $mapped): array
    {
        $rpjmd = $this->upsertRpjmd($mapped);
        $this->setContext('rpjmd', $rpjmd);

        return $this->result($rpjmd);
    }

    /**
     * @param  array<string, mixed>  $mapped
     * @return array<string, mixed>
     */
    private function applyVisi(array $mapped): array
    {
        $rpjmd = $this->resolveRpjmd($mapped);
        $visi = RpjmdVisi::updateOrCreate([
            'rpjmd_id' => $rpjmd->id,
            'visi' => $this->requiredText($mapped, ['visi', 'uraian', 'nama'], 'Visi'),
        ], [
            'urutan' => $this->order($mapped),
        ]);

        $this->setContext('visi', $visi);

        return $this->result($visi);
    }

    /**
     * @param  array<string, mixed>  $mapped
     * @return array<string, mixed>
     */
    private function applyMisi(array $mapped): array
    {
        $rpjmd = $this->resolveRpjmd($mapped);
        $text = $this->requiredText($mapped, ['misi', 'uraian', 'nama'], 'Misi');
        $kode = $this->text($mapped, ['kode', 'kode_misi']);
        $identity = $kode ? ['rpjmd_id' => $rpjmd->id, 'kode' => $kode] : ['rpjmd_id' => $rpjmd->id, 'misi' => $text];

        $misi = RpjmdMisi::updateOrCreate($identity, [
            'rpjmd_visi_id' => $this->context['visi']->id ?? null,
            'misi' => $text,
            'urutan' => $this->order($mapped),
        ]);

        $this->setContext('misi', $misi);

        return $this->result($misi);
    }

    /**
     * @param  array<string, mixed>  $mapped
     * @return array<string, mixed>
     */
    private function applyTujuan(array $mapped): array
    {
        $visi = $this->requiredContext('visi', 'Tujuan daerah harus berada setelah baris visi.');
        $text = $this->requiredText($mapped, ['tujuan', 'uraian', 'nama'], 'Tujuan daerah');
        $kode = $this->text($mapped, ['kode', 'kode_tujuan']);
        $identity = $kode ? ['rpjmd_visi_id' => $visi->id, 'kode' => $kode] : ['rpjmd_visi_id' => $visi->id, 'tujuan' => $text];

        $tujuan = TujuanDaerah::updateOrCreate($identity, [
            'rpjmd_misi_id' => null,
            'tujuan' => $text,
            'urutan' => $this->order($mapped),
        ]);

        $relatedMisiIds = $this->relatedMisiIds($mapped, $visi);

        if ($relatedMisiIds !== null) {
            $tujuan->misiTerkait()->sync($this->orderedSyncPayload($relatedMisiIds));
            $tujuan->forceFill(['rpjmd_misi_id' => $relatedMisiIds[0] ?? null])->save();
        } elseif (($this->context['misi'] ?? null) instanceof RpjmdMisi) {
            $tujuan->misiTerkait()->syncWithoutDetaching([
                $this->context['misi']->id => ['urutan' => $this->context['misi']->urutan ?? 1],
            ]);
            $tujuan->forceFill(['rpjmd_misi_id' => $this->context['misi']->id])->save();
        }

        $this->setContext('tujuan', $tujuan);

        return $this->result($tujuan);
    }

    /**
     * @param  array<string, mixed>  $mapped
     * @return array<string, mixed>
     */
    private function applyIndikatorTujuan(array $mapped): array
    {
        $tujuan = $this->requiredContext('tujuan', 'Indikator tujuan harus berada setelah baris tujuan.');
        $indikator = $this->upsertIndikatorTujuan($mapped, $tujuan);
        $this->setContext('indikator_tujuan', $indikator);
        $this->maybeApplyTargetTujuan($mapped, $indikator);

        return $this->result($indikator);
    }

    /**
     * @param  array<string, mixed>  $mapped
     * @return array<string, mixed>
     */
    private function applyTargetTujuan(array $mapped): array
    {
        $indikator = $this->requiredContext('indikator_tujuan', 'Target tujuan harus berada setelah baris indikator tujuan.');
        $target = $this->upsertTargetTujuan($mapped, $indikator);

        return $this->result($target);
    }

    /**
     * @param  array<string, mixed>  $mapped
     * @return array<string, mixed>
     */
    private function applySasaran(array $mapped): array
    {
        $tujuan = $this->requiredContext('tujuan', 'Sasaran daerah harus berada setelah baris tujuan.');
        $text = $this->requiredText($mapped, ['sasaran', 'uraian', 'nama'], 'Sasaran daerah');
        $kode = $this->text($mapped, ['kode', 'kode_sasaran']);
        $identity = $kode ? ['tujuan_daerah_id' => $tujuan->id, 'kode' => $kode] : ['tujuan_daerah_id' => $tujuan->id, 'sasaran' => $text];

        $sasaran = SasaranDaerah::updateOrCreate($identity, [
            'sasaran' => $text,
            'urutan' => $this->order($mapped),
        ]);

        $relatedIndikatorIds = $this->relatedIndikatorTujuanIds($mapped, $tujuan);

        if ($relatedIndikatorIds !== null) {
            $sasaran->indikatorTujuanTerkait()->sync($relatedIndikatorIds);
        }

        $this->setContext('sasaran', $sasaran);

        return $this->result($sasaran);
    }

    /**
     * @param  array<string, mixed>  $mapped
     * @return array<string, mixed>
     */
    private function applyIndikatorSasaran(array $mapped): array
    {
        $sasaran = $this->requiredContext('sasaran', 'Indikator sasaran harus berada setelah baris sasaran.');
        $indikator = $this->upsertIndikatorSasaran($mapped, $sasaran);
        $this->setContext('indikator_sasaran', $indikator);
        $this->maybeApplyTargetSasaran($mapped, $indikator);

        return $this->result($indikator);
    }

    /**
     * @param  array<string, mixed>  $mapped
     * @return array<string, mixed>
     */
    private function applyTargetSasaran(array $mapped): array
    {
        $indikator = $this->requiredContext('indikator_sasaran', 'Target sasaran harus berada setelah baris indikator sasaran.');
        $target = $this->upsertTargetSasaran($mapped, $indikator);

        return $this->result($target);
    }

    /**
     * @param  array<string, mixed>  $mapped
     * @return array<string, mixed>
     */
    private function applyProgram(array $mapped): array
    {
        $indikatorSasaran = $this->requiredContext('indikator_sasaran', 'Program RPJMD harus berada setelah baris indikator sasaran.');
        $strategi = $this->resolveProgramStrategi($mapped);

        $text = $this->requiredText($mapped, ['program', 'nama_program', 'uraian', 'nama'], 'Program RPJMD');
        $kode = $this->text($mapped, ['kode', 'kode_program']);
        $identity = $kode
            ? ['kode' => $kode, 'indikator_sasaran_daerah_id' => $indikatorSasaran->id]
            : ['nama' => $text, 'indikator_sasaran_daerah_id' => $indikatorSasaran->id];

        $program = ProgramRpjmd::updateOrCreate($identity, [
            'strategi_daerah_id' => $strategi?->id,
            'sasaran_daerah_id' => $indikatorSasaran->sasaran_daerah_id,
            'indikator_sasaran_daerah_id' => $indikatorSasaran->id,
            'nama' => $text,
            'status' => $this->status($mapped, 'draft'),
            'urutan' => $this->order($mapped),
        ]);

        $this->setContext('program', $program);

        return $this->result($program);
    }

    /**
     * @param  array<string, mixed>  $mapped
     * @return array<string, mixed>
     */
    private function applyIndikatorProgram(array $mapped): array
    {
        $program = $this->requiredContext('program', 'Indikator program harus berada setelah baris program.');
        $indikator = $this->upsertIndikatorProgram($mapped, $program);
        $this->setContext('indikator_program', $indikator);
        $this->maybeApplyTargetProgram($mapped, $indikator);

        return $this->result($indikator);
    }

    /**
     * @param  array<string, mixed>  $mapped
     * @return array<string, mixed>
     */
    private function applyTargetProgram(array $mapped): array
    {
        $indikator = $this->requiredContext('indikator_program', 'Target program harus berada setelah baris indikator program.');
        $target = $this->upsertTargetProgram($mapped, $indikator);

        return $this->result($target);
    }

    /**
     * @param  array<string, mixed>  $mapped
     * @return array<string, mixed>
     */
    private function applyOpdPenanggungJawab(array $mapped): array
    {
        $program = $this->requiredContext('program', 'OPD penanggung jawab harus berada setelah baris program.');
        $opd = $this->resolveOpd($mapped);

        $relation = ProgramRpjmdOpdPenanggungJawab::updateOrCreate([
            'program_rpjmd_id' => $program->id,
            'opd_id' => $opd->id,
            'peran' => $this->text($mapped, ['peran']) ?? 'penanggung_jawab',
        ], [
            'is_utama' => $this->boolean($mapped, ['is_utama', 'utama'], true),
        ]);

        return $this->result($relation);
    }

    /**
     * @param  array<string, mixed>  $mapped
     */
    private function upsertRpjmd(array $mapped): Rpjmd
    {
        $id = $this->text($mapped, ['rpjmd_id']);

        if ($id) {
            $rpjmd = Rpjmd::find($id);

            if (! $rpjmd) {
                throw new RuntimeException("RPJMD ID {$id} tidak ditemukan.");
            }

            $this->assertCanUpdate($rpjmd);

            return $rpjmd;
        }

        $judul = $this->requiredText($mapped, ['rpjmd_judul', 'judul_rpjmd', 'judul', 'nama', 'uraian'], 'Judul RPJMD');
        $tahunAwal = $this->integer($mapped, ['tahun_awal', 'awal_periode']);
        $tahunAkhir = $this->integer($mapped, ['tahun_akhir', 'akhir_periode']);

        if (! $tahunAwal || ! $tahunAkhir) {
            throw new RuntimeException('RPJMD membutuhkan tahun_awal dan tahun_akhir.');
        }

        $periode = PeriodeTahun::query()->where('tahun', $tahunAwal)->first();

        $identity = [
            'judul' => $judul,
            'tahun_awal' => $tahunAwal,
            'tahun_akhir' => $tahunAkhir,
        ];
        $rpjmd = Rpjmd::query()->firstOrNew($identity);

        if ($rpjmd->exists) {
            $this->assertCanUpdate($rpjmd);
        }

        $rpjmd->fill([
            'periode_tahun_id' => $periode?->id,
            'nomor_perda' => $this->text($mapped, ['nomor_perda', 'nomor_dokumen']),
            'keterangan' => $this->text($mapped, ['keterangan', 'catatan']),
        ]);

        if (! $rpjmd->exists) {
            $rpjmd->status = 'draft';
        }

        $rpjmd->save();

        return $rpjmd;
    }

    /**
     * @param  array<string, mixed>  $mapped
     */
    private function resolveRpjmd(array $mapped): Rpjmd
    {
        if (($this->context['rpjmd'] ?? null) instanceof Rpjmd) {
            return $this->context['rpjmd'];
        }

        $rpjmd = $this->upsertRpjmd($mapped);
        $this->setContext('rpjmd', $rpjmd);

        return $rpjmd;
    }

    /**
     * @param  array<string, mixed>  $mapped
     */
    private function upsertIndikatorTujuan(array $mapped, TujuanDaerah $tujuan): IndikatorTujuanDaerah
    {
        $text = $this->requiredText($mapped, ['indikator_tujuan', 'indikator', 'uraian', 'nama'], 'Indikator tujuan');
        $kode = $this->text($mapped, ['kode', 'kode_indikator', 'kode_indikator_tujuan']);
        $identity = $kode ? ['tujuan_daerah_id' => $tujuan->id, 'kode' => $kode] : ['tujuan_daerah_id' => $tujuan->id, 'indikator' => $text];

        return IndikatorTujuanDaerah::updateOrCreate($identity, [
            'indikator' => $text,
            ...$this->indikatorMetadata($mapped),
        ]);
    }

    /**
     * @param  array<string, mixed>  $mapped
     */
    private function upsertIndikatorSasaran(array $mapped, SasaranDaerah $sasaran): IndikatorSasaranDaerah
    {
        $text = $this->requiredText($mapped, ['indikator_sasaran', 'indikator', 'uraian', 'nama'], 'Indikator sasaran');
        $kode = $this->text($mapped, ['kode', 'kode_indikator', 'kode_indikator_sasaran']);
        $identity = $kode ? ['sasaran_daerah_id' => $sasaran->id, 'kode' => $kode] : ['sasaran_daerah_id' => $sasaran->id, 'indikator' => $text];

        return IndikatorSasaranDaerah::updateOrCreate($identity, [
            'indikator' => $text,
            ...$this->indikatorMetadata($mapped),
        ]);
    }

    /**
     * @param  array<string, mixed>  $mapped
     */
    private function upsertIndikatorProgram(array $mapped, ProgramRpjmd $program): IndikatorProgramRpjmd
    {
        $text = $this->requiredText($mapped, ['indikator_program', 'indikator', 'uraian', 'nama'], 'Indikator program');
        $kode = $this->text($mapped, ['kode', 'kode_indikator', 'kode_indikator_program']);
        $identity = $kode ? ['program_rpjmd_id' => $program->id, 'kode' => $kode] : ['program_rpjmd_id' => $program->id, 'indikator' => $text];

        return IndikatorProgramRpjmd::updateOrCreate($identity, [
            'indikator' => $text,
            ...$this->indikatorMetadata($mapped),
        ]);
    }

    /**
     * @param  array<string, mixed>  $mapped
     */
    private function resolveProgramStrategi(array $mapped): ?StrategiDaerah
    {
        $strategiText = $this->text($mapped, ['strategi', 'strategi_daerah', 'nama_strategi']);
        $strategiKode = $this->text($mapped, ['strategi_kode', 'kode_strategi']);

        if (! $strategiText && ! $strategiKode) {
            return null;
        }

        $strategi = StrategiDaerah::query()
            ->where('status', 'active')
            ->when(
                $strategiKode,
                fn ($query) => $query->where('kode', $strategiKode),
                fn ($query) => $query->where('strategi', 'ilike', $strategiText),
            )
            ->first();

        if (! $strategi) {
            $reference = $strategiKode ?: $strategiText;
            throw new RuntimeException("Strategi '{$reference}' belum tersedia atau tidak aktif di Master Strategi Daerah.");
        }

        return $strategi;
    }

    /**
     * @param  array<string, mixed>  $mapped
     * @return array<string, mixed>
     */
    private function indikatorMetadata(array $mapped): array
    {
        return [
            'definisi_operasional' => $this->text($mapped, ['definisi_operasional', 'definisi_operasional_indikator', 'definisi']),
            'alasan_pemilihan' => $this->text($mapped, ['alasan_pemilihan', 'alasan_pemilihan_indikator', 'alasan']),
            'formulasi_pengukuran' => $this->text($mapped, ['formulasi_pengukuran', 'formula', 'rumus']),
            'tipe_perhitungan' => $this->tipePerhitungan($mapped),
            'sumber_data' => $this->text($mapped, ['sumber_data']),
            'satuan_indikator_id' => $this->optionalSatuanIndikator($mapped)?->id,
            'opd_id' => $this->optionalOpd($mapped)?->id,
            'urutan' => $this->order($mapped),
        ];
    }

    /**
     * @param  array<string, mixed>  $mapped
     */
    private function optionalSatuanIndikator(array $mapped): ?SatuanIndikator
    {
        $id = $this->text($mapped, ['satuan_indikator_id', 'satuan_id']);
        $reference = $this->text($mapped, ['satuan', 'satuan_indikator', 'satuan_simbol', 'simbol_satuan']);

        if ($id === null && $reference === null) {
            return null;
        }

        $query = SatuanIndikator::query()->where('status', 'active');

        if ($id !== null) {
            $query->whereKey($id);
        } else {
            $normalized = str($reference)->lower()->toString();
            $query->where(function ($query) use ($normalized) {
                $query->whereRaw('LOWER(nama) = ?', [$normalized])
                    ->orWhereRaw('LOWER(simbol) = ?', [$normalized]);
            });
        }

        $satuan = $query->first();

        if (! $satuan) {
            $label = $reference ?? $id;
            throw new RuntimeException("Satuan indikator '{$label}' belum tersedia atau tidak aktif di master satuan.");
        }

        return $satuan;
    }

    /**
     * @param  array<string, mixed>  $mapped
     * @return array<int, int>|null
     */
    private function relatedMisiIds(array $mapped, RpjmdVisi $visi): ?array
    {
        $ids = $this->integerList($mapped, ['misi_ids', 'misi_id_terkait']);
        $codes = $this->textList($mapped, ['misi_kode_terkait', 'kode_misi_terkait']);

        if ($ids === [] && $codes === []) {
            return null;
        }

        $misi = RpjmdMisi::query()
            ->where('rpjmd_id', $visi->rpjmd_id)
            ->where('rpjmd_visi_id', $visi->id)
            ->where(function ($query) use ($ids, $codes) {
                if ($ids !== []) {
                    $query->whereIn('id', $ids);
                }

                if ($codes !== []) {
                    $method = $ids === [] ? 'whereIn' : 'orWhereIn';
                    $query->{$method}('kode', $codes);
                }
            })
            ->get(['id', 'kode']);

        $resolvedIds = [
            ...collect($ids)->map(fn (int $id) => $misi->firstWhere('id', $id)?->id)->filter()->all(),
            ...collect($codes)->map(fn (string $code) => $misi->firstWhere('kode', $code)?->id)->filter()->all(),
        ];

        if (
            $misi->whereIn('id', $ids)->count() !== count(array_unique($ids))
            || $misi->whereIn('kode', $codes)->count() !== count(array_unique($codes))
        ) {
            throw new RuntimeException('Misi terkait tidak ditemukan pada visi dan RPJMD yang sama.');
        }

        return array_values(array_unique($resolvedIds));
    }

    /**
     * @param  array<string, mixed>  $mapped
     * @return array<int, int>|null
     */
    private function relatedIndikatorTujuanIds(array $mapped, TujuanDaerah $tujuan): ?array
    {
        $ids = $this->integerList($mapped, ['indikator_tujuan_ids', 'indikator_tujuan_id_terkait']);
        $codes = $this->textList($mapped, ['indikator_tujuan_kode_terkait', 'kode_indikator_tujuan_terkait']);

        if ($ids === [] && $codes === []) {
            return null;
        }

        $indikator = IndikatorTujuanDaerah::query()
            ->where('tujuan_daerah_id', $tujuan->id)
            ->where(function ($query) use ($ids, $codes) {
                if ($ids !== []) {
                    $query->whereIn('id', $ids);
                }

                if ($codes !== []) {
                    $method = $ids === [] ? 'whereIn' : 'orWhereIn';
                    $query->{$method}('kode', $codes);
                }
            })
            ->get(['id', 'kode']);

        $resolvedIds = [
            ...collect($ids)->map(fn (int $id) => $indikator->firstWhere('id', $id)?->id)->filter()->all(),
            ...collect($codes)->map(fn (string $code) => $indikator->firstWhere('kode', $code)?->id)->filter()->all(),
        ];

        if (
            $indikator->whereIn('id', $ids)->count() !== count(array_unique($ids))
            || $indikator->whereIn('kode', $codes)->count() !== count(array_unique($codes))
        ) {
            throw new RuntimeException('Indikator tujuan terkait tidak ditemukan pada tujuan daerah yang sama.');
        }

        return array_values(array_unique($resolvedIds));
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

    /**
     * @param  array<string, mixed>  $mapped
     * @param  array<int, string>  $keys
     * @return array<int, string>
     */
    private function textList(array $mapped, array $keys): array
    {
        $value = $this->text($mapped, $keys);

        if ($value === null) {
            return [];
        }

        return collect(preg_split('/[,;|]+/', $value) ?: [])
            ->map(fn (string $item) => trim($item))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $mapped
     * @param  array<int, string>  $keys
     * @return array<int, int>
     */
    private function integerList(array $mapped, array $keys): array
    {
        return collect($this->textList($mapped, $keys))
            ->filter(fn (string $item) => ctype_digit($item))
            ->map(fn (string $item) => (int) $item)
            ->filter(fn (int $id) => $id > 0)
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $mapped
     */
    private function maybeApplyTargetTujuan(array $mapped, IndikatorTujuanDaerah $indikator): void
    {
        if ($this->hasTargetData($mapped)) {
            $this->upsertTargetTujuan($mapped, $indikator);
        }
    }

    /**
     * @param  array<string, mixed>  $mapped
     */
    private function maybeApplyTargetSasaran(array $mapped, IndikatorSasaranDaerah $indikator): void
    {
        if ($this->hasTargetData($mapped)) {
            $this->upsertTargetSasaran($mapped, $indikator);
        }
    }

    /**
     * @param  array<string, mixed>  $mapped
     */
    private function maybeApplyTargetProgram(array $mapped, IndikatorProgramRpjmd $indikator): void
    {
        if ($this->hasTargetData($mapped)) {
            $this->upsertTargetProgram($mapped, $indikator);
        }
    }

    /**
     * @param  array<string, mixed>  $mapped
     */
    private function upsertTargetTujuan(array $mapped, IndikatorTujuanDaerah $indikator): TargetIndikatorTujuanDaerah
    {
        $periode = $this->periodeTarget($mapped);

        return TargetIndikatorTujuanDaerah::updateOrCreate([
            'indikator_tujuan_daerah_id' => $indikator->id,
            'periode_tahun_id' => $periode->id,
        ], [
            'jenis_target' => $this->jenisTarget($periode),
            'target' => $this->text($mapped, ['target', 'target_tujuan', 'target_angka']),
            'target_text' => $this->text($mapped, ['target_text', 'target_teks', 'target_tujuan_text']),
        ]);
    }

    /**
     * @param  array<string, mixed>  $mapped
     */
    private function upsertTargetSasaran(array $mapped, IndikatorSasaranDaerah $indikator): TargetIndikatorSasaranDaerah
    {
        $periode = $this->periodeTarget($mapped);

        return TargetIndikatorSasaranDaerah::updateOrCreate([
            'indikator_sasaran_daerah_id' => $indikator->id,
            'periode_tahun_id' => $periode->id,
        ], [
            'jenis_target' => $this->jenisTarget($periode),
            'target' => $this->text($mapped, ['target', 'target_sasaran', 'target_angka']),
            'target_text' => $this->text($mapped, ['target_text', 'target_teks', 'target_sasaran_text']),
        ]);
    }

    /**
     * @param  array<string, mixed>  $mapped
     */
    private function upsertTargetProgram(array $mapped, IndikatorProgramRpjmd $indikator): TargetIndikatorProgramRpjmd
    {
        $periode = $this->periodeTarget($mapped);

        return TargetIndikatorProgramRpjmd::updateOrCreate([
            'indikator_program_rpjmd_id' => $indikator->id,
            'periode_tahun_id' => $periode->id,
        ], [
            'jenis_target' => $this->jenisTarget($periode),
            'target' => $this->text($mapped, ['target', 'target_program', 'target_angka']),
            'target_text' => $this->text($mapped, ['target_text', 'target_teks', 'target_program_text']),
        ]);
    }

    private function jenisTarget(PeriodeTahun $periode): string
    {
        $rpjmd = $this->context['rpjmd'] ?? null;

        if (! $rpjmd instanceof Rpjmd) {
            return 'tahunan';
        }

        return (int) $periode->tahun > (int) $rpjmd->tahun_akhir ? 'prakiraan_maju' : 'tahunan';
    }

    /**
     * @param  array<string, mixed>  $mapped
     */
    private function optionalOpd(array $mapped): ?Opd
    {
        $hasOpdReference = collect(['opd_id', 'opd_kode', 'kode_opd', 'opd', 'opd_nama', 'nama_opd'])
            ->contains(fn (string $key) => filled($mapped[$key] ?? null));

        return $hasOpdReference ? $this->resolveOpd($mapped) : null;
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
            $query->where('nama', 'ilike', $nama)
                ->orWhere('singkatan', 'ilike', $nama);
        } else {
            throw new RuntimeException('OPD penanggung jawab membutuhkan opd_id, opd_kode, atau opd_nama.');
        }

        $opd = $query->first();

        if (! $opd) {
            throw new RuntimeException('OPD penanggung jawab tidak ditemukan di master OPD.');
        }

        return $opd;
    }

    /**
     * @param  array<string, mixed>  $mapped
     */
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

    /**
     * @param  array<string, mixed>  $mapped
     */
    private function hasTargetData(array $mapped): bool
    {
        return $this->text($mapped, ['target', 'target_angka', 'target_text', 'target_teks', 'target_tujuan', 'target_sasaran', 'target_program']) !== null;
    }

    private function normalizeLevel(?string $level): string
    {
        $level = str((string) $level)
            ->lower()
            ->replaceMatches('/[^a-z0-9]+/', '_')
            ->trim('_')
            ->toString();

        return match ($level) {
            'rpjmd' => 'rpjmd',
            'visi', 'rpjmd_visi' => 'visi',
            'misi', 'rpjmd_misi' => 'misi',
            'tujuan', 'tujuan_daerah' => 'tujuan',
            'indikator_tujuan', 'indikator_tujuan_daerah', 'iku_tujuan' => 'indikator_tujuan',
            'target_tujuan', 'target_indikator_tujuan', 'target_indikator_tujuan_daerah' => 'target_tujuan',
            'sasaran', 'sasaran_daerah' => 'sasaran',
            'indikator_sasaran', 'indikator_sasaran_daerah', 'iku_sasaran' => 'indikator_sasaran',
            'target_sasaran', 'target_indikator_sasaran', 'target_indikator_sasaran_daerah' => 'target_sasaran',
            'program', 'program_rpjmd' => 'program',
            'indikator_program', 'indikator_program_rpjmd' => 'indikator_program',
            'target_program', 'target_indikator_program', 'target_indikator_program_rpjmd' => 'target_program',
            'opd', 'opd_penanggung_jawab', 'penanggung_jawab', 'program_opd' => 'opd_penanggung_jawab',
            default => $level,
        };
    }

    /**
     * @param  array<string, mixed>  $mapped
     * @param  array<int, string>  $keys
     */
    private function requiredText(array $mapped, array $keys, string $label): string
    {
        $value = $this->text($mapped, $keys);

        if ($value === null) {
            throw new RuntimeException("{$label} wajib diisi.");
        }

        return $value;
    }

    /**
     * @param  array<string, mixed>  $mapped
     * @param  array<int, string>  $keys
     */
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

    /**
     * @param  array<string, mixed>  $mapped
     * @param  array<int, string>  $keys
     */
    private function integer(array $mapped, array $keys): ?int
    {
        $value = $this->text($mapped, $keys);

        return $value === null ? null : (int) $value;
    }

    /**
     * @param  array<string, mixed>  $mapped
     * @param  array<int, string>  $keys
     */
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

    /**
     * @param  array<string, mixed>  $mapped
     */
    private function status(array $mapped, string $default): string
    {
        $status = $this->text($mapped, ['status']);

        return in_array($status, ['draft', 'active', 'submitted', 'revision', 'verified', 'approved', 'rejected', 'locked', 'inactive'], true)
            ? $status
            : $default;
    }

    /**
     * @param  array<string, mixed>  $mapped
     */
    private function tipePerhitungan(array $mapped): string
    {
        $tipe = str((string) $this->text($mapped, ['tipe_perhitungan', 'tipe_perhitungan_indikator']))
            ->lower()
            ->replace([' ', '-'], '_')
            ->replace('komulatif', 'kumulatif')
            ->toString();

        return $tipe === 'kumulatif' ? 'kumulatif' : 'non_kumulatif';
    }

    /**
     * @param  array<string, mixed>  $mapped
     * @param  array<int, string>  $keys
     */
    private function boolean(array $mapped, array $keys, bool $default): bool
    {
        $value = $this->text($mapped, $keys);

        if ($value === null) {
            return $default;
        }

        return in_array(strtolower($value), ['1', 'true', 'ya', 'yes', 'utama'], true);
    }

    /**
     * @param  array<string, mixed>  $mapped
     */
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
        $order = ['rpjmd', 'visi', 'misi', 'tujuan', 'indikator_tujuan', 'sasaran', 'indikator_sasaran', 'program', 'indikator_program'];
        $index = array_search($key, $order, true);

        if ($index !== false) {
            foreach (array_slice($order, $index + 1) as $childKey) {
                unset($this->context[$childKey]);
            }
        }

        $this->context[$key] = $model;
    }

    private function assertCanUpdate(Rpjmd $rpjmd): void
    {
        if (! $this->actor?->can('update', $rpjmd)) {
            throw new RuntimeException('RPJMD yang sudah disetujui atau dikunci tidak dapat diubah melalui import.');
        }
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
     * @param  array<string, mixed>  $result
     */
    private function markRow(ImportBatchRow $row, string $status, array $result, ?string $error = null): void
    {
        $row->update([
            'status' => $status,
            'normalized_data' => [
                ...($row->normalized_data ?? []),
                'result' => $result,
            ],
            'error_message' => $error,
        ]);
    }
}
