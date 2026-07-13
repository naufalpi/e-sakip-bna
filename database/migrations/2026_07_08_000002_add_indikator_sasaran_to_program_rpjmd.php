<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('program_rpjmd', 'indikator_sasaran_daerah_id')) {
            Schema::table('program_rpjmd', function (Blueprint $table) {
                $table->foreignId('indikator_sasaran_daerah_id')
                    ->nullable()
                    ->after('sasaran_daerah_id')
                    ->constrained('indikator_sasaran_daerah')
                    ->nullOnDelete();

                $table->index(['indikator_sasaran_daerah_id', 'urutan'], 'program_rpjmd_indikator_sasaran_urutan_idx');
            });
        }

        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement(<<<'SQL'
                UPDATE program_rpjmd program
                SET indikator_sasaran_daerah_id = (
                    SELECT indikator.id
                    FROM indikator_sasaran_daerah indikator
                    WHERE indikator.sasaran_daerah_id = program.sasaran_daerah_id
                      AND indikator.deleted_at IS NULL
                    ORDER BY indikator.urutan, indikator.id
                    LIMIT 1
                )
                WHERE program.indikator_sasaran_daerah_id IS NULL
                  AND program.sasaran_daerah_id IS NOT NULL
            SQL);

            DB::statement(<<<'SQL'
                UPDATE program_rpjmd program
                SET indikator_sasaran_daerah_id = (
                    SELECT indikator.id
                    FROM strategi_daerah strategi
                    JOIN indikator_sasaran_daerah indikator
                      ON indikator.sasaran_daerah_id = strategi.sasaran_daerah_id
                    WHERE strategi.id = program.strategi_daerah_id
                      AND indikator.deleted_at IS NULL
                    ORDER BY indikator.urutan, indikator.id
                    LIMIT 1
                ),
                sasaran_daerah_id = (
                    SELECT strategi.sasaran_daerah_id
                    FROM strategi_daerah strategi
                    WHERE strategi.id = program.strategi_daerah_id
                    LIMIT 1
                )
                WHERE program.indikator_sasaran_daerah_id IS NULL
                  AND program.strategi_daerah_id IS NOT NULL
            SQL);
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('program_rpjmd', 'indikator_sasaran_daerah_id')) {
            return;
        }

        Schema::table('program_rpjmd', function (Blueprint $table) {
            $table->dropForeign(['indikator_sasaran_daerah_id']);
            $table->dropIndex('program_rpjmd_indikator_sasaran_urutan_idx');
            $table->dropColumn('indikator_sasaran_daerah_id');
        });
    }
};
