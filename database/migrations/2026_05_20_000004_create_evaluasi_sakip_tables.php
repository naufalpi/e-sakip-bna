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
        Schema::create('komponen_evaluasi', function (Blueprint $table) {
            $table->id();
            $table->string('kode', 50)->unique();
            $table->string('nama');
            $table->text('deskripsi')->nullable();
            $table->decimal('bobot', 8, 2)->default(0);
            $table->unsignedSmallInteger('urutan')->default(1);
            $table->string('status', 30)->default('active')->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('sub_komponen_evaluasi', function (Blueprint $table) {
            $table->id();
            $table->foreignId('komponen_evaluasi_id')->constrained('komponen_evaluasi')->cascadeOnDelete();
            $table->string('kode', 50)->index();
            $table->string('nama');
            $table->text('deskripsi')->nullable();
            $table->decimal('bobot', 8, 2)->default(0);
            $table->unsignedSmallInteger('urutan')->default(1);
            $table->string('status', 30)->default('active')->index();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['komponen_evaluasi_id', 'kode'], 'sub_komponen_evaluasi_unique');
        });

        Schema::create('kriteria_evaluasi', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sub_komponen_evaluasi_id')->constrained('sub_komponen_evaluasi')->cascadeOnDelete();
            $table->string('kode', 50)->index();
            $table->text('nama');
            $table->text('panduan')->nullable();
            $table->decimal('bobot', 8, 2)->default(0);
            $table->decimal('nilai_maksimal', 8, 2)->default(100);
            $table->unsignedSmallInteger('urutan')->default(1);
            $table->string('status', 30)->default('active')->index();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['sub_komponen_evaluasi_id', 'kode'], 'kriteria_evaluasi_unique');
        });

        Schema::create('evaluasi_sakip', function (Blueprint $table) {
            $table->id();
            $table->foreignId('opd_id')->constrained('opds')->cascadeOnDelete();
            $table->foreignId('periode_tahun_id')->constrained('periode_tahun')->cascadeOnDelete();
            $table->unsignedSmallInteger('tahun')->index();
            $table->foreignId('evaluator_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('tanggal_evaluasi')->nullable();
            $table->string('status', 30)->default('draft')->index();
            $table->decimal('nilai_akhir', 8, 2)->default(0);
            $table->string('predikat', 10)->nullable()->index();
            $table->text('catatan_umum')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['opd_id', 'periode_tahun_id'], 'evaluasi_sakip_opd_periode_unique');
            $table->index(['tahun', 'status']);
            $table->index(['opd_id', 'tahun']);
        });

        Schema::create('evaluasi_sakip_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('evaluasi_sakip_id')->constrained('evaluasi_sakip')->cascadeOnDelete();
            $table->foreignId('kriteria_evaluasi_id')->constrained('kriteria_evaluasi')->cascadeOnDelete();
            $table->decimal('nilai', 8, 2)->default(0);
            $table->decimal('skor', 8, 2)->default(0);
            $table->text('catatan')->nullable();
            $table->text('rekomendasi_text')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['evaluasi_sakip_id', 'kriteria_evaluasi_id'], 'evaluasi_sakip_item_unique');
        });

        Schema::create('lhe', function (Blueprint $table) {
            $table->id();
            $table->foreignId('evaluasi_sakip_id')->constrained('evaluasi_sakip')->cascadeOnDelete();
            $table->string('nomor_lhe')->nullable()->index();
            $table->date('tanggal_lhe')->nullable();
            $table->text('ringkasan')->nullable();
            $table->decimal('nilai_akhir', 8, 2)->default(0);
            $table->string('predikat', 10)->nullable()->index();
            $table->string('status', 30)->default('draft')->index();
            $table->foreignId('disusun_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique('evaluasi_sakip_id');
        });

        Schema::create('rekomendasi_evaluasi', function (Blueprint $table) {
            $table->id();
            $table->foreignId('evaluasi_sakip_id')->constrained('evaluasi_sakip')->cascadeOnDelete();
            $table->foreignId('evaluasi_sakip_item_id')->nullable()->constrained('evaluasi_sakip_items')->nullOnDelete();
            $table->foreignId('opd_id')->constrained('opds')->cascadeOnDelete();
            $table->string('nomor', 80)->nullable()->index();
            $table->text('rekomendasi');
            $table->string('prioritas', 30)->default('sedang')->index();
            $table->string('status_tindak_lanjut', 30)->default('belum')->index();
            $table->date('target_tanggal')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['opd_id', 'status_tindak_lanjut']);
        });

        Schema::create('tindak_lanjut_rekomendasi', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rekomendasi_evaluasi_id')->constrained('rekomendasi_evaluasi')->cascadeOnDelete();
            $table->foreignId('opd_id')->constrained('opds')->cascadeOnDelete();
            $table->text('uraian_tindak_lanjut');
            $table->string('status_tindak_lanjut', 30)->default('proses')->index();
            $table->date('tanggal_tindak_lanjut')->nullable();
            $table->text('catatan_opd')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('diverifikasi_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('diverifikasi_at')->nullable();
            $table->text('catatan_verifikator')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['opd_id', 'status_tindak_lanjut']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tindak_lanjut_rekomendasi');
        Schema::dropIfExists('rekomendasi_evaluasi');
        Schema::dropIfExists('lhe');
        Schema::dropIfExists('evaluasi_sakip_items');
        Schema::dropIfExists('evaluasi_sakip');
        Schema::dropIfExists('kriteria_evaluasi');
        Schema::dropIfExists('sub_komponen_evaluasi');
        Schema::dropIfExists('komponen_evaluasi');
    }
};
