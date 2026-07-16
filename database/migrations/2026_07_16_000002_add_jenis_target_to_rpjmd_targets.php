<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach ($this->targetTables() as $tableName) {
            if (! Schema::hasColumn($tableName, 'jenis_target')) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->string('jenis_target', 30)->default('tahunan')->after('periode_tahun_id')->index();
                });
            }
        }

        $this->markPrakiraanMajuTargets();
    }

    public function down(): void
    {
        foreach ($this->targetTables() as $tableName) {
            if (Schema::hasColumn($tableName, 'jenis_target')) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->dropColumn('jenis_target');
                });
            }
        }
    }

    /**
     * @return array<int, string>
     */
    private function targetTables(): array
    {
        return [
            'target_indikator_tujuan_daerah',
            'target_indikator_sasaran_daerah',
            'target_indikator_program_rpjmd',
        ];
    }

    private function markPrakiraanMajuTargets(): void
    {
        DB::table('target_indikator_tujuan_daerah')
            ->whereExists(function ($query) {
                $query->selectRaw('1')
                    ->from('indikator_tujuan_daerah as indikator')
                    ->join('tujuan_daerah as tujuan', 'tujuan.id', '=', 'indikator.tujuan_daerah_id')
                    ->join('rpjmd_visi as visi', 'visi.id', '=', 'tujuan.rpjmd_visi_id')
                    ->join('rpjmd', 'rpjmd.id', '=', 'visi.rpjmd_id')
                    ->join('periode_tahun as periode', 'periode.id', '=', 'target_indikator_tujuan_daerah.periode_tahun_id')
                    ->whereColumn('indikator.id', 'target_indikator_tujuan_daerah.indikator_tujuan_daerah_id')
                    ->whereColumn('periode.tahun', '>', 'rpjmd.tahun_akhir');
            })
            ->update(['jenis_target' => 'prakiraan_maju']);

        DB::table('target_indikator_sasaran_daerah')
            ->whereExists(function ($query) {
                $query->selectRaw('1')
                    ->from('indikator_sasaran_daerah as indikator')
                    ->join('sasaran_daerah as sasaran', 'sasaran.id', '=', 'indikator.sasaran_daerah_id')
                    ->join('tujuan_daerah as tujuan', 'tujuan.id', '=', 'sasaran.tujuan_daerah_id')
                    ->join('rpjmd_visi as visi', 'visi.id', '=', 'tujuan.rpjmd_visi_id')
                    ->join('rpjmd', 'rpjmd.id', '=', 'visi.rpjmd_id')
                    ->join('periode_tahun as periode', 'periode.id', '=', 'target_indikator_sasaran_daerah.periode_tahun_id')
                    ->whereColumn('indikator.id', 'target_indikator_sasaran_daerah.indikator_sasaran_daerah_id')
                    ->whereColumn('periode.tahun', '>', 'rpjmd.tahun_akhir');
            })
            ->update(['jenis_target' => 'prakiraan_maju']);

        DB::table('target_indikator_program_rpjmd')
            ->whereExists(function ($query) {
                $query->selectRaw('1')
                    ->from('indikator_program_rpjmd as indikator')
                    ->join('program_rpjmd as program', 'program.id', '=', 'indikator.program_rpjmd_id')
                    ->join('sasaran_daerah as sasaran', 'sasaran.id', '=', 'program.sasaran_daerah_id')
                    ->join('tujuan_daerah as tujuan', 'tujuan.id', '=', 'sasaran.tujuan_daerah_id')
                    ->join('rpjmd_visi as visi', 'visi.id', '=', 'tujuan.rpjmd_visi_id')
                    ->join('rpjmd', 'rpjmd.id', '=', 'visi.rpjmd_id')
                    ->join('periode_tahun as periode', 'periode.id', '=', 'target_indikator_program_rpjmd.periode_tahun_id')
                    ->whereColumn('indikator.id', 'target_indikator_program_rpjmd.indikator_program_rpjmd_id')
                    ->whereColumn('periode.tahun', '>', 'rpjmd.tahun_akhir');
            })
            ->update(['jenis_target' => 'prakiraan_maju']);
    }
};
