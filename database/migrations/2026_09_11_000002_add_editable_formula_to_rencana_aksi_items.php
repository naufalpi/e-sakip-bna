<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rencana_aksi_items', function (Blueprint $table) {
            $table->text('formula')->nullable()->after('formula_snapshot');
        });

        DB::table('rencana_aksi_items')
            ->where('is_snapshot', true)
            ->update(['formula' => DB::raw('formula_snapshot')]);
    }

    public function down(): void
    {
        Schema::table('rencana_aksi_items', function (Blueprint $table) {
            $table->dropColumn('formula');
        });
    }
};
