<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('opd_unit_id')
                ->nullable()
                ->after('opd_id')
                ->constrained('opd_units')
                ->nullOnDelete();

            $table->index(['opd_id', 'opd_unit_id']);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['opd_id', 'opd_unit_id']);
            $table->dropConstrainedForeignId('opd_unit_id');
        });
    }
};
