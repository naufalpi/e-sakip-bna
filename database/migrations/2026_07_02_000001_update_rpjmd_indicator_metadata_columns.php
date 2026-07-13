<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * @var array<int, string>
     */
    private array $tables = [
        'indikator_tujuan_daerah',
        'indikator_sasaran_daerah',
        'indikator_program_rpjmd',
    ];

    public function up(): void
    {
        foreach ($this->tables as $tableName) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName): void {
                if (! Schema::hasColumn($tableName, 'definisi_operasional')) {
                    $table->text('definisi_operasional')->nullable();
                }

                if (! Schema::hasColumn($tableName, 'alasan_pemilihan')) {
                    $table->text('alasan_pemilihan')->nullable();
                }

                if (! Schema::hasColumn($tableName, 'formulasi_pengukuran')) {
                    $table->text('formulasi_pengukuran')->nullable();
                }

                if (! Schema::hasColumn($tableName, 'tipe_perhitungan')) {
                    $table->string('tipe_perhitungan', 30)->default('non_kumulatif')->index();
                }

                if (! Schema::hasColumn($tableName, 'opd_id')) {
                    $table->foreignId('opd_id')->nullable()->constrained('opds')->nullOnDelete();
                }
            });

            if (Schema::hasColumn($tableName, 'formula') && Schema::hasColumn($tableName, 'formulasi_pengukuran')) {
                DB::table($tableName)
                    ->whereNull('formulasi_pengukuran')
                    ->update(['formulasi_pengukuran' => DB::raw('formula')]);
            }

            $this->dropIndexIfExistsForSqlite($tableName, 'tipe_indikator');

            Schema::table($tableName, function (Blueprint $table) use ($tableName): void {
                if (Schema::hasColumn($tableName, 'tipe_indikator')) {
                    $table->dropColumn('tipe_indikator');
                }

                if (Schema::hasColumn($tableName, 'formula')) {
                    $table->dropColumn('formula');
                }
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $tableName) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName): void {
                if (! Schema::hasColumn($tableName, 'formula')) {
                    $table->text('formula')->nullable();
                }

                if (! Schema::hasColumn($tableName, 'tipe_indikator')) {
                    $table->string('tipe_indikator', 20)->default('positif')->index();
                }
            });

            if (Schema::hasColumn($tableName, 'formula') && Schema::hasColumn($tableName, 'formulasi_pengukuran')) {
                DB::table($tableName)
                    ->whereNull('formula')
                    ->update(['formula' => DB::raw('formulasi_pengukuran')]);
            }

            $this->dropIndexIfExistsForSqlite($tableName, 'tipe_perhitungan');

            Schema::table($tableName, function (Blueprint $table) use ($tableName): void {
                if (Schema::hasColumn($tableName, 'opd_id')) {
                    $table->dropConstrainedForeignId('opd_id');
                }

                foreach (['definisi_operasional', 'alasan_pemilihan', 'formulasi_pengukuran', 'tipe_perhitungan'] as $column) {
                    if (Schema::hasColumn($tableName, $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }

    private function dropIndexIfExistsForSqlite(string $tableName, string $column): void
    {
        if (DB::connection()->getDriverName() !== 'sqlite') {
            return;
        }

        DB::statement(sprintf('drop index if exists "%s_%s_index"', $tableName, $column));
    }
};
