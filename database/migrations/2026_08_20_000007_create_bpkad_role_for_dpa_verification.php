<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        DB::table('roles')->updateOrInsert(
            ['name' => 'admin_kabupaten_bpkad'],
            [
                'label' => 'Admin Kabupaten BPKAD',
                'description' => 'Verifikasi anggaran dan pengesahan DPA sebagai fungsi PPKD/TAPD.',
                'is_system' => true,
                'deleted_at' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        );

        $bpkadRoleId = DB::table('roles')->where('name', 'admin_kabupaten_bpkad')->value('id');
        foreach (DB::table('permissions')->whereIn('name', ['dpa.view', 'dpa.verify'])->pluck('id') as $permissionId) {
            DB::table('permission_role')->insertOrIgnore([
                'permission_id' => $permissionId,
                'role_id' => $bpkadRoleId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $bapperidaRoleId = DB::table('roles')->where('name', 'admin_kabupaten_bapperida')->value('id');
        if ($bapperidaRoleId) {
            DB::table('permission_role')
                ->where('role_id', $bapperidaRoleId)
                ->whereIn('permission_id', DB::table('permissions')->whereIn('name', ['dpa.manage', 'dpa.verify'])->select('id'))
                ->delete();
        }
    }

    public function down(): void
    {
        $bpkadRoleId = DB::table('roles')->where('name', 'admin_kabupaten_bpkad')->value('id');
        if ($bpkadRoleId) {
            DB::table('permission_role')->where('role_id', $bpkadRoleId)->delete();
            if (! DB::table('role_user')->where('role_id', $bpkadRoleId)->exists()) {
                DB::table('roles')->where('id', $bpkadRoleId)->delete();
            }
        }

        $bapperidaRoleId = DB::table('roles')->where('name', 'admin_kabupaten_bapperida')->value('id');
        if ($bapperidaRoleId) {
            $now = now();
            foreach (DB::table('permissions')->whereIn('name', ['dpa.manage', 'dpa.verify'])->pluck('id') as $permissionId) {
                DB::table('permission_role')->insertOrIgnore([
                    'permission_id' => $permissionId,
                    'role_id' => $bapperidaRoleId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }
};
