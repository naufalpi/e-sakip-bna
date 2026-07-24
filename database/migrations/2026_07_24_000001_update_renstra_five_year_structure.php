<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('opd_program', function (Blueprint $table) {
            if (! Schema::hasColumn('opd_program', 'sasaran_program')) {
                $table->text('sasaran_program')->nullable();
            }
        });

        Schema::table('opd_kegiatan', function (Blueprint $table) {
            if (! Schema::hasColumn('opd_kegiatan', 'sasaran_kegiatan')) {
                $table->text('sasaran_kegiatan')->nullable();
            }
        });

        Schema::table('opd_sub_kegiatan', function (Blueprint $table) {
            if (! Schema::hasColumn('opd_sub_kegiatan', 'sasaran_sub_kegiatan')) {
                $table->text('sasaran_sub_kegiatan')->nullable();
            }
        });

        if (! Schema::hasTable('indikator_opd_kegiatan')) {
            Schema::create('indikator_opd_kegiatan', function (Blueprint $table) {
                $table->id();
                $table->foreignId('opd_kegiatan_id')->constrained('opd_kegiatan')->cascadeOnDelete();
                $table->foreignId('satuan_indikator_id')->nullable()->constrained('satuan_indikator')->nullOnDelete();
                $table->string('kode', 50)->nullable();
                $table->text('indikator');
                $table->string('tipe_indikator', 20)->default('positif')->index();
                $table->text('formula')->nullable();
                $table->string('sumber_data')->nullable();
                $table->unsignedSmallInteger('urutan')->default(1);
                $table->timestamps();
                $table->softDeletes();

                $table->index(['opd_kegiatan_id', 'urutan']);
            });
        }

        if (! Schema::hasTable('target_indikator_opd_kegiatan')) {
            Schema::create('target_indikator_opd_kegiatan', function (Blueprint $table) {
                $table->id();
                $table->foreignId('indikator_opd_kegiatan_id')->constrained('indikator_opd_kegiatan')->cascadeOnDelete();
                $table->foreignId('periode_tahun_id')->constrained('periode_tahun')->cascadeOnDelete();
                $table->decimal('target', 18, 4)->nullable();
                $table->text('target_text')->nullable();
                $table->timestamps();

                $table->unique(['indikator_opd_kegiatan_id', 'periode_tahun_id'], 'target_kegiatan_opd_unique');
            });
        }

        if (! Schema::hasTable('target_indikator_sub_kegiatan')) {
            Schema::create('target_indikator_sub_kegiatan', function (Blueprint $table) {
                $table->id();
                $table->foreignId('indikator_sub_kegiatan_id')->constrained('indikator_sub_kegiatan')->cascadeOnDelete();
                $table->foreignId('periode_tahun_id')->constrained('periode_tahun')->cascadeOnDelete();
                $table->decimal('target', 18, 4)->nullable();
                $table->text('target_text')->nullable();
                $table->timestamps();

                $table->unique(['indikator_sub_kegiatan_id', 'periode_tahun_id'], 'target_sub_kegiatan_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('target_indikator_sub_kegiatan');
        Schema::dropIfExists('target_indikator_opd_kegiatan');
        Schema::dropIfExists('indikator_opd_kegiatan');

        Schema::table('opd_sub_kegiatan', function (Blueprint $table) {
            if (Schema::hasColumn('opd_sub_kegiatan', 'sasaran_sub_kegiatan')) {
                $table->dropColumn('sasaran_sub_kegiatan');
            }
        });

        Schema::table('opd_kegiatan', function (Blueprint $table) {
            if (Schema::hasColumn('opd_kegiatan', 'sasaran_kegiatan')) {
                $table->dropColumn('sasaran_kegiatan');
            }
        });

        Schema::table('opd_program', function (Blueprint $table) {
            if (Schema::hasColumn('opd_program', 'sasaran_program')) {
                $table->dropColumn('sasaran_program');
            }
        });
    }
};
