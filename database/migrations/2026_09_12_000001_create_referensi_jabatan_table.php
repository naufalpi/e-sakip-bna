<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('referensi_jabatan', function (Blueprint $table) {
            $table->id();
            $table->string('identity_key', 64)->unique();
            $table->string('kode', 120)->nullable()->unique();
            $table->string('nama');
            $table->string('jenis_jabatan', 30)->index();
            $table->string('jenjang', 120)->nullable()->index();
            $table->unsignedTinyInteger('kelas_jabatan')->nullable()->index();
            $table->text('kualifikasi')->nullable();
            $table->text('dasar_hukum')->nullable();
            $table->date('berlaku_mulai')->nullable();
            $table->date('berlaku_sampai')->nullable();
            $table->string('verification_status', 20)->default('draft')->index();
            $table->string('status', 20)->default('active')->index();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['jenis_jabatan', 'status', 'verification_status'], 'referensi_jabatan_filter_index');
        });

        Schema::table('jabatan_organisasi', function (Blueprint $table) {
            $table->foreignId('referensi_jabatan_id')
                ->nullable()
                ->after('parent_id')
                ->constrained('referensi_jabatan')
                ->nullOnDelete();
            $table->index(['referensi_jabatan_id', 'opd_id'], 'jabatan_referensi_opd_index');
        });

        $now = now();
        $existing = DB::table('jabatan_organisasi')
            ->whereNull('deleted_at')
            ->whereIn('level_jabatan', ['fungsional', 'pelaksana'])
            ->select(['nama', 'level_jabatan'])
            ->distinct()
            ->orderBy('level_jabatan')
            ->orderBy('nama')
            ->get();

        foreach ($existing as $row) {
            $normalizedName = preg_replace('/\s+/u', ' ', mb_strtolower(trim((string) $row->nama))) ?: '';
            $identityKey = hash('sha256', $row->level_jabatan.'|'.$normalizedName.'|');

            DB::table('referensi_jabatan')->insertOrIgnore([
                'identity_key' => $identityKey,
                'kode' => null,
                'nama' => $row->nama,
                'jenis_jabatan' => $row->level_jabatan,
                'jenjang' => null,
                'kelas_jabatan' => null,
                'kualifikasi' => null,
                'dasar_hukum' => 'Migrasi dari data jabatan organisasi sebelum penerapan referensi global.',
                'berlaku_mulai' => null,
                'berlaku_sampai' => null,
                'verification_status' => 'draft',
                'status' => 'active',
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $referenceId = DB::table('referensi_jabatan')->where('identity_key', $identityKey)->value('id');
            DB::table('jabatan_organisasi')
                ->where('level_jabatan', $row->level_jabatan)
                ->where('nama', $row->nama)
                ->update(['referensi_jabatan_id' => $referenceId]);
        }

        $permissions = [
            [
                'name' => 'referensi_jabatan.view',
                'label' => 'Lihat Referensi Jabatan Global',
                'module' => 'jabatan_organisasi',
                'description' => 'Melihat nomenklatur jabatan fungsional dan pelaksana yang berlaku.',
                'is_system' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'referensi_jabatan.manage',
                'label' => 'Kelola Referensi Jabatan Global',
                'module' => 'jabatan_organisasi',
                'description' => 'Menetapkan nomenklatur, kelas, dasar hukum, dan masa berlaku referensi jabatan.',
                'is_system' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ];

        DB::table('permissions')->upsert(
            $permissions,
            ['name'],
            ['label', 'module', 'description', 'is_system', 'updated_at'],
        );

        $rolePermissions = [
            'super_admin' => ['referensi_jabatan.view', 'referensi_jabatan.manage'],
            'admin_kabupaten_bagian_organisasi' => ['referensi_jabatan.view', 'referensi_jabatan.manage'],
            'admin_kabupaten_dinkominfo' => ['referensi_jabatan.view', 'referensi_jabatan.manage'],
            'admin_kabupaten_bapperida' => ['referensi_jabatan.view'],
            'admin_kabupaten_bpkad' => ['referensi_jabatan.view'],
            'admin_kabupaten_inspektorat' => ['referensi_jabatan.view'],
            'admin_opd' => ['referensi_jabatan.view'],
            'pimpinan' => ['referensi_jabatan.view'],
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

        $organizationRoleId = DB::table('roles')->where('name', 'admin_kabupaten_bagian_organisasi')->value('id');
        $unitManagePermissionId = DB::table('permissions')->where('name', 'opd_units.manage')->value('id');
        if ($organizationRoleId && $unitManagePermissionId) {
            DB::table('permission_role')->insertOrIgnore([
                'permission_id' => $unitManagePermissionId,
                'role_id' => $organizationRoleId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        $permissionIds = DB::table('permissions')
            ->whereIn('name', ['referensi_jabatan.view', 'referensi_jabatan.manage'])
            ->pluck('id');

        DB::table('permission_role')->whereIn('permission_id', $permissionIds)->delete();
        DB::table('permissions')->whereIn('id', $permissionIds)->delete();

        Schema::table('jabatan_organisasi', function (Blueprint $table) {
            $table->dropIndex('jabatan_referensi_opd_index');
            $table->dropConstrainedForeignId('referensi_jabatan_id');
        });

        Schema::dropIfExists('referensi_jabatan');
    }
};
