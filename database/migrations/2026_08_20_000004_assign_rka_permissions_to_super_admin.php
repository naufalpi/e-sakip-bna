<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $roleId = DB::table('roles')->where('name', 'super_admin')->value('id');
        if (! $roleId) {
            return;
        }

        $now = now();
        DB::table('permissions')
            ->whereIn('name', ['rka.view', 'rka.manage', 'rka.verify'])
            ->pluck('id')
            ->each(fn ($permissionId) => DB::table('permission_role')->insertOrIgnore([
                'permission_id' => $permissionId,
                'role_id' => $roleId,
                'created_at' => $now,
                'updated_at' => $now,
            ]));
    }

    public function down(): void
    {
        $roleId = DB::table('roles')->where('name', 'super_admin')->value('id');
        if (! $roleId) {
            return;
        }

        DB::table('permission_role')
            ->where('role_id', $roleId)
            ->whereIn('permission_id', DB::table('permissions')->whereIn('name', ['rka.view', 'rka.manage', 'rka.verify'])->select('id'))
            ->delete();
    }
};
