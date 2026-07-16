<?php

namespace Database\Seeders;

use App\Models\BidangUrusan;
use App\Models\KomponenEvaluasi;
use App\Models\PeriodeTahun;
use App\Models\Permission;
use App\Models\PredikatEvaluasi;
use App\Models\Role;
use App\Models\SatuanIndikator;
use App\Models\SystemSetting;
use App\Models\UrusanPemerintahan;
use App\Models\User;
use App\Support\SystemSettingCatalog;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $minimalPermissions = [
            ['name' => 'manage_users', 'label' => 'Kelola User', 'module' => 'user'],
            ['name' => 'manage_roles', 'label' => 'Kelola Role', 'module' => 'role_permission'],
            ['name' => 'manage_opd', 'label' => 'Kelola OPD', 'module' => 'master_opd'],
            ['name' => 'manage_master_umum', 'label' => 'Kelola Master Umum', 'module' => 'master_umum'],
            ['name' => 'manage_rpjmd', 'label' => 'Kelola RPJMD', 'module' => 'rpjmd'],
            ['name' => 'view_rpjmd', 'label' => 'Lihat RPJMD', 'module' => 'rpjmd'],
            ['name' => 'manage_renstra_opd', 'label' => 'Kelola Renstra OPD', 'module' => 'renstra'],
            ['name' => 'view_renstra_opd', 'label' => 'Lihat Renstra OPD', 'module' => 'renstra'],
            ['name' => 'manage_perjanjian_kinerja', 'label' => 'Kelola Perjanjian Kinerja', 'module' => 'perjanjian_kinerja'],
            ['name' => 'manage_rencana_aksi', 'label' => 'Kelola Rencana Aksi', 'module' => 'rencana_aksi'],
            ['name' => 'input_realisasi', 'label' => 'Input Realisasi', 'module' => 'realisasi'],
            ['name' => 'verify_realisasi', 'label' => 'Verifikasi Realisasi', 'module' => 'realisasi'],
            ['name' => 'manage_evaluasi', 'label' => 'Kelola Evaluasi', 'module' => 'evaluasi_sakip'],
            ['name' => 'view_dashboard_kabupaten', 'label' => 'Lihat Dashboard Kabupaten', 'module' => 'dashboard'],
            ['name' => 'view_dashboard_opd', 'label' => 'Lihat Dashboard OPD', 'module' => 'dashboard'],
            ['name' => 'view_dashboard_pimpinan', 'label' => 'Lihat Dashboard Pimpinan', 'module' => 'dashboard'],
            ['name' => 'manage_dokumen', 'label' => 'Kelola Dokumen', 'module' => 'dokumen'],
            ['name' => 'export_laporan', 'label' => 'Export Laporan', 'module' => 'laporan'],
            ['name' => 'lock_period', 'label' => 'Kunci Periode', 'module' => 'periode'],
        ];

        $uiPermissions = [
            ['name' => 'dashboard.view', 'label' => 'Lihat Dashboard', 'module' => 'dashboard'],
            ['name' => 'opd.view', 'label' => 'Lihat OPD', 'module' => 'master_opd'],
            ['name' => 'opd.manage', 'label' => 'Kelola OPD', 'module' => 'master_opd'],
            ['name' => 'opd_units.manage', 'label' => 'Kelola Unit OPD', 'module' => 'master_opd'],
            ['name' => 'users.view', 'label' => 'Lihat User', 'module' => 'user'],
            ['name' => 'users.manage', 'label' => 'Kelola User', 'module' => 'user'],
            ['name' => 'roles.view', 'label' => 'Lihat Role Permission', 'module' => 'role_permission'],
            ['name' => 'periode.view', 'label' => 'Lihat Periode', 'module' => 'periode'],
            ['name' => 'periode.manage', 'label' => 'Kelola Periode', 'module' => 'periode'],
            ['name' => 'satuan.view', 'label' => 'Lihat Satuan Indikator', 'module' => 'satuan_indikator'],
            ['name' => 'satuan.manage', 'label' => 'Kelola Satuan Indikator', 'module' => 'satuan_indikator'],
            ['name' => 'strategi.view', 'label' => 'Lihat Strategi Daerah', 'module' => 'strategi_daerah'],
            ['name' => 'strategi.manage', 'label' => 'Kelola Strategi Daerah', 'module' => 'strategi_daerah'],
            ['name' => 'urusan.view', 'label' => 'Lihat Urusan Pemerintahan', 'module' => 'urusan_pemerintahan'],
            ['name' => 'urusan.manage', 'label' => 'Kelola Urusan Pemerintahan', 'module' => 'urusan_pemerintahan'],
            ['name' => 'settings.view', 'label' => 'Lihat Pengaturan Sistem', 'module' => 'system_settings'],
            ['name' => 'settings.manage', 'label' => 'Kelola Pengaturan Sistem', 'module' => 'system_settings'],
            ['name' => 'activity_logs.view', 'label' => 'Lihat Audit Log', 'module' => 'audit_log'],
            ['name' => 'rpjmd.view', 'label' => 'Lihat RPJMD', 'module' => 'rpjmd'],
            ['name' => 'rpjmd.manage', 'label' => 'Kelola RPJMD', 'module' => 'rpjmd'],
            ['name' => 'renstra.view', 'label' => 'Lihat Renstra OPD', 'module' => 'renstra'],
            ['name' => 'renstra.manage', 'label' => 'Kelola Renstra OPD', 'module' => 'renstra'],
            ['name' => 'rkpd.view', 'label' => 'Lihat RKPD', 'module' => 'rkpd'],
            ['name' => 'rkpd.manage', 'label' => 'Kelola RKPD', 'module' => 'rkpd'],
            ['name' => 'renja.view', 'label' => 'Lihat Renja OPD', 'module' => 'renja'],
            ['name' => 'renja.manage', 'label' => 'Kelola Renja OPD', 'module' => 'renja'],
            ['name' => 'kinerja.view', 'label' => 'Lihat Kinerja', 'module' => 'kinerja'],
            ['name' => 'kinerja.manage', 'label' => 'Kelola Kinerja', 'module' => 'kinerja'],
            ['name' => 'evaluasi.view', 'label' => 'Lihat Evaluasi SAKIP', 'module' => 'evaluasi_sakip'],
            ['name' => 'evaluasi.manage', 'label' => 'Kelola Evaluasi SAKIP', 'module' => 'evaluasi_sakip'],
            ['name' => 'dokumen.view', 'label' => 'Lihat Dokumen', 'module' => 'dokumen'],
            ['name' => 'dokumen.manage', 'label' => 'Kelola Dokumen', 'module' => 'dokumen'],
            ['name' => 'lkjip.view', 'label' => 'Lihat LKJIP', 'module' => 'lkjip'],
            ['name' => 'lkjip.manage', 'label' => 'Kelola LKJIP', 'module' => 'lkjip'],
            ['name' => 'laporan.view', 'label' => 'Lihat Laporan', 'module' => 'laporan'],
            ['name' => 'laporan.manage', 'label' => 'Kelola Laporan', 'module' => 'laporan'],
        ];

        $permissions = collect([...$minimalPermissions, ...$uiPermissions])->mapWithKeys(function (array $permission) {
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
                'permissions' => ['dashboard.view', 'view_dashboard_kabupaten', 'opd.view', 'users.view', 'periode.view', 'satuan.view', 'strategi.view', 'urusan.view', 'rpjmd.view', 'view_rpjmd', 'renstra.view', 'view_renstra_opd', 'rkpd.view', 'renja.view', 'kinerja.view', 'evaluasi.view', 'dokumen.view', 'lkjip.view', 'laporan.view', 'export_laporan'],
            ],
            'admin_kabupaten_bapperida' => [
                'label' => 'Admin Kabupaten Bapperida',
                'description' => 'Input dan kelola data perencanaan kabupaten/RPJMD.',
                'permissions' => ['dashboard.view', 'view_dashboard_kabupaten', 'opd.view', 'periode.view', 'satuan.view', 'strategi.view', 'strategi.manage', 'urusan.view', 'rpjmd.view', 'rpjmd.manage', 'view_rpjmd', 'manage_rpjmd', 'renstra.view', 'view_renstra_opd', 'rkpd.view', 'rkpd.manage', 'renja.view', 'kinerja.view', 'dokumen.view', 'dokumen.manage', 'manage_dokumen', 'lkjip.view', 'laporan.view', 'export_laporan'],
            ],
            'admin_kabupaten_inspektorat' => [
                'label' => 'Admin Kabupaten Inspektorat',
                'description' => 'Evaluasi kinerja, LKE, LHE, rekomendasi, dan verifikasi tindak lanjut.',
                'permissions' => ['dashboard.view', 'view_dashboard_kabupaten', 'opd.view', 'periode.view', 'strategi.view', 'rpjmd.view', 'view_rpjmd', 'renstra.view', 'view_renstra_opd', 'rkpd.view', 'renja.view', 'kinerja.view', 'evaluasi.view', 'evaluasi.manage', 'manage_evaluasi', 'verify_realisasi', 'dokumen.view', 'dokumen.manage', 'manage_dokumen', 'lkjip.view', 'laporan.view', 'export_laporan'],
            ],
            'admin_kabupaten_dinkominfo' => [
                'label' => 'Admin Kabupaten Dinkominfo',
                'description' => 'Kelola master data umum, OPD, user, dan konfigurasi aplikasi.',
                'permissions' => ['dashboard.view', 'view_dashboard_kabupaten', 'opd.view', 'opd.manage', 'opd_units.manage', 'manage_opd', 'users.view', 'users.manage', 'manage_users', 'roles.view', 'manage_roles', 'periode.view', 'periode.manage', 'lock_period', 'satuan.view', 'satuan.manage', 'strategi.view', 'strategi.manage', 'urusan.view', 'urusan.manage', 'manage_master_umum', 'settings.view', 'settings.manage', 'activity_logs.view'],
            ],
            'admin_opd' => [
                'label' => 'Admin OPD',
                'description' => 'Kelola data perencanaan dan kinerja OPD masing-masing.',
                'permissions' => ['dashboard.view', 'view_dashboard_opd', 'opd.view', 'opd_units.manage', 'periode.view', 'satuan.view', 'rpjmd.view', 'view_rpjmd', 'renstra.view', 'renstra.manage', 'view_renstra_opd', 'manage_renstra_opd', 'rkpd.view', 'renja.view', 'renja.manage', 'kinerja.view', 'kinerja.manage', 'manage_perjanjian_kinerja', 'manage_rencana_aksi', 'input_realisasi', 'dokumen.view', 'dokumen.manage', 'manage_dokumen', 'evaluasi.view', 'lkjip.view', 'lkjip.manage', 'laporan.view', 'laporan.manage'],
            ],
            'pimpinan' => [
                'label' => 'Pimpinan',
                'description' => 'Hanya melihat dashboard, laporan, progres, capaian, dan status evaluasi.',
                'permissions' => ['dashboard.view', 'view_dashboard_pimpinan', 'rpjmd.view', 'view_rpjmd', 'rkpd.view', 'renja.view', 'lkjip.view', 'laporan.view'],
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
            ['username' => env('SUPER_ADMIN_USERNAME', 'superadmin')],
            [
                'name' => env('SUPER_ADMIN_NAME', 'Super Admin'),
                'email' => env('SUPER_ADMIN_EMAIL', 'admin@example.test'),
                'phone' => env('SUPER_ADMIN_PHONE'),
                'jabatan' => 'Administrator Sistem',
                'password' => Hash::make(env('SUPER_ADMIN_PASSWORD', 'password')),
                'status' => 'active',
            ],
        );

        $superAdmin->roles()->sync([
            Role::where('name', 'super_admin')->value('id'),
        ]);

        foreach (range(((int) date('Y')) - 1, ((int) date('Y')) + 4) as $year) {
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

        $this->seedUrusanDanBidang();
        $this->call(OpdSeeder::class);
        $this->call(OperationalUserSeeder::class);
        $this->call(ProgramKegiatanReferenceSeeder::class);

        foreach (SystemSettingCatalog::settings() as $key => $setting) {
            SystemSetting::updateOrCreate(
                ['key' => $key],
                [
                    'group' => $setting['group'],
                    'label' => $setting['label'],
                    'type' => $setting['type'],
                    'value' => $setting['value'],
                    'is_public' => $setting['is_public'],
                ],
            );
        }

        foreach ([
            ['kode' => 'AA', 'nama' => 'Sangat Memuaskan', 'nilai_min' => 90.01, 'nilai_max' => 100, 'warna' => 'emerald'],
            ['kode' => 'A', 'nama' => 'Memuaskan', 'nilai_min' => 80.01, 'nilai_max' => 90, 'warna' => 'green'],
            ['kode' => 'BB', 'nama' => 'Sangat Baik', 'nilai_min' => 70.01, 'nilai_max' => 80, 'warna' => 'blue'],
            ['kode' => 'B', 'nama' => 'Baik', 'nilai_min' => 60.01, 'nilai_max' => 70, 'warna' => 'cyan'],
            ['kode' => 'CC', 'nama' => 'Cukup', 'nilai_min' => 50.01, 'nilai_max' => 60, 'warna' => 'amber'],
            ['kode' => 'C', 'nama' => 'Kurang', 'nilai_min' => 30.01, 'nilai_max' => 50, 'warna' => 'orange'],
            ['kode' => 'D', 'nama' => 'Sangat Kurang', 'nilai_min' => 0, 'nilai_max' => 30, 'warna' => 'red'],
        ] as $predikat) {
            PredikatEvaluasi::updateOrCreate(
                ['kode' => $predikat['kode']],
                [
                    ...$predikat,
                    'deskripsi' => "Predikat {$predikat['kode']} untuk nilai {$predikat['nilai_min']} sampai {$predikat['nilai_max']}.",
                    'is_active' => true,
                ],
            );
        }

        $komponenEvaluasi = [
            [
                'kode' => 'A',
                'nama' => 'Perencanaan Kinerja',
                'bobot' => 30,
                'urutan' => 1,
                'sub' => [
                    [
                        'kode' => 'A1',
                        'nama' => 'Kualitas Perencanaan Kinerja',
                        'bobot' => 15,
                        'kriteria' => [
                            ['kode' => 'A1.1', 'nama' => 'Dokumen perencanaan kinerja telah tersedia dan selaras dengan RPJMD/Renstra.', 'bobot' => 7.5],
                            ['kode' => 'A1.2', 'nama' => 'Indikator kinerja telah memenuhi karakteristik terukur, relevan, dan cukup.', 'bobot' => 7.5],
                        ],
                    ],
                    [
                        'kode' => 'A2',
                        'nama' => 'Pemanfaatan Perencanaan Kinerja',
                        'bobot' => 15,
                        'kriteria' => [
                            ['kode' => 'A2.1', 'nama' => 'Perencanaan kinerja digunakan sebagai acuan penyusunan perjanjian kinerja dan rencana aksi.', 'bobot' => 7.5],
                            ['kode' => 'A2.2', 'nama' => 'Target kinerja ditetapkan secara realistis dan berorientasi hasil.', 'bobot' => 7.5],
                        ],
                    ],
                ],
            ],
            [
                'kode' => 'B',
                'nama' => 'Pengukuran Kinerja',
                'bobot' => 30,
                'urutan' => 2,
                'sub' => [
                    [
                        'kode' => 'B1',
                        'nama' => 'Kualitas Pengukuran Kinerja',
                        'bobot' => 15,
                        'kriteria' => [
                            ['kode' => 'B1.1', 'nama' => 'Pengukuran kinerja dilakukan berkala dengan data yang memadai.', 'bobot' => 7.5],
                            ['kode' => 'B1.2', 'nama' => 'Realisasi kinerja didukung bukti yang valid dan dapat ditelusuri.', 'bobot' => 7.5],
                        ],
                    ],
                    [
                        'kode' => 'B2',
                        'nama' => 'Pemanfaatan Hasil Pengukuran',
                        'bobot' => 15,
                        'kriteria' => [
                            ['kode' => 'B2.1', 'nama' => 'Hasil pengukuran digunakan untuk pengendalian dan perbaikan kinerja.', 'bobot' => 7.5],
                            ['kode' => 'B2.2', 'nama' => 'Capaian kinerja dianalisis sampai faktor penyebab dan tindak lanjut.', 'bobot' => 7.5],
                        ],
                    ],
                ],
            ],
            [
                'kode' => 'C',
                'nama' => 'Pelaporan Kinerja',
                'bobot' => 15,
                'urutan' => 3,
                'sub' => [
                    [
                        'kode' => 'C1',
                        'nama' => 'Kualitas Laporan Kinerja',
                        'bobot' => 15,
                        'kriteria' => [
                            ['kode' => 'C1.1', 'nama' => 'Laporan kinerja menyajikan capaian, analisis, dan efisiensi penggunaan sumber daya.', 'bobot' => 7.5],
                            ['kode' => 'C1.2', 'nama' => 'Laporan kinerja disusun tepat waktu dan mudah dipahami pemangku kepentingan.', 'bobot' => 7.5],
                        ],
                    ],
                ],
            ],
            [
                'kode' => 'D',
                'nama' => 'Evaluasi Akuntabilitas Internal',
                'bobot' => 25,
                'urutan' => 4,
                'sub' => [
                    [
                        'kode' => 'D1',
                        'nama' => 'Pelaksanaan Evaluasi Internal',
                        'bobot' => 12.5,
                        'kriteria' => [
                            ['kode' => 'D1.1', 'nama' => 'Evaluasi internal dilaksanakan secara berkala dan terdokumentasi.', 'bobot' => 6.25],
                            ['kode' => 'D1.2', 'nama' => 'Evaluasi internal menghasilkan rekomendasi yang spesifik dan dapat ditindaklanjuti.', 'bobot' => 6.25],
                        ],
                    ],
                    [
                        'kode' => 'D2',
                        'nama' => 'Tindak Lanjut Evaluasi',
                        'bobot' => 12.5,
                        'kriteria' => [
                            ['kode' => 'D2.1', 'nama' => 'Rekomendasi evaluasi ditindaklanjuti oleh OPD secara memadai.', 'bobot' => 6.25],
                            ['kode' => 'D2.2', 'nama' => 'Tindak lanjut dievaluasi kembali untuk memastikan perbaikan berkelanjutan.', 'bobot' => 6.25],
                        ],
                    ],
                ],
            ],
        ];

        foreach ($komponenEvaluasi as $komponenData) {
            $komponen = KomponenEvaluasi::updateOrCreate(
                ['kode' => $komponenData['kode']],
                [
                    'nama' => $komponenData['nama'],
                    'bobot' => $komponenData['bobot'],
                    'urutan' => $komponenData['urutan'],
                    'status' => 'active',
                ],
            );

            foreach ($komponenData['sub'] as $subIndex => $subData) {
                $sub = $komponen->subKomponen()->updateOrCreate(
                    ['kode' => $subData['kode']],
                    [
                        'nama' => $subData['nama'],
                        'bobot' => $subData['bobot'],
                        'urutan' => $subIndex + 1,
                        'status' => 'active',
                    ],
                );

                foreach ($subData['kriteria'] as $index => $kriteriaData) {
                    $sub->kriteria()->updateOrCreate(
                        ['kode' => $kriteriaData['kode']],
                        [
                            'nama' => $kriteriaData['nama'],
                            'bobot' => $kriteriaData['bobot'],
                            'nilai_maksimal' => 100,
                            'urutan' => $index + 1,
                            'status' => 'active',
                        ],
                    );
                }
            }
        }
    }

    private function seedUrusanDanBidang(): void
    {
        $urusan = collect([
            ['kode' => '1', 'nama' => 'URUSAN PEMERINTAHAN WAJIB YANG BERKAITAN DENGAN PELAYANAN DASAR'],
            ['kode' => '2', 'nama' => 'URUSAN PEMERINTAHAN WAJIB YANG TIDAK BERKAITAN DENGAN PELAYANAN DASAR'],
            ['kode' => '3', 'nama' => 'URUSAN PEMERINTAHAN PILIHAN'],
            ['kode' => '4', 'nama' => 'UNSUR PENDUKUNG URUSAN PEMERINTAHAN'],
            ['kode' => '5', 'nama' => 'UNSUR PENUNJANG URUSAN PEMERINTAHAN'],
            ['kode' => '6', 'nama' => 'UNSUR PENGAWASAN URUSAN PEMERINTAHAN'],
            ['kode' => '7', 'nama' => 'UNSUR KEWILAYAHAN'],
            ['kode' => '8', 'nama' => 'UNSUR PEMERINTAHAN UMUM'],
        ])->mapWithKeys(function (array $item) {
            $model = UrusanPemerintahan::updateOrCreate(
                ['kode' => $item['kode']],
                [
                    'nama' => $item['nama'],
                    'deskripsi' => null,
                    'status' => 'active',
                ],
            );

            return [$model->kode => $model];
        });

        $bidang = [
            ['kode' => '1.01', 'nama' => 'URUSAN PEMERINTAHAN BIDANG PENDIDIKAN'],
            ['kode' => '2.19', 'nama' => 'URUSAN PEMERINTAHAN BIDANG KEPEMUDAAN DAN OLAHRAGA'],
            ['kode' => '1.02', 'nama' => 'URUSAN PEMERINTAHAN BIDANG KESEHATAN'],
            ['kode' => '1.03', 'nama' => 'URUSAN PEMERINTAHAN BIDANG PEKERJAAN UMUM DAN PENATAAN RUANG'],
            ['kode' => '1.04', 'nama' => 'URUSAN PEMERINTAHAN BIDANG PERUMAHAN DAN KAWASAN PERMUKIMAN'],
            ['kode' => '2.10', 'nama' => 'URUSAN PEMERINTAHAN BIDANG PERTANAHAN'],
            ['kode' => '2.11', 'nama' => 'URUSAN PEMERINTAHAN BIDANG LINGKUNGAN HIDUP'],
            ['kode' => '1.05', 'nama' => 'URUSAN PEMERINTAHAN BIDANG KETENTERAMAN DAN KETERTIBAN UMUM SERTA PERLINDUNGAN MASYARAKAT'],
            ['kode' => '1.06', 'nama' => 'URUSAN PEMERINTAHAN BIDANG SOSIAL'],
            ['kode' => '2.08', 'nama' => 'URUSAN PEMERINTAHAN BIDANG PEMBERDAYAAN PEREMPUAN DAN PERLINDUNGAN ANAK'],
            ['kode' => '2.07', 'nama' => 'URUSAN PEMERINTAHAN BIDANG TENAGA KERJA'],
            ['kode' => '3.32', 'nama' => 'URUSAN PEMERINTAHAN BIDANG TRANSMIGRASI'],
            ['kode' => '2.12', 'nama' => 'URUSAN PEMERINTAHAN BIDANG ADMINISTRASI KEPENDUDUKAN DAN PENCATATAN SIPIL'],
            ['kode' => '2.13', 'nama' => 'URUSAN PEMERINTAHAN BIDANG PEMBERDAYAAN MASYARAKAT DAN DESA'],
            ['kode' => '2.14', 'nama' => 'URUSAN PEMERINTAHAN BIDANG PENGENDALIAN PENDUDUK DAN KELUARGA BERENCANA'],
            ['kode' => '2.15', 'nama' => 'URUSAN PEMERINTAHAN BIDANG PERHUBUNGAN'],
            ['kode' => '2.16', 'nama' => 'URUSAN PEMERINTAHAN BIDANG KOMUNIKASI DAN INFORMATIKA'],
            ['kode' => '2.20', 'nama' => 'URUSAN PEMERINTAHAN BIDANG STATISTIK'],
            ['kode' => '2.21', 'nama' => 'URUSAN PEMERINTAHAN BIDANG PERSANDIAN'],
            ['kode' => '2.18', 'nama' => 'URUSAN PEMERINTAHAN BIDANG PENANAMAN MODAL'],
            ['kode' => '2.23', 'nama' => 'URUSAN PEMERINTAHAN BIDANG PERPUSTAKAAN'],
            ['kode' => '2.24', 'nama' => 'URUSAN PEMERINTAHAN BIDANG KEARSIPAN'],
            ['kode' => '2.22', 'nama' => 'URUSAN PEMERINTAHAN BIDANG KEBUDAYAAN'],
            ['kode' => '3.26', 'nama' => 'URUSAN PEMERINTAHAN BIDANG PARIWISATA'],
            ['kode' => '2.09', 'nama' => 'URUSAN PEMERINTAHAN BIDANG PANGAN'],
            ['kode' => '3.25', 'nama' => 'URUSAN PEMERINTAHAN BIDANG KELAUTAN  DAN PERIKANAN'],
            ['kode' => '3.27', 'nama' => 'URUSAN PEMERINTAHAN BIDANG PERTANIAN'],
            ['kode' => '2.17', 'nama' => 'URUSAN PEMERINTAHAN BIDANG KOPERASI, USAHA KECIL, DAN MENENGAH'],
            ['kode' => '3.30', 'nama' => 'URUSAN PEMERINTAHAN BIDANG PERDAGANGAN'],
            ['kode' => '3.31', 'nama' => 'URUSAN PEMERINTAHAN BIDANG PERINDUSTRIAN'],
            ['kode' => '4.01', 'nama' => 'SEKRETARIAT DAERAH'],
            ['kode' => '4.02', 'nama' => 'SEKRETARIAT DPRD'],
            ['kode' => '5.01', 'nama' => 'PERENCANAAN'],
            ['kode' => '5.05', 'nama' => 'PENELITIAN DAN PENGEMBANGAN'],
            ['kode' => '5.02', 'nama' => 'KEUANGAN'],
            ['kode' => '5.03', 'nama' => 'KEPEGAWAIAN'],
            ['kode' => '5.04', 'nama' => 'PENDIDIKAN DAN PELATIHAN'],
            ['kode' => '6.01', 'nama' => 'INSPEKTORAT DAERAH'],
            ['kode' => '7.01', 'nama' => 'KECAMATAN'],
            ['kode' => '8.01', 'nama' => 'KESATUAN BANGSA DAN POLITIK'],
        ];

        foreach ($bidang as $item) {
            $urusanKode = str($item['kode'])->before('.')->toString();
            $parent = $urusan->get($urusanKode);

            if (! $parent) {
                continue;
            }

            BidangUrusan::updateOrCreate(
                [
                    'urusan_pemerintahan_id' => $parent->id,
                    'kode' => $item['kode'],
                ],
                [
                    'nama' => $item['nama'],
                    'status' => 'active',
                ],
            );
        }
    }
}
