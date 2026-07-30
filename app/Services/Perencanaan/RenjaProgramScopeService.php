<?php

namespace App\Services\Perencanaan;

use App\Models\OpdProgram;
use App\Models\ProgramRpjmd;
use App\Models\RenjaOpd;
use Illuminate\Support\Collection;

class RenjaProgramScopeService
{
    /**
     * @return array<int, int>
     */
    public function programPemerintahanIds(RenjaOpd $renja): array
    {
        $renja->loadMissing([
            'opd:id,kode',
            'renstraOpd:id,rpjmd_id,opd_id',
            'rkpd:id,rpjmd_id',
        ]);

        $opdId = (int) ($renja->opd_id ?: $renja->renstraOpd?->opd_id);

        if (! $opdId) {
            return [];
        }

        $rpjmdId = (int) ($renja->renstraOpd?->rpjmd_id ?: $renja->rkpd?->rpjmd_id);
        $ids = $this->idsFromRenstra($renja, $opdId)
            ->merge($this->idsFromRpjmd($rpjmdId, $opdId));

        return $ids
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return Collection<int, int>
     */
    private function idsFromRenstra(RenjaOpd $renja, int $opdId): Collection
    {
        if (! $renja->renstra_opd_id) {
            return collect();
        }

        return OpdProgram::query()
            ->with([
                'programPemerintahan:id,bidang_urusan_id,kode,nama',
                'programRpjmd:id,program_pemerintahan_id,kode,nama,is_penanggung_jawab_manual',
                'programRpjmd.programPemerintahan:id,bidang_urusan_id,kode,nama',
                'programRpjmd.programPemerintahan.bidangUrusan.opdPengampu:id',
                'programRpjmd.programPemerintahanReferences:id,bidang_urusan_id,kode,nama',
                'programRpjmd.programPemerintahanReferences.bidangUrusan.opdPengampu:id',
            ])
            ->where('renstra_opd_id', $renja->renstra_opd_id)
            ->orderBy('urutan')
            ->get(['id', 'renstra_opd_id', 'program_rpjmd_id', 'program_pemerintahan_id', 'kode', 'nama'])
            ->map(function (OpdProgram $program) use ($opdId): ?int {
                return $program->programRpjmd?->preferredProgramPemerintahanReferenceForOpd($opdId)?->id
                    ?? ($program->program_pemerintahan_id ? (int) $program->program_pemerintahan_id : null);
            });
    }

    /**
     * @return Collection<int, int>
     */
    private function idsFromRpjmd(int $rpjmdId, int $opdId): Collection
    {
        if (! $rpjmdId) {
            return collect();
        }

        return ProgramRpjmd::query()
            ->forRpjmd($rpjmdId)
            ->relevantForOpd($opdId)
            ->with([
                'programPemerintahan:id,bidang_urusan_id,kode,nama',
                'programPemerintahan.bidangUrusan.opdPengampu:id',
                'programPemerintahanReferences:id,bidang_urusan_id,kode,nama',
                'programPemerintahanReferences.bidangUrusan.opdPengampu:id',
            ])
            ->orderBy('urutan')
            ->get(['id', 'program_pemerintahan_id', 'kode', 'nama', 'is_penanggung_jawab_manual'])
            ->map(fn (ProgramRpjmd $program) => $program->preferredProgramPemerintahanReferenceForOpd($opdId)?->id
                ?? ($program->program_pemerintahan_id ? (int) $program->program_pemerintahan_id : null));
    }
}
