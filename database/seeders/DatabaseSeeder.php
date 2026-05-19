<?php

namespace Database\Seeders;

use App\Models\PeriodeTahun;
use App\Models\Permission;
use App\Models\Role;
use App\Models\SatuanIndikator;
use App\Models\SystemSetting;
use App\Models\UrusanPemerintahan;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $permissions = collect([
            ['name' => 'dashboard.view', 'label' => 'Lihat Dashboard', 'module' => 'dashboard'],
            ['name' => 'opd.view', 'label' => 'Lihat OPD', 'module' => 'master_opd'],
            ['name' => 'opd.manage', 'label' => 'Kelola OPD', 'module' => 'master_opd'],
            ['name' => 'users.view', 'label' => 'Lihat User', 'module' => 'user'],
            ['name' => 'users.manage', 'label' => 'Kelola User', 'module' => 'user'],
            ['name' => 'roles.view', 'label' => 'Lihat Role Permission', 'module' => 'role_permission'],
            ['name' => 'periode.view', 'label' => 'Lihat Periode', 'module' => 'periode'],
            ['name' => 'periode.manage', 'label' => 'Kelola Periode', 'module' => 'periode'],
            ['name' => 'satuan.view', 'label' => 'Lihat Satuan Indikator', 'module' => 'satuan_indikator'],
            ['name' => 'satuan.manage', 'label' => 'Kelola Satuan Indikator', 'module' => 'satuan_indikator'],
            ['name' => 'urusan.view', 'label' => 'Lihat Urusan Pemerintahan', 'module' => 'urusan_pemerintahan'],
            ['name' => 'urusan.manage', 'label' => 'Kelola Urusan Pemerintahan', 'module' => 'urusan_pemerintahan'],
            ['name' => 'settings.view', 'label' => 'Lihat Pengaturan Sistem', 'module' => 'system_settings'],
            ['name' => 'settings.manage', 'label' => 'Kelola Pengaturan Sistem', 'module' => 'system_settings'],
            ['name' => 'activity_logs.view', 'label' => 'Lihat Audit Log', 'module' => 'audit_log'],
            ['name' => 'rpjmd.view', 'label' => 'Lihat RPJMD', 'module' => 'rpjmd'],
            ['name' => 'rpjmd.manage', 'label' => 'Kelola RPJMD', 'module' => 'rpjmd'],
            ['name' => 'renstra.view', 'label' => 'Lihat Renstra OPD', 'module' => 'renstra'],
            ['name' => 'renstra.manage', 'label' => 'Kelola Renstra OPD', 'module' => 'renstra'],
            ['name' => 'kinerja.view', 'label' => 'Lihat Kinerja', 'module' => 'kinerja'],
            ['name' => 'kinerja.manage', 'label' => 'Kelola Kinerja', 'module' => 'kinerja'],
            ['name' => 'evaluasi.view', 'label' => 'Lihat Evaluasi SAKIP', 'module' => 'evaluasi_sakip'],
            ['name' => 'evaluasi.manage', 'label' => 'Kelola Evaluasi SAKIP', 'module' => 'evaluasi_sakip'],
            ['name' => 'dokumen.view', 'label' => 'Lihat Dokumen', 'module' => 'dokumen'],
            ['name' => 'dokumen.manage', 'label' => 'Kelola Dokumen', 'module' => 'dokumen'],
            ['name' => 'laporan.view', 'label' => 'Lihat Laporan', 'module' => 'laporan'],
        ])->mapWithKeys(function (array $permission) {
            $model = Permission::updateOrCreate(
                ['name' => $permission['name']],
                [...$permission, 'is_system' => true],
            );

            return [$model->name => $model];
        });

        $roles = [
            'super_admin' => [
                'label' => 'Super Admin',
                'description' => 'Akses penuh ke seluruh aplikasi.',
                'permissions' => $permissions->keys()->all(),
            ],
            'admin_kabupaten_bagian_organisasi' => [
                'label' => 'Admin Kabupaten Bagian Organisasi',
                'description' => 'Monitoring, validasi umum, dan melihat progres OPD.',
                'permissions' => ['dashboard.view', 'opd.view', 'users.view', 'roles.view', 'periode.view', 'satuan.view', 'urusan.view', 'rpjmd.view', 'renstra.view', 'kinerja.view', 'evaluasi.view', 'dokumen.view', 'laporan.view'],
            ],
            'admin_kabupaten_bapperida' => [
                'label' => 'Admin Kabupaten Bapperida',
                'description' => 'Input dan kelola data perencanaan kabupaten/RPJMD.',
                'permissions' => ['dashboard.view', 'opd.view', 'roles.view', 'periode.view', 'satuan.view', 'urusan.view', 'rpjmd.view', 'rpjmd.manage', 'renstra.view', 'laporan.view'],
            ],
            'admin_kabupaten_inspektorat' => [
                'label' => 'Admin Kabupaten Inspektorat',
                'description' => 'Evaluasi kinerja, LKE, LHE, rekomendasi, dan verifikasi tindak lanjut.',
                'permissions' => ['dashboard.view', 'opd.view', 'roles.view', 'periode.view', 'evaluasi.view', 'evaluasi.manage', 'dokumen.view', 'dokumen.manage', 'laporan.view'],
            ],
            'admin_kabupaten_dinkominfo' => [
                'label' => 'Admin Kabupaten Dinkominfo',
                'description' => 'Kelola master data umum, OPD, user, dan konfigurasi aplikasi.',
                'permissions' => ['dashboard.view', 'opd.view', 'opd.manage', 'users.view', 'users.manage', 'roles.view', 'periode.view', 'periode.manage', 'satuan.view', 'satuan.manage', 'urusan.view', 'urusan.manage', 'settings.view', 'settings.manage', 'activity_logs.view'],
            ],
            'admin_opd' => [
                'label' => 'Admin OPD',
                'description' => 'Kelola data perencanaan dan kinerja OPD masing-masing.',
                'permissions' => ['dashboard.view', 'opd.view', 'roles.view', 'periode.view', 'satuan.view', 'rpjmd.view', 'renstra.view', 'renstra.manage', 'kinerja.view', 'kinerja.manage', 'dokumen.view', 'dokumen.manage', 'evaluasi.view'],
            ],
            'pimpinan' => [
                'label' => 'Pimpinan',
                'description' => 'Hanya melihat dashboard, laporan, progres, capaian, dan status evaluasi.',
                'permissions' => ['dashboard.view'],
            ],
        ];

        foreach ($roles as $name => $roleData) {
            $role = Role::updateOrCreate(
                ['name' => $name],
                [
                    'label' => $roleData['label'],
                    'description' => $roleData['description'],
                    'is_system' => true,
                ],
            );

            $role->permissions()->sync(
                $permissions->only($roleData['permissions'])->pluck('id')->all()
            );
        }

        $superAdmin = User::updateOrCreate(
            ['email' => env('SUPER_ADMIN_EMAIL', 'admin@example.com')],
            [
                'name' => env('SUPER_ADMIN_NAME', 'Super Admin'),
                'password' => Hash::make(env('SUPER_ADMIN_PASSWORD', 'password')),
                'status' => 'active',
            ],
        );

        $superAdmin->roles()->sync([
            Role::where('name', 'super_admin')->value('id'),
        ]);

        foreach (range(((int) date('Y')) - 1, ((int) date('Y')) + 3) as $year) {
            PeriodeTahun::updateOrCreate(
                ['tahun' => $year],
                [
                    'nama' => "Tahun {$year}",
                    'tanggal_mulai' => "{$year}-01-01",
                    'tanggal_selesai' => "{$year}-12-31",
                    'status' => $year === (int) date('Y') ? 'active' : 'draft',
                ],
            );
        }

        foreach ([
            ['nama' => 'Persen', 'simbol' => '%', 'jenis' => 'persentase'],
            ['nama' => 'Nilai', 'simbol' => null, 'jenis' => 'angka'],
            ['nama' => 'Dokumen', 'simbol' => 'dok', 'jenis' => 'jumlah'],
            ['nama' => 'Kegiatan', 'simbol' => 'keg', 'jenis' => 'jumlah'],
            ['nama' => 'Orang', 'simbol' => 'orang', 'jenis' => 'jumlah'],
            ['nama' => 'Rupiah', 'simbol' => 'Rp', 'jenis' => 'mata_uang'],
        ] as $satuan) {
            SatuanIndikator::updateOrCreate(
                ['nama' => $satuan['nama']],
                [...$satuan, 'status' => 'active'],
            );
        }

        UrusanPemerintahan::updateOrCreate(
            ['kode' => '00'],
            [
                'nama' => 'Urusan Pemerintahan Umum',
                'deskripsi' => 'Urusan pemerintahan umum untuk data awal.',
                'status' => 'active',
            ],
        );

        SystemSetting::updateOrCreate(
            ['key' => 'app.name'],
            [
                'group' => 'umum',
                'label' => 'Nama Aplikasi',
                'type' => 'string',
                'value' => config('app.name'),
                'is_public' => true,
            ],
        );
    }
}
