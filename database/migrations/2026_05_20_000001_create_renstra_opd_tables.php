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
        Schema::create('renstra_opd', function (Blueprint $table) {
            $table->id();
            $table->foreignId('opd_id')->constrained('opds')->cascadeOnDelete();
            $table->foreignId('rpjmd_id')->constrained('rpjmd')->cascadeOnDelete();
            $table->foreignId('periode_tahun_id')->nullable()->constrained('periode_tahun')->nullOnDelete();
            $table->string('judul');
            $table->string('nomor_dokumen')->nullable();
            $table->unsignedSmallInteger('tahun_awal')->index();
            $table->unsignedSmallInteger('tahun_akhir')->index();
            $table->string('status', 30)->default('draft')->index();
            $table->text('keterangan')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['opd_id', 'status']);
            $table->index(['rpjmd_id', 'status']);
            $table->index(['status', 'tahun_awal', 'tahun_akhir']);
        });

        Schema::create('tujuan_opd', function (Blueprint $table) {
            $table->id();
            $table->foreignId('renstra_opd_id')->constrained('renstra_opd')->cascadeOnDelete();
            $table->foreignId('tujuan_daerah_id')->nullable()->constrained('tujuan_daerah')->nullOnDelete();
            $table->string('kode', 50)->nullable();
            $table->text('tujuan');
            $table->unsignedSmallInteger('urutan')->default(1);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['renstra_opd_id', 'urutan']);
            $table->index('tujuan_daerah_id');
        });

        Schema::create('indikator_tujuan_opd', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tujuan_opd_id')->constrained('tujuan_opd')->cascadeOnDelete();
            $table->foreignId('indikator_tujuan_daerah_id')->nullable()->constrained('indikator_tujuan_daerah')->nullOnDelete();
            $table->foreignId('satuan_indikator_id')->nullable()->constrained('satuan_indikator')->nullOnDelete();
            $table->string('kode', 50)->nullable();
            $table->text('indikator');
            $table->text('formula')->nullable();
            $table->string('sumber_data')->nullable();
            $table->unsignedSmallInteger('urutan')->default(1);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tujuan_opd_id', 'urutan']);
            $table->index('indikator_tujuan_daerah_id');
        });

        Schema::create('target_indikator_tujuan_opd', function (Blueprint $table) {
            $table->id();
            $table->foreignId('indikator_tujuan_opd_id')->constrained('indikator_tujuan_opd')->cascadeOnDelete();
            $table->foreignId('periode_tahun_id')->constrained('periode_tahun')->cascadeOnDelete();
            $table->decimal('target', 18, 4)->nullable();
            $table->string('target_text')->nullable();
            $table->timestamps();

            $table->unique(['indikator_tujuan_opd_id', 'periode_tahun_id'], 'target_tujuan_opd_unique');
        });

        Schema::create('sasaran_opd', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tujuan_opd_id')->constrained('tujuan_opd')->cascadeOnDelete();
            $table->foreignId('sasaran_daerah_id')->nullable()->constrained('sasaran_daerah')->nullOnDelete();
            $table->string('kode', 50)->nullable();
            $table->text('sasaran');
            $table->unsignedSmallInteger('urutan')->default(1);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tujuan_opd_id', 'urutan']);
            $table->index('sasaran_daerah_id');
        });

        Schema::create('indikator_sasaran_opd', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sasaran_opd_id')->constrained('sasaran_opd')->cascadeOnDelete();
            $table->foreignId('indikator_sasaran_daerah_id')->nullable()->constrained('indikator_sasaran_daerah')->nullOnDelete();
            $table->foreignId('satuan_indikator_id')->nullable()->constrained('satuan_indikator')->nullOnDelete();
            $table->string('kode', 50)->nullable();
            $table->text('indikator');
            $table->text('formula')->nullable();
            $table->string('sumber_data')->nullable();
            $table->unsignedSmallInteger('urutan')->default(1);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['sasaran_opd_id', 'urutan']);
            $table->index('indikator_sasaran_daerah_id');
        });

        Schema::create('target_indikator_sasaran_opd', function (Blueprint $table) {
            $table->id();
            $table->foreignId('indikator_sasaran_opd_id')->constrained('indikator_sasaran_opd')->cascadeOnDelete();
            $table->foreignId('periode_tahun_id')->constrained('periode_tahun')->cascadeOnDelete();
            $table->decimal('target', 18, 4)->nullable();
            $table->string('target_text')->nullable();
            $table->timestamps();

            $table->unique(['indikator_sasaran_opd_id', 'periode_tahun_id'], 'target_sasaran_opd_unique');
        });

        Schema::create('opd_program', function (Blueprint $table) {
            $table->id();
            $table->foreignId('renstra_opd_id')->constrained('renstra_opd')->cascadeOnDelete();
            $table->foreignId('sasaran_opd_id')->constrained('sasaran_opd')->cascadeOnDelete();
            $table->foreignId('program_rpjmd_id')->nullable()->constrained('program_rpjmd')->nullOnDelete();
            $table->string('kode', 80)->nullable()->index();
            $table->string('nama');
            $table->decimal('pagu_indikatif', 20, 2)->nullable();
            $table->string('status', 30)->default('draft')->index();
            $table->unsignedSmallInteger('urutan')->default(1);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['renstra_opd_id', 'status']);
            $table->index(['sasaran_opd_id', 'urutan']);
            $table->index('program_rpjmd_id');
        });

        Schema::create('indikator_opd_program', function (Blueprint $table) {
            $table->id();
            $table->foreignId('opd_program_id')->constrained('opd_program')->cascadeOnDelete();
            $table->foreignId('indikator_program_rpjmd_id')->nullable()->constrained('indikator_program_rpjmd')->nullOnDelete();
            $table->foreignId('satuan_indikator_id')->nullable()->constrained('satuan_indikator')->nullOnDelete();
            $table->string('kode', 50)->nullable();
            $table->text('indikator');
            $table->text('formula')->nullable();
            $table->string('sumber_data')->nullable();
            $table->unsignedSmallInteger('urutan')->default(1);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['opd_program_id', 'urutan']);
            $table->index('indikator_program_rpjmd_id');
        });

        Schema::create('target_indikator_opd_program', function (Blueprint $table) {
            $table->id();
            $table->foreignId('indikator_opd_program_id')->constrained('indikator_opd_program')->cascadeOnDelete();
            $table->foreignId('periode_tahun_id')->constrained('periode_tahun')->cascadeOnDelete();
            $table->decimal('target', 18, 4)->nullable();
            $table->string('target_text')->nullable();
            $table->decimal('pagu', 20, 2)->nullable();
            $table->timestamps();

            $table->unique(['indikator_opd_program_id', 'periode_tahun_id'], 'target_program_opd_unique');
        });

        Schema::create('opd_kegiatan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('opd_program_id')->constrained('opd_program')->cascadeOnDelete();
            $table->string('kode', 80)->nullable()->index();
            $table->string('nama');
            $table->decimal('pagu_indikatif', 20, 2)->nullable();
            $table->unsignedSmallInteger('urutan')->default(1);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['opd_program_id', 'urutan']);
        });

        Schema::create('opd_sub_kegiatan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('opd_kegiatan_id')->constrained('opd_kegiatan')->cascadeOnDelete();
            $table->string('kode', 80)->nullable()->index();
            $table->string('nama');
            $table->decimal('pagu_indikatif', 20, 2)->nullable();
            $table->unsignedSmallInteger('urutan')->default(1);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['opd_kegiatan_id', 'urutan']);
        });

        Schema::create('indikator_sub_kegiatan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('opd_sub_kegiatan_id')->constrained('opd_sub_kegiatan')->cascadeOnDelete();
            $table->foreignId('satuan_indikator_id')->nullable()->constrained('satuan_indikator')->nullOnDelete();
            $table->string('kode', 50)->nullable();
            $table->text('indikator');
            $table->text('formula')->nullable();
            $table->string('sumber_data')->nullable();
            $table->unsignedSmallInteger('urutan')->default(1);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['opd_sub_kegiatan_id', 'urutan']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('indikator_sub_kegiatan');
        Schema::dropIfExists('opd_sub_kegiatan');
        Schema::dropIfExists('opd_kegiatan');
        Schema::dropIfExists('target_indikator_opd_program');
        Schema::dropIfExists('indikator_opd_program');
        Schema::dropIfExists('opd_program');
        Schema::dropIfExists('target_indikator_sasaran_opd');
        Schema::dropIfExists('indikator_sasaran_opd');
        Schema::dropIfExists('sasaran_opd');
        Schema::dropIfExists('target_indikator_tujuan_opd');
        Schema::dropIfExists('indikator_tujuan_opd');
        Schema::dropIfExists('tujuan_opd');
        Schema::dropIfExists('renstra_opd');
    }
};
