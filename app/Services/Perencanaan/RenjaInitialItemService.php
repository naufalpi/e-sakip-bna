<?php

namespace App\Services\Perencanaan;

use App\Models\OpdSubKegiatan;
use App\Models\PeriodeTahun;
use App\Models\RenjaOpd;
use App\Models\RenjaOpdItem;
use App\Models\RenstraOpd;
use App\Models\SubKegiatanPemerintahan;
use App\Models\TargetIndikatorSubKegiatan;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RenjaInitialItemService
{
    /**
     * Mengisi struktur awal RENJA tepat satu kali. Baris yang sudah pernah ada,
     * termasuk yang telah dihapus user, mencegah bootstrap dijalankan ulang.
     *
     * @return array{copied: int, skipped: int, bootstrapped: bool}
     */
    public function bootstrapFromRenstra(RenjaOpd $renja): array
    {
        return DB::transaction(function () use ($renja): array {
            /** @var RenjaOpd $renja */
            $renja = RenjaOpd::query()->lockForUpdate()->findOrFail($renja->id);

            if (RenjaOpdItem::withTrashed()->where('renja_opd_id', $renja->id)->exists()) {
                return ['copied' => 0, 'skipped' => 0, 'bootstrapped' => false];
            }

            if (! $renja->renstra_opd_id) {
                throw ValidationException::withMessages([
                    'renstra_opd_id' => 'RENSTRA resmi wajib dipilih sebagai sumber RENJA.',
                ]);
            }

            $renstra = RenstraOpd::query()->find($renja->renstra_opd_id, [
                'id',
                'opd_id',
                'tahun_awal',
                'tahun_akhir',
                'status',
                'is_active_version',
            ]);

            if (! $renstra
                || (int) $renstra->opd_id !== (int) $renja->opd_id
                || ! in_array($renstra->status, ['approved', 'locked'], true)
                || ! $renstra->is_active_version
                || (int) $renstra->tahun_awal > (int) $renja->tahun
                || (int) $renstra->tahun_akhir < (int) $renja->tahun) {
                throw ValidationException::withMessages([
                    'renstra_opd_id' => 'RENJA hanya dapat menyalin RENSTRA aktif yang sudah disetujui atau dikunci, berasal dari OPD yang sama, dan mencakup tahun RENJA.',
                ]);
            }

            $finalPeriodId = $renstra?->tahun_akhir
                ? PeriodeTahun::query()->where('tahun', $renstra->tahun_akhir)->value('id')
                : null;

            $sources = OpdSubKegiatan::query()
                ->join('opd_kegiatan', 'opd_kegiatan.id', '=', 'opd_sub_kegiatan.opd_kegiatan_id')
                ->join('opd_program', 'opd_program.id', '=', 'opd_kegiatan.opd_program_id')
                ->where('opd_program.renstra_opd_id', $renja->renstra_opd_id)
                ->whereNull('opd_program.deleted_at')
                ->whereNull('opd_kegiatan.deleted_at')
                ->when($renja->opd_unit_id, fn ($query) => $query
                    ->where('opd_sub_kegiatan.opd_unit_id', $renja->opd_unit_id))
                ->with([
                    'kegiatan.program:id,renstra_opd_id,program_pemerintahan_id,kode,nama,urutan',
                    'subKegiatanPemerintahan:id,periode_tahun_id,kegiatan_pemerintahan_id,kode,nama,indikator_sub_kegiatan',
                    'subKegiatanPemerintahan.kegiatanPemerintahan:id,periode_tahun_id,program_pemerintahan_id,kode,nama',
                    'indikator:id,opd_sub_kegiatan_id,indikator,urutan',
                    'indikator.targets' => fn ($query) => $query
                        ->when($finalPeriodId, fn ($query) => $query->where('periode_tahun_id', $finalPeriodId), fn ($query) => $query->whereRaw('1 = 0'))
                        ->orderBy('id'),
                ])
                ->orderBy('opd_program.urutan')
                ->orderBy('opd_program.id')
                ->orderBy('opd_kegiatan.urutan')
                ->orderBy('opd_kegiatan.id')
                ->orderBy('opd_sub_kegiatan.urutan')
                ->orderBy('opd_sub_kegiatan.id')
                ->get(['opd_sub_kegiatan.*'])
                ->unique(function (OpdSubKegiatan $source): string {
                    $master = $source->subKegiatanPemerintahan;
                    $kegiatan = $master?->kegiatanPemerintahan;
                    $programId = $source->kegiatan?->program?->program_pemerintahan_id
                        ?: $kegiatan?->program_pemerintahan_id;

                    return implode('|', [
                        $programId ?: 'program-'.$source->kegiatan?->program?->id,
                        $kegiatan?->kode ?: 'kegiatan-'.$source->opd_kegiatan_id,
                        $master?->kode ?: 'sub-'.$source->sub_kegiatan_pemerintahan_id,
                    ]);
                })
                ->values();

            $copied = 0;
            $skipped = 0;

            foreach ($sources as $source) {
                $target = $this->targetSubKegiatan($source, $renja);

                if (! $target || ! $target->kegiatanPemerintahan?->programPemerintahan) {
                    $skipped++;

                    continue;
                }

                $indicatorRows = $source->indikator
                    ->filter(fn ($indicator) => filled(trim((string) $indicator->indikator)))
                    ->unique(fn ($indicator) => trim((string) $indicator->indikator))
                    ->values();
                $indicators = $indicatorRows
                    ->map(fn ($indicator) => trim((string) $indicator->indikator));
                $targets = $indicatorRows
                    ->map(fn ($indicator) => $this->targetText($indicator->targets->first()));
                $hasTarget = $targets->contains(fn (?string $target) => $target !== null);

                $renja->items()->create([
                    'opd_sub_kegiatan_id' => $source->id,
                    'sumber_item' => 'renstra',
                    'program_pemerintahan_id' => $target->kegiatanPemerintahan->programPemerintahan->id,
                    'kegiatan_pemerintahan_id' => $target->kegiatanPemerintahan->id,
                    'sub_kegiatan_pemerintahan_id' => $target->id,
                    'indikator_sub_kegiatan_id' => $source->indikator->first()?->id,
                    'kode' => $target->kode,
                    'nama_sub_kegiatan' => $target->nama,
                    'indikator' => $indicators->isNotEmpty()
                        ? $indicators->implode("\n")
                        : $target->indikator_sub_kegiatan,
                    'target_akhir_renstra' => $hasTarget
                        ? $targets->map(fn (?string $target) => $target ?? '-')->implode("\n")
                        : null,
                    'status' => 'draft',
                    'urutan' => ++$copied,
                ]);
            }

            return ['copied' => $copied, 'skipped' => $skipped, 'bootstrapped' => true];
        });
    }

    private function targetSubKegiatan(OpdSubKegiatan $source, RenjaOpd $renja): ?SubKegiatanPemerintahan
    {
        $sourceMaster = $source->subKegiatanPemerintahan;
        $sourceKegiatan = $sourceMaster?->kegiatanPemerintahan;
        $programId = $source->kegiatan?->program?->program_pemerintahan_id
            ?: $sourceKegiatan?->program_pemerintahan_id;

        if (! $sourceMaster || ! $sourceKegiatan || ! $programId) {
            return null;
        }

        if ((int) $sourceMaster->periode_tahun_id === (int) $renja->periode_tahun_id) {
            return $sourceMaster->loadMissing('kegiatanPemerintahan.programPemerintahan');
        }

        return SubKegiatanPemerintahan::query()
            ->with('kegiatanPemerintahan.programPemerintahan')
            ->where('periode_tahun_id', $renja->periode_tahun_id)
            ->where('kode', $sourceMaster->kode)
            ->where('status', 'active')
            ->whereHas('kegiatanPemerintahan', fn ($query) => $query
                ->where('periode_tahun_id', $renja->periode_tahun_id)
                ->where('program_pemerintahan_id', $programId)
                ->where('kode', $sourceKegiatan->kode)
                ->where('status', 'active'))
            ->first();
    }

    private function targetText(?TargetIndikatorSubKegiatan $target): ?string
    {
        if (! $target) {
            return null;
        }

        if (filled($target->target_text)) {
            return trim((string) $target->target_text);
        }

        if ($target->target === null) {
            return null;
        }

        return rtrim(rtrim(number_format((float) $target->target, 4, '.', ''), '0'), '.');
    }
}
