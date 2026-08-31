<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE perjanjian_kinerja ALTER COLUMN opd_id DROP NOT NULL');
        } else {
            Schema::table('perjanjian_kinerja', function (Blueprint $table) {
                $table->foreignId('opd_id')->nullable()->change();
            });
        }

        Schema::table('perjanjian_kinerja', function (Blueprint $table) {
            $table->string('level_pk', 30)->default('struktural')->after('tipe_pk')->index();
            $table->foreignId('rkpd_id')->nullable()->after('renstra_opd_id')->constrained('rkpd')->nullOnDelete();
            $table->foreignId('renja_opd_id')->nullable()->after('rkpd_id')->constrained('renja_opd')->nullOnDelete();
            $table->foreignId('dpa_opd_id')->nullable()->after('renja_opd_id')->constrained('dpa_opd')->nullOnDelete();
            $table->string('sumber_data', 30)->default('manual')->after('dpa_opd_id')->index();
            $table->date('tanggal_dokumen')->nullable()->after('nomor_dokumen');
            $table->string('tempat_penandatanganan')->default('Banjarnegara')->after('tanggal_dokumen');
            $table->string('nama_atasan_snapshot')->nullable()->after('jabatan_snapshot');
            $table->string('nip_atasan_snapshot', 30)->nullable()->after('nama_atasan_snapshot');
            $table->string('jabatan_atasan_snapshot')->nullable()->after('nip_atasan_snapshot');
            $table->timestamp('snapshot_dibuat_pada')->nullable()->after('jabatan_atasan_snapshot');

            $table->index(['level_pk', 'tahun', 'status'], 'pk_level_tahun_status_index');
            $table->index(['pegawai_id', 'tahun', 'level_pk'], 'pk_pegawai_tahun_level_index');
        });

        Schema::table('perjanjian_kinerja_items', function (Blueprint $table) {
            $table->string('jenis_item', 30)->default('manual')->after('sumber_item')->index();
            $table->string('satuan_snapshot')->nullable()->after('satuan_indikator_id');
            $table->boolean('is_readonly')->default(false)->after('urutan');
        });

        Schema::create('perjanjian_kinerja_programs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('perjanjian_kinerja_id')->constrained('perjanjian_kinerja')->cascadeOnDelete();
            $table->foreignId('program_rpjmd_id')->nullable()->constrained('program_rpjmd')->nullOnDelete();
            $table->foreignId('opd_program_id')->nullable()->constrained('opd_program')->nullOnDelete();
            $table->foreignId('program_pemerintahan_id')->nullable()->constrained('program_pemerintahan')->nullOnDelete();
            $table->string('kode', 80)->nullable();
            $table->text('nama_program');
            $table->decimal('anggaran', 20, 2)->default(0);
            $table->string('keterangan')->nullable();
            $table->unsignedSmallInteger('urutan')->default(1);
            $table->timestamps();

            $table->index(['perjanjian_kinerja_id', 'urutan'], 'pk_program_urutan_index');
        });

        DB::table('perjanjian_kinerja')
            ->where('tipe_pk', 'individual')
            ->update([
                'level_pk' => 'individu',
                'sumber_data' => 'manual',
            ]);

        DB::table('perjanjian_kinerja')
            ->where('tipe_pk', 'cascading')
            ->update([
                'level_pk' => 'struktural',
                'sumber_data' => 'penugasan',
            ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('perjanjian_kinerja_programs');

        Schema::table('perjanjian_kinerja_items', function (Blueprint $table) {
            $table->dropColumn(['jenis_item', 'satuan_snapshot', 'is_readonly']);
        });

        Schema::table('perjanjian_kinerja', function (Blueprint $table) {
            $table->dropIndex('pk_level_tahun_status_index');
            $table->dropIndex('pk_pegawai_tahun_level_index');
            $table->dropConstrainedForeignId('dpa_opd_id');
            $table->dropConstrainedForeignId('renja_opd_id');
            $table->dropConstrainedForeignId('rkpd_id');
            $table->dropColumn([
                'level_pk',
                'sumber_data',
                'tanggal_dokumen',
                'tempat_penandatanganan',
                'nama_atasan_snapshot',
                'nip_atasan_snapshot',
                'jabatan_atasan_snapshot',
                'snapshot_dibuat_pada',
            ]);
        });

        // opd_id sengaja tetap nullable agar rollback tidak merusak PK Bupati yang mungkin sudah tersimpan.
    }
};
