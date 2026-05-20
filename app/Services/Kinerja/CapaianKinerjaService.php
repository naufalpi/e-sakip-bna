<?php

namespace App\Services\Kinerja;

use App\Models\RealisasiKinerja;
use App\Models\RealisasiProgram;
use Illuminate\Support\Collection;

class CapaianKinerjaService
{
    public function calculateCapaian(mixed $target, mixed $realisasi, string $tipeIndikator = 'positif'): ?float
    {
        if (! is_numeric($target) || ! is_numeric($realisasi) || (float) $target == 0.0) {
            return null;
        }

        $target = (float) $target;
        $realisasi = (float) $realisasi;

        $capaian = $tipeIndikator === 'negatif'
            ? (((2 * $target) - $realisasi) / $target) * 100
            : ($realisasi / $target) * 100;

        return round(max($capaian, 0), 2);
    }

    public function determineStatusCapaian(mixed $capaianPersen): ?string
    {
        if (! is_numeric($capaianPersen)) {
            return null;
        }

        $capaianPersen = (float) $capaianPersen;

        return match (true) {
            $capaianPersen < 70 => 'merah',
            $capaianPersen <= 90 => 'kuning',
            default => 'hijau',
        };
    }

    public function calculateSerapanAnggaran(mixed $pagu, mixed $realisasiAnggaran): ?float
    {
        if (! is_numeric($pagu) || ! is_numeric($realisasiAnggaran) || (float) $pagu == 0.0) {
            return null;
        }

        return round(((float) $realisasiAnggaran / (float) $pagu) * 100, 2);
    }

    public function determineEfisiensi(mixed $capaianPersen, mixed $serapanAnggaranPersen): ?string
    {
        if (! is_numeric($capaianPersen) || ! is_numeric($serapanAnggaranPersen)) {
            return null;
        }

        $capaianPersen = (float) $capaianPersen;
        $serapanAnggaranPersen = (float) $serapanAnggaranPersen;
        $selisih = $capaianPersen - $serapanAnggaranPersen;

        return match (true) {
            $selisih > 5 => 'efisien',
            abs($selisih) <= 5 => 'cukup_efisien',
            default => 'tidak_efisien',
        };
    }

    public function syncRealisasiKinerjaSummary(RealisasiKinerja $realisasiKinerja): void
    {
        /** @var Collection<int, RealisasiProgram> $programs */
        $programs = $realisasiKinerja->programs()->get();

        $targetAnggaran = $programs->sum(fn ($program) => (float) ($program->anggaran ?? 0));
        $realisasiAnggaran = $programs->sum(fn ($program) => (float) ($program->realisasi_anggaran ?? 0));
        $capaianPersen = $programs
            ->filter(fn ($program) => $program->capaian_persen !== null)
            ->avg(fn ($program) => (float) $program->capaian_persen);

        $serapanAnggaran = $this->calculateSerapanAnggaran($targetAnggaran, $realisasiAnggaran);
        $capaian = $capaianPersen !== null ? round((float) $capaianPersen, 2) : null;

        $realisasiKinerja->forceFill([
            'target_anggaran' => $targetAnggaran > 0 ? $targetAnggaran : null,
            'realisasi_anggaran' => $realisasiAnggaran > 0 ? $realisasiAnggaran : null,
            'serapan_anggaran_persen' => $serapanAnggaran,
            'capaian_persen' => $capaian,
            'status_capaian' => $this->determineStatusCapaian($capaian),
            'status_efisiensi' => $this->determineEfisiensi($capaian, $serapanAnggaran),
        ])->save();
    }
}
