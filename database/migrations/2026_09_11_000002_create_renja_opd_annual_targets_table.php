<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('renja_opd_annual_targets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('renja_opd_id')->constrained('renja_opd')->cascadeOnDelete();
            $table->string('level', 40)->index();
            $table->string('indicator_type', 80);
            $table->unsignedBigInteger('indicator_id');
            $table->string('kode_snapshot', 100)->nullable();
            $table->text('uraian_snapshot');
            $table->text('indikator_snapshot');
            $table->foreignId('satuan_indikator_id')->nullable()->constrained('satuan_indikator')->nullOnDelete();
            $table->string('satuan_snapshot')->nullable();
            $table->text('formula_snapshot')->nullable();
            $table->json('hierarchy_snapshot')->nullable();
            $table->decimal('target_renstra', 18, 4)->nullable();
            $table->text('target_renstra_text')->nullable();
            $table->decimal('target_renja', 18, 4)->nullable();
            $table->text('target_renja_text')->nullable();
            $table->boolean('is_adjusted')->default(false)->index();
            $table->text('alasan_penyesuaian')->nullable();
            $table->string('bootstrap_source', 40)->default('renstra_initial');
            $table->unsignedInteger('urutan')->default(1);
            $table->timestamps();

            $table->unique(
                ['renja_opd_id', 'indicator_type', 'indicator_id'],
                'renja_annual_target_source_unique'
            );
            $table->index(['renja_opd_id', 'level', 'urutan'], 'renja_annual_target_level_order_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('renja_opd_annual_targets');
    }
};
