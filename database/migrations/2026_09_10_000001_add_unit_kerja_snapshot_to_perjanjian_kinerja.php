<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('perjanjian_kinerja', function (Blueprint $table): void {
            $table->string('unit_kerja_snapshot')->nullable()->after('jabatan_snapshot');
        });
    }

    public function down(): void
    {
        Schema::table('perjanjian_kinerja', function (Blueprint $table): void {
            $table->dropColumn('unit_kerja_snapshot');
        });
    }
};
