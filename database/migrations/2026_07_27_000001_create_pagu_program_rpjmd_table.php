<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pagu_program_rpjmd', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('program_rpjmd_id')->constrained('program_rpjmd')->cascadeOnDelete();
            $table->foreignId('periode_tahun_id')->constrained('periode_tahun')->restrictOnDelete();
            $table->string('jenis_pagu', 30)->default('tahunan')->index();
            $table->decimal('pagu_anggaran', 20, 2)->nullable();
            $table->timestamps();

            $table->unique(['program_rpjmd_id', 'periode_tahun_id'], 'pagu_program_rpjmd_unique');
            $table->index(['periode_tahun_id', 'jenis_pagu'], 'pagu_program_rpjmd_periode_jenis_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pagu_program_rpjmd');
    }
};
