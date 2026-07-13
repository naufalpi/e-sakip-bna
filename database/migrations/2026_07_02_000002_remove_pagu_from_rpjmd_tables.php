<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('program_rpjmd', 'pagu_indikatif')) {
            Schema::table('program_rpjmd', function (Blueprint $table) {
                $table->dropColumn('pagu_indikatif');
            });
        }

        if (Schema::hasColumn('target_indikator_program_rpjmd', 'pagu')) {
            Schema::table('target_indikator_program_rpjmd', function (Blueprint $table) {
                $table->dropColumn('pagu');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('program_rpjmd', 'pagu_indikatif')) {
            Schema::table('program_rpjmd', function (Blueprint $table) {
                $table->decimal('pagu_indikatif', 20, 2)->nullable();
            });
        }

        if (! Schema::hasColumn('target_indikator_program_rpjmd', 'pagu')) {
            Schema::table('target_indikator_program_rpjmd', function (Blueprint $table) {
                $table->decimal('pagu', 20, 2)->nullable();
            });
        }
    }
};
