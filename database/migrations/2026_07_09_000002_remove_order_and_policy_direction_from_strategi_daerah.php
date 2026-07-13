<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('strategi_daerah', function (Blueprint $table) {
            $table->dropIndex(['status', 'urutan']);
            $table->dropColumn(['arah_kebijakan', 'urutan']);
            $table->index(['status', 'strategi']);
        });
    }

    public function down(): void
    {
        Schema::table('strategi_daerah', function (Blueprint $table) {
            $table->dropIndex(['status', 'strategi']);
            $table->text('arah_kebijakan')->nullable();
            $table->unsignedSmallInteger('urutan')->default(1);
            $table->index(['status', 'urutan']);
        });
    }
};
