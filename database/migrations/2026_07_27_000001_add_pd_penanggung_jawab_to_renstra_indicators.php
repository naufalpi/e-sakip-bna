<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        foreach ($this->indicatorTables() as $tableName) {
            if (! Schema::hasColumn($tableName, 'pd_penanggung_jawab')) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->string('pd_penanggung_jawab')->nullable()->after('opd_penanggung_jawab_id');
                });
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach (array_reverse($this->indicatorTables()) as $tableName) {
            if (Schema::hasColumn($tableName, 'pd_penanggung_jawab')) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->dropColumn('pd_penanggung_jawab');
                });
            }
        }
    }

    /**
     * @return array<int, string>
     */
    private function indicatorTables(): array
    {
        return [
            'indikator_tujuan_opd',
            'indikator_sasaran_opd',
            'indikator_opd_program',
            'indikator_opd_kegiatan',
            'indikator_sub_kegiatan',
        ];
    }
};
