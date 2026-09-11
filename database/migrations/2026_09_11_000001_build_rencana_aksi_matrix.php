<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rencana_aksi', function (Blueprint $table) {
            $table->foreignId('renstra_opd_id')->nullable()->after('perjanjian_kinerja_id')->constrained('renstra_opd')->nullOnDelete();
            $table->foreignId('dpa_opd_id')->nullable()->after('renstra_opd_id')->constrained('dpa_opd')->nullOnDelete();
            $table->timestamp('snapshot_dibuat_pada')->nullable()->after('catatan');
            $table->unsignedSmallInteger('format_version')->default(1)->after('snapshot_dibuat_pada')->index();
        });

        Schema::table('rencana_aksi_items', function (Blueprint $table) {
            $table->foreignId('parent_id')->nullable()->after('rencana_aksi_id')->constrained('rencana_aksi_items')->nullOnDelete();
            $table->string('source_type', 50)->nullable()->after('parent_id');
            $table->unsignedBigInteger('source_id')->nullable()->after('source_type');
            $table->string('level', 30)->nullable()->after('source_id')->index();
            $table->string('kode_snapshot', 100)->nullable()->after('level');
            $table->text('uraian_snapshot')->nullable()->after('kode_snapshot');
            $table->text('formula_snapshot')->nullable()->after('indikator');
            $table->string('satuan_snapshot')->nullable()->after('formula_snapshot');
            $table->string('tipe_perhitungan_snapshot', 30)->nullable()->after('satuan_snapshot');
            $table->boolean('is_snapshot')->default(false)->after('tipe_perhitungan_snapshot')->index();

            $table->index(['rencana_aksi_id', 'level', 'urutan'], 'rencana_aksi_items_matrix_index');
            $table->index(['source_type', 'source_id'], 'rencana_aksi_items_source_index');
        });

        Schema::create('rencana_aksi_target_triwulan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rencana_aksi_item_id')->constrained('rencana_aksi_items')->cascadeOnDelete();
            $table->unsignedTinyInteger('triwulan');
            $table->decimal('target', 18, 4)->nullable();
            $table->string('target_text')->nullable();
            $table->timestamps();

            $table->unique(['rencana_aksi_item_id', 'triwulan'], 'rencana_aksi_item_triwulan_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rencana_aksi_target_triwulan');

        Schema::table('rencana_aksi_items', function (Blueprint $table) {
            $table->dropIndex('rencana_aksi_items_matrix_index');
            $table->dropIndex('rencana_aksi_items_source_index');
            $table->dropConstrainedForeignId('parent_id');
            $table->dropColumn([
                'source_type', 'source_id', 'level', 'kode_snapshot', 'uraian_snapshot',
                'formula_snapshot', 'satuan_snapshot', 'tipe_perhitungan_snapshot', 'is_snapshot',
            ]);
        });

        Schema::table('rencana_aksi', function (Blueprint $table) {
            $table->dropConstrainedForeignId('renstra_opd_id');
            $table->dropConstrainedForeignId('dpa_opd_id');
            $table->dropColumn(['snapshot_dibuat_pada', 'format_version']);
        });
    }
};
