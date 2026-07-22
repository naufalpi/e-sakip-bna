<?php

namespace App\Services\Rpjmd;

use App\Models\IndikatorProgramRpjmd;
use App\Models\Opd;
use App\Models\ProgramPemerintahan;
use App\Models\ProgramRpjmd;
use Illuminate\Support\Collection;

class RpjmdProgramPengampuResolver
{
    /**
     * @return array{cakupan_pengampu: string, opd_ids: array<int, int>}
     */
    public function resolveForProgram(ProgramRpjmd $program): array
    {
        $references = $this->programReferences($program);

        if ($references->contains(fn (ProgramPemerintahan $reference) => $this->isProgramPenunjang($reference->nama))) {
            return [
                'cakupan_pengampu' => 'semua_opd',
                'opd_ids' => [],
            ];
        }

        $opdIds = $references
            ->pluck('bidangUrusan')
            ->filter()
            ->flatMap(fn ($bidang) => $bidang->opdPengampu->pluck('id'))
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        return [
            'cakupan_pengampu' => 'opd_tertentu',
            'opd_ids' => $opdIds,
        ];
    }

    public function syncForIndikator(IndikatorProgramRpjmd $indikator): void
    {
        $indikator->loadMissing([
            'program.programPemerintahan.bidangUrusan.opdPengampu',
            'program.programPemerintahanReferences.bidangUrusan.opdPengampu',
        ]);

        $resolved = $this->resolveForProgram($indikator->program);
        $opdIds = $resolved['opd_ids'];

        $indikator->update([
            'cakupan_pengampu' => $resolved['cakupan_pengampu'],
            'opd_id' => $resolved['cakupan_pengampu'] === 'semua_opd' ? null : ($opdIds[0] ?? null),
        ]);

        if ($resolved['cakupan_pengampu'] === 'semua_opd') {
            $indikator->opdPengampu()->sync([]);

            return;
        }

        $indikator->opdPengampu()->sync($this->syncPayload($opdIds));
    }

    public function syncForProgramIndicators(ProgramRpjmd $program): void
    {
        $program->loadMissing('indikator');

        $program->indikator->each(fn (IndikatorProgramRpjmd $indikator) => $this->syncForIndikator($indikator));
    }

    /**
     * @return array<int, int>
     */
    public function resolveOpdPenanggungJawabIds(ProgramRpjmd $program): array
    {
        $resolved = $this->resolveForProgram($program);

        if ($resolved['cakupan_pengampu'] === 'semua_opd') {
            return Opd::query()
                ->where('status', 'active')
                ->orderBy('nama')
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->values()
                ->all();
        }

        return $resolved['opd_ids'];
    }

    /**
     * @param  array<int, int>  $opdIds
     */
    public function syncProgramPenanggungJawab(ProgramRpjmd $program, array $opdIds): void
    {
        $program->opdPenanggungJawab()->sync($this->programPenanggungJawabPayload($opdIds));
    }

    public function labelForIndikator(IndikatorProgramRpjmd $indikator): string
    {
        if (($indikator->cakupan_pengampu ?: 'opd_tertentu') === 'semua_opd') {
            return 'Semua Perangkat Daerah';
        }

        $indikator->loadMissing('opdPengampu');

        $names = $indikator->opdPengampu
            ->map(fn (Opd $opd) => $opd->singkatan ?: $opd->nama)
            ->filter()
            ->values();

        return $names->isEmpty() ? 'Belum terdeteksi' : $names->implode('; ');
    }

    /**
     * @return Collection<int, ProgramPemerintahan>
     */
    private function programReferences(ProgramRpjmd $program): Collection
    {
        $program->loadMissing([
            'programPemerintahan.bidangUrusan.opdPengampu',
            'programPemerintahanReferences.bidangUrusan.opdPengampu',
        ]);

        $references = collect();

        if ($program->programPemerintahan) {
            $references->push($program->programPemerintahan);
        }

        return $references
            ->merge($program->programPemerintahanReferences)
            ->unique('id')
            ->values();
    }

    private function isProgramPenunjang(?string $name): bool
    {
        $normalized = strtolower((string) preg_replace('/\s+/', ' ', trim((string) $name)));

        return str_contains($normalized, 'program penunjang urusan pemerintahan daerah')
            && (str_contains($normalized, 'kabupaten/kota') || str_contains($normalized, 'kab/kota'));
    }

    /**
     * @param  array<int, int>  $opdIds
     * @return array<int, array{peran: string, is_utama: bool}>
     */
    private function syncPayload(array $opdIds): array
    {
        return collect($opdIds)
            ->mapWithKeys(fn (int $opdId) => [
                $opdId => [
                    'peran' => 'pengampu_data',
                    'is_utama' => true,
                ],
            ])
            ->all();
    }

    /**
     * @param  array<int, int>  $opdIds
     * @return array<int, array{peran: string, is_utama: bool}>
     */
    private function programPenanggungJawabPayload(array $opdIds): array
    {
        return collect($opdIds)
            ->unique()
            ->mapWithKeys(fn (int $opdId) => [
                $opdId => [
                    'peran' => 'penanggung_jawab',
                    'is_utama' => true,
                ],
            ])
            ->all();
    }
}
