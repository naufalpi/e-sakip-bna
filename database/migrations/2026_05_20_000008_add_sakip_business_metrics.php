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
        foreach ($this->indikatorTables() as $tableName) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                if (! Schema::hasColumn($tableName, 'tipe_indikator')) {
                    $table->string('tipe_indikator', 20)->default('positif')->index();
                }
            });
        }

        Schema::create('target_triwulan_indikator', function (Blueprint $table) {
            $table->id();
            $table->string('related_table', 120);
            $table->unsignedBigInteger('related_id');
            $table->foreignId('periode_tahun_id')->constrained('periode_tahun')->cascadeOnDelete();
            $table->string('triwulan', 10)->index();
            $table->string('target_text')->nullable();
            $table->decimal('target_angka', 18, 4)->nullable();
            $table->decimal('target_anggaran', 20, 2)->nullable();
            $table->timestamps();

            $table->unique(['related_table', 'related_id', 'periode_tahun_id', 'triwulan'], 'target_triwulan_related_unique');
            $table->index(['related_table', 'related_id']);
            $table->index(['periode_tahun_id', 'triwulan']);
        });

        Schema::table('realisasi_kinerja', function (Blueprint $table) {
            if (! Schema::hasColumn('realisasi_kinerja', 'target_anggaran')) {
                $table->decimal('target_anggaran', 20, 2)->nullable()->after('status');
            }

            if (! Schema::hasColumn('realisasi_kinerja', 'realisasi_anggaran')) {
                $table->decimal('realisasi_anggaran', 20, 2)->nullable()->after('target_anggaran');
            }

            if (! Schema::hasColumn('realisasi_kinerja', 'serapan_anggaran_persen')) {
                $table->decimal('serapan_anggaran_persen', 8, 2)->nullable()->after('realisasi_anggaran');
            }

            if (! Schema::hasColumn('realisasi_kinerja', 'capaian_persen')) {
                $table->decimal('capaian_persen', 8, 2)->nullable()->after('serapan_anggaran_persen');
            }

            if (! Schema::hasColumn('realisasi_kinerja', 'status_capaian')) {
                $table->string('status_capaian', 20)->nullable()->index()->after('capaian_persen');
            }

            if (! Schema::hasColumn('realisasi_kinerja', 'status_efisiensi')) {
                $table->string('status_efisiensi', 30)->nullable()->index()->after('status_capaian');
            }

            if (! Schema::hasColumn('realisasi_kinerja', 'analisis_efisiensi')) {
                $table->text('analisis_efisiensi')->nullable()->after('status_efisiensi');
            }
        });

        Schema::table('realisasi_program', function (Blueprint $table) {
            if (! Schema::hasColumn('realisasi_program', 'tipe_indikator')) {
                $table->string('tipe_indikator', 20)->default('positif')->index()->after('indikator_opd_program_id');
            }

            if (! Schema::hasColumn('realisasi_program', 'status_capaian')) {
                $table->string('status_capaian', 20)->nullable()->index()->after('capaian_persen');
            }

            if (! Schema::hasColumn('realisasi_program', 'serapan_anggaran_persen')) {
                $table->decimal('serapan_anggaran_persen', 8, 2)->nullable()->after('realisasi_anggaran');
            }

            if (! Schema::hasColumn('realisasi_program', 'status_efisiensi')) {
                $table->string('status_efisiensi', 30)->nullable()->index()->after('serapan_anggaran_persen');
            }

            if (! Schema::hasColumn('realisasi_program', 'analisis_efisiensi')) {
                $table->text('analisis_efisiensi')->nullable()->after('status_efisiensi');
            }
        });

        Schema::create('predikat_evaluasi', function (Blueprint $table) {
            $table->id();
            $table->string('kode', 10)->unique();
            $table->string('nama');
            $table->decimal('nilai_min', 8, 2)->default(0);
            $table->decimal('nilai_max', 8, 2)->default(100);
            $table->text('deskripsi')->nullable();
            $table->string('warna', 30)->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();

            $table->index(['is_active', 'nilai_min', 'nilai_max']);
        });

        Schema::table('evaluasi_sakip', function (Blueprint $table) {
            if (! Schema::hasColumn('evaluasi_sakip', 'predikat_evaluasi_id')) {
                $table->foreignId('predikat_evaluasi_id')->nullable()->after('predikat')->constrained('predikat_evaluasi')->nullOnDelete();
            }
        });

        Schema::table('lhe', function (Blueprint $table) {
            if (! Schema::hasColumn('lhe', 'predikat_evaluasi_id')) {
                $table->foreignId('predikat_evaluasi_id')->nullable()->after('predikat')->constrained('predikat_evaluasi')->nullOnDelete();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('lhe', function (Blueprint $table) {
            if (Schema::hasColumn('lhe', 'predikat_evaluasi_id')) {
                $table->dropConstrainedForeignId('predikat_evaluasi_id');
            }
        });

        Schema::table('evaluasi_sakip', function (Blueprint $table) {
            if (Schema::hasColumn('evaluasi_sakip', 'predikat_evaluasi_id')) {
                $table->dropConstrainedForeignId('predikat_evaluasi_id');
            }
        });

        Schema::dropIfExists('predikat_evaluasi');

        Schema::table('realisasi_program', function (Blueprint $table) {
            foreach (['analisis_efisiensi', 'status_efisiensi', 'serapan_anggaran_persen', 'status_capaian', 'tipe_indikator'] as $column) {
                if (Schema::hasColumn('realisasi_program', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('realisasi_kinerja', function (Blueprint $table) {
            foreach (['analisis_efisiensi', 'status_efisiensi', 'status_capaian', 'capaian_persen', 'serapan_anggaran_persen', 'realisasi_anggaran', 'target_anggaran'] as $column) {
                if (Schema::hasColumn('realisasi_kinerja', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::dropIfExists('target_triwulan_indikator');

        foreach ($this->indikatorTables() as $tableName) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                if (Schema::hasColumn($tableName, 'tipe_indikator')) {
                    $table->dropColumn('tipe_indikator');
                }
            });
        }
    }

    /**
     * @return array<int, string>
     */
    private function indikatorTables(): array
    {
        return [
            'indikator_tujuan_daerah',
            'indikator_sasaran_daerah',
            'indikator_program_rpjmd',
            'indikator_tujuan_opd',
            'indikator_sasaran_opd',
            'indikator_opd_program',
            'indikator_sub_kegiatan',
        ];
    }
};
