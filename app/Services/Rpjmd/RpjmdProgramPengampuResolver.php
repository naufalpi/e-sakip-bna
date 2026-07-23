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

    /**
     * @return array{cakupan_pengampu: string, opd_ids: array<int, int>}
     */
    public function resolveForIndikator(IndikatorProgramRpjmd $indikator): array
    {
        $indikator->loadMissing([
            'program.programPemerintahan.bidangUrusan.opdPengampu',
            'program.programPemerintahanReferences.bidangUrusan.opdPengampu',
        ]);

        $program = $indikator->program;
        $references = $this->programReferences($program);
        $isPenunjang = $references->contains(fn (ProgramPemerintahan $reference) => $this->isProgramPenunjang($reference->nama));

        if ($isPenunjang && $this->isAllOpdProgramIndicator($indikator->indikator)) {
            return [
                'cakupan_pengampu' => 'semua_opd',
                'opd_ids' => [],
            ];
        }

        if ($isPenunjang) {
            return [
                'cakupan_pengampu' => 'opd_tertentu',
                'opd_ids' => [],
            ];
        }

        return $this->resolveForProgramBidang($program);
    }

    /**
     * @param  array<int, int>|null  $manualOpdIds
     */
    public function syncForIndikator(IndikatorProgramRpjmd $indikator, ?array $manualOpdIds = null, ?string $cakupanPengampu = null): void
    {
        $indikator->loadMissing([
            'opdPengampu',
            'program.programPemerintahan.bidangUrusan.opdPengampu',
            'program.programPemerintahanReferences.bidangUrusan.opdPengampu',
        ]);

        $manualOpdIds = $this->normalizeIds($manualOpdIds ?? []);
        $existingOpdIds = $indikator->opdPengampu
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
        $isGenericAllOpdIndicator = $this->isGenericAllOpdIndicator($indikator);
        $resolved = match (true) {
            $isGenericAllOpdIndicator => [
                'cakupan_pengampu' => 'semua_opd',
                'opd_ids' => [],
            ],
            $cakupanPengampu === 'semua_opd' => [
                'cakupan_pengampu' => 'semua_opd',
                'opd_ids' => [],
            ],
            $manualOpdIds !== [] => [
                'cakupan_pengampu' => 'opd_tertentu',
                'opd_ids' => $manualOpdIds,
            ],
            $cakupanPengampu === 'opd_tertentu' => $this->resolveForIndikator($indikator),
            ! $isGenericAllOpdIndicator
                && ($indikator->cakupan_pengampu ?: 'opd_tertentu') !== 'semua_opd'
                && $existingOpdIds !== [] => [
                    'cakupan_pengampu' => 'opd_tertentu',
                    'opd_ids' => $existingOpdIds,
                ],
            default => $this->resolveForIndikator($indikator),
        };
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

        $indikator->loadMissing([
            'opdPengampu',
            'program.programPemerintahan.bidangUrusan.opdPengampu',
            'program.programPemerintahanReferences.bidangUrusan.opdPengampu',
        ]);

        $names = $indikator->opdPengampu
            ->map(fn (Opd $opd) => $opd->singkatan ?: $opd->nama)
            ->filter()
            ->values();

        if ($names->isEmpty()) {
            return 'Belum terdeteksi';
        }

        $bidangLabels = $this->bidangLabelsForOpdIds(
            $indikator->program,
            $indikator->opdPengampu->pluck('id')->map(fn ($id) => (int) $id)->all(),
        );

        return $bidangLabels === [] ? $names->implode('; ') : implode('; ', $bidangLabels);
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
     * @return array{cakupan_pengampu: string, opd_ids: array<int, int>}
     */
    private function resolveForProgramBidang(ProgramRpjmd $program): array
    {
        $opdIds = $this->programReferences($program)
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

    private function isAllOpdProgramIndicator(?string $name): bool
    {
        $normalized = strtolower((string) preg_replace('/\s+/', ' ', trim((string) $name)));

        return str_contains($normalized, 'persentase tingkat ketercapaian kinerja perangkat daerah')
            || (
                str_contains($normalized, 'persentase tingkat pelayanan umum')
                && str_contains($normalized, 'kepegawaian')
                && str_contains($normalized, 'keuangan perangkat daerah')
            );
    }

    private function isGenericAllOpdIndicator(IndikatorProgramRpjmd $indikator): bool
    {
        if (! $this->isAllOpdProgramIndicator($indikator->indikator)) {
            return false;
        }

        return $this->programReferences($indikator->program)
            ->contains(fn (ProgramPemerintahan $reference) => $this->isProgramPenunjang($reference->nama));
    }

    /**
     * @param  array<int, int>|null  $ids
     * @return array<int, int>
     */
    private function normalizeIds(?array $ids): array
    {
        return collect($ids ?? [])
            ->filter(fn ($id) => filled($id))
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  array<int, int>  $opdIds
     * @return array<int, string>
     */
    private function bidangLabelsForOpdIds(ProgramRpjmd $program, array $opdIds): array
    {
        if ($opdIds === []) {
            return [];
        }

        return $this->programReferences($program)
            ->pluck('bidangUrusan')
            ->filter()
            ->filter(fn ($bidang) => $bidang->opdPengampu
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->intersect($opdIds)
                ->isNotEmpty())
            ->map(fn ($bidang) => $this->bidangPengampuLabel($bidang->nama))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function bidangPengampuLabel(?string $name): string
    {
        $cleaned = trim((string) preg_replace('/^urusan pemerintahan bidang\s+/i', '', (string) $name));

        if ($cleaned === '') {
            return '';
        }

        $smallWords = ['dan', 'di', 'ke', 'dengan', 'untuk', 'yang', 'serta', 'atau', 'dalam', 'atas'];
        $title = collect(preg_split('/\s+/', strtolower($cleaned)) ?: [])
            ->map(function (string $word, int $index) use ($smallWords): string {
                if ($index > 0 && in_array($word, $smallWords, true)) {
                    return $word;
                }

                return ucfirst($word);
            })
            ->implode(' ');

        return "PD Pengampu Urusan {$title}";
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
