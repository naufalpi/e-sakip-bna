<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('perjanjian_kinerja_items', function (Blueprint $table) {
            $table->decimal('target_sumber', 18, 4)->nullable()->after('target');
            $table->string('target_sumber_text')->nullable()->after('target_text');
            $table->boolean('target_disesuaikan')->default(false)->after('target_sumber_text')->index();
        });

        DB::table('perjanjian_kinerja_items')
            ->where('sumber_item', 'snapshot')
            ->update([
                'target_sumber' => DB::raw('target'),
                'target_sumber_text' => DB::raw('target_text'),
            ]);
    }

    public function down(): void
    {
        Schema::table('perjanjian_kinerja_items', function (Blueprint $table) {
            $table->dropColumn(['target_sumber', 'target_sumber_text', 'target_disesuaikan']);
        });
    }
};
