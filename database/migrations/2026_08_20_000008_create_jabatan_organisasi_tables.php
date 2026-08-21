<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jabatan_organisasi', function (Blueprint $table) {
            $table->id();
            $table->foreignId('opd_id')->nullable()->constrained('opds')->nullOnDelete();
            $table->foreignId('opd_unit_id')->nullable()->constrained('opd_units')->nullOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('jabatan_organisasi')->nullOnDelete();
            $table->string('nama');
            $table->string('level_jabatan', 40)->index();
            $table->string('eselon', 20)->nullable()->index();
            $table->unsignedSmallInteger('urutan')->default(0);
            $table->string('status', 20)->default('active')->index();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['opd_id', 'status']);
            $table->index(['parent_id', 'urutan']);
        });

        Schema::create('riwayat_pejabat_jabatan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('jabatan_organisasi_id')->constrained('jabatan_organisasi')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('nama_pejabat');
            $table->string('nip', 30)->nullable()->index();
            $table->string('pangkat_golongan', 120)->nullable();
            $table->string('jenis_penugasan', 30)->default('definitif')->index();
            $table->string('nomor_sk', 150)->nullable();
            $table->date('tanggal_sk')->nullable();
            $table->date('tanggal_mulai')->index();
            $table->date('tanggal_selesai')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['jabatan_organisasi_id', 'tanggal_mulai'], 'riwayat_pejabat_jabatan_mulai_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('riwayat_pejabat_jabatan');
        Schema::dropIfExists('jabatan_organisasi');
    }
};
