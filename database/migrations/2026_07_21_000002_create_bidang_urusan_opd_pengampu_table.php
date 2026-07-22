<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bidang_urusan_opd_pengampu', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('bidang_urusan_id')
                ->constrained('bidang_urusan')
                ->cascadeOnDelete();
            $table->foreignId('opd_id')
                ->constrained('opds')
                ->cascadeOnDelete();
            $table->string('peran', 50)->default('pengampu_urusan')->index();
            $table->boolean('is_utama')->default(true)->index();
            $table->timestamps();

            $table->unique(
                ['bidang_urusan_id', 'opd_id', 'peran'],
                'bidang_urusan_opd_pengampu_unique',
            );
            $table->index('opd_id', 'bidang_urusan_opd_pengampu_opd_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bidang_urusan_opd_pengampu');
    }
};
