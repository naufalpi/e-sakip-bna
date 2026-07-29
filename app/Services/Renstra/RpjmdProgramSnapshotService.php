<?php

namespace App\Services\Renstra;

use App\Models\IndikatorOpdProgram;
use App\Models\IndikatorProgramRpjmd;
use App\Models\OpdProgram;
use App\Models\ProgramRpjmd;
use App\Models\TargetIndikatorOpdProgram;
use App\Models\TargetIndikatorProgramRpjmd;
use App\Services\Rpjmd\RpjmdProgramPengampuResolver;

class RpjmdProgramSnapshotService
{
    public function __construct(
        private readonly RpjmdProgramPengampuResolver $pengampuResolver,
    ) {}

    public function syncProgram(OpdProgram $program): void
    {
        if (! $program->program_rpjmd_id) {
            return;
        }

        $program->loadMissing('renstra');
        $opdId = (int) ($program->renstra?->opd_id ?? 0);

        if ($opdId < 1) {
            return;
        }

        $programRpjmd = ProgramRpjmd::query()
            ->with([
                'indikator.opdPengampu',
                'indikator.satuanIndikator',
                'indikator.targets.periodeTahun',
                'programPemerintahan.bidangUrusan.opdPengampu',
                'programPemerintahanReferences.bidangUrusan.opdPengampu',
            ])
            ->find($program->program_rpjmd_id);

        if (! $programRpjmd) {
            return;
        }

        $programRpjmd->indikator
            ->filter(fn (IndikatorProgramRpjmd $indikator) => $this->isRelevantForOpd($indikator, $opdId))
            ->each(fn (IndikatorProgramRpjmd $indikator) => $this->syncIndikator($program, $indikator, $opdId));
    }

    private function isRelevantForOpd(IndikatorProgramRpjmd $indikator, int $opdId): bool
    {
        $indikator->loadMissing('opdPengampu');

        if (($indikator->cakupan_pengampu ?: 'opd_tertentu') === 'semua_opd') {
            return true;
        }

        if ($indikator->opdPengampu->contains('id', $opdId)) {
            return true;
        }

        $resolved = $this->pengampuResolver->resolveForIndikator($indikator);

        return $resolved['cakupan_pengampu'] === 'semua_opd'
            || in_array($opdId, $resolved['opd_ids'], true);
    }

    private function syncIndikator(OpdProgram $program, IndikatorProgramRpjmd $indikator, int $opdId): void
    {
        $opdIndikator = $this->existingIndikator($program, $indikator)
            ?? $this->sameNameIndikator($program, $indikator);

        if ($opdIndikator) {
            if (! $opdIndikator->indikator_program_rpjmd_id) {
                $opdIndikator->update(['indikator_program_rpjmd_id' => $indikator->id]);
            }
        } else {
            $opdIndikator = IndikatorOpdProgram::create([
                'opd_program_id' => $program->id,
                'indikator_program_rpjmd_id' => $indikator->id,
                'satuan_indikator_id' => $indikator->satuan_indikator_id,
                'kode' => $indikator->kode,
                'indikator' => $indikator->indikator,
                'tipe_indikator' => 'positif',
                'definisi_operasional' => $indikator->definisi_operasional,
                'formula' => $indikator->formulasi_pengukuran,
                'formulasi_pengukuran' => $indikator->formulasi_pengukuran,
                'tipe_perhitungan' => $indikator->tipe_perhitungan ?: 'non_kumulatif',
                'opd_penanggung_jawab_id' => $opdId,
                'pd_penanggung_jawab' => $this->pengampuResolver->labelForIndikator($indikator),
                'sumber_data' => $indikator->sumber_data,
                'urutan' => $indikator->urutan ?: ($program->indikator()->count() + 1),
            ]);
        }

        $indikator->targets->each(fn (TargetIndikatorProgramRpjmd $target) => $this->syncTarget($opdIndikator, $target));
    }

    private function existingIndikator(OpdProgram $program, IndikatorProgramRpjmd $indikator): ?IndikatorOpdProgram
    {
        return IndikatorOpdProgram::query()
            ->where('opd_program_id', $program->id)
            ->where('indikator_program_rpjmd_id', $indikator->id)
            ->first();
    }

    private function sameNameIndikator(OpdProgram $program, IndikatorProgramRpjmd $indikator): ?IndikatorOpdProgram
    {
        $name = strtolower(trim((string) $indikator->indikator));

        if ($name === '') {
            return null;
        }

        return IndikatorOpdProgram::query()
            ->where('opd_program_id', $program->id)
            ->whereNull('indikator_program_rpjmd_id')
            ->whereRaw('LOWER(TRIM(indikator)) = ?', [$name])
            ->first();
    }

    private function syncTarget(IndikatorOpdProgram $indikator, TargetIndikatorProgramRpjmd $targetRpjmd): void
    {
        if (! $targetRpjmd->periode_tahun_id || (blank($targetRpjmd->target) && blank($targetRpjmd->target_text))) {
            return;
        }

        $targetOpd = TargetIndikatorOpdProgram::query()
            ->where('indikator_opd_program_id', $indikator->id)
            ->where('periode_tahun_id', $targetRpjmd->periode_tahun_id)
            ->first();

        if (! $targetOpd) {
            TargetIndikatorOpdProgram::create([
                'indikator_opd_program_id' => $indikator->id,
                'periode_tahun_id' => $targetRpjmd->periode_tahun_id,
                'target' => $targetRpjmd->target,
                'target_text' => $targetRpjmd->target_text,
                'pagu' => null,
            ]);

            return;
        }

        if (blank($targetOpd->target) && blank($targetOpd->target_text)) {
            $targetOpd->update([
                'target' => $targetRpjmd->target,
                'target_text' => $targetRpjmd->target_text,
                'pagu' => null,
            ]);
        }
    }
}
