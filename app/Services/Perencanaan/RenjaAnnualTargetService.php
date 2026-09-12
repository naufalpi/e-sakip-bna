<?php

namespace App\Services\Perencanaan;

use App\Models\OpdKegiatan;
use App\Models\OpdProgram;
use App\Models\RenjaOpd;
use App\Models\RenjaOpdAnnualTarget;
use App\Models\SasaranOpd;
use App\Models\TujuanOpd;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class RenjaAnnualTargetService
{
    private ?bool $available = null;

    public function available(): bool
    {
        return $this->available ??= (bool) config('features.renja_annual_targets')
            && Schema::hasTable('renja_opd_annual_targets');
    }

    /**
     * Membentuk snapshot target tahunan tepat satu kali untuk setiap versi RENJA.
     *
     * @return array{created: int, existing: int}
     */
    public function bootstrap(RenjaOpd $renja, string $source = 'renstra_initial'): array
    {
        return DB::transaction(function () use ($renja, $source): array {
            /** @var RenjaOpd $renja */
            $renja = RenjaOpd::query()->lockForUpdate()->findOrFail($renja->id);
            $existing = $renja->annualTargets()->count();

            if ($existing > 0) {
                return ['created' => 0, 'existing' => $existing];
            }

            if (! $renja->renstra_opd_id) {
                return ['created' => 0, 'existing' => 0];
            }

            $rows = $this->sourceRows($renja);
            $timestamp = now();
            $records = $rows->values()->map(
                fn (array $row, int $index) => [
                    ...$row,
                    'renja_opd_id' => $renja->id,
                    'hierarchy_snapshot' => json_encode($row['hierarchy_snapshot'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    'bootstrap_source' => $source,
                    'urutan' => $index + 1,
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp,
                ]
            );

            if ($records->isNotEmpty()) {
                RenjaOpdAnnualTarget::query()->insert($records->all());
            }

            return ['created' => $rows->count(), 'existing' => 0];
        });
    }

    /** @return array{total: int, complete: int, missing: int, adjusted: int, following: int} */
    public function summary(RenjaOpd $renja): array
    {
        $aggregate = $renja->annualTargets()
            ->reorder()
            ->selectRaw('COUNT(*) AS total')
            ->selectRaw('SUM(CASE WHEN target_renja IS NOT NULL OR target_renja_text IS NOT NULL THEN 1 ELSE 0 END) AS complete')
            ->selectRaw('SUM(CASE WHEN is_adjusted = ? THEN 1 ELSE 0 END) AS adjusted', [true])
            ->first();
        $total = (int) ($aggregate?->total ?? 0);
        $complete = (int) ($aggregate?->complete ?? 0);
        $adjusted = (int) ($aggregate?->adjusted ?? 0);

        return [
            'total' => $total,
            'complete' => $complete,
            'missing' => max(0, $total - $complete),
            'adjusted' => $adjusted,
            'following' => max(0, $complete - $adjusted),
        ];
    }

    /**
     * @param  array<int, array{id: int, target_text: string, alasan_penyesuaian?: string|null}>  $changes
     */
    public function updateTargets(RenjaOpd $renja, array $changes): void
    {
        DB::transaction(function () use ($renja, $changes): void {
            $rows = $renja->annualTargets()
                ->whereIn('id', collect($changes)->pluck('id'))
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            foreach ($changes as $index => $change) {
                /** @var RenjaOpdAnnualTarget|null $row */
                $row = $rows->get((int) $change['id']);
                if (! $row) {
                    throw ValidationException::withMessages([
                        "targets.{$index}.id" => 'Target tahunan tidak ditemukan pada RENJA ini.',
                    ]);
                }

                $value = trim((string) ($change['target_text'] ?? ''));
                if ($value === '') {
                    throw ValidationException::withMessages([
                        "targets.{$index}.target_text" => 'Target RENJA wajib diisi. Gunakan NA jika target belum dapat ditetapkan.',
                    ]);
                }

                $baseline = $this->targetDisplay($row->target_renstra_text, $row->target_renstra);
                $adjusted = ! $this->valuesEqual($baseline, $value);
                $reason = trim((string) ($change['alasan_penyesuaian'] ?? ''));

                if ($adjusted && $reason === '') {
                    throw ValidationException::withMessages([
                        "targets.{$index}.alasan_penyesuaian" => 'Alasan wajib diisi karena target RENJA berbeda dari RENSTRA.',
                    ]);
                }

                $row->update([
                    'target_renja' => $this->numericValue($value),
                    'target_renja_text' => $value,
                    'is_adjusted' => $adjusted,
                    'alasan_penyesuaian' => $adjusted ? $reason : null,
                ]);
            }
        });
    }

    public function targetDisplay(mixed $text, mixed $number): ?string
    {
        if (filled($text)) {
            return trim((string) $text);
        }

        if ($number === null || $number === '') {
            return null;
        }

        return rtrim(rtrim(number_format((float) $number, 4, '.', ''), '0'), '.');
    }

    /** @return Collection<int, array<string, mixed>> */
    private function sourceRows(RenjaOpd $renja): Collection
    {
        $rows = collect();
        $periodId = (int) $renja->periode_tahun_id;
        $renstraId = (int) $renja->renstra_opd_id;

        $goals = TujuanOpd::query()
            ->where('renstra_opd_id', $renstraId)
            ->with(['indikator' => fn ($query) => $query->with([
                'satuanIndikator:id,nama,simbol',
                'targets' => fn ($query) => $query->where('periode_tahun_id', $periodId),
            ])])
            ->orderBy('urutan')->orderBy('id')->get();

        foreach ($goals as $goal) {
            foreach ($goal->indikator as $indicator) {
                $rows->push($this->row(
                    'tujuan_opd',
                    $goal,
                    $goal->tujuan,
                    $indicator,
                    [['level' => 'Tujuan OPD', 'kode' => null, 'label' => $goal->tujuan]],
                ));
            }
        }

        $objectives = SasaranOpd::query()
            ->whereHas('tujuan', fn ($query) => $query->where('renstra_opd_id', $renstraId))
            ->with([
                'tujuan:id,renstra_opd_id,tujuan,urutan',
                'indikator' => fn ($query) => $query->with([
                    'satuanIndikator:id,nama,simbol',
                    'targets' => fn ($query) => $query->where('periode_tahun_id', $periodId),
                ]),
            ])
            ->get()
            ->sortBy(fn (SasaranOpd $item) => sprintf('%06d-%06d-%010d', $item->tujuan?->urutan ?? 0, $item->urutan, $item->id));

        foreach ($objectives as $objective) {
            $hierarchy = [
                ['level' => 'Tujuan OPD', 'kode' => null, 'label' => $objective->tujuan?->tujuan],
                ['level' => 'Sasaran Strategis', 'kode' => $objective->kode, 'label' => $objective->sasaran],
            ];
            foreach ($objective->indikator as $indicator) {
                $rows->push($this->row('sasaran_opd', $objective, $objective->sasaran, $indicator, $hierarchy));
            }
        }

        $programs = OpdProgram::query()
            ->where('renstra_opd_id', $renstraId)
            ->with([
                'sasaran.tujuan',
                'indikator' => fn ($query) => $query->with([
                    'satuanIndikator:id,nama,simbol',
                    'targets' => fn ($query) => $query->where('periode_tahun_id', $periodId),
                ]),
            ])
            ->orderBy('urutan')->orderBy('id')->get();

        foreach ($programs as $program) {
            $hierarchy = [
                ['level' => 'Tujuan OPD', 'kode' => null, 'label' => $program->sasaran?->tujuan?->tujuan],
                ['level' => 'Sasaran Strategis', 'kode' => $program->sasaran?->kode, 'label' => $program->sasaran?->sasaran],
                ['level' => 'Program', 'kode' => $program->kode, 'label' => $program->nama],
            ];
            foreach ($program->indikator as $indicator) {
                $rows->push($this->row('program_opd', $program, $program->sasaran_program ?: $program->nama, $indicator, $hierarchy));
            }
        }

        $activities = OpdKegiatan::query()
            ->whereHas('program', fn ($query) => $query->where('renstra_opd_id', $renstraId))
            ->with([
                'program.sasaran.tujuan',
                'indikator' => fn ($query) => $query->with([
                    'satuanIndikator:id,nama,simbol',
                    'targets' => fn ($query) => $query->where('periode_tahun_id', $periodId),
                ]),
            ])
            ->get()
            ->sortBy(fn (OpdKegiatan $item) => sprintf('%06d-%06d-%010d', $item->program?->urutan ?? 0, $item->urutan, $item->id));

        foreach ($activities as $activity) {
            $program = $activity->program;
            $hierarchy = [
                ['level' => 'Tujuan OPD', 'kode' => null, 'label' => $program?->sasaran?->tujuan?->tujuan],
                ['level' => 'Sasaran Strategis', 'kode' => $program?->sasaran?->kode, 'label' => $program?->sasaran?->sasaran],
                ['level' => 'Program', 'kode' => $program?->kode, 'label' => $program?->nama],
                ['level' => 'Kegiatan', 'kode' => $activity->kode, 'label' => $activity->nama],
            ];
            foreach ($activity->indikator as $indicator) {
                $rows->push($this->row('kegiatan_opd', $activity, $activity->sasaran_kegiatan ?: $activity->nama, $indicator, $hierarchy));
            }
        }

        return $rows;
    }

    /** @param array<int, array<string, mixed>> $hierarchy */
    private function row(string $level, Model $node, string $description, Model $indicator, array $hierarchy): array
    {
        $target = $indicator->targets->first();
        $unit = $indicator->satuanIndikator;

        return [
            'level' => $level,
            'indicator_type' => $indicator->getTable(),
            'indicator_id' => $indicator->getKey(),
            'kode_snapshot' => $node->getAttribute('kode') ?: $indicator->getAttribute('kode'),
            'uraian_snapshot' => $description,
            'indikator_snapshot' => $indicator->getAttribute('indikator'),
            'satuan_indikator_id' => $indicator->getAttribute('satuan_indikator_id'),
            'satuan_snapshot' => $unit?->nama ?: $unit?->simbol,
            'formula_snapshot' => $indicator->getAttribute('formulasi_pengukuran') ?: $indicator->getAttribute('formula'),
            'hierarchy_snapshot' => collect($hierarchy)->filter(fn (array $item) => filled($item['label'] ?? null))->values()->all(),
            'target_renstra' => $target?->target,
            'target_renstra_text' => $target?->target_text,
            'target_renja' => $target?->target,
            'target_renja_text' => $this->targetDisplay($target?->target_text, $target?->target),
            'is_adjusted' => false,
            'alasan_penyesuaian' => null,
        ];
    }

    private function valuesEqual(?string $left, string $right): bool
    {
        $leftNumeric = $this->numericValue($left);
        $rightNumeric = $this->numericValue($right);

        if ($leftNumeric !== null && $rightNumeric !== null) {
            return abs($leftNumeric - $rightNumeric) < 0.0001;
        }

        return mb_strtolower(preg_replace('/\s+/', ' ', trim((string) $left)) ?? '')
            === mb_strtolower(preg_replace('/\s+/', ' ', trim($right)) ?? '');
    }

    private function numericValue(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        $normalized = str_replace(' ', '', trim((string) $value));
        if (! preg_match('/^-?\d+(?:[.,]\d+)?$/', $normalized)) {
            return null;
        }

        return (float) str_replace(',', '.', $normalized);
    }
}
