<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sub_kegiatan_pemerintahan', function (Blueprint $table) {
            if (! Schema::hasColumn('sub_kegiatan_pemerintahan', 'sasaran_sub_kegiatan')) {
                $table->text('sasaran_sub_kegiatan')->nullable()->after('nama');
            }

            if (! Schema::hasColumn('sub_kegiatan_pemerintahan', 'indikator_sub_kegiatan')) {
                $table->text('indikator_sub_kegiatan')->nullable()->after('sasaran_sub_kegiatan');
            }

            if (! Schema::hasColumn('sub_kegiatan_pemerintahan', 'satuan_indikator_id')) {
                $table->foreignId('satuan_indikator_id')
                    ->nullable()
                    ->after('indikator_sub_kegiatan')
                    ->constrained('satuan_indikator')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('sub_kegiatan_pemerintahan', 'definisi_operasional')) {
                $table->text('definisi_operasional')->nullable()->after('satuan_indikator_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('sub_kegiatan_pemerintahan', function (Blueprint $table) {
            if (Schema::hasColumn('sub_kegiatan_pemerintahan', 'definisi_operasional')) {
                $table->dropColumn('definisi_operasional');
            }

            if (Schema::hasColumn('sub_kegiatan_pemerintahan', 'satuan_indikator_id')) {
                $table->dropConstrainedForeignId('satuan_indikator_id');
            }

            if (Schema::hasColumn('sub_kegiatan_pemerintahan', 'indikator_sub_kegiatan')) {
                $table->dropColumn('indikator_sub_kegiatan');
            }

            if (Schema::hasColumn('sub_kegiatan_pemerintahan', 'sasaran_sub_kegiatan')) {
                $table->dropColumn('sasaran_sub_kegiatan');
            }
        });
    }
};
