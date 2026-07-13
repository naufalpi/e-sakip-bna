<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('strategi_daerah', function (Blueprint $table) {
            $table->string('status', 20)->default('active')->index();
            $table->dropIndex(['sasaran_daerah_id', 'urutan']);
            $table->dropConstrainedForeignId('sasaran_daerah_id');
            $table->index(['status', 'urutan']);
        });
    }

    public function down(): void
    {
        Schema::table('strategi_daerah', function (Blueprint $table) {
            $table->dropIndex(['status', 'urutan']);
            $table->dropColumn('status');
            $table->foreignId('sasaran_daerah_id')
                ->nullable()
                ->constrained('sasaran_daerah')
                ->nullOnDelete();
            $table->index(['sasaran_daerah_id', 'urutan']);
        });
    }
};
