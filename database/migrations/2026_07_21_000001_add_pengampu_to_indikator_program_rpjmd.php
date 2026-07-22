<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('indikator_program_rpjmd', function (Blueprint $table): void {
            if (! Schema::hasColumn('indikator_program_rpjmd', 'cakupan_pengampu')) {
                $table->string('cakupan_pengampu', 30)->default('opd_tertentu')->index();
            }
        });

        Schema::create('indikator_program_rpjmd_opd_pengampu', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('indikator_program_rpjmd_id')
                ->constrained('indikator_program_rpjmd')
                ->cascadeOnDelete();
            $table->foreignId('opd_id')->constrained('opds')->cascadeOnDelete();
            $table->string('peran', 50)->default('pengampu_data')->index();
            $table->boolean('is_utama')->default(true)->index();
            $table->timestamps();

            $table->unique(
                ['indikator_program_rpjmd_id', 'opd_id', 'peran'],
                'indikator_program_opd_pengampu_unique',
            );
            $table->index('opd_id', 'indikator_program_opd_pengampu_opd_index');
        });

        if (Schema::hasColumn('indikator_program_rpjmd', 'opd_id')) {
            DB::table('indikator_program_rpjmd')
                ->whereNotNull('opd_id')
                ->orderBy('id')
                ->select(['id', 'opd_id'])
                ->chunkById(100, function ($indicators): void {
                    foreach ($indicators as $indicator) {
                        DB::table('indikator_program_rpjmd_opd_pengampu')->updateOrInsert(
                            [
                                'indikator_program_rpjmd_id' => $indicator->id,
                                'opd_id' => $indicator->opd_id,
                                'peran' => 'pengampu_data',
                            ],
                            [
                                'is_utama' => true,
                                'created_at' => now(),
                                'updated_at' => now(),
                            ],
                        );
                    }
                });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('indikator_program_rpjmd_opd_pengampu');

        Schema::table('indikator_program_rpjmd', function (Blueprint $table): void {
            if (Schema::hasColumn('indikator_program_rpjmd', 'cakupan_pengampu')) {
                $table->dropColumn('cakupan_pengampu');
            }
        });
    }
};
