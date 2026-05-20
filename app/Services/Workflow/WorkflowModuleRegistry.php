<?php

namespace App\Services\Workflow;

use App\Models\EvaluasiSakip;
use App\Models\PerjanjianKinerja;
use App\Models\RealisasiKinerja;
use App\Models\RencanaAksi;
use App\Models\RenstraOpd;
use App\Models\Rpjmd;
use App\Models\TindakLanjutRekomendasi;
use Illuminate\Database\Eloquent\Model;

class WorkflowModuleRegistry
{
    /**
     * @return class-string<Model>
     */
    public function modelClass(string $module): string
    {
        return match ($module) {
            'rpjmd' => Rpjmd::class,
            'renstra_opd' => RenstraOpd::class,
            'perjanjian_kinerja' => PerjanjianKinerja::class,
            'rencana_aksi' => RencanaAksi::class,
            'realisasi_kinerja' => RealisasiKinerja::class,
            'evaluasi_sakip' => EvaluasiSakip::class,
            'tindak_lanjut_rekomendasi' => TindakLanjutRekomendasi::class,
            'lkjip' => class_exists('App\\Models\\Lkjip') ? 'App\\Models\\Lkjip' : abort(404),
            default => abort(404),
        };
    }

    public function label(string $module): string
    {
        return match ($module) {
            'rpjmd' => 'RPJMD',
            'renstra_opd' => 'Renstra OPD',
            'perjanjian_kinerja' => 'Perjanjian Kinerja',
            'rencana_aksi' => 'Rencana Aksi',
            'realisasi_kinerja' => 'Realisasi Kinerja',
            'evaluasi_sakip' => 'Evaluasi SAKIP',
            'tindak_lanjut_rekomendasi' => 'Tindak Lanjut Rekomendasi',
            'lkjip' => 'LKJIP',
            default => str($module)->replace('_', ' ')->title()->toString(),
        };
    }

    /**
     * @return array<int, string>
     */
    public function reviewerRoles(string $module): array
    {
        return match ($module) {
            'rpjmd' => ['super_admin', 'admin_kabupaten_bapperida', 'admin_kabupaten_bagian_organisasi'],
            'renstra_opd', 'perjanjian_kinerja', 'rencana_aksi', 'realisasi_kinerja', 'lkjip' => ['super_admin', 'admin_kabupaten_bagian_organisasi'],
            'evaluasi_sakip', 'tindak_lanjut_rekomendasi' => ['super_admin', 'admin_kabupaten_inspektorat'],
            default => ['super_admin'],
        };
    }
}
