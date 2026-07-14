<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bidang_urusan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('urusan_pemerintahan_id')->constrained('urusan_pemerintahan')->cascadeOnDelete();
            $table->string('kode', 80);
            $table->string('nama');
            $table->string('status', 20)->default('active')->index();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['urusan_pemerintahan_id', 'kode']);
            $table->index(['urusan_pemerintahan_id', 'status']);
            $table->index('nama');
        });

        Schema::create('program_pemerintahan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bidang_urusan_id')->constrained('bidang_urusan')->cascadeOnDelete();
            $table->string('kode', 80);
            $table->string('nama');
            $table->string('status', 20)->default('active')->index();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['bidang_urusan_id', 'kode']);
            $table->index(['bidang_urusan_id', 'status']);
            $table->index('nama');
        });

        Schema::create('kegiatan_pemerintahan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('program_pemerintahan_id')->constrained('program_pemerintahan')->cascadeOnDelete();
            $table->string('kode', 80);
            $table->string('nama');
            $table->string('status', 20)->default('active')->index();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['program_pemerintahan_id', 'kode']);
            $table->index(['program_pemerintahan_id', 'status']);
            $table->index('nama');
        });

        Schema::create('sub_kegiatan_pemerintahan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kegiatan_pemerintahan_id')->constrained('kegiatan_pemerintahan')->cascadeOnDelete();
            $table->string('kode', 80);
            $table->string('nama');
            $table->string('status', 20)->default('active')->index();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['kegiatan_pemerintahan_id', 'kode']);
            $table->index(['kegiatan_pemerintahan_id', 'status']);
            $table->index('nama');
        });

        Schema::table('program_rpjmd', function (Blueprint $table) {
            $table->foreignId('program_pemerintahan_id')
                ->nullable()
                ->after('indikator_sasaran_daerah_id')
                ->constrained('program_pemerintahan')
                ->nullOnDelete();

            $table->index('program_pemerintahan_id');
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('program_rpjmd', 'program_pemerintahan_id')) {
            Schema::table('program_rpjmd', function (Blueprint $table) {
                $table->dropConstrainedForeignId('program_pemerintahan_id');
            });
        }

        Schema::dropIfExists('sub_kegiatan_pemerintahan');
        Schema::dropIfExists('kegiatan_pemerintahan');
        Schema::dropIfExists('program_pemerintahan');
        Schema::dropIfExists('bidang_urusan');
    }
};
