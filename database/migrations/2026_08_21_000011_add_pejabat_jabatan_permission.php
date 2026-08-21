<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        DB::table('permissions')->updateOrInsert(
            ['name' => 'pejabat_jabatan.manage'],
            [
                'label' => 'Kelola Pejabat dalam Jabatan',
                'module' => 'jabatan_organisasi',
                'is_system' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        );

        $permissionId = DB::table('permissions')->where('name', 'pejabat_jabatan.manage')->value('id');

        foreach (['super_admin', 'admin_kabupaten_bagian_organisasi', 'admin_kabupaten_dinkominfo', 'admin_opd'] as $roleName) {
            $roleId = DB::table('roles')->where('name', $roleName)->value('id');

            if (! $roleId || ! $permissionId) {
                continue;
            }

            DB::table('permission_role')->insertOrIgnore([
                'permission_id' => $permissionId,
                'role_id' => $roleId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        $permissionId = DB::table('permissions')->where('name', 'pejabat_jabatan.manage')->value('id');

        if (! $permissionId) {
            return;
        }

        DB::table('permission_role')->where('permission_id', $permissionId)->delete();
        DB::table('permissions')->where('id', $permissionId)->delete();
    }
};
