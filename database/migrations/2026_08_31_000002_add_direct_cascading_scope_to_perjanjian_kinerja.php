<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('perjanjian_kinerja', 'lingkup_kinerja_snapshot')) {
            Schema::table('perjanjian_kinerja', function (Blueprint $table): void {
                $table->json('lingkup_kinerja_snapshot')->nullable()->after('sumber_data');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('perjanjian_kinerja', 'lingkup_kinerja_snapshot')) {
            Schema::table('perjanjian_kinerja', function (Blueprint $table): void {
                $table->dropColumn('lingkup_kinerja_snapshot');
            });
        }
    }
};
