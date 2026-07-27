<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasColumn('tujuan_opd', 'kode')) {
            Schema::table('tujuan_opd', function (Blueprint $table) {
                $table->dropColumn('kode');
            });
        }

        foreach ($this->indicatorTables() as $tableName) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                if (! Schema::hasColumn($tableName, 'definisi_operasional')) {
                    $table->text('definisi_operasional')->nullable();
                }

                if (! Schema::hasColumn($tableName, 'formulasi_pengukuran')) {
                    $table->text('formulasi_pengukuran')->nullable();
                }

                if (! Schema::hasColumn($tableName, 'tipe_perhitungan')) {
                    $table->string('tipe_perhitungan', 30)->default('non_kumulatif')->index();
                }

                if (! Schema::hasColumn($tableName, 'opd_penanggung_jawab_id')) {
                    $table->foreignId('opd_penanggung_jawab_id')->nullable()->constrained('opds')->nullOnDelete();
                }
            });

            if (Schema::hasColumn($tableName, 'formula') && Schema::hasColumn($tableName, 'formulasi_pengukuran')) {
                DB::table($tableName)
                    ->whereNull('formulasi_pengukuran')
                    ->whereNotNull('formula')
                    ->update(['formulasi_pengukuran' => DB::raw('formula')]);
            }
        }

        foreach (['target_indikator_opd_kegiatan', 'target_indikator_sub_kegiatan'] as $tableName) {
            if (Schema::hasTable($tableName) && ! Schema::hasColumn($tableName, 'pagu')) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->decimal('pagu', 20, 2)->nullable();
                });
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach (['target_indikator_sub_kegiatan', 'target_indikator_opd_kegiatan'] as $tableName) {
            if (Schema::hasTable($tableName) && Schema::hasColumn($tableName, 'pagu')) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->dropColumn('pagu');
                });
            }
        }

        foreach (array_reverse($this->indicatorTables()) as $tableName) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                if (Schema::hasColumn($tableName, 'opd_penanggung_jawab_id')) {
                    $table->dropConstrainedForeignId('opd_penanggung_jawab_id');
                }

                foreach (['definisi_operasional', 'formulasi_pengukuran', 'tipe_perhitungan'] as $column) {
                    if (Schema::hasColumn($tableName, $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        if (! Schema::hasColumn('tujuan_opd', 'kode')) {
            Schema::table('tujuan_opd', function (Blueprint $table) {
                $table->string('kode', 50)->nullable();
            });
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
