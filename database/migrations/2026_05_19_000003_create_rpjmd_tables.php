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
        Schema::create('rpjmd', function (Blueprint $table) {
            $table->id();
            $table->foreignId('periode_tahun_id')->nullable()->constrained('periode_tahun')->nullOnDelete();
            $table->string('judul');
            $table->string('nomor_perda')->nullable();
            $table->unsignedSmallInteger('tahun_awal')->index();
            $table->unsignedSmallInteger('tahun_akhir')->index();
            $table->string('status', 30)->default('draft')->index();
            $table->text('keterangan')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'tahun_awal', 'tahun_akhir']);
        });

        Schema::create('rpjmd_visi', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rpjmd_id')->constrained('rpjmd')->cascadeOnDelete();
            $table->text('visi');
            $table->unsignedSmallInteger('urutan')->default(1);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['rpjmd_id', 'urutan']);
        });

        Schema::create('rpjmd_misi', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rpjmd_id')->constrained('rpjmd')->cascadeOnDelete();
            $table->foreignId('rpjmd_visi_id')->nullable()->constrained('rpjmd_visi')->nullOnDelete();
            $table->string('kode', 50)->nullable();
            $table->text('misi');
            $table->unsignedSmallInteger('urutan')->default(1);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['rpjmd_id', 'urutan']);
        });

        Schema::create('tujuan_daerah', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rpjmd_misi_id')->constrained('rpjmd_misi')->cascadeOnDelete();
            $table->string('kode', 50)->nullable();
            $table->text('tujuan');
            $table->unsignedSmallInteger('urutan')->default(1);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['rpjmd_misi_id', 'urutan']);
        });

        Schema::create('indikator_tujuan_daerah', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tujuan_daerah_id')->constrained('tujuan_daerah')->cascadeOnDelete();
            $table->foreignId('satuan_indikator_id')->nullable()->constrained('satuan_indikator')->nullOnDelete();
            $table->string('kode', 50)->nullable();
            $table->text('indikator');
            $table->text('formula')->nullable();
            $table->string('sumber_data')->nullable();
            $table->unsignedSmallInteger('urutan')->default(1);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tujuan_daerah_id', 'urutan']);
        });

        Schema::create('target_indikator_tujuan_daerah', function (Blueprint $table) {
            $table->id();
            $table->foreignId('indikator_tujuan_daerah_id')->constrained('indikator_tujuan_daerah')->cascadeOnDelete();
            $table->foreignId('periode_tahun_id')->constrained('periode_tahun')->cascadeOnDelete();
            $table->decimal('target', 18, 4)->nullable();
            $table->string('target_text')->nullable();
            $table->timestamps();

            $table->unique(['indikator_tujuan_daerah_id', 'periode_tahun_id'], 'target_tujuan_unique');
        });

        Schema::create('sasaran_daerah', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tujuan_daerah_id')->constrained('tujuan_daerah')->cascadeOnDelete();
            $table->string('kode', 50)->nullable();
            $table->text('sasaran');
            $table->unsignedSmallInteger('urutan')->default(1);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tujuan_daerah_id', 'urutan']);
        });

        Schema::create('indikator_sasaran_daerah', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sasaran_daerah_id')->constrained('sasaran_daerah')->cascadeOnDelete();
            $table->foreignId('satuan_indikator_id')->nullable()->constrained('satuan_indikator')->nullOnDelete();
            $table->string('kode', 50)->nullable();
            $table->text('indikator');
            $table->text('formula')->nullable();
            $table->string('sumber_data')->nullable();
            $table->unsignedSmallInteger('urutan')->default(1);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['sasaran_daerah_id', 'urutan']);
        });

        Schema::create('target_indikator_sasaran_daerah', function (Blueprint $table) {
            $table->id();
            $table->foreignId('indikator_sasaran_daerah_id')->constrained('indikator_sasaran_daerah')->cascadeOnDelete();
            $table->foreignId('periode_tahun_id')->constrained('periode_tahun')->cascadeOnDelete();
            $table->decimal('target', 18, 4)->nullable();
            $table->string('target_text')->nullable();
            $table->timestamps();

            $table->unique(['indikator_sasaran_daerah_id', 'periode_tahun_id'], 'target_sasaran_unique');
        });

        Schema::create('strategi_daerah', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sasaran_daerah_id')->constrained('sasaran_daerah')->cascadeOnDelete();
            $table->string('kode', 50)->nullable();
            $table->text('strategi');
            $table->text('arah_kebijakan')->nullable();
            $table->unsignedSmallInteger('urutan')->default(1);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['sasaran_daerah_id', 'urutan']);
        });

        Schema::create('program_rpjmd', function (Blueprint $table) {
            $table->id();
            $table->foreignId('strategi_daerah_id')->nullable()->constrained('strategi_daerah')->nullOnDelete();
            $table->foreignId('sasaran_daerah_id')->nullable()->constrained('sasaran_daerah')->nullOnDelete();
            $table->foreignId('urusan_pemerintahan_id')->nullable()->constrained('urusan_pemerintahan')->nullOnDelete();
            $table->string('kode', 80)->nullable()->index();
            $table->string('nama');
            $table->decimal('pagu_indikatif', 20, 2)->nullable();
            $table->string('status', 30)->default('draft')->index();
            $table->unsignedSmallInteger('urutan')->default(1);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['strategi_daerah_id', 'urutan']);
            $table->index(['sasaran_daerah_id', 'urutan']);
        });

        Schema::create('indikator_program_rpjmd', function (Blueprint $table) {
            $table->id();
            $table->foreignId('program_rpjmd_id')->constrained('program_rpjmd')->cascadeOnDelete();
            $table->foreignId('satuan_indikator_id')->nullable()->constrained('satuan_indikator')->nullOnDelete();
            $table->string('kode', 50)->nullable();
            $table->text('indikator');
            $table->text('formula')->nullable();
            $table->string('sumber_data')->nullable();
            $table->unsignedSmallInteger('urutan')->default(1);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['program_rpjmd_id', 'urutan']);
        });

        Schema::create('target_indikator_program_rpjmd', function (Blueprint $table) {
            $table->id();
            $table->foreignId('indikator_program_rpjmd_id')->constrained('indikator_program_rpjmd')->cascadeOnDelete();
            $table->foreignId('periode_tahun_id')->constrained('periode_tahun')->cascadeOnDelete();
            $table->decimal('target', 18, 4)->nullable();
            $table->string('target_text')->nullable();
            $table->decimal('pagu', 20, 2)->nullable();
            $table->timestamps();

            $table->unique(['indikator_program_rpjmd_id', 'periode_tahun_id'], 'target_program_unique');
        });

        Schema::create('program_rpjmd_opd_penanggung_jawab', function (Blueprint $table) {
            $table->id();
            $table->foreignId('program_rpjmd_id')->constrained('program_rpjmd')->cascadeOnDelete();
            $table->foreignId('opd_id')->constrained('opds')->cascadeOnDelete();
            $table->string('peran', 50)->default('penanggung_jawab')->index();
            $table->boolean('is_utama')->default(true)->index();
            $table->timestamps();

            $table->unique(['program_rpjmd_id', 'opd_id', 'peran'], 'program_rpjmd_opd_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('program_rpjmd_opd_penanggung_jawab');
        Schema::dropIfExists('target_indikator_program_rpjmd');
        Schema::dropIfExists('indikator_program_rpjmd');
        Schema::dropIfExists('program_rpjmd');
        Schema::dropIfExists('strategi_daerah');
        Schema::dropIfExists('target_indikator_sasaran_daerah');
        Schema::dropIfExists('indikator_sasaran_daerah');
        Schema::dropIfExists('sasaran_daerah');
        Schema::dropIfExists('target_indikator_tujuan_daerah');
        Schema::dropIfExists('indikator_tujuan_daerah');
        Schema::dropIfExists('tujuan_daerah');
        Schema::dropIfExists('rpjmd_misi');
        Schema::dropIfExists('rpjmd_visi');
        Schema::dropIfExists('rpjmd');
    }
};
