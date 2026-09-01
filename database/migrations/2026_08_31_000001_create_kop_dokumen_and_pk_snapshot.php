<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kop_dokumen', function (Blueprint $table) {
            $table->id();
            $table->string('scope_key')->unique();
            $table->foreignId('opd_id')->nullable()->unique()->constrained('opds')->cascadeOnUpdate()->nullOnDelete();
            $table->string('nama_pemerintah')->default('PEMERINTAH KABUPATEN BANJARNEGARA');
            $table->string('nama_instansi');
            $table->text('alamat')->nullable();
            $table->string('telepon', 100)->nullable();
            $table->string('faksimile', 100)->nullable();
            $table->string('website')->nullable();
            $table->string('email')->nullable();
            $table->string('kota', 100)->default('BANJARNEGARA');
            $table->string('kode_pos', 20)->nullable();
            $table->string('logo_path')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::table('perjanjian_kinerja', function (Blueprint $table) {
            $table->json('kop_dokumen_snapshot')->nullable()->after('snapshot_dibuat_pada');
        });

        $now = now();
        $permissions = [
            ['name' => 'kop_dokumen.view', 'label' => 'Lihat Pengaturan Kop Dokumen', 'module' => 'kop_dokumen', 'is_system' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'kop_dokumen.manage', 'label' => 'Kelola Kop Dokumen', 'module' => 'kop_dokumen', 'is_system' => true, 'created_at' => $now, 'updated_at' => $now],
        ];

        DB::table('permissions')->upsert($permissions, ['name'], ['label', 'module', 'is_system', 'updated_at']);

        $rolePermissions = [
            'super_admin' => ['kop_dokumen.view', 'kop_dokumen.manage'],
            'admin_kabupaten_bagian_organisasi' => ['kop_dokumen.view', 'kop_dokumen.manage'],
            'admin_kabupaten_bapperida' => ['kop_dokumen.view', 'kop_dokumen.manage'],
            'admin_kabupaten_bpkad' => ['kop_dokumen.view', 'kop_dokumen.manage'],
            'admin_kabupaten_inspektorat' => ['kop_dokumen.view', 'kop_dokumen.manage'],
            'admin_kabupaten_dinkominfo' => ['kop_dokumen.view', 'kop_dokumen.manage'],
            'admin_opd' => ['kop_dokumen.view', 'kop_dokumen.manage'],
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

        DB::table('perjanjian_kinerja')
            ->whereNull('kop_dokumen_snapshot')
            ->orderBy('id')
            ->each(function (object $pk): void {
                $opd = $pk->opd_id ? DB::table('opds')->where('id', $pk->opd_id)->first() : null;
                $isBupati = ($pk->level_pk ?? null) === 'bupati';

                DB::table('perjanjian_kinerja')->where('id', $pk->id)->update([
                    'kop_dokumen_snapshot' => json_encode([
                        'nama_pemerintah' => 'PEMERINTAH KABUPATEN BANJARNEGARA',
                        'nama_instansi' => $isBupati ? 'BUPATI BANJARNEGARA' : mb_strtoupper((string) ($opd->nama ?? 'PERANGKAT DAERAH')),
                        'alamat' => $isBupati ? 'Jalan Ahmad Yani No. 16 Banjarnegara' : ($opd->alamat ?? 'Kabupaten Banjarnegara, Jawa Tengah'),
                        'telepon' => $opd->telepon ?? null,
                        'faksimile' => null,
                        'website' => null,
                        'email' => $opd->email ?? null,
                        'kota' => 'BANJARNEGARA',
                        'kode_pos' => null,
                        'logo_path' => null,
                    ], JSON_UNESCAPED_UNICODE),
                ]);
            });
    }

    public function down(): void
    {
        $permissionIds = DB::table('permissions')
            ->whereIn('name', ['kop_dokumen.view', 'kop_dokumen.manage'])
            ->pluck('id');

        DB::table('permission_role')->whereIn('permission_id', $permissionIds)->delete();
        DB::table('permissions')->whereIn('id', $permissionIds)->delete();

        Schema::table('perjanjian_kinerja', function (Blueprint $table) {
            $table->dropColumn('kop_dokumen_snapshot');
        });

        Schema::dropIfExists('kop_dokumen');
    }
};
