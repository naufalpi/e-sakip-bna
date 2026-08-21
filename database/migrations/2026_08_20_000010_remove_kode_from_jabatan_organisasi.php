<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('jabatan_organisasi', 'kode')) {
            Schema::table('jabatan_organisasi', function (Blueprint $table) {
                $table->dropUnique(['kode']);
                $table->dropColumn('kode');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('jabatan_organisasi', 'kode')) {
            Schema::table('jabatan_organisasi', function (Blueprint $table) {
                $table->string('kode', 120)->nullable()->unique();
            });
        }
    }
};
