<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pegawai', function (Blueprint $table) {
            $table->id();
            $table->foreignId('opd_id')->nullable()->constrained('opds')->nullOnDelete();
            $table->foreignId('opd_unit_id')->nullable()->constrained('opd_units')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->unique()->constrained('users')->nullOnDelete();
            $table->string('nama');
            $table->string('nip', 30)->nullable()->unique();
            $table->string('pangkat_golongan', 120)->nullable();
            $table->string('jenis_pegawai', 30)->default('pns')->index();
            $table->string('status', 20)->default('active')->index();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['opd_id', 'status']);
            $table->index(['opd_unit_id', 'status']);
        });

        Schema::table('riwayat_pejabat_jabatan', function (Blueprint $table) {
            $table->foreignId('pegawai_id')
                ->nullable()
                ->after('jabatan_organisasi_id')
                ->constrained('pegawai')
                ->nullOnDelete();
            $table->index(['pegawai_id', 'tanggal_mulai'], 'penempatan_pegawai_mulai_index');
        });

        $this->backfillPegawai();

        Schema::create('penugasan_pengampu_kinerja', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pegawai_id')->constrained('pegawai')->cascadeOnDelete();
            $table->foreignId('penempatan_pegawai_id')->nullable()->constrained('riwayat_pejabat_jabatan')->nullOnDelete();
            $table->foreignId('opd_id')->constrained('opds')->cascadeOnDelete();
            $table->foreignId('periode_tahun_id')->constrained('periode_tahun')->cascadeOnDelete();
            $table->unsignedSmallInteger('tahun')->index();
            $table->string('sumber_kinerja_type', 40)->index();
            $table->unsignedBigInteger('sumber_kinerja_id')->index();
            $table->text('sumber_kinerja_label');
            $table->string('peran', 30)->default('penanggung_jawab')->index();
            $table->string('status', 20)->default('active')->index();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(
                ['pegawai_id', 'periode_tahun_id', 'sumber_kinerja_type', 'sumber_kinerja_id'],
                'pengampu_kinerja_unique',
            );
            $table->index(['opd_id', 'tahun', 'status'], 'pengampu_opd_tahun_status_index');
        });

        Schema::table('perjanjian_kinerja', function (Blueprint $table) {
            $table->foreignId('pegawai_id')->nullable()->after('opd_id')->constrained('pegawai')->nullOnDelete();
            $table->foreignId('penempatan_pegawai_id')->nullable()->after('pegawai_id')->constrained('riwayat_pejabat_jabatan')->nullOnDelete();
            $table->foreignId('atasan_pegawai_id')->nullable()->after('penempatan_pegawai_id')->constrained('pegawai')->nullOnDelete();
            $table->string('tipe_pk', 30)->default('cascading')->after('tahun')->index();
            $table->string('nama_pegawai_snapshot')->nullable()->after('tipe_pk');
            $table->string('nip_snapshot', 30)->nullable()->after('nama_pegawai_snapshot');
            $table->string('jabatan_snapshot')->nullable()->after('nip_snapshot');
        });

        Schema::table('perjanjian_kinerja_items', function (Blueprint $table) {
            $table->string('sumber_item', 30)->default('cascading')->after('perjanjian_kinerja_id')->index();
            $table->string('level_cascading', 40)->nullable()->after('sumber_item');
            $table->string('cascading_source_type', 40)->nullable()->after('level_cascading');
            $table->unsignedBigInteger('cascading_source_id')->nullable()->after('cascading_source_type');
            $table->index(['cascading_source_type', 'cascading_source_id'], 'pk_item_cascading_source_index');
        });

        $this->createPermissions();
    }

    public function down(): void
    {
        $permissionIds = DB::table('permissions')
            ->whereIn('name', ['pegawai.view', 'pegawai.manage'])
            ->pluck('id');
        DB::table('permission_role')->whereIn('permission_id', $permissionIds)->delete();
        DB::table('permissions')->whereIn('id', $permissionIds)->delete();

        Schema::table('perjanjian_kinerja_items', function (Blueprint $table) {
            $table->dropIndex('pk_item_cascading_source_index');
            $table->dropColumn(['sumber_item', 'level_cascading', 'cascading_source_type', 'cascading_source_id']);
        });

        Schema::table('perjanjian_kinerja', function (Blueprint $table) {
            $table->dropConstrainedForeignId('atasan_pegawai_id');
            $table->dropConstrainedForeignId('penempatan_pegawai_id');
            $table->dropConstrainedForeignId('pegawai_id');
            $table->dropColumn(['tipe_pk', 'nama_pegawai_snapshot', 'nip_snapshot', 'jabatan_snapshot']);
        });

        Schema::dropIfExists('penugasan_pengampu_kinerja');

        Schema::table('riwayat_pejabat_jabatan', function (Blueprint $table) {
            $table->dropIndex('penempatan_pegawai_mulai_index');
            $table->dropConstrainedForeignId('pegawai_id');
        });

        Schema::dropIfExists('pegawai');
    }

    private function backfillPegawai(): void
    {
        $jobs = DB::table('jabatan_organisasi')
            ->select(['id', 'opd_id', 'opd_unit_id'])
            ->get()
            ->keyBy('id');

        DB::table('riwayat_pejabat_jabatan')
            ->whereNull('deleted_at')
            ->orderBy('id')
            ->get()
            ->each(function (object $history) use ($jobs): void {
                $job = $jobs->get($history->jabatan_organisasi_id);
                $pegawaiId = null;
                $nip = filled($history->nip) ? trim((string) $history->nip) : null;

                if ($nip) {
                    $pegawaiId = DB::table('pegawai')->where('nip', $nip)->value('id');
                }

                if (! $pegawaiId && $history->user_id) {
                    $pegawaiId = DB::table('pegawai')->where('user_id', $history->user_id)->value('id');
                }

                if (! $pegawaiId && ! $nip && ! $history->user_id) {
                    $pegawaiId = DB::table('pegawai')
                        ->where('opd_id', $job?->opd_id)
                        ->where('nama', $history->nama_pejabat)
                        ->value('id');
                }

                if (! $pegawaiId) {
                    $pegawaiId = DB::table('pegawai')->insertGetId([
                        'opd_id' => $job?->opd_id,
                        'opd_unit_id' => $job?->opd_unit_id,
                        'user_id' => $history->user_id,
                        'nama' => $history->nama_pejabat,
                        'nip' => $nip,
                        'pangkat_golongan' => $history->pangkat_golongan,
                        'jenis_pegawai' => 'pns',
                        'status' => 'active',
                        'created_at' => $history->created_at ?? now(),
                        'updated_at' => $history->updated_at ?? now(),
                    ]);
                }

                DB::table('riwayat_pejabat_jabatan')
                    ->where('id', $history->id)
                    ->update(['pegawai_id' => $pegawaiId]);
            });
    }

    private function createPermissions(): void
    {
        $now = now();

        foreach ([
            ['name' => 'pegawai.view', 'label' => 'Lihat Master Pegawai'],
            ['name' => 'pegawai.manage', 'label' => 'Kelola Pegawai dan Penempatan'],
        ] as $permission) {
            DB::table('permissions')->updateOrInsert(
                ['name' => $permission['name']],
                [
                    'label' => $permission['label'],
                    'module' => 'pegawai',
                    'is_system' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            );
        }

        $rolePermissions = [
            'super_admin' => ['pegawai.view', 'pegawai.manage'],
            'admin_kabupaten_bagian_organisasi' => ['pegawai.view', 'pegawai.manage'],
            'admin_kabupaten_dinkominfo' => ['pegawai.view', 'pegawai.manage'],
            'admin_opd' => ['pegawai.view', 'pegawai.manage'],
            'admin_kabupaten_bapperida' => ['pegawai.view'],
            'admin_kabupaten_bpkad' => ['pegawai.view'],
            'admin_kabupaten_inspektorat' => ['pegawai.view'],
            'pimpinan' => ['pegawai.view'],
        ];

        foreach ($rolePermissions as $roleName => $permissionNames) {
            $roleId = DB::table('roles')->where('name', $roleName)->value('id');
            if (! $roleId) {
                continue;
            }

            foreach (DB::table('permissions')->whereIn('name', $permissionNames)->pluck('id') as $permissionId) {
                DB::table('permission_role')->insertOrIgnore([
                    'permission_id' => $permissionId,
                    'role_id' => $roleId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }
};
