<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rka_opd', function (Blueprint $table) {
            $table->id();
            $table->foreignId('renja_opd_id')->nullable()->constrained('renja_opd')->nullOnDelete();
            $table->foreignId('rkpd_id')->nullable()->constrained('rkpd')->nullOnDelete();
            $table->foreignId('opd_id')->constrained('opds')->restrictOnDelete();
            $table->foreignId('opd_unit_id')->nullable()->constrained('opd_units')->nullOnDelete();
            $table->foreignId('periode_tahun_id')->constrained('periode_tahun')->restrictOnDelete();
            $table->unsignedSmallInteger('tahun')->index();
            $table->string('jenis_anggaran', 20)->default('murni')->index();
            $table->string('judul');
            $table->string('nomor_dokumen')->nullable();
            $table->date('tanggal_dokumen')->nullable();
            $table->string('nomor_kua')->nullable();
            $table->date('tanggal_kua')->nullable();
            $table->string('nomor_ppas')->nullable();
            $table->date('tanggal_ppas')->nullable();
            $table->string('status', 30)->default('draft')->index();
            $table->text('catatan')->nullable();
            $table->text('catatan_verifikasi')->nullable();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['opd_id', 'tahun', 'jenis_anggaran']);
            $table->index(['periode_tahun_id', 'status']);
            $table->index(['rkpd_id', 'status']);
        });

        DB::statement('CREATE UNIQUE INDEX rka_opd_active_renja_unique ON rka_opd (renja_opd_id) WHERE deleted_at IS NULL');

        Schema::create('rka_opd_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rka_opd_id')->constrained('rka_opd')->cascadeOnDelete();
            $table->foreignId('renja_opd_item_id')->nullable()->constrained('renja_opd_items')->nullOnDelete();
            $table->foreignId('urusan_pemerintahan_id')->nullable()->constrained('urusan_pemerintahan')->nullOnDelete();
            $table->foreignId('bidang_urusan_id')->nullable()->constrained('bidang_urusan')->nullOnDelete();
            $table->foreignId('program_pemerintahan_id')->nullable()->constrained('program_pemerintahan')->nullOnDelete();
            $table->foreignId('kegiatan_pemerintahan_id')->nullable()->constrained('kegiatan_pemerintahan')->nullOnDelete();
            $table->foreignId('sub_kegiatan_pemerintahan_id')->nullable()->constrained('sub_kegiatan_pemerintahan')->nullOnDelete();
            $table->string('kode_urusan', 100)->nullable();
            $table->text('nama_urusan')->nullable();
            $table->string('kode_bidang', 100)->nullable();
            $table->text('nama_bidang')->nullable();
            $table->string('kode_program', 100)->nullable();
            $table->text('nama_program')->nullable();
            $table->string('kode_kegiatan', 100)->nullable();
            $table->text('nama_kegiatan')->nullable();
            $table->string('kode_sub_kegiatan', 100)->nullable()->index();
            $table->text('nama_sub_kegiatan')->nullable();
            $table->text('tolok_ukur_kinerja')->nullable();
            $table->string('target_kinerja')->nullable();
            $table->string('satuan_kinerja')->nullable();
            $table->text('sumber_pendanaan')->nullable();
            $table->text('lokasi')->nullable();
            $table->text('kelompok_sasaran')->nullable();
            $table->unsignedTinyInteger('bulan_mulai')->default(1);
            $table->unsignedTinyInteger('bulan_selesai')->default(12);
            $table->string('jenis_belanja', 30)->nullable();
            $table->decimal('alokasi_tahun_sebelumnya', 20, 2)->default(0);
            $table->decimal('pagu_renja', 20, 2)->default(0);
            $table->decimal('pagu_usulan', 20, 2)->default(0);
            $table->decimal('pagu_hasil_verifikasi', 20, 2)->default(0);
            $table->decimal('alokasi_tahun_berikutnya', 20, 2)->default(0);
            $table->text('alasan_penyesuaian')->nullable();
            $table->text('catatan')->nullable();
            $table->unsignedSmallInteger('urutan')->default(1);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['rka_opd_id', 'urutan']);
            $table->index(['rka_opd_id', 'program_pemerintahan_id', 'kegiatan_pemerintahan_id'], 'rka_items_hierarchy_index');
        });

        DB::statement('CREATE UNIQUE INDEX rka_opd_items_active_renja_item_unique ON rka_opd_items (rka_opd_id, renja_opd_item_id) WHERE deleted_at IS NULL');

        $now = now();
        $permissions = [
            ['name' => 'rka.view', 'label' => 'Lihat RKA OPD', 'module' => 'rka', 'is_system' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'rka.manage', 'label' => 'Kelola RKA OPD', 'module' => 'rka', 'is_system' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'rka.verify', 'label' => 'Verifikasi RKA OPD', 'module' => 'rka', 'is_system' => true, 'created_at' => $now, 'updated_at' => $now],
        ];

        DB::table('permissions')->upsert($permissions, ['name'], ['label', 'module', 'is_system', 'updated_at']);

        $rolePermissions = [
            'admin_kabupaten_bagian_organisasi' => ['rka.view'],
            'admin_kabupaten_bapperida' => ['rka.view', 'rka.manage', 'rka.verify'],
            'admin_kabupaten_inspektorat' => ['rka.view'],
            'admin_opd' => ['rka.view', 'rka.manage'],
            'pimpinan' => ['rka.view'],
        ];

        foreach ($rolePermissions as $roleName => $permissionNames) {
            $roleId = DB::table('roles')->where('name', $roleName)->value('id');
            if (! $roleId) {
                continue;
            }

            $permissionIds = DB::table('permissions')->whereIn('name', $permissionNames)->pluck('id');
            foreach ($permissionIds as $permissionId) {
                DB::table('permission_role')->insertOrIgnore([
                    'permission_id' => $permissionId,
                    'role_id' => $roleId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('rka_opd_items');
        Schema::dropIfExists('rka_opd');
        DB::table('permissions')->whereIn('name', ['rka.view', 'rka.manage', 'rka.verify'])->delete();
    }
};
