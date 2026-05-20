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
        Schema::create('perjanjian_kinerja', function (Blueprint $table) {
            $table->id();
            $table->foreignId('opd_id')->constrained('opds')->cascadeOnDelete();
            $table->foreignId('renstra_opd_id')->nullable()->constrained('renstra_opd')->nullOnDelete();
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

            $table->index(['opd_id', 'tahun', 'status']);
            $table->index(['periode_tahun_id', 'status']);
        });

        Schema::create('perjanjian_kinerja_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('perjanjian_kinerja_id')->constrained('perjanjian_kinerja')->cascadeOnDelete();
            $table->foreignId('sasaran_opd_id')->nullable()->constrained('sasaran_opd')->nullOnDelete();
            $table->foreignId('indikator_sasaran_opd_id')->nullable()->constrained('indikator_sasaran_opd')->nullOnDelete();
            $table->foreignId('opd_program_id')->nullable()->constrained('opd_program')->nullOnDelete();
            $table->foreignId('satuan_indikator_id')->nullable()->constrained('satuan_indikator')->nullOnDelete();
            $table->string('kode', 80)->nullable();
            $table->text('sasaran');
            $table->text('indikator');
            $table->decimal('target', 18, 4)->nullable();
            $table->string('target_text')->nullable();
            $table->unsignedSmallInteger('urutan')->default(1);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['perjanjian_kinerja_id', 'urutan']);
        });

        Schema::create('rencana_aksi', function (Blueprint $table) {
            $table->id();
            $table->foreignId('opd_id')->constrained('opds')->cascadeOnDelete();
            $table->foreignId('perjanjian_kinerja_id')->nullable()->constrained('perjanjian_kinerja')->nullOnDelete();
            $table->foreignId('periode_tahun_id')->constrained('periode_tahun')->cascadeOnDelete();
            $table->unsignedSmallInteger('tahun')->index();
            $table->string('judul');
            $table->string('status', 30)->default('draft')->index();
            $table->text('catatan')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['opd_id', 'tahun', 'status']);
            $table->index(['periode_tahun_id', 'status']);
        });

        Schema::create('rencana_aksi_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rencana_aksi_id')->constrained('rencana_aksi')->cascadeOnDelete();
            $table->foreignId('perjanjian_kinerja_item_id')->nullable()->constrained('perjanjian_kinerja_items')->nullOnDelete();
            $table->foreignId('opd_program_id')->nullable()->constrained('opd_program')->nullOnDelete();
            $table->foreignId('opd_kegiatan_id')->nullable()->constrained('opd_kegiatan')->nullOnDelete();
            $table->foreignId('opd_sub_kegiatan_id')->nullable()->constrained('opd_sub_kegiatan')->nullOnDelete();
            $table->string('periode_realisasi', 30)->default('triwulan')->index();
            $table->string('triwulan', 10)->nullable()->index();
            $table->unsignedTinyInteger('bulan')->nullable()->index();
            $table->text('aksi');
            $table->text('indikator')->nullable();
            $table->decimal('target', 18, 4)->nullable();
            $table->string('target_text')->nullable();
            $table->decimal('anggaran', 20, 2)->nullable();
            $table->string('penanggung_jawab')->nullable();
            $table->string('status', 30)->default('draft')->index();
            $table->unsignedSmallInteger('urutan')->default(1);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['rencana_aksi_id', 'urutan']);
        });

        Schema::create('realisasi_kinerja', function (Blueprint $table) {
            $table->id();
            $table->foreignId('opd_id')->constrained('opds')->cascadeOnDelete();
            $table->foreignId('perjanjian_kinerja_id')->nullable()->constrained('perjanjian_kinerja')->nullOnDelete();
            $table->foreignId('rencana_aksi_id')->nullable()->constrained('rencana_aksi')->nullOnDelete();
            $table->foreignId('periode_tahun_id')->constrained('periode_tahun')->cascadeOnDelete();
            $table->unsignedSmallInteger('tahun')->index();
            $table->string('periode_realisasi', 30)->default('triwulan')->index();
            $table->string('triwulan', 10)->nullable()->index();
            $table->unsignedTinyInteger('bulan')->nullable()->index();
            $table->unsignedTinyInteger('semester')->nullable()->index();
            $table->string('status', 30)->default('draft')->index();
            $table->text('catatan')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['opd_id', 'tahun', 'status']);
            $table->index(['periode_tahun_id', 'periode_realisasi']);
        });

        Schema::create('realisasi_program', function (Blueprint $table) {
            $table->id();
            $table->foreignId('realisasi_kinerja_id')->constrained('realisasi_kinerja')->cascadeOnDelete();
            $table->foreignId('perjanjian_kinerja_item_id')->nullable()->constrained('perjanjian_kinerja_items')->nullOnDelete();
            $table->foreignId('rencana_aksi_item_id')->nullable()->constrained('rencana_aksi_items')->nullOnDelete();
            $table->foreignId('opd_program_id')->nullable()->constrained('opd_program')->nullOnDelete();
            $table->foreignId('indikator_opd_program_id')->nullable()->constrained('indikator_opd_program')->nullOnDelete();
            $table->text('indikator');
            $table->decimal('target', 18, 4)->nullable();
            $table->string('target_text')->nullable();
            $table->decimal('realisasi', 18, 4)->nullable();
            $table->string('realisasi_text')->nullable();
            $table->decimal('capaian_persen', 8, 2)->nullable();
            $table->decimal('anggaran', 20, 2)->nullable();
            $table->decimal('realisasi_anggaran', 20, 2)->nullable();
            $table->text('kendala')->nullable();
            $table->text('tindak_lanjut')->nullable();
            $table->unsignedSmallInteger('urutan')->default(1);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['realisasi_kinerja_id', 'urutan']);
        });

        Schema::create('workflow_submissions', function (Blueprint $table) {
            $table->id();
            $table->string('related_table', 120);
            $table->unsignedBigInteger('related_id');
            $table->string('module', 80)->index();
            $table->string('status', 30)->default('draft')->index();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('current_reviewer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('note')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['related_table', 'related_id', 'module'], 'workflow_submission_related_unique');
            $table->index(['module', 'status']);
        });

        Schema::create('workflow_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workflow_submission_id')->nullable()->constrained('workflow_submissions')->nullOnDelete();
            $table->string('related_table', 120);
            $table->unsignedBigInteger('related_id');
            $table->string('module', 80)->index();
            $table->string('from_status', 30)->nullable();
            $table->string('to_status', 30)->index();
            $table->string('action', 50)->index();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['related_table', 'related_id']);
            $table->index(['module', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('workflow_histories');
        Schema::dropIfExists('workflow_submissions');
        Schema::dropIfExists('realisasi_program');
        Schema::dropIfExists('realisasi_kinerja');
        Schema::dropIfExists('rencana_aksi_items');
        Schema::dropIfExists('rencana_aksi');
        Schema::dropIfExists('perjanjian_kinerja_items');
        Schema::dropIfExists('perjanjian_kinerja');
    }
};
