<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        $permissions = [
            ['name' => 'jabatan_organisasi.view', 'label' => 'Lihat Master Jabatan Organisasi'],
            ['name' => 'jabatan_organisasi.manage', 'label' => 'Kelola Master Jabatan Organisasi'],
        ];

        foreach ($permissions as $permission) {
            DB::table('permissions')->updateOrInsert(
                ['name' => $permission['name']],
                [
                    'label' => $permission['label'],
                    'module' => 'jabatan_organisasi',
                    'is_system' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            );
        }

        $rolePermissions = [
            'super_admin' => ['jabatan_organisasi.view', 'jabatan_organisasi.manage'],
            'admin_kabupaten_bagian_organisasi' => ['jabatan_organisasi.view', 'jabatan_organisasi.manage'],
            'admin_kabupaten_dinkominfo' => ['jabatan_organisasi.view', 'jabatan_organisasi.manage'],
            'admin_kabupaten_bapperida' => ['jabatan_organisasi.view'],
            'admin_kabupaten_bpkad' => ['jabatan_organisasi.view'],
            'admin_kabupaten_inspektorat' => ['jabatan_organisasi.view'],
            'admin_opd' => ['jabatan_organisasi.view'],
            'pimpinan' => ['jabatan_organisasi.view'],
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

    public function down(): void
    {
        $permissionIds = DB::table('permissions')
            ->whereIn('name', ['jabatan_organisasi.view', 'jabatan_organisasi.manage'])
            ->pluck('id');

        DB::table('permission_role')->whereIn('permission_id', $permissionIds)->delete();
        DB::table('permissions')->whereIn('id', $permissionIds)->delete();
    }
};
