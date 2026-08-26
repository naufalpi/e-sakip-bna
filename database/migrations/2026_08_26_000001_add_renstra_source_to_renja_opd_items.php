<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('renja_opd_items', function (Blueprint $table) {
            $table->foreignId('opd_sub_kegiatan_id')
                ->nullable()
                ->after('renja_opd_id')
                ->constrained('opd_sub_kegiatan')
                ->nullOnDelete();
            $table->string('sumber_item', 20)
                ->default('manual')
                ->after('opd_sub_kegiatan_id')
                ->index();
        });
    }

    public function down(): void
    {
        Schema::table('renja_opd_items', function (Blueprint $table) {
            $table->dropIndex(['sumber_item']);
            $table->dropConstrainedForeignId('opd_sub_kegiatan_id');
            $table->dropColumn('sumber_item');
        });
    }
};
