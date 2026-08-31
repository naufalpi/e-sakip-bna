<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('jabatan_organisasi', function (Blueprint $table) {
            $table->string('verification_status', 20)->default('verified')->index();
            $table->foreignId('proposed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->text('verification_note')->nullable();
        });

        $now = now();
        $permissions = [
            [
                'name' => 'jabatan_organisasi.manage_opd',
                'label' => 'Kelola Usulan Jabatan OPD',
                'module' => 'jabatan_organisasi',
                'is_system' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'jabatan_organisasi.verify',
                'label' => 'Verifikasi Jabatan Organisasi',
                'module' => 'jabatan_organisasi',
                'is_system' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ];

        DB::table('permissions')->upsert($permissions, ['name'], ['label', 'module', 'is_system', 'updated_at']);

        $rolePermissions = [
            'super_admin' => ['jabatan_organisasi.manage_opd', 'jabatan_organisasi.verify'],
            'admin_kabupaten_bagian_organisasi' => ['jabatan_organisasi.verify'],
            'admin_kabupaten_dinkominfo' => ['jabatan_organisasi.verify'],
            'admin_opd' => ['jabatan_organisasi.manage_opd'],
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
            ->whereIn('name', ['jabatan_organisasi.manage_opd', 'jabatan_organisasi.verify'])
            ->pluck('id');

        DB::table('permission_role')->whereIn('permission_id', $permissionIds)->delete();
        DB::table('permissions')->whereIn('id', $permissionIds)->delete();

        Schema::table('jabatan_organisasi', function (Blueprint $table) {
            $table->dropConstrainedForeignId('proposed_by');
            $table->dropConstrainedForeignId('verified_by');
            $table->dropColumn(['verification_status', 'verified_at', 'verification_note']);
        });
    }
};
