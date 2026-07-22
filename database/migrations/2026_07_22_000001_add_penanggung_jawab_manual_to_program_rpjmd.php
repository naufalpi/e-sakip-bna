<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('program_rpjmd', 'is_penanggung_jawab_manual')) {
            Schema::table('program_rpjmd', function (Blueprint $table): void {
                $table->boolean('is_penanggung_jawab_manual')
                    ->default(false)
                    ->after('status')
                    ->index('program_rpjmd_penanggung_manual_idx');
            });
        }

        if (Schema::hasTable('program_rpjmd_opd_penanggung_jawab')) {
            DB::table('program_rpjmd_opd_penanggung_jawab')
                ->distinct()
                ->orderBy('program_rpjmd_id')
                ->pluck('program_rpjmd_id')
                ->chunk(500)
                ->each(function ($programIds): void {
                    DB::table('program_rpjmd')
                        ->whereIn('id', $programIds)
                        ->update(['is_penanggung_jawab_manual' => true]);
                });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('program_rpjmd', 'is_penanggung_jawab_manual')) {
            return;
        }

        Schema::table('program_rpjmd', function (Blueprint $table): void {
            $table->dropIndex('program_rpjmd_penanggung_manual_idx');
            $table->dropColumn('is_penanggung_jawab_manual');
        });
    }
};
