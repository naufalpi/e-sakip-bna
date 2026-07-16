<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('opd_program', function (Blueprint $table) {
            $table->foreignId('program_pemerintahan_id')
                ->nullable()
                ->after('program_rpjmd_id')
                ->constrained('program_pemerintahan')
                ->nullOnDelete();

            $table->index('program_pemerintahan_id');
        });

        Schema::table('opd_kegiatan', function (Blueprint $table) {
            $table->foreignId('kegiatan_pemerintahan_id')
                ->nullable()
                ->after('opd_program_id')
                ->constrained('kegiatan_pemerintahan')
                ->nullOnDelete();

            $table->index('kegiatan_pemerintahan_id');
        });

        Schema::table('opd_sub_kegiatan', function (Blueprint $table) {
            $table->foreignId('sub_kegiatan_pemerintahan_id')
                ->nullable()
                ->after('opd_kegiatan_id')
                ->constrained('sub_kegiatan_pemerintahan')
                ->nullOnDelete();

            $table->foreignId('opd_unit_id')
                ->nullable()
                ->after('sub_kegiatan_pemerintahan_id')
                ->constrained('opd_units')
                ->nullOnDelete();

            $table->index('sub_kegiatan_pemerintahan_id');
            $table->index('opd_unit_id');
        });

        DB::statement(<<<'SQL'
            UPDATE opd_program
            SET program_pemerintahan_id = program_pemerintahan.id
            FROM program_pemerintahan
            WHERE opd_program.program_pemerintahan_id IS NULL
              AND opd_program.kode = program_pemerintahan.kode
              AND opd_program.deleted_at IS NULL
              AND program_pemerintahan.deleted_at IS NULL
        SQL);

        DB::statement(<<<'SQL'
            UPDATE opd_kegiatan
            SET kegiatan_pemerintahan_id = kegiatan_pemerintahan.id
            FROM kegiatan_pemerintahan
            WHERE opd_kegiatan.kegiatan_pemerintahan_id IS NULL
              AND opd_kegiatan.kode = kegiatan_pemerintahan.kode
              AND opd_kegiatan.deleted_at IS NULL
              AND kegiatan_pemerintahan.deleted_at IS NULL
        SQL);

        DB::statement(<<<'SQL'
            UPDATE opd_sub_kegiatan
            SET sub_kegiatan_pemerintahan_id = sub_kegiatan_pemerintahan.id
            FROM sub_kegiatan_pemerintahan
            WHERE opd_sub_kegiatan.sub_kegiatan_pemerintahan_id IS NULL
              AND opd_sub_kegiatan.kode = sub_kegiatan_pemerintahan.kode
              AND opd_sub_kegiatan.deleted_at IS NULL
              AND sub_kegiatan_pemerintahan.deleted_at IS NULL
        SQL);
    }

    public function down(): void
    {
        Schema::table('opd_sub_kegiatan', function (Blueprint $table) {
            $table->dropConstrainedForeignId('opd_unit_id');
            $table->dropConstrainedForeignId('sub_kegiatan_pemerintahan_id');
        });

        Schema::table('opd_kegiatan', function (Blueprint $table) {
            $table->dropConstrainedForeignId('kegiatan_pemerintahan_id');
        });

        Schema::table('opd_program', function (Blueprint $table) {
            $table->dropConstrainedForeignId('program_pemerintahan_id');
        });
    }
};
