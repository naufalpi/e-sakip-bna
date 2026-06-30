<?php

namespace Database\Seeders;

use App\Models\ActivityLog;
use App\Models\Dokumen;
use App\Models\EvaluasiSakip;
use App\Models\ImportBatch;
use App\Models\IndikatorOpdProgram;
use App\Models\IndikatorProgramRpjmd;
use App\Models\IndikatorSasaranDaerah;
use App\Models\IndikatorSasaranOpd;
use App\Models\IndikatorSubKegiatan;
use App\Models\IndikatorTujuanDaerah;
use App\Models\IndikatorTujuanOpd;
use App\Models\KriteriaEvaluasi;
use App\Models\Lkjip;
use App\Models\Notification;
use App\Models\Opd;
use App\Models\OpdKegiatan;
use App\Models\OpdProgram;
use App\Models\OpdSubKegiatan;
use App\Models\OpdUnit;
use App\Models\PeriodeTahun;
use App\Models\PerjanjianKinerja;
use App\Models\ProgramRpjmd;
use App\Models\RealisasiKinerja;
use App\Models\RekomendasiEvaluasi;
use App\Models\RencanaAksi;
use App\Models\RenstraOpd;
use App\Models\Role;
use App\Models\Rpjmd;
use App\Models\RpjmdMisi;
use App\Models\RpjmdVisi;
use App\Models\SasaranDaerah;
use App\Models\SasaranOpd;
use App\Models\SatuanIndikator;
use App\Models\StrategiDaerah;
use App\Models\TargetTriwulanIndikator;
use App\Models\TujuanDaerah;
use App\Models\TujuanOpd;
use App\Models\UrusanPemerintahan;
use App\Models\User;
use App\Models\WorkflowSubmission;
use App\Services\Evaluasi\EvaluasiSakipScoreService;
use App\Services\Kinerja\CapaianKinerjaService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DemoDataSeeder extends Seeder
{
    private array $users = [];

    public function run(): void
    {
        $this->call(DatabaseSeeder::class);

        DB::transaction(function () {
            $periode = $this->periode();
            $satuan = $this->satuan();
            $urusan = $this->urusan();
            $opds = $this->opds($urusan);
            $this->users($opds);

            $rpjmdContext = $this->rpjmd($periode, $satuan, $urusan, $opds);
            $renstraContexts = $this->renstraOpd($periode, $satuan, $opds, $rpjmdContext);

            foreach ($renstraContexts as $context) {
                $kinerja = $this->kinerja($periode, $context);
                $evaluasi = $this->evaluasi($periode, $context, $kinerja['realisasi']);
                $lkjip = $this->lkjip($periode, $context, $kinerja, $evaluasi);
                $this->dokumen($periode, $context, $rpjmdContext['rpjmd'], $kinerja, $evaluasi, $lkjip);
                $this->workflowAndNotifications($context, $kinerja, $evaluasi, $lkjip);
            }

            $this->importBatch();
            $this->activityLogs();
        });
    }

    private function periode(): PeriodeTahun
    {
        $year = (int) date('Y');

        /** @var PeriodeTahun $periode */
        $periode = PeriodeTahun::query()->updateOrCreate(
            ['tahun' => $year],
            [
                'nama' => "Tahun {$year}",
                'tanggal_mulai' => "{$year}-01-01",
                'tanggal_selesai' => "{$year}-12-31",
                'status' => 'active',
            ],
        );

        return $periode;
    }

    /**
     * @return array<string, SatuanIndikator>
     */
    private function satuan(): array
    {
        return [
            'persen' => SatuanIndikator::query()->where('nama', 'Persen')->firstOrFail(),
            'nilai' => SatuanIndikator::query()->where('nama', 'Nilai')->firstOrFail(),
            'dokumen' => SatuanIndikator::query()->where('nama', 'Dokumen')->firstOrFail(),
            'kegiatan' => SatuanIndikator::query()->where('nama', 'Kegiatan')->firstOrFail(),
            'orang' => SatuanIndikator::query()->where('nama', 'Orang')->firstOrFail(),
        ];
    }

    /**
     * @return array<string, UrusanPemerintahan>
     */
    private function urusan(): array
    {
        return [
            'umum' => UrusanPemerintahan::query()->where('kode', '00')->firstOrFail(),
            'kesehatan' => UrusanPemerintahan::query()->updateOrCreate(
                ['kode' => '1.02'],
                ['nama' => 'Kesehatan', 'deskripsi' => 'Urusan pemerintahan bidang kesehatan.', 'status' => 'active'],
            ),
            'pendidikan' => UrusanPemerintahan::query()->updateOrCreate(
                ['kode' => '1.01'],
                ['nama' => 'Pendidikan', 'deskripsi' => 'Urusan pemerintahan bidang pendidikan.', 'status' => 'active'],
            ),
            'kominfo' => UrusanPemerintahan::query()->updateOrCreate(
                ['kode' => '2.16'],
                ['nama' => 'Komunikasi dan Informatika', 'deskripsi' => 'Urusan komunikasi, informatika, statistik, dan persandian.', 'status' => 'active'],
            ),
        ];
    }

    /**
     * @param  array<string, UrusanPemerintahan>  $urusan
     * @return array<string, Opd>
     */
    private function opds(array $urusan): array
    {
        $opds = [
            'dinkes' => Opd::query()->updateOrCreate(
                ['kode' => '1.02.0.00.0.00.01.0000'],
                [
                    'urusan_pemerintahan_id' => $urusan['kesehatan']->id,
                    'nama' => 'Dinas Kesehatan Kabupaten Banjarnegara',
                    'singkatan' => 'Dinkes',
                    'jenis' => 'dinas',
                    'alamat' => 'Jl. Selamanik No. 33 Banjarnegara',
                    'telepon' => '0286-591001',
                    'email' => 'dinkes@example.test',
                    'nama_kepala' => 'dr. Anindya Pramesti',
                    'nip_kepala' => '197805102006041001',
                    'status' => 'active',
                ],
            ),
            'disdikpora' => Opd::query()->updateOrCreate(
                ['kode' => '1.01.0.00.0.00.01.0000'],
                [
                    'urusan_pemerintahan_id' => $urusan['pendidikan']->id,
                    'nama' => 'Dinas Pendidikan, Kepemudaan dan Olahraga Kabupaten Banjarnegara',
                    'singkatan' => 'Disdikpora',
                    'jenis' => 'dinas',
                    'alamat' => 'Jl. Mayjend Panjaitan No. 57 Banjarnegara',
                    'telepon' => '0286-591002',
                    'email' => 'disdikpora@example.test',
                    'nama_kepala' => 'Drs. Bagus Wicaksono',
                    'nip_kepala' => '197206151998031002',
                    'status' => 'active',
                ],
            ),
            'dinkominfo' => Opd::query()->updateOrCreate(
                ['kode' => '2.16.0.00.0.00.01.0000'],
                [
                    'urusan_pemerintahan_id' => $urusan['kominfo']->id,
                    'nama' => 'Dinas Komunikasi dan Informatika Kabupaten Banjarnegara',
                    'singkatan' => 'Dinkominfo',
                    'jenis' => 'dinas',
                    'alamat' => 'Jl. Dipayuda No. 8 Banjarnegara',
                    'telepon' => '0286-591003',
                    'email' => 'dinkominfo@example.test',
                    'nama_kepala' => 'Rina Puspitasari, S.Kom.',
                    'nip_kepala' => '198004022006042004',
                    'status' => 'active',
                ],
            ),
        ];

        foreach ($opds as $key => $opd) {
            OpdUnit::query()->updateOrCreate(
                ['opd_id' => $opd->id, 'kode' => strtoupper($key).'-SEK'],
                [
                    'nama' => 'Sekretariat',
                    'jenis_unit' => 'sekretariat',
                    'nama_pimpinan' => 'Sekretaris '.($opd->singkatan ?: 'OPD'),
                    'nip_pimpinan' => '198101012010011001',
                    'status' => 'active',
                ],
            );

            OpdUnit::query()->updateOrCreate(
                ['opd_id' => $opd->id, 'kode' => strtoupper($key).'-PRG'],
                [
                    'nama' => 'Bidang Perencanaan dan Evaluasi',
                    'jenis_unit' => 'bidang',
                    'nama_pimpinan' => 'Kepala Bidang Perencanaan',
                    'nip_pimpinan' => '198202022011012002',
                    'status' => 'active',
                ],
            );
        }

        return $opds;
    }

    /**
     * @param  array<string, Opd>  $opds
     */
    private function users(array $opds): void
    {
        $roleNames = [
            'admin_kabupaten_bagian_organisasi' => ['name' => 'Admin Bagian Organisasi', 'email' => 'bagorg@example.test', 'jabatan' => 'Analis Kinerja Bagian Organisasi'],
            'admin_kabupaten_bapperida' => ['name' => 'Admin Bapperida', 'email' => 'bapperida@example.test', 'jabatan' => 'Perencana Ahli Muda Bapperida'],
            'admin_kabupaten_inspektorat' => ['name' => 'Admin Inspektorat', 'email' => 'inspektorat@example.test', 'jabatan' => 'Auditor Inspektorat'],
            'admin_kabupaten_dinkominfo' => ['name' => 'Admin Dinkominfo', 'email' => 'dinkominfo.admin@example.test', 'jabatan' => 'Administrator Aplikasi'],
            'pimpinan' => ['name' => 'Pimpinan Daerah', 'email' => 'pimpinan@example.test', 'jabatan' => 'Pimpinan Daerah'],
        ];

        foreach ($roleNames as $roleName => $data) {
            $user = User::query()->updateOrCreate(
                ['username' => $roleName],
                [
                    'name' => $data['name'],
                    'email' => $data['email'],
                    'phone' => '0800000000',
                    'jabatan' => $data['jabatan'],
                    'password' => Hash::make('password'),
                    'status' => 'active',
                ],
            );

            $user->roles()->sync([Role::query()->where('name', $roleName)->value('id')]);
            $this->users[$roleName] = $user;
        }

        foreach ($opds as $key => $opd) {
            $user = User::query()->updateOrCreate(
                ['username' => 'admin_'.$key],
                [
                    'opd_id' => $opd->id,
                    'name' => 'Admin '.($opd->singkatan ?: $opd->nama),
                    'email' => 'admin.'.$key.'@example.test',
                    'phone' => '08120000'.str_pad((string) $opd->id, 4, '0', STR_PAD_LEFT),
                    'jabatan' => 'Kasubbag Program dan Keuangan',
                    'password' => Hash::make('password'),
                    'status' => 'active',
                ],
            );

            $user->roles()->sync([Role::query()->where('name', 'admin_opd')->value('id')]);
            $this->users['admin_'.$key] = $user;
        }
    }

    /**
     * @param  array<string, SatuanIndikator>  $satuan
     * @param  array<string, UrusanPemerintahan>  $urusan
     * @param  array<string, Opd>  $opds
     * @return array<string, mixed>
     */
    private function rpjmd(PeriodeTahun $periode, array $satuan, array $urusan, array $opds): array
    {
        $tahunAwal = $periode->tahun;
        $tahunAkhir = $periode->tahun + 4;

        $rpjmd = Rpjmd::query()->updateOrCreate(
            ['judul' => 'RPJMD Kabupaten Banjarnegara '.$tahunAwal.'-'.$tahunAkhir],
            [
                'periode_tahun_id' => $periode->id,
                'nomor_perda' => 'Perda Nomor 1 Tahun '.$tahunAwal,
                'tahun_awal' => $tahunAwal,
                'tahun_akhir' => $tahunAkhir,
                'status' => 'approved',
                'keterangan' => 'Data demo RPJMD untuk pengujian cascading kabupaten sampai OPD.',
            ],
        );

        $visi = RpjmdVisi::query()->updateOrCreate(
            ['rpjmd_id' => $rpjmd->id, 'urutan' => 1],
            ['visi' => 'Banjarnegara Maju, Sejahtera, dan Berdaya Saing Berbasis Pelayanan Publik yang Akuntabel.'],
        );

        $misi = RpjmdMisi::query()->updateOrCreate(
            ['rpjmd_id' => $rpjmd->id, 'kode' => 'M-1'],
            ['rpjmd_visi_id' => $visi->id, 'misi' => 'Meningkatkan kualitas pelayanan dasar dan tata kelola pemerintahan yang akuntabel.', 'urutan' => 1],
        );

        $tujuan = TujuanDaerah::query()->updateOrCreate(
            ['rpjmd_visi_id' => $visi->id, 'kode' => 'T-1'],
            [
                'rpjmd_misi_id' => null,
                'tujuan' => 'Meningkatnya kesejahteraan masyarakat dan kualitas layanan publik.',
                'urutan' => 1,
            ],
        );

        $indikatorTujuan = IndikatorTujuanDaerah::query()->updateOrCreate(
            ['tujuan_daerah_id' => $tujuan->id, 'kode' => 'ITD-1'],
            [
                'satuan_indikator_id' => $satuan['nilai']->id,
                'indikator' => 'Indeks pembangunan manusia',
                'tipe_indikator' => 'positif',
                'formula' => 'Indeks komposit pendidikan, kesehatan, dan pengeluaran.',
                'sumber_data' => 'BPS',
                'urutan' => 1,
            ],
        );

        $this->seedAnnualTargets($indikatorTujuan, 'indikator_tujuan_daerah_id', 'target_indikator_tujuan_daerah', $periode, 72, '72');

        $sasaran = SasaranDaerah::query()->updateOrCreate(
            ['tujuan_daerah_id' => $tujuan->id, 'kode' => 'SD-1'],
            ['sasaran' => 'Meningkatnya kualitas layanan dasar dan akuntabilitas kinerja perangkat daerah.', 'urutan' => 1],
        );

        $indikatorSasaran = IndikatorSasaranDaerah::query()->updateOrCreate(
            ['sasaran_daerah_id' => $sasaran->id, 'kode' => 'ISD-1'],
            [
                'satuan_indikator_id' => $satuan['nilai']->id,
                'indikator' => 'Nilai SAKIP Kabupaten',
                'tipe_indikator' => 'positif',
                'formula' => 'Nilai hasil evaluasi SAKIP kabupaten.',
                'sumber_data' => 'Inspektorat',
                'urutan' => 1,
            ],
        );

        $this->seedAnnualTargets($indikatorSasaran, 'indikator_sasaran_daerah_id', 'target_indikator_sasaran_daerah', $periode, 78, 'BB');

        $strategi = StrategiDaerah::query()->updateOrCreate(
            ['sasaran_daerah_id' => $sasaran->id, 'kode' => 'STR-1'],
            [
                'strategi' => 'Penguatan manajemen kinerja dan digitalisasi layanan prioritas.',
                'arah_kebijakan' => 'Mendorong OPD menyelaraskan target kinerja dengan anggaran dan bukti dukung.',
                'urutan' => 1,
            ],
        );

        $program = ProgramRpjmd::query()->updateOrCreate(
            ['kode' => 'PRG-RPJMD-01'],
            [
                'strategi_daerah_id' => $strategi->id,
                'sasaran_daerah_id' => $sasaran->id,
                'urusan_pemerintahan_id' => $urusan['umum']->id,
                'nama' => 'Program Penguatan Akuntabilitas Kinerja Pemerintah Daerah',
                'pagu_indikatif' => 15000000000,
                'status' => 'approved',
                'urutan' => 1,
            ],
        );

        $indikatorProgram = IndikatorProgramRpjmd::query()->updateOrCreate(
            ['program_rpjmd_id' => $program->id, 'kode' => 'IPR-1'],
            [
                'satuan_indikator_id' => $satuan['persen']->id,
                'indikator' => 'Persentase OPD dengan capaian kinerja minimal baik',
                'tipe_indikator' => 'positif',
                'formula' => '(Jumlah OPD kategori baik / seluruh OPD) x 100',
                'sumber_data' => 'E-SAKIP',
                'urutan' => 1,
            ],
        );

        $this->seedAnnualTargets($indikatorProgram, 'indikator_program_rpjmd_id', 'target_indikator_program_rpjmd', $periode, 85, '85%', 15000000000);
        $this->seedTriwulan($indikatorProgram, $periode, [20, 45, 70, 85], [2000000000, 5500000000, 9500000000, 15000000000]);

        foreach ($opds as $index => $opd) {
            DB::table('program_rpjmd_opd_penanggung_jawab')->updateOrInsert(
                ['program_rpjmd_id' => $program->id, 'opd_id' => $opd->id, 'peran' => $index === 'dinkominfo' ? 'pendukung' : 'penanggung_jawab'],
                ['is_utama' => $index === 'dinkes', 'created_at' => now(), 'updated_at' => now()],
            );
        }

        return compact('rpjmd', 'visi', 'misi', 'tujuan', 'indikatorTujuan', 'sasaran', 'indikatorSasaran', 'strategi', 'program', 'indikatorProgram');
    }

    /**
     * @param  array<string, SatuanIndikator>  $satuan
     * @param  array<string, Opd>  $opds
     * @param  array<string, mixed>  $rpjmdContext
     * @return array<int, array<string, mixed>>
     */
    private function renstraOpd(PeriodeTahun $periode, array $satuan, array $opds, array $rpjmdContext): array
    {
        $contexts = [];

        foreach ($opds as $key => $opd) {
            $label = $opd->singkatan ?: $opd->nama;
            $renstra = RenstraOpd::query()->updateOrCreate(
                ['opd_id' => $opd->id, 'tahun_awal' => $periode->tahun],
                [
                    'rpjmd_id' => $rpjmdContext['rpjmd']->id,
                    'periode_tahun_id' => $periode->id,
                    'judul' => 'Renstra '.$label.' '.$periode->tahun.'-'.($periode->tahun + 4),
                    'nomor_dokumen' => 'RENSTRA/'.$label.'/'.$periode->tahun,
                    'tahun_akhir' => $periode->tahun + 4,
                    'status' => $key === 'dinkominfo' ? 'verified' : 'approved',
                    'keterangan' => 'Data demo cascading OPD terkait RPJMD.',
                ],
            );

            $tujuan = TujuanOpd::query()->updateOrCreate(
                ['renstra_opd_id' => $renstra->id, 'kode' => 'TO-'.$opd->id],
                [
                    'tujuan_daerah_id' => $rpjmdContext['tujuan']->id,
                    'tujuan' => 'Meningkatnya kualitas layanan '.$label.' yang akuntabel.',
                    'urutan' => 1,
                ],
            );

            $indikatorTujuan = IndikatorTujuanOpd::query()->updateOrCreate(
                ['tujuan_opd_id' => $tujuan->id, 'kode' => 'ITO-'.$opd->id],
                [
                    'indikator_tujuan_daerah_id' => $rpjmdContext['indikatorTujuan']->id,
                    'satuan_indikator_id' => $satuan['nilai']->id,
                    'indikator' => 'Indeks kualitas layanan '.$label,
                    'tipe_indikator' => 'positif',
                    'formula' => 'Nilai indeks layanan berdasarkan survei dan capaian kinerja.',
                    'sumber_data' => $label,
                    'urutan' => 1,
                ],
            );

            $this->seedAnnualTargets($indikatorTujuan, 'indikator_tujuan_opd_id', 'target_indikator_tujuan_opd', $periode, 80, '80');

            $sasaran = SasaranOpd::query()->updateOrCreate(
                ['tujuan_opd_id' => $tujuan->id, 'kode' => 'SO-'.$opd->id],
                [
                    'sasaran_daerah_id' => $rpjmdContext['sasaran']->id,
                    'sasaran' => 'Meningkatnya capaian indikator kinerja utama '.$label.'.',
                    'urutan' => 1,
                ],
            );

            $indikatorSasaran = IndikatorSasaranOpd::query()->updateOrCreate(
                ['sasaran_opd_id' => $sasaran->id, 'kode' => 'ISO-'.$opd->id],
                [
                    'indikator_sasaran_daerah_id' => $rpjmdContext['indikatorSasaran']->id,
                    'satuan_indikator_id' => $satuan['persen']->id,
                    'indikator' => 'Persentase target kinerja '.$label.' tercapai',
                    'tipe_indikator' => 'positif',
                    'formula' => '(Jumlah indikator tercapai / seluruh indikator) x 100',
                    'sumber_data' => 'E-SAKIP',
                    'urutan' => 1,
                ],
            );

            $this->seedAnnualTargets($indikatorSasaran, 'indikator_sasaran_opd_id', 'target_indikator_sasaran_opd', $periode, 90, '90%');
            $this->seedTriwulan($indikatorSasaran, $periode, [22, 45, 70, 90], [0, 0, 0, 0]);

            $program = OpdProgram::query()->updateOrCreate(
                ['renstra_opd_id' => $renstra->id, 'kode' => 'OPD-PRG-'.$opd->id],
                [
                    'sasaran_opd_id' => $sasaran->id,
                    'program_rpjmd_id' => $rpjmdContext['program']->id,
                    'nama' => 'Program Peningkatan Kinerja '.$label,
                    'pagu_indikatif' => 2500000000 + ($opd->id * 100000000),
                    'status' => 'approved',
                    'urutan' => 1,
                ],
            );

            $indikatorProgram = IndikatorOpdProgram::query()->updateOrCreate(
                ['opd_program_id' => $program->id, 'kode' => 'IOP-'.$opd->id],
                [
                    'indikator_program_rpjmd_id' => $rpjmdContext['indikatorProgram']->id,
                    'satuan_indikator_id' => $satuan['persen']->id,
                    'indikator' => 'Persentase output program '.$label.' tercapai',
                    'tipe_indikator' => 'positif',
                    'formula' => '(Output tercapai / target output) x 100',
                    'sumber_data' => $label,
                    'urutan' => 1,
                ],
            );

            $this->seedAnnualTargets($indikatorProgram, 'indikator_opd_program_id', 'target_indikator_opd_program', $periode, 92, '92%', (float) $program->pagu_indikatif);

            $kegiatan = OpdKegiatan::query()->updateOrCreate(
                ['opd_program_id' => $program->id, 'kode' => 'KEG-'.$opd->id],
                [
                    'nama' => 'Kegiatan Penguatan Perencanaan, Pengukuran, dan Evaluasi Kinerja',
                    'pagu_indikatif' => (float) $program->pagu_indikatif * 0.65,
                    'urutan' => 1,
                ],
            );

            $subKegiatan = OpdSubKegiatan::query()->updateOrCreate(
                ['opd_kegiatan_id' => $kegiatan->id, 'kode' => 'SUB-'.$opd->id],
                [
                    'nama' => 'Sub Kegiatan Penyusunan, Monitoring, dan Evaluasi Capaian Kinerja',
                    'pagu_indikatif' => (float) $kegiatan->pagu_indikatif,
                    'urutan' => 1,
                ],
            );

            $indikatorSub = IndikatorSubKegiatan::query()->updateOrCreate(
                ['opd_sub_kegiatan_id' => $subKegiatan->id, 'kode' => 'ISK-'.$opd->id],
                [
                    'satuan_indikator_id' => $satuan['dokumen']->id,
                    'indikator' => 'Jumlah dokumen monitoring kinerja '.$label.' yang disusun',
                    'tipe_indikator' => 'positif',
                    'formula' => 'Jumlah dokumen yang selesai dan diverifikasi',
                    'sumber_data' => $label,
                    'urutan' => 1,
                ],
            );

            $this->seedTriwulan($indikatorSub, $periode, [1, 2, 3, 4], [
                (float) $subKegiatan->pagu_indikatif * 0.15,
                (float) $subKegiatan->pagu_indikatif * 0.35,
                (float) $subKegiatan->pagu_indikatif * 0.65,
                (float) $subKegiatan->pagu_indikatif,
            ]);

            $contexts[] = compact('key', 'opd', 'renstra', 'tujuan', 'indikatorTujuan', 'sasaran', 'indikatorSasaran', 'program', 'indikatorProgram', 'kegiatan', 'subKegiatan', 'indikatorSub');
        }

        return $contexts;
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    private function kinerja(PeriodeTahun $periode, array $context): array
    {
        $admin = $this->users['admin_'.$context['key']];
        $target = 92;
        $realisasiValue = $context['key'] === 'dinkominfo' ? 78 : ($context['key'] === 'disdikpora' ? 84 : 96);
        $anggaran = (float) $context['subKegiatan']->pagu_indikatif;
        $realisasiAnggaran = $anggaran * ($context['key'] === 'dinkes' ? 0.82 : 0.88);

        /** @var PerjanjianKinerja $pk */
        $pk = PerjanjianKinerja::query()->updateOrCreate(
            ['opd_id' => $context['opd']->id, 'tahun' => $periode->tahun],
            [
                'renstra_opd_id' => $context['renstra']->id,
                'periode_tahun_id' => $periode->id,
                'judul' => 'Perjanjian Kinerja '.$context['opd']->singkatan.' '.$periode->tahun,
                'nomor_dokumen' => 'PK/'.$context['opd']->id.'/'.$periode->tahun,
                'status' => 'approved',
                'catatan' => 'Data demo PK yang sudah disetujui.',
                'submitted_by' => $admin->id,
                'submitted_at' => now()->subMonths(4),
            ],
        );

        $pkItem = $pk->items()->updateOrCreate(
            ['kode' => 'PKI-'.$context['opd']->id],
            [
                'sasaran_opd_id' => $context['sasaran']->id,
                'indikator_sasaran_opd_id' => $context['indikatorSasaran']->id,
                'opd_program_id' => $context['program']->id,
                'satuan_indikator_id' => $context['indikatorSasaran']->satuan_indikator_id,
                'sasaran' => $context['sasaran']->sasaran,
                'indikator' => $context['indikatorSasaran']->indikator,
                'target' => $target,
                'target_text' => $target.'%',
                'urutan' => 1,
            ],
        );

        /** @var RencanaAksi $rencana */
        $rencana = RencanaAksi::query()->updateOrCreate(
            ['opd_id' => $context['opd']->id, 'tahun' => $periode->tahun],
            [
                'perjanjian_kinerja_id' => $pk->id,
                'periode_tahun_id' => $periode->id,
                'judul' => 'Rencana Aksi '.$context['opd']->singkatan.' '.$periode->tahun,
                'status' => 'verified',
                'catatan' => 'Rencana aksi triwulan data demo.',
            ],
        );

        foreach (['tw1' => 23, 'tw2' => 46, 'tw3' => 69, 'tw4' => 92] as $index => $targetTriwulan) {
            $rencana->items()->updateOrCreate(
                ['perjanjian_kinerja_item_id' => $pkItem->id, 'triwulan' => $index],
                [
                    'opd_program_id' => $context['program']->id,
                    'opd_kegiatan_id' => $context['kegiatan']->id,
                    'opd_sub_kegiatan_id' => $context['subKegiatan']->id,
                    'periode_realisasi' => 'triwulan',
                    'aksi' => 'Pelaksanaan rencana aksi '.$index.' untuk '.$context['opd']->singkatan,
                    'indikator' => $context['indikatorSasaran']->indikator,
                    'target' => $targetTriwulan,
                    'target_text' => $targetTriwulan.'%',
                    'anggaran' => $anggaran / 4,
                    'penanggung_jawab' => 'Sekretariat '.$context['opd']->singkatan,
                    'status' => 'approved',
                    'urutan' => (int) substr($index, -1),
                ],
            );
        }

        /** @var RealisasiKinerja $realisasi */
        $realisasi = RealisasiKinerja::query()->updateOrCreate(
            ['opd_id' => $context['opd']->id, 'tahun' => $periode->tahun, 'triwulan' => 'tw4'],
            [
                'perjanjian_kinerja_id' => $pk->id,
                'rencana_aksi_id' => $rencana->id,
                'periode_tahun_id' => $periode->id,
                'periode_realisasi' => 'triwulan',
                'status' => $context['key'] === 'dinkominfo' ? 'revision' : 'approved',
                'catatan' => 'Realisasi sampai Triwulan IV data demo.',
            ],
        );

        $capaianService = app(CapaianKinerjaService::class);
        $capaian = $capaianService->calculateCapaian($target, $realisasiValue, 'positif');
        $serapan = $capaianService->calculateSerapanAnggaran($anggaran, $realisasiAnggaran);

        $program = $realisasi->programs()->updateOrCreate(
            ['perjanjian_kinerja_item_id' => $pkItem->id],
            [
                'rencana_aksi_item_id' => $rencana->items()->where('triwulan', 'tw4')->value('id'),
                'opd_program_id' => $context['program']->id,
                'indikator_opd_program_id' => $context['indikatorProgram']->id,
                'tipe_indikator' => 'positif',
                'indikator' => $context['indikatorSasaran']->indikator,
                'target' => $target,
                'target_text' => $target.'%',
                'realisasi' => $realisasiValue,
                'realisasi_text' => $realisasiValue.'%',
                'capaian_persen' => $capaian,
                'status_capaian' => $capaianService->determineStatusCapaian($capaian),
                'anggaran' => $anggaran,
                'realisasi_anggaran' => $realisasiAnggaran,
                'serapan_anggaran_persen' => $serapan,
                'status_efisiensi' => $capaianService->determineEfisiensi($capaian, $serapan),
                'analisis_efisiensi' => 'Capaian dibanding serapan anggaran menunjukkan status efisiensi awal.',
                'kendala' => 'Ketersediaan bukti dukung dan konsolidasi data masih perlu diperkuat.',
                'tindak_lanjut' => 'Melakukan verifikasi data berkala dan pendampingan unit kerja.',
                'urutan' => 1,
            ],
        );

        $capaianService->syncRealisasiKinerjaSummary($realisasi);

        return compact('pk', 'pkItem', 'rencana', 'realisasi', 'program');
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function evaluasi(PeriodeTahun $periode, array $context, RealisasiKinerja $realisasi): EvaluasiSakip
    {
        $inspektorat = $this->users['admin_kabupaten_inspektorat'];

        /** @var EvaluasiSakip $evaluasi */
        $evaluasi = EvaluasiSakip::query()->updateOrCreate(
            ['opd_id' => $context['opd']->id, 'periode_tahun_id' => $periode->id],
            [
                'tahun' => $periode->tahun,
                'evaluator_id' => $inspektorat->id,
                'tanggal_evaluasi' => now()->subMonth()->toDateString(),
                'status' => 'approved',
                'catatan_umum' => 'Evaluasi demo berdasarkan data perencanaan, pengukuran, pelaporan, dan tindak lanjut.',
            ],
        );

        $scoreService = app(EvaluasiSakipScoreService::class);
        $nilai = match ($context['key']) {
            'dinkes' => 86,
            'disdikpora' => 78,
            default => 66,
        };

        KriteriaEvaluasi::query()
            ->with('subKomponen.komponen')
            ->where('status', 'active')
            ->orderBy('id')
            ->limit(6)
            ->get()
            ->each(function (KriteriaEvaluasi $kriteria) use ($evaluasi, $nilai, $scoreService) {
                $evaluasi->items()->updateOrCreate(
                    ['kriteria_evaluasi_id' => $kriteria->id],
                    [
                        'nilai' => $nilai,
                        'skor' => $scoreService->skorItem($nilai, (float) $kriteria->nilai_maksimal, (float) $kriteria->bobot),
                        'catatan' => 'Penilaian demo untuk '.$kriteria->nama,
                        'rekomendasi_text' => 'Perkuat kualitas bukti dukung dan pemanfaatan hasil pengukuran.',
                    ],
                );
            });

        $scoreService->recalculate($evaluasi);
        $evaluasi->refresh();

        $evaluasi->lhe()->updateOrCreate(
            ['evaluasi_sakip_id' => $evaluasi->id],
            [
                'nomor_lhe' => 'LHE/'.$context['opd']->id.'/'.$periode->tahun,
                'tanggal_lhe' => now()->subWeeks(2)->toDateString(),
                'ringkasan' => 'Hasil evaluasi menunjukkan nilai '.$evaluasi->nilai_akhir.' dengan predikat '.$evaluasi->predikat.'. OPD perlu menindaklanjuti rekomendasi prioritas.',
                'nilai_akhir' => $evaluasi->nilai_akhir,
                'predikat' => $evaluasi->predikat,
                'predikat_evaluasi_id' => $evaluasi->predikat_evaluasi_id,
                'status' => 'approved',
                'disusun_oleh' => $inspektorat->id,
            ],
        );

        $item = $evaluasi->items()->first();
        $rekomendasi = RekomendasiEvaluasi::query()->updateOrCreate(
            ['evaluasi_sakip_id' => $evaluasi->id, 'nomor' => 'R-'.$context['opd']->id.'-01'],
            [
                'evaluasi_sakip_item_id' => $item?->id,
                'opd_id' => $context['opd']->id,
                'rekomendasi' => 'Lengkapi bukti dukung realisasi dan perkuat analisis efisiensi anggaran.',
                'prioritas' => $context['key'] === 'dinkominfo' ? 'tinggi' : 'sedang',
                'status_tindak_lanjut' => $context['key'] === 'dinkominfo' ? 'proses' : 'selesai',
                'target_tanggal' => now()->addMonth()->toDateString(),
                'created_by' => $inspektorat->id,
            ],
        );

        $rekomendasi->tindakLanjut()->updateOrCreate(
            ['opd_id' => $context['opd']->id, 'created_by' => $this->users['admin_'.$context['key']]->id],
            [
                'uraian_tindak_lanjut' => 'OPD telah menyiapkan daftar bukti dukung dan jadwal pemutakhiran data.',
                'status_tindak_lanjut' => $context['key'] === 'dinkominfo' ? 'proses' : 'selesai',
                'status' => 'submitted',
                'tanggal_tindak_lanjut' => now()->subWeek()->toDateString(),
                'catatan_opd' => 'Tindak lanjut awal data demo.',
                'diverifikasi_oleh' => $context['key'] === 'dinkominfo' ? null : $inspektorat->id,
                'diverifikasi_at' => $context['key'] === 'dinkominfo' ? null : now()->subDays(3),
                'catatan_verifikator' => $context['key'] === 'dinkominfo' ? null : 'Tindak lanjut diterima.',
            ],
        );

        return $evaluasi;
    }

    /**
     * @param  array<string, mixed>  $context
     * @param  array<string, mixed>  $kinerja
     */
    private function lkjip(PeriodeTahun $periode, array $context, array $kinerja, EvaluasiSakip $evaluasi): Lkjip
    {
        $admin = $this->users['admin_'.$context['key']];

        /** @var Lkjip $lkjip */
        $lkjip = Lkjip::query()->updateOrCreate(
            ['opd_id' => $context['opd']->id, 'tahun' => $periode->tahun],
            [
                'periode_tahun_id' => $periode->id,
                'perjanjian_kinerja_id' => $kinerja['pk']->id,
                'realisasi_kinerja_id' => $kinerja['realisasi']->id,
                'evaluasi_sakip_id' => $evaluasi->id,
                'judul' => 'LKJIP '.$context['opd']->singkatan.' '.$periode->tahun,
                'nomor_dokumen' => 'LKJIP/'.$context['opd']->id.'/'.$periode->tahun,
                'ringkasan_eksekutif' => 'LKJIP demo menyajikan capaian kinerja, realisasi anggaran, efisiensi, dan tindak lanjut rekomendasi.',
                'status' => $context['key'] === 'dinkominfo' ? 'submitted' : 'approved',
                'catatan' => 'Data dummy LKJIP untuk pengujian dashboard dan laporan.',
                'submitted_by' => $admin->id,
                'submitted_at' => now()->subDays(10),
            ],
        );

        foreach ([
            ['kode' => 'BAB I', 'judul' => 'Pendahuluan', 'jenis' => 'pendahuluan', 'konten' => 'Pendahuluan LKJIP demo '.$context['opd']->singkatan.'.'],
            ['kode' => 'BAB II', 'judul' => 'Perencanaan Kinerja', 'jenis' => 'perencanaan', 'konten' => 'Perencanaan kinerja mengacu pada Renstra dan Perjanjian Kinerja.'],
            ['kode' => 'BAB III', 'judul' => 'Akuntabilitas Kinerja', 'jenis' => 'akuntabilitas', 'konten' => 'Capaian kinerja sebesar '.$kinerja['realisasi']->fresh()->capaian_persen.'% dengan status '.$kinerja['realisasi']->fresh()->status_capaian.'.'],
            ['kode' => 'BAB IV', 'judul' => 'Penutup', 'jenis' => 'penutup', 'konten' => 'OPD berkomitmen memperbaiki capaian dan efisiensi tahun berikutnya.'],
            ['kode' => 'LAMPIRAN', 'judul' => 'Lampiran', 'jenis' => 'lampiran', 'konten' => 'Lampiran memuat PK, rencana aksi, realisasi, dan bukti dukung.'],
        ] as $index => $bab) {
            $lkjip->bab()->updateOrCreate(
                ['kode' => $bab['kode']],
                [...$bab, 'urutan' => $index + 1],
            );
        }

        return $lkjip;
    }

    /**
     * @param  array<string, mixed>  $context
     * @param  array<string, mixed>  $kinerja
     */
    private function dokumen(PeriodeTahun $periode, array $context, Rpjmd $rpjmd, array $kinerja, EvaluasiSakip $evaluasi, Lkjip $lkjip): void
    {
        $admin = $this->users['admin_'.$context['key']];

        $this->storeDummyDocument($periode, $context['opd'], 'rpjmd', 'Dokumen RPJMD Demo', $rpjmd, $this->users['admin_kabupaten_bapperida']);
        $this->storeDummyDocument($periode, $context['opd'], 'renstra', 'Dokumen Renstra '.$context['opd']->singkatan, $context['renstra'], $admin);
        $this->storeDummyDocument($periode, $context['opd'], 'perjanjian_kinerja', 'Dokumen PK '.$context['opd']->singkatan, $kinerja['pk'], $admin);
        $this->storeDummyDocument($periode, $context['opd'], 'rencana_aksi', 'Dokumen Rencana Aksi '.$context['opd']->singkatan, $kinerja['rencana'], $admin);
        $this->storeDummyDocument($periode, $context['opd'], 'realisasi_kinerja', 'Bukti Realisasi '.$context['opd']->singkatan, $kinerja['realisasi'], $admin);
        $this->storeDummyDocument($periode, $context['opd'], 'lkjip', 'Dokumen LKJIP '.$context['opd']->singkatan, $lkjip, $admin);
        $this->storeDummyDocument($periode, $context['opd'], 'lhe', 'Dokumen LHE '.$context['opd']->singkatan, $evaluasi, $this->users['admin_kabupaten_inspektorat']);

        $rekomendasi = $evaluasi->rekomendasi()->first();
        if ($rekomendasi) {
            $this->storeDummyDocument($periode, $context['opd'], 'rekomendasi', 'Dokumen Rekomendasi '.$context['opd']->singkatan, $rekomendasi, $this->users['admin_kabupaten_inspektorat']);
            $tindakLanjut = $rekomendasi->tindakLanjut()->first();

            if ($tindakLanjut) {
                $this->storeDummyDocument($periode, $context['opd'], 'tindak_lanjut', 'Bukti Tindak Lanjut '.$context['opd']->singkatan, $tindakLanjut, $admin);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $context
     * @param  array<string, mixed>  $kinerja
     */
    private function workflowAndNotifications(array $context, array $kinerja, EvaluasiSakip $evaluasi, Lkjip $lkjip): void
    {
        /** @var Opd $opd */
        $opd = $context['opd'];
        $admin = $this->users['admin_'.$context['key']];
        $reviewer = $this->users['admin_kabupaten_bagian_organisasi'];

        foreach ([
            ['model' => $kinerja['pk'], 'module' => 'perjanjian_kinerja'],
            ['model' => $kinerja['rencana'], 'module' => 'rencana_aksi'],
            ['model' => $kinerja['realisasi'], 'module' => 'realisasi_kinerja'],
            ['model' => $evaluasi, 'module' => 'evaluasi_sakip', 'actor' => $this->users['admin_kabupaten_inspektorat']],
            ['model' => $lkjip, 'module' => 'lkjip'],
        ] as $item) {
            /** @var Model $model */
            $model = $item['model'];
            $actor = $item['actor'] ?? $admin;

            /** @var WorkflowSubmission $submission */
            $submission = WorkflowSubmission::query()->updateOrCreate(
                ['related_table' => $model->getTable(), 'related_id' => $model->getKey(), 'module' => $item['module']],
                [
                    'status' => $model->status ?? 'submitted',
                    'submitted_by' => $actor->id,
                    'current_reviewer_id' => $reviewer->id,
                    'submitted_at' => now()->subDays(12),
                    'reviewed_at' => now()->subDays(8),
                    'note' => 'Workflow demo '.$item['module'].' untuk '.$opd->singkatan,
                    'metadata' => ['seeded' => true],
                ],
            );

            $submission->histories()->updateOrCreate(
                ['action' => 'submit', 'related_table' => $model->getTable(), 'related_id' => $model->getKey(), 'module' => $item['module']],
                [
                    'from_status' => 'draft',
                    'to_status' => 'submitted',
                    'actor_id' => $actor->id,
                    'reviewer_id' => $reviewer->id,
                    'notes' => 'Pengajuan data demo.',
                    'metadata' => ['seeded' => true],
                ],
            );

            $submission->histories()->updateOrCreate(
                ['action' => 'approve', 'related_table' => $model->getTable(), 'related_id' => $model->getKey(), 'module' => $item['module']],
                [
                    'from_status' => 'submitted',
                    'to_status' => $model->status ?? 'approved',
                    'actor_id' => $reviewer->id,
                    'reviewer_id' => $reviewer->id,
                    'notes' => 'Review data demo selesai.',
                    'metadata' => ['seeded' => true],
                ],
            );
        }

        Notification::query()->updateOrCreate(
            ['user_id' => $admin->id, 'title' => 'Data demo E-SAKIP tersedia untuk '.$opd->singkatan],
            [
                'type' => 'demo',
                'message' => 'Silakan cek Renstra, PK, Rencana Aksi, Realisasi, LKJIP, dan tindak lanjut rekomendasi.',
                'data' => ['opd_id' => $opd->id],
                'read_at' => null,
            ],
        );
    }

    private function importBatch(): void
    {
        /** @var ImportBatch $batch */
        $batch = ImportBatch::query()->updateOrCreate(
            ['module' => 'rpjmd', 'import_type' => 'cascading_rpjmd', 'original_filename' => 'demo-cascading-rpjmd.csv'],
            [
                'status' => 'preview',
                'mime_type' => 'text/csv',
                'file_size' => 256,
                'storage_disk' => config('filesystems.documents_disk', 'local'),
                'storage_path' => 'imports/demo-cascading-rpjmd.csv',
                'uploaded_by' => $this->users['admin_kabupaten_bapperida']->id,
                'total_rows' => 2,
                'preview_rows' => 2,
                'metadata' => ['seeded' => true],
            ],
        );

        foreach ([1, 2] as $rowNumber) {
            $batch->rows()->updateOrCreate(
                ['row_number' => $rowNumber],
                [
                    'status' => 'preview',
                    'raw_data' => ['kode' => 'DEMO-'.$rowNumber, 'uraian' => 'Baris import demo '.$rowNumber],
                    'normalized_data' => ['kode' => 'DEMO-'.$rowNumber, 'module' => 'rpjmd'],
                ],
            );
        }
    }

    private function activityLogs(): void
    {
        ActivityLog::query()->updateOrCreate(
            ['action' => 'seed_demo_data', 'model_type' => self::class, 'model_id' => 1],
            [
                'user_id' => User::query()->where('username', 'superadmin')->value('id'),
                'description' => 'Seeder demo E-SAKIP Banjarnegara dijalankan.',
                'old_values' => null,
                'new_values' => ['modules' => ['master', 'rpjmd', 'renstra', 'kinerja', 'dokumen', 'lkjip', 'evaluasi', 'workflow']],
                'ip_address' => '127.0.0.1',
                'user_agent' => 'artisan db:seed',
            ],
        );
    }

    private function seedAnnualTargets(Model $indicator, string $foreignKey, string $table, PeriodeTahun $periode, float $baseTarget, string $targetText, ?float $pagu = null): void
    {
        foreach (range(0, 4) as $offset) {
            $target = $baseTarget + ($offset * 1.5);
            DB::table($table)->updateOrInsert(
                [$foreignKey => $indicator->getKey(), 'periode_tahun_id' => $this->periodeByYear($periode->tahun + $offset)->id],
                [
                    'target' => $target,
                    'target_text' => str_contains($targetText, '%') ? ((int) round($target)).'%' : $targetText,
                    ...($pagu !== null ? ['pagu' => $pagu + ($offset * 250000000)] : []),
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            );
        }
    }

    /**
     * @param  array<int, float|int>  $targets
     * @param  array<int, float|int>  $anggaran
     */
    private function seedTriwulan(Model $indicator, PeriodeTahun $periode, array $targets, array $anggaran): void
    {
        foreach (['tw1', 'tw2', 'tw3', 'tw4'] as $index => $triwulan) {
            TargetTriwulanIndikator::query()->updateOrCreate(
                [
                    'related_table' => $indicator->getTable(),
                    'related_id' => $indicator->getKey(),
                    'periode_tahun_id' => $periode->id,
                    'triwulan' => $triwulan,
                ],
                [
                    'target_text' => (string) $targets[$index],
                    'target_angka' => $targets[$index],
                    'target_anggaran' => $anggaran[$index] ?? 0,
                ],
            );
        }
    }

    private function periodeByYear(int $year): PeriodeTahun
    {
        /** @var PeriodeTahun $periode */
        $periode = PeriodeTahun::query()->updateOrCreate(
            ['tahun' => $year],
            [
                'nama' => "Tahun {$year}",
                'tanggal_mulai' => "{$year}-01-01",
                'tanggal_selesai' => "{$year}-12-31",
                'status' => $year === (int) date('Y') ? 'active' : 'draft',
            ],
        );

        return $periode;
    }

    private function storeDummyDocument(PeriodeTahun $periode, Opd $opd, string $jenis, string $judul, Model $related, User $uploadedBy): void
    {
        $disk = (string) config('filesystems.documents_disk', 'local');
        $filename = Str::slug($judul).'.txt';
        $path = 'dokumen/demo/'.$jenis.'/'.$filename;
        $contents = "Dokumen dummy {$judul}\nRelasi: ".$related::class.' #'.$related->getKey()."\nDibuat: ".now()->toDateTimeString()."\n";

        Storage::disk($disk)->put($path, $contents);

        /** @var Dokumen $dokumen */
        $dokumen = Dokumen::query()->updateOrCreate(
            ['jenis' => $jenis, 'judul' => $judul, 'opd_id' => $opd->id],
            [
                'periode_tahun_id' => $periode->id,
                'nomor_dokumen' => strtoupper($jenis).'/DEMO/'.$opd->id.'/'.$periode->tahun,
                'deskripsi' => 'Dokumen dummy untuk modul '.$jenis.'.',
                'status' => 'approved',
                'original_filename' => $filename,
                'mime_type' => 'text/plain',
                'file_size' => strlen($contents),
                'file_hash' => hash('sha256', $contents),
                'storage_disk' => $disk,
                'storage_path' => $path,
                'uploaded_by' => $uploadedBy->id,
                'metadata' => ['seeded' => true],
            ],
        );

        $dokumen->relations()->updateOrCreate(
            ['related_type' => $related::class, 'related_id' => $related->getKey()],
            [
                'label' => $judul,
                'metadata' => ['seeded' => true],
                'created_by' => $uploadedBy->id,
            ],
        );
    }
}
