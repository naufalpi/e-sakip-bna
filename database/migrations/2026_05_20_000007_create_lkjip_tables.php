<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('lkjip', function (Blueprint $table) {
            $table->id();
            $table->foreignId('opd_id')->constrained('opds')->cascadeOnDelete();
            $table->foreignId('periode_tahun_id')->constrained('periode_tahun')->cascadeOnDelete();
            $table->foreignId('perjanjian_kinerja_id')->nullable()->constrained('perjanjian_kinerja')->nullOnDelete();
            $table->foreignId('realisasi_kinerja_id')->nullable()->constrained('realisasi_kinerja')->nullOnDelete();
            $table->foreignId('evaluasi_sakip_id')->nullable()->constrained('evaluasi_sakip')->nullOnDelete();
            $table->unsignedSmallInteger('tahun')->index();
            $table->string('judul');
            $table->string('nomor_dokumen')->nullable();
            $table->text('ringkasan_eksekutif')->nullable();
            $table->string('status', 30)->default('draft')->index();
            $table->text('catatan')->nullable();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['opd_id', 'tahun', 'status']);
            $table->index(['periode_tahun_id', 'status']);
            $table->unique(['opd_id', 'tahun'], 'lkjip_opd_tahun_unique');
        });

        Schema::create('lkjip_bab', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lkjip_id')->constrained('lkjip')->cascadeOnDelete();
            $table->string('kode', 50);
            $table->string('judul');
            $table->string('jenis', 60)->default('bab')->index();
            $table->text('konten')->nullable();
            $table->unsignedSmallInteger('urutan')->default(1);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['lkjip_id', 'urutan']);
            $table->unique(['lkjip_id', 'kode'], 'lkjip_bab_kode_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lkjip_bab');
        Schema::dropIfExists('lkjip');
    }
};
