<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rkpd', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rpjmd_id')->nullable()->constrained('rpjmd')->nullOnDelete();
            $table->foreignId('periode_tahun_id')->constrained('periode_tahun')->cascadeOnDelete();
            $table->unsignedSmallInteger('tahun')->index();
            $table->string('judul');
            $table->string('nomor_dokumen')->nullable();
            $table->string('status', 30)->default('draft')->index();
            $table->text('catatan')->nullable();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['periode_tahun_id', 'tahun'], 'rkpd_periode_tahun_unique');
            $table->index(['rpjmd_id', 'status']);
            $table->index(['tahun', 'status']);
        });

        Schema::create('renja_opd', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rkpd_id')->nullable()->constrained('rkpd')->nullOnDelete();
            $table->foreignId('renstra_opd_id')->nullable()->constrained('renstra_opd')->nullOnDelete();
            $table->foreignId('opd_id')->constrained('opds')->cascadeOnDelete();
            $table->foreignId('opd_unit_id')->nullable()->constrained('opd_units')->nullOnDelete();
            $table->foreignId('periode_tahun_id')->constrained('periode_tahun')->cascadeOnDelete();
            $table->unsignedSmallInteger('tahun')->index();
            $table->string('judul');
            $table->string('nomor_dokumen')->nullable();
            $table->string('status', 30)->default('draft')->index();
            $table->text('catatan')->nullable();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['rkpd_id', 'status']);
            $table->index(['opd_id', 'tahun', 'status']);
            $table->index(['opd_unit_id', 'tahun', 'status']);
            $table->index(['periode_tahun_id', 'status']);
        });

        Schema::create('renja_opd_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('renja_opd_id')->constrained('renja_opd')->cascadeOnDelete();
            $table->foreignId('program_pemerintahan_id')->nullable()->constrained('program_pemerintahan')->nullOnDelete();
            $table->foreignId('kegiatan_pemerintahan_id')->nullable()->constrained('kegiatan_pemerintahan')->nullOnDelete();
            $table->foreignId('sub_kegiatan_pemerintahan_id')->nullable()->constrained('sub_kegiatan_pemerintahan')->nullOnDelete();
            $table->foreignId('indikator_sub_kegiatan_id')->nullable()->constrained('indikator_sub_kegiatan')->nullOnDelete();
            $table->string('kode', 100)->nullable()->index();
            $table->text('nama_sub_kegiatan')->nullable();
            $table->text('indikator')->nullable();
            $table->string('target_akhir_renstra')->nullable();
            $table->string('realisasi_capaian_renja_tahun_lalu')->nullable();
            $table->string('prakiraan_capaian_target_renja_tahun_berjalan')->nullable();
            $table->string('target')->nullable();
            $table->decimal('pagu_indikatif', 20, 2)->nullable();
            $table->text('lokasi')->nullable();
            $table->string('sumber_dana')->nullable();
            $table->text('prioritas_nasional')->nullable();
            $table->text('prioritas_daerah')->nullable();
            $table->text('kelompok_sasaran')->nullable();
            $table->string('prakiraan_maju_target')->nullable();
            $table->decimal('prakiraan_maju_pagu_indikatif', 20, 2)->nullable();
            $table->string('status', 30)->default('draft')->index();
            $table->unsignedSmallInteger('urutan')->default(1);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['renja_opd_id', 'urutan']);
            $table->index(['program_pemerintahan_id', 'kegiatan_pemerintahan_id']);
            $table->index(['sub_kegiatan_pemerintahan_id', 'status'], 'renja_item_sub_status_index');
        });

        Schema::create('rkpd_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rkpd_id')->constrained('rkpd')->cascadeOnDelete();
            $table->foreignId('renja_opd_id')->nullable()->constrained('renja_opd')->nullOnDelete();
            $table->foreignId('renja_opd_item_id')->nullable()->constrained('renja_opd_items')->nullOnDelete();
            $table->foreignId('opd_id')->constrained('opds')->cascadeOnDelete();
            $table->foreignId('opd_unit_id')->nullable()->constrained('opd_units')->nullOnDelete();
            $table->foreignId('urusan_pemerintahan_id')->nullable()->constrained('urusan_pemerintahan')->nullOnDelete();
            $table->foreignId('bidang_urusan_id')->nullable()->constrained('bidang_urusan')->nullOnDelete();
            $table->foreignId('program_pemerintahan_id')->nullable()->constrained('program_pemerintahan')->nullOnDelete();
            $table->foreignId('kegiatan_pemerintahan_id')->nullable()->constrained('kegiatan_pemerintahan')->nullOnDelete();
            $table->foreignId('sub_kegiatan_pemerintahan_id')->nullable()->constrained('sub_kegiatan_pemerintahan')->nullOnDelete();
            $table->foreignId('program_rpjmd_id')->nullable()->constrained('program_rpjmd')->nullOnDelete();
            $table->string('kode', 100)->nullable()->index();
            $table->text('nama_urusan_bidang_program_kegiatan_sub')->nullable();
            $table->text('indikator')->nullable();
            $table->string('target_akhir_renstra')->nullable();
            $table->string('realisasi_capaian_renja_tahun_lalu')->nullable();
            $table->string('prakiraan_capaian_target_renja_tahun_berjalan')->nullable();
            $table->string('target')->nullable();
            $table->decimal('pagu_indikatif', 20, 2)->nullable();
            $table->text('lokasi')->nullable();
            $table->string('sumber_dana')->nullable();
            $table->text('prioritas_nasional')->nullable();
            $table->text('prioritas_daerah')->nullable();
            $table->text('kelompok_sasaran')->nullable();
            $table->string('prakiraan_maju_target')->nullable();
            $table->decimal('prakiraan_maju_pagu_indikatif', 20, 2)->nullable();
            $table->string('perangkat_daerah_penanggung_jawab')->nullable();
            $table->string('status', 30)->default('draft')->index();
            $table->unsignedSmallInteger('urutan')->default(1);
            $table->jsonb('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['rkpd_id', 'opd_id', 'status']);
            $table->index(['rkpd_id', 'urutan']);
            $table->index(['bidang_urusan_id', 'program_pemerintahan_id'], 'rkpd_item_bidang_program_index');
            $table->index(['sub_kegiatan_pemerintahan_id', 'status'], 'rkpd_item_sub_status_index');
            $table->unique(['rkpd_id', 'renja_opd_item_id'], 'rkpd_renja_item_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rkpd_items');
        Schema::dropIfExists('renja_opd_items');
        Schema::dropIfExists('renja_opd');
        Schema::dropIfExists('rkpd');
    }
};
