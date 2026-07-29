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
        Schema::create('anggaran_sub_kegiatan_renstra', function (Blueprint $table) {
            $table->id();
            $table->foreignId('opd_sub_kegiatan_id')->constrained('opd_sub_kegiatan')->cascadeOnDelete();
            $table->foreignId('periode_tahun_id')->constrained('periode_tahun')->restrictOnDelete();
            $table->decimal('anggaran', 20, 2)->nullable();
            $table->timestamps();

            $table->unique(['opd_sub_kegiatan_id', 'periode_tahun_id'], 'anggaran_sub_kegiatan_renstra_unique');
            $table->index('periode_tahun_id');
        });

        $nowExpression = DB::connection()->getDriverName() === 'sqlite' ? 'CURRENT_TIMESTAMP' : 'NOW()';

        DB::statement(<<<SQL
            INSERT INTO anggaran_sub_kegiatan_renstra (opd_sub_kegiatan_id, periode_tahun_id, anggaran, created_at, updated_at)
            SELECT
                indikator_sub_kegiatan.opd_sub_kegiatan_id,
                target_indikator_sub_kegiatan.periode_tahun_id,
                SUM(target_indikator_sub_kegiatan.pagu) AS anggaran,
                {$nowExpression},
                {$nowExpression}
            FROM target_indikator_sub_kegiatan
            INNER JOIN indikator_sub_kegiatan
                ON indikator_sub_kegiatan.id = target_indikator_sub_kegiatan.indikator_sub_kegiatan_id
            WHERE target_indikator_sub_kegiatan.pagu IS NOT NULL
            GROUP BY indikator_sub_kegiatan.opd_sub_kegiatan_id, target_indikator_sub_kegiatan.periode_tahun_id
            ON CONFLICT (opd_sub_kegiatan_id, periode_tahun_id)
            DO UPDATE SET
                anggaran = EXCLUDED.anggaran,
                updated_at = {$nowExpression}
        SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('anggaran_sub_kegiatan_renstra');
    }
};
