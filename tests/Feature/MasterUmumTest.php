<?php

namespace Tests\Feature;

use App\Models\BidangUrusan;
use App\Models\KegiatanPemerintahan;
use App\Models\Opd;
use App\Models\OpdUnit;
use App\Models\PeriodeTahun;
use App\Models\ProgramPemerintahan;
use App\Models\Role;
use App\Models\Rpjmd;
use App\Models\SatuanIndikator;
use App\Models\StrategiDaerah;
use App\Models\SubKegiatanPemerintahan;
use App\Models\SystemSetting;
use App\Models\UrusanPemerintahan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class MasterUmumTest extends TestCase
{
    use RefreshDatabase;

    public function test_dinkominfo_admin_can_manage_master_umum(): void
    {
        $this->seed();

        $this->assertDatabaseHas('urusan_pemerintahan', [
            'kode' => '1',
            'nama' => 'URUSAN PEMERINTAHAN WAJIB YANG BERKAITAN DENGAN PELAYANAN DASAR',
        ]);
        $this->assertDatabaseHas('urusan_pemerintahan', [
            'kode' => '5',
            'nama' => 'UNSUR PENUNJANG URUSAN PEMERINTAHAN',
        ]);
        $this->assertSame(8, UrusanPemerintahan::query()->whereIn('kode', ['1', '2', '3', '4', '5', '6', '7', '8'])->count());
        $this->assertSame(43, BidangUrusan::query()->whereIn('kode', [
            '1.01',
            '1.02',
            '1.03',
            '1.04',
            '1.05',
            '1.06',
            '2.07',
            '2.08',
            '2.09',
            '2.10',
            '2.11',
            '2.12',
            '2.13',
            '2.14',
            '2.15',
            '2.16',
            '2.17',
            '2.18',
            '2.19',
            '2.20',
            '2.21',
            '2.22',
            '2.23',
            '2.24',
            '3.25',
            '3.26',
            '3.27',
            '3.28',
            '3.29',
            '3.30',
            '3.31',
            '3.32',
            '4.01',
            '4.02',
            '5.01',
            '5.02',
            '5.03',
            '5.04',
            '5.05',
            '5.06',
            '6.01',
            '7.01',
            '8.01',
        ])->count());
        $this->assertDatabaseHas('opds', [
            'kode' => '2.16.2.20.2.21.01.0000',
            'nama' => 'Dinas Komunikasi dan Informatika',
            'singkatan' => 'Diskominfo',
        ]);
        $this->assertSame(
            UrusanPemerintahan::query()->where('kode', '8')->value('id'),
            Opd::query()->where('kode', '8.01.0.00.0.00.01.0000')->value('urusan_pemerintahan_id'),
        );
        $this->assertDatabaseHas('opd_units', [
            'kode' => '1.01.2.19.0.00.01.0103',
            'nama' => 'SMPN 1 Banjarnegara',
            'jenis_unit' => 'sekolah',
        ]);
        $this->assertDatabaseHas('opd_units', [
            'kode' => '1.02.0.00.0.00.01.0048',
            'nama' => 'UPTD Puskesmas BANJARNEGARA 1',
            'jenis_unit' => 'puskesmas',
        ]);
        $this->assertDatabaseHas('opd_units', [
            'kode' => '4.01.0.00.0.00.01.0006',
            'nama' => 'Bagian Organisasi',
            'jenis_unit' => 'bagian',
        ]);
        $this->assertDatabaseHas('users', [
            'username' => 'superadmin',
            'email' => 'admin@example.test',
        ]);
        $this->assertDatabaseHas('users', [
            'username' => 'admin_kabupaten_bagian_organisasi',
            'email' => 'admin.bagian-organisasi@example.test',
        ]);
        $this->assertDatabaseHas('users', [
            'username' => 'admin_kabupaten_bapperida',
            'email' => 'admin.bapperida@example.test',
        ]);
        $this->assertDatabaseHas('users', [
            'username' => 'admin_kabupaten_inspektorat',
            'email' => 'admin.inspektorat@example.test',
        ]);
        $this->assertDatabaseHas('users', [
            'username' => 'pimpinan',
            'email' => 'pimpinan@example.test',
        ]);
        $this->assertDatabaseHas('users', [
            'username' => 'opd_2_16_2_20_2_21_01_0000',
            'email' => 'opd-2_16_2_20_2_21_01_0000@example.test',
        ]);
        $this->assertDatabaseHas('users', [
            'username' => 'unit_1_01_2_19_0_00_01_0103',
            'email' => 'unit-1_01_2_19_0_00_01_0103@example.test',
        ]);
        $this->assertSame(
            Opd::query()->where('status', 'active')->count(),
            User::query()->pluck('username')->filter(fn (string $username) => str_starts_with($username, 'opd_'))->count(),
        );
        $this->assertSame(
            OpdUnit::query()->where('status', 'active')->count(),
            User::query()->pluck('username')->filter(fn (string $username) => str_starts_with($username, 'unit_'))->count(),
        );

        $dinkes = Opd::query()->where('kode', '1.02.0.00.0.00.01.0000')->firstOrFail();
        $dinkesRootUnit = OpdUnit::query()->where('opd_id', $dinkes->id)->where('kode', $dinkes->kode)->firstOrFail();
        $puskesmasUnit = OpdUnit::query()->where('opd_id', $dinkes->id)->where('kode', '1.02.0.00.0.00.01.0048')->firstOrFail();

        $this->assertSame($dinkesRootUnit->id, $puskesmasUnit->parent_id);

        $admin = $this->userWithRole('admin_kabupaten_dinkominfo');
        $opd = Opd::create([
            'kode' => '9.01.01',
            'nama' => 'Dinas Demo',
            'singkatan' => 'Demo',
            'status' => 'active',
        ]);

        $this->actingAs($admin)
            ->get(route('master.periode-tahun.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Master/PeriodeTahun/Index'));

        $this->actingAs($admin)
            ->post(route('master.periode-tahun.store'), [
                'tahun' => 2099,
                'nama' => 'Tahun 2099',
                'tanggal_mulai' => '2099-01-01',
                'tanggal_selesai' => '2099-12-31',
                'status' => 'draft',
            ])
            ->assertRedirect(route('master.periode-tahun.index'));

        $periode = PeriodeTahun::where('tahun', 2099)->firstOrFail();

        $this->actingAs($admin)
            ->put(route('master.periode-tahun.update', $periode), [
                'tahun' => 2099,
                'nama' => 'Tahun 2099 Revisi',
                'tanggal_mulai' => '2099-01-01',
                'tanggal_selesai' => '2099-12-31',
                'status' => 'locked',
            ])
            ->assertRedirect(route('master.periode-tahun.index'));

        $this->assertDatabaseHas('periode_tahun', ['id' => $periode->id, 'status' => 'locked']);

        $this->actingAs($admin)
            ->post(route('master.satuan-indikator.store'), [
                'nama' => 'Persentase Demo',
                'simbol' => '%d',
                'jenis' => 'persentase',
                'deskripsi' => 'Satuan demo.',
                'status' => 'active',
            ])
            ->assertRedirect(route('master.satuan-indikator.index'));

        $satuan = SatuanIndikator::where('nama', 'Persentase Demo')->firstOrFail();

        $this->actingAs($admin)
            ->put(route('master.satuan-indikator.update', $satuan), [
                'nama' => 'Persentase Demo',
                'simbol' => '%',
                'jenis' => 'persentase',
                'deskripsi' => 'Satuan demo revisi.',
                'status' => 'inactive',
            ])
            ->assertRedirect(route('master.satuan-indikator.index'));

        $this->assertDatabaseHas('satuan_indikator', ['id' => $satuan->id, 'status' => 'inactive']);

        $this->actingAs($admin)
            ->post(route('master.urusan-pemerintahan.store'), [
                'kode' => '9.99',
                'nama' => 'Urusan Demo',
                'deskripsi' => 'Urusan untuk pengujian.',
                'status' => 'active',
            ])
            ->assertRedirect(route('master.urusan-pemerintahan.index'));

        $urusan = UrusanPemerintahan::where('kode', '9.99')->firstOrFail();

        $this->actingAs($admin)
            ->put(route('master.urusan-pemerintahan.update', $urusan), [
                'kode' => '9.99',
                'nama' => 'Urusan Demo Revisi',
                'deskripsi' => 'Urusan untuk pengujian.',
                'status' => 'inactive',
            ])
            ->assertRedirect(route('master.urusan-pemerintahan.index'));

        $this->assertDatabaseHas('urusan_pemerintahan', ['id' => $urusan->id, 'status' => 'inactive']);

        $this->actingAs($admin)
            ->post(route('master.strategi-daerah.store'), [
                'kode' => 'STR-DEMO',
                'strategi' => 'Strategi pelayanan publik terpadu',
                'status' => 'active',
            ])
            ->assertRedirect(route('master.strategi-daerah.index'));

        $strategi = StrategiDaerah::where('kode', 'STR-DEMO')->firstOrFail();

        $this->actingAs($admin)
            ->put(route('master.strategi-daerah.update', $strategi), [
                'kode' => 'STR-DEMO',
                'strategi' => 'Strategi pelayanan publik terintegrasi',
                'status' => 'inactive',
            ])
            ->assertRedirect(route('master.strategi-daerah.index'));

        $this->assertDatabaseHas('strategi_daerah', [
            'id' => $strategi->id,
            'status' => 'inactive',
        ]);
        $this->assertFalse(Schema::hasColumn('strategi_daerah', 'sasaran_daerah_id'));
        $this->assertFalse(Schema::hasColumn('strategi_daerah', 'arah_kebijakan'));
        $this->assertFalse(Schema::hasColumn('strategi_daerah', 'urutan'));

        $this->actingAs($admin)
            ->post(route('master.system-settings.store'), [
                'group' => 'demo',
                'key' => 'demo.enabled',
                'label' => 'Demo Enabled',
                'type' => 'boolean',
                'value' => '1',
                'is_public' => true,
            ])
            ->assertRedirect(route('master.system-settings.index'));

        $setting = SystemSetting::where('key', 'demo.enabled')->firstOrFail();

        $this->assertSame(true, $setting->value);
        $this->assertTrue($setting->is_public);

        $this->actingAs($admin)
            ->post(route('master.opd-units.store'), [
                'opd_id' => $opd->id,
                'parent_id' => null,
                'kode' => 'DEMO-SEK',
                'nama' => 'Sekretariat Demo',
                'jenis_unit' => 'sekretariat',
                'nama_pimpinan' => 'Sekretaris Demo',
                'nip_pimpinan' => '198001012010011001',
                'status' => 'active',
            ])
            ->assertRedirect(route('master.opd.index'));

        $this->assertDatabaseHas('opd_units', [
            'opd_id' => $opd->id,
            'kode' => 'DEMO-SEK',
            'status' => 'active',
        ]);
    }

    public function test_pimpinan_cannot_access_master_umum_pages(): void
    {
        $this->seed();

        $pimpinan = $this->userWithRole('pimpinan');

        foreach ([
            route('master.periode-tahun.index'),
            route('master.satuan-indikator.index'),
            route('master.strategi-daerah.index'),
            route('master.urusan-pemerintahan.index'),
            route('master.program-pemerintahan.index'),
            route('master.system-settings.index'),
        ] as $url) {
            $this->actingAs($pimpinan)->get($url)->assertForbidden();
        }
    }

    public function test_dinkominfo_admin_can_manage_program_kegiatan_references(): void
    {
        $this->seed();
        $periode = PeriodeTahun::where('status', 'active')->firstOrFail();
        $rpjmdSeed = Rpjmd::where('tahun_awal', 2025)->where('tahun_akhir', 2029)->firstOrFail();

        $this->assertSame('RPJMD KABUPATEN BANJARNEGARA TAHUN 2025-2029', $rpjmdSeed->judul);
        $this->assertSame('PERATURAN DAERAH KABUPATEN BANJARNEGARA NOMOR 2 TAHUN 2025', $rpjmdSeed->nomor_perda);
        $this->assertSame(2025, $rpjmdSeed->periodeTahun?->tahun);
        $this->assertSame($this->expectedProgramReferenceCount(), ProgramPemerintahan::query()->count());
        $this->assertSame($this->expectedProgramReferenceCount(), ProgramPemerintahan::where('tahun_awal', 2025)->where('tahun_akhir', 2029)->count());
        $this->assertSame(0, ProgramPemerintahan::where('tahun_awal', 2026)->where('tahun_akhir', 2030)->count());
        $this->assertSame(384, KegiatanPemerintahan::query()->count());
        $this->assertSame(985, SubKegiatanPemerintahan::query()->count());
        $this->assertDatabaseHas('program_pemerintahan', [
            'kode' => '1.01.02',
            'nama' => 'PROGRAM PENGELOLAAN PENDIDIKAN',
        ]);
        $this->assertDatabaseHas('kegiatan_pemerintahan', [
            'kode' => '1.01.02.2.01',
            'nama' => 'Pengelolaan Pendidikan Sekolah Dasar',
        ]);
        $kegiatanSekolahDasar = KegiatanPemerintahan::query()->where('kode', '1.01.02.2.01')->firstOrFail();
        $this->assertDatabaseHas('sub_kegiatan_pemerintahan', [
            'kegiatan_pemerintahan_id' => $kegiatanSekolahDasar->id,
            'kode' => '1.01.02.2.01.0006',
            'nama' => 'Pembangunan Sarana, Prasarana dan Utilitas Sekolah',
        ]);

        $admin = $this->userWithRole('admin_kabupaten_dinkominfo');
        $urusan = UrusanPemerintahan::create([
            'kode' => '8.01',
            'nama' => 'Urusan Referensi Test',
            'status' => 'active',
        ]);

        $this->actingAs($admin)
            ->get(route('master.urusan-pemerintahan.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Master/UrusanPemerintahan/Index'));

        $this->actingAs($admin)
            ->post(route('master.urusan-pemerintahan.bidang.store'), [
                'urusan_pemerintahan_id' => $urusan->id,
                'kode' => '8.01.01',
                'nama' => 'Bidang Referensi Test',
                'status' => 'active',
            ])
            ->assertRedirect();

        $bidang = $urusan->bidangUrusan()->where('kode', '8.01.01')->firstOrFail();

        $this->actingAs($admin)
            ->put(route('master.urusan-pemerintahan.bidang.update', $bidang), [
                'urusan_pemerintahan_id' => $urusan->id,
                'kode' => '8.01.01',
                'nama' => 'Bidang Referensi Test Revisi',
                'status' => 'active',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('bidang_urusan', [
            'id' => $bidang->id,
            'nama' => 'Bidang Referensi Test Revisi',
        ]);

        $this->actingAs($admin)
            ->get(route('master.program-pemerintahan.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Master/ProgramPemerintahan/Index')
                ->where('level', 'program')
            );

        $this->actingAs($admin)
            ->post(route('master.program-pemerintahan.store'), [
                'type' => 'program',
                'tahun_awal' => 2026,
                'tahun_akhir' => 2030,
                'bidang_urusan_id' => $bidang->id,
                'kode' => '8.01.01.2.01',
                'nama' => 'Program Referensi Test',
                'status' => 'active',
            ])
            ->assertRedirect(route('master.program-pemerintahan.index'));

        $this->actingAs($admin)
            ->post(route('master.program-pemerintahan.store'), [
                'type' => 'program',
                'tahun_awal' => 2031,
                'tahun_akhir' => 2035,
                'bidang_urusan_id' => $bidang->id,
                'kode' => '8.01.01.2.01',
                'nama' => 'Program Referensi Test Tahun Lain',
                'status' => 'active',
            ])
            ->assertRedirect(route('master.program-pemerintahan.index'));

        $this->assertSame(2, ProgramPemerintahan::where('bidang_urusan_id', $bidang->id)->where('kode', '8.01.01.2.01')->count());

        $program = ProgramPemerintahan::where('tahun_awal', 2026)->where('tahun_akhir', 2030)->where('kode', '8.01.01.2.01')->firstOrFail();

        $this->actingAs($admin)
            ->post(route('master.program-pemerintahan.store'), [
                'type' => 'kegiatan',
                'periode_tahun_id' => $periode->id,
                'program_pemerintahan_id' => $program->id,
                'kode' => '8.01.01.2.01.0001',
                'nama' => 'Kegiatan Referensi Test',
                'status' => 'active',
            ])
            ->assertRedirect(route('master.program-pemerintahan.index'));

        $kegiatan = $program->kegiatan()->where('kode', '8.01.01.2.01.0001')->firstOrFail();

        $this->actingAs($admin)
            ->post(route('master.program-pemerintahan.store'), [
                'type' => 'sub_kegiatan',
                'periode_tahun_id' => $periode->id,
                'kegiatan_pemerintahan_id' => $kegiatan->id,
                'kode' => '8.01.01.2.01.0001.01',
                'nama' => 'Sub Kegiatan Referensi Test',
                'status' => 'active',
            ])
            ->assertRedirect(route('master.program-pemerintahan.index'));

        $this->assertDatabaseHas('sub_kegiatan_pemerintahan', [
            'kegiatan_pemerintahan_id' => $kegiatan->id,
            'kode' => '8.01.01.2.01.0001.01',
        ]);

        $this->actingAs($admin)
            ->get(route('master.program-pemerintahan.index', ['level' => 'kegiatan', 'program_id' => $program->id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Master/ProgramPemerintahan/Index')
                ->where('level', 'kegiatan')
                ->where('context.program.id', $program->id)
                ->has('items.data', 1)
            );

        $this->actingAs($admin)
            ->get(route('master.program-pemerintahan.index', ['level' => 'sub_kegiatan', 'kegiatan_id' => $kegiatan->id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Master/ProgramPemerintahan/Index')
                ->where('level', 'sub_kegiatan')
                ->where('context.kegiatan.id', $kegiatan->id)
                ->has('items.data', 1)
            );

        $this->actingAs($admin)
            ->put(route('master.program-pemerintahan.update', ['type' => 'program', 'id' => $program->id]), [
                'tahun_awal' => 2026,
                'tahun_akhir' => 2030,
                'bidang_urusan_id' => $bidang->id,
                'kode' => '8.01.01.2.01',
                'nama' => 'Program Referensi Test Revisi',
                'status' => 'inactive',
            ])
            ->assertRedirect(route('master.program-pemerintahan.index'));

        $this->assertDatabaseHas('program_pemerintahan', [
            'id' => $program->id,
            'nama' => 'Program Referensi Test Revisi',
            'status' => 'inactive',
        ]);

        $this->actingAs($admin)
            ->delete(route('master.program-pemerintahan.destroy', ['type' => 'program', 'id' => $program->id]))
            ->assertSessionHas('error');

        $this->actingAs($admin)
            ->post(route('master.program-pemerintahan.bulk-store'), [
                'type' => 'program',
                'tahun_awal' => 2026,
                'tahun_akhir' => 2030,
                'bidang_urusan_id' => $bidang->id,
                'rows' => "8.01.01.2.02 | Program Input Cepat\n8.01.01.2.03 | Program Input Cepat Kedua",
                'status' => 'active',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('program_pemerintahan', [
            'bidang_urusan_id' => $bidang->id,
            'kode' => '8.01.01.2.02',
            'nama' => 'Program Input Cepat',
        ]);

        $this->actingAs($admin)
            ->get(route('master.program-pemerintahan.export'))
            ->assertNotFound();

        $this->actingAs($admin)
            ->get(route('master.urusan-pemerintahan.export'))
            ->assertNotFound();
    }

    public function test_program_kegiatan_summary_cards_follow_current_context(): void
    {
        $this->seed();

        $admin = $this->userWithRole('admin_kabupaten_dinkominfo');
        $periode = PeriodeTahun::where('tahun', 2026)->firstOrFail();
        $program = ProgramPemerintahan::where('tahun_awal', 2025)
            ->where('tahun_akhir', 2029)
            ->where('kode', '1.01.01')
            ->firstOrFail();
        $kegiatan = KegiatanPemerintahan::where('program_pemerintahan_id', $program->id)
            ->where('periode_tahun_id', $periode->id)
            ->where('kode', '1.01.01.2.01')
            ->firstOrFail();

        $programCount = ProgramPemerintahan::where('tahun_awal', 2025)->where('tahun_akhir', 2029)->count();
        $allKegiatanCount = KegiatanPemerintahan::where('periode_tahun_id', $periode->id)
            ->whereHas('programPemerintahan', fn ($query) => $query->where('tahun_awal', 2025)->where('tahun_akhir', 2029))
            ->count();
        $allSubKegiatanCount = SubKegiatanPemerintahan::where('periode_tahun_id', $periode->id)
            ->whereHas('kegiatanPemerintahan.programPemerintahan', fn ($query) => $query->where('tahun_awal', 2025)->where('tahun_akhir', 2029))
            ->count();
        $programKegiatanCount = KegiatanPemerintahan::where('program_pemerintahan_id', $program->id)
            ->where('periode_tahun_id', $periode->id)
            ->count();
        $programSubKegiatanCount = SubKegiatanPemerintahan::where('periode_tahun_id', $periode->id)
            ->whereHas('kegiatanPemerintahan', fn ($query) => $query->where('program_pemerintahan_id', $program->id))
            ->count();
        $kegiatanSubKegiatanCount = SubKegiatanPemerintahan::where('kegiatan_pemerintahan_id', $kegiatan->id)
            ->where('periode_tahun_id', $periode->id)
            ->count();

        $this->actingAs($admin)
            ->get(route('master.program-pemerintahan.index', [
                'tahun_awal' => 2025,
                'tahun_akhir' => 2029,
                'periode_tahun_id' => $periode->id,
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('summary.program_count', $programCount)
                ->where('summary.kegiatan_count', $allKegiatanCount)
                ->where('summary.sub_kegiatan_count', $allSubKegiatanCount)
            );

        $this->actingAs($admin)
            ->get(route('master.program-pemerintahan.index', [
                'level' => 'kegiatan',
                'program_id' => $program->id,
                'periode_tahun_id' => $periode->id,
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('summary.program_count', 1)
                ->where('summary.kegiatan_count', $programKegiatanCount)
                ->where('summary.sub_kegiatan_count', $programSubKegiatanCount)
            );

        $this->actingAs($admin)
            ->get(route('master.program-pemerintahan.index', [
                'level' => 'sub_kegiatan',
                'kegiatan_id' => $kegiatan->id,
                'periode_tahun_id' => $periode->id,
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('summary.program_count', 1)
                ->where('summary.kegiatan_count', 1)
                ->where('summary.sub_kegiatan_count', $kegiatanSubKegiatanCount)
            );
    }

    public function test_bapperida_admin_can_manage_planning_program_references(): void
    {
        $this->seed();

        $admin = $this->userWithRole('admin_kabupaten_bapperida');
        $bidang = BidangUrusan::where('kode', '8.01')->firstOrFail();

        $this->actingAs($admin)
            ->get(route('master.program-pemerintahan.index', [
                'tahun_awal' => 2025,
                'tahun_akhir' => 2029,
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Master/ProgramPemerintahan/Index')
                ->where('can.manage', true)
            );

        $this->actingAs($admin)
            ->post(route('master.program-pemerintahan.store'), [
                'type' => 'program',
                'tahun_awal' => 2025,
                'tahun_akhir' => 2029,
                'bidang_urusan_id' => $bidang->id,
                'kode' => '8.01.99',
                'nama' => 'PROGRAM REFERENSI PERENCANAAN BAPPERIDA',
                'status' => 'active',
            ])
            ->assertRedirect(route('master.program-pemerintahan.index'));

        $this->assertDatabaseHas('program_pemerintahan', [
            'tahun_awal' => 2025,
            'tahun_akhir' => 2029,
            'bidang_urusan_id' => $bidang->id,
            'kode' => '8.01.99',
            'nama' => 'PROGRAM REFERENSI PERENCANAAN BAPPERIDA',
        ]);
    }

    public function test_dinkominfo_admin_can_copy_program_references_between_rpjmd_periods(): void
    {
        $this->seed();

        $admin = $this->userWithRole('admin_kabupaten_dinkominfo');
        $source = ProgramPemerintahan::query()
            ->select(['tahun_awal', 'tahun_akhir'])
            ->firstOrFail();
        $targetTahunAwal = (int) $source->tahun_akhir + 1;
        $targetTahunAkhir = $targetTahunAwal + 4;

        $expectedProgramCount = $this->expectedProgramReferenceCount();

        $this->assertSame($expectedProgramCount, ProgramPemerintahan::where('tahun_awal', $source->tahun_awal)->where('tahun_akhir', $source->tahun_akhir)->count());
        $this->assertSame(0, ProgramPemerintahan::where('tahun_awal', $targetTahunAwal)->where('tahun_akhir', $targetTahunAkhir)->count());

        $this->actingAs($admin)
            ->post(route('master.program-pemerintahan.copy'), [
                'source_tahun_awal' => $source->tahun_awal,
                'source_tahun_akhir' => $source->tahun_akhir,
                'target_tahun_awal' => $targetTahunAwal,
                'target_tahun_akhir' => $targetTahunAkhir,
            ])
            ->assertRedirect(route('master.program-pemerintahan.index', ['tahun_awal' => $targetTahunAwal, 'tahun_akhir' => $targetTahunAkhir]));

        $this->assertSame($expectedProgramCount, ProgramPemerintahan::where('tahun_awal', $targetTahunAwal)->where('tahun_akhir', $targetTahunAkhir)->count());

        $targetProgram = ProgramPemerintahan::where('tahun_awal', $targetTahunAwal)
            ->where('tahun_akhir', $targetTahunAkhir)
            ->where('kode', '1.01.02')
            ->firstOrFail();
        $sourceProgram = ProgramPemerintahan::where('tahun_awal', $source->tahun_awal)
            ->where('tahun_akhir', $source->tahun_akhir)
            ->where('kode', '1.01.02')
            ->firstOrFail();

        $this->assertSame($sourceProgram->nama, $targetProgram->nama);

        $this->actingAs($admin)
            ->post(route('master.program-pemerintahan.copy'), [
                'source_tahun_awal' => $source->tahun_awal,
                'source_tahun_akhir' => $source->tahun_akhir,
                'target_tahun_awal' => $targetTahunAwal,
                'target_tahun_akhir' => $targetTahunAkhir,
            ])
            ->assertRedirect(route('master.program-pemerintahan.index', ['tahun_awal' => $targetTahunAwal, 'tahun_akhir' => $targetTahunAkhir]));

        $this->assertSame($expectedProgramCount, ProgramPemerintahan::where('tahun_awal', $targetTahunAwal)->where('tahun_akhir', $targetTahunAkhir)->count());
    }

    public function test_dinkominfo_admin_can_copy_kegiatan_and_sub_kegiatan_from_previous_year(): void
    {
        $this->seed();

        $admin = $this->userWithRole('admin_kabupaten_dinkominfo');
        $sourcePeriode = PeriodeTahun::where('tahun', 2026)->firstOrFail();
        $targetPeriode = PeriodeTahun::where('tahun', 2027)->firstOrFail();
        $program = ProgramPemerintahan::where('tahun_awal', 2025)
            ->where('tahun_akhir', 2029)
            ->where('kode', '1.01.02')
            ->firstOrFail();

        $sourceKegiatanCount = KegiatanPemerintahan::where('program_pemerintahan_id', $program->id)
            ->where('periode_tahun_id', $sourcePeriode->id)
            ->count();
        $sourceSubKegiatanCount = SubKegiatanPemerintahan::where('periode_tahun_id', $sourcePeriode->id)
            ->whereHas('kegiatanPemerintahan', fn ($query) => $query->where('program_pemerintahan_id', $program->id))
            ->count();

        $this->assertGreaterThan(0, $sourceKegiatanCount);
        $this->assertGreaterThan(0, $sourceSubKegiatanCount);
        $this->assertSame(0, KegiatanPemerintahan::where('program_pemerintahan_id', $program->id)->where('periode_tahun_id', $targetPeriode->id)->count());

        $this->actingAs($admin)
            ->post(route('master.program-pemerintahan.copy-kegiatan-year'), [
                'program_pemerintahan_id' => $program->id,
                'source_periode_tahun_id' => $sourcePeriode->id,
                'target_periode_tahun_id' => $targetPeriode->id,
            ])
            ->assertRedirect(route('master.program-pemerintahan.index', [
                'level' => 'kegiatan',
                'program_id' => $program->id,
                'periode_tahun_id' => $targetPeriode->id,
            ]))
            ->assertSessionHas('success');

        $this->assertSame($sourceKegiatanCount, KegiatanPemerintahan::where('program_pemerintahan_id', $program->id)->where('periode_tahun_id', $targetPeriode->id)->count());
        $this->assertSame(
            $sourceSubKegiatanCount,
            SubKegiatanPemerintahan::where('periode_tahun_id', $targetPeriode->id)
                ->whereHas('kegiatanPemerintahan', fn ($query) => $query->where('program_pemerintahan_id', $program->id))
                ->count(),
        );

        $this->actingAs($admin)
            ->post(route('master.program-pemerintahan.copy-kegiatan-year'), [
                'program_pemerintahan_id' => $program->id,
                'source_periode_tahun_id' => $sourcePeriode->id,
                'target_periode_tahun_id' => $targetPeriode->id,
            ])
            ->assertRedirect();

        $this->assertSame($sourceKegiatanCount, KegiatanPemerintahan::where('program_pemerintahan_id', $program->id)->where('periode_tahun_id', $targetPeriode->id)->count());
        $this->assertSame(
            $sourceSubKegiatanCount,
            SubKegiatanPemerintahan::where('periode_tahun_id', $targetPeriode->id)
                ->whereHas('kegiatanPemerintahan', fn ($query) => $query->where('program_pemerintahan_id', $program->id))
                ->count(),
        );
    }

    public function test_dinkominfo_admin_can_copy_all_kegiatan_and_sub_kegiatan_to_multiple_years_in_same_rpjmd(): void
    {
        $this->seed();

        $admin = $this->userWithRole('admin_kabupaten_dinkominfo');
        $sourcePeriode = PeriodeTahun::where('tahun', 2026)->firstOrFail();
        $targetPeriode2027 = PeriodeTahun::where('tahun', 2027)->firstOrFail();
        $targetPeriode2028 = PeriodeTahun::where('tahun', 2028)->firstOrFail();

        $sourceKegiatanCount = KegiatanPemerintahan::where('periode_tahun_id', $sourcePeriode->id)->count();
        $sourceSubKegiatanCount = SubKegiatanPemerintahan::where('periode_tahun_id', $sourcePeriode->id)->count();

        $this->assertGreaterThan(0, $sourceKegiatanCount);
        $this->assertGreaterThan(0, $sourceSubKegiatanCount);
        $this->assertSame(0, KegiatanPemerintahan::where('periode_tahun_id', $targetPeriode2027->id)->count());
        $this->assertSame(0, SubKegiatanPemerintahan::where('periode_tahun_id', $targetPeriode2027->id)->count());

        $this->actingAs($admin)
            ->post(route('master.program-pemerintahan.copy-kegiatan-years'), [
                'tahun_awal' => 2025,
                'tahun_akhir' => 2029,
                'source_periode_tahun_id' => $sourcePeriode->id,
                'target_periode_tahun_ids' => [$targetPeriode2027->id, $targetPeriode2028->id],
            ])
            ->assertRedirect(route('master.program-pemerintahan.index', [
                'tahun_awal' => 2025,
                'tahun_akhir' => 2029,
            ]))
            ->assertSessionHas('success');

        foreach ([$targetPeriode2027, $targetPeriode2028] as $targetPeriode) {
            $this->assertSame($sourceKegiatanCount, KegiatanPemerintahan::where('periode_tahun_id', $targetPeriode->id)->count());
            $this->assertSame($sourceSubKegiatanCount, SubKegiatanPemerintahan::where('periode_tahun_id', $targetPeriode->id)->count());
        }

        $this->actingAs($admin)
            ->post(route('master.program-pemerintahan.copy-kegiatan-years'), [
                'tahun_awal' => 2025,
                'tahun_akhir' => 2029,
                'source_periode_tahun_id' => $sourcePeriode->id,
                'target_periode_tahun_ids' => [$targetPeriode2027->id, $targetPeriode2028->id],
            ])
            ->assertRedirect();

        foreach ([$targetPeriode2027, $targetPeriode2028] as $targetPeriode) {
            $this->assertSame($sourceKegiatanCount, KegiatanPemerintahan::where('periode_tahun_id', $targetPeriode->id)->count());
            $this->assertSame($sourceSubKegiatanCount, SubKegiatanPemerintahan::where('periode_tahun_id', $targetPeriode->id)->count());
        }
    }

    public function test_admin_opd_can_manage_own_units_from_master_opd(): void
    {
        $this->seed();

        $opd = Opd::create(['kode' => '1.01.01', 'nama' => 'OPD Sendiri', 'status' => 'active']);
        $otherOpd = Opd::create(['kode' => '1.01.02', 'nama' => 'OPD Lain', 'status' => 'active']);

        $ownUnit = OpdUnit::create([
            'opd_id' => $opd->id,
            'kode' => 'OWN',
            'nama' => 'Unit Sendiri',
            'jenis_unit' => 'bidang',
            'status' => 'active',
        ]);
        $otherUnit = OpdUnit::create([
            'opd_id' => $otherOpd->id,
            'kode' => 'OTHER',
            'nama' => 'Unit Lain',
            'jenis_unit' => 'bidang',
            'status' => 'active',
        ]);

        $adminOpd = User::factory()->create(['opd_id' => $opd->id]);
        $adminOpd->roles()->sync([Role::where('name', 'admin_opd')->value('id')]);

        $this->actingAs($adminOpd)
            ->get(route('master.opd.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Master/Opd/Index')
                ->has('opds.data', 1)
                ->where('opds.data.0.kode', '1.01.01')
                ->has('opds.data.0.units', 1)
                ->where('opds.data.0.units.0.kode', 'OWN')
                ->where('totalUnits', 1)
                ->where('can.manageUnits', true)
            );

        $this->actingAs($adminOpd)
            ->get(route('master.opd-units.index'))
            ->assertRedirect(route('master.opd.index'));

        $this->actingAs($adminOpd)
            ->post(route('master.opd-units.store'), [
                'opd_id' => $opd->id,
                'kode' => 'OWN-NEW',
                'nama' => 'Unit Baru',
                'status' => 'active',
            ])
            ->assertRedirect(route('master.opd.index'));

        $this->assertDatabaseHas('opd_units', [
            'opd_id' => $opd->id,
            'kode' => 'OWN-NEW',
            'nama' => 'Unit Baru',
        ]);

        $this->actingAs($adminOpd)
            ->post(route('master.opd-units.store'), [
                'opd_id' => $otherOpd->id,
                'kode' => 'OTHER-NEW',
                'nama' => 'Unit OPD Lain',
                'status' => 'active',
            ])
            ->assertSessionHasErrors('opd_id');

        $this->actingAs($adminOpd)
            ->get(route('master.opd-units.edit', $ownUnit))
            ->assertRedirect(route('master.opd.index'));

        $this->actingAs($adminOpd)
            ->put(route('master.opd-units.update', $ownUnit), [
                'opd_id' => $opd->id,
                'kode' => 'OWN-EDIT',
                'nama' => 'Unit Sendiri Revisi',
                'status' => 'active',
            ])
            ->assertRedirect(route('master.opd.index'));

        $this->assertDatabaseHas('opd_units', [
            'id' => $ownUnit->id,
            'kode' => 'OWN-EDIT',
            'nama' => 'Unit Sendiri Revisi',
        ]);

        $this->actingAs($adminOpd)
            ->put(route('master.opd-units.update', $otherUnit), [
                'opd_id' => $opd->id,
                'kode' => 'OWN-HIJACK',
                'nama' => 'Unit Lain Diubah',
                'status' => 'active',
            ])
            ->assertForbidden();

        $this->actingAs($adminOpd)
            ->delete(route('master.opd-units.destroy', $otherUnit))
            ->assertForbidden();
    }

    public function test_dinkominfo_can_assign_user_to_valid_opd_unit(): void
    {
        $this->seed();

        $admin = $this->userWithRole('admin_kabupaten_dinkominfo');
        $roleId = Role::where('name', 'admin_opd')->value('id');

        $opd = Opd::create(['kode' => '9.01.10', 'nama' => 'OPD Unit User', 'status' => 'active']);
        $unit = OpdUnit::create([
            'opd_id' => $opd->id,
            'kode' => '9.01.10.001',
            'nama' => 'Unit Valid',
            'jenis_unit' => 'uptd',
            'status' => 'active',
        ]);

        $otherOpd = Opd::create(['kode' => '9.01.11', 'nama' => 'OPD Unit Lain', 'status' => 'active']);
        $otherUnit = OpdUnit::create([
            'opd_id' => $otherOpd->id,
            'kode' => '9.01.11.001',
            'nama' => 'Unit Tidak Valid',
            'jenis_unit' => 'uptd',
            'status' => 'active',
        ]);

        $this->actingAs($admin)
            ->post(route('master.users.store'), [
                'opd_id' => $opd->id,
                'opd_unit_id' => $unit->id,
                'username' => 'unitvalid',
                'name' => 'Admin Unit Valid',
                'email' => 'unitvalid@example.test',
                'phone' => '081111111111',
                'jabatan' => 'Operator Unit',
                'password' => 'password',
                'password_confirmation' => 'password',
                'status' => 'active',
                'role_ids' => [$roleId],
            ])
            ->assertRedirect(route('master.users.index'));

        $this->assertDatabaseHas('users', [
            'username' => 'unitvalid',
            'opd_id' => $opd->id,
            'opd_unit_id' => $unit->id,
        ]);

        $this->actingAs($admin)
            ->post(route('master.users.store'), [
                'opd_id' => $opd->id,
                'opd_unit_id' => $otherUnit->id,
                'username' => 'unitinvalid',
                'name' => 'Admin Unit Invalid',
                'email' => 'unitinvalid@example.test',
                'password' => 'password',
                'password_confirmation' => 'password',
                'status' => 'active',
                'role_ids' => [$roleId],
            ])
            ->assertSessionHasErrors('opd_unit_id');
    }

    public function test_unit_scoped_admin_opd_only_sees_own_unit_scope(): void
    {
        $this->seed();

        $opd = Opd::create(['kode' => '9.02.10', 'nama' => 'OPD Scope Unit', 'status' => 'active']);
        $ownUnit = OpdUnit::create([
            'opd_id' => $opd->id,
            'kode' => '9.02.10.001',
            'nama' => 'Unit Scope Sendiri',
            'jenis_unit' => 'puskesmas',
            'status' => 'active',
        ]);
        OpdUnit::create([
            'opd_id' => $opd->id,
            'kode' => '9.02.10.002',
            'nama' => 'Unit Scope Lain',
            'jenis_unit' => 'puskesmas',
            'status' => 'active',
        ]);

        $adminOpd = User::factory()->create([
            'opd_id' => $opd->id,
            'opd_unit_id' => $ownUnit->id,
        ]);
        $adminOpd->roles()->sync([Role::where('name', 'admin_opd')->value('id')]);

        $this->actingAs($adminOpd)
            ->get(route('master.opd.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Master/Opd/Index')
                ->has('opds.data', 1)
                ->has('opds.data.0.units', 1)
                ->where('opds.data.0.units.0.id', $ownUnit->id)
                ->where('totalUnits', 1)
                ->where('can.manageUnits', false)
            );

        $this->actingAs($adminOpd)
            ->post(route('master.opd-units.store'), [
                'opd_id' => $opd->id,
                'kode' => '9.02.10.003',
                'nama' => 'Unit Baru Tidak Boleh',
                'status' => 'active',
            ])
            ->assertForbidden();
    }

    private function userWithRole(string $roleName): User
    {
        $user = User::factory()->create();
        $user->roles()->sync([Role::where('name', $roleName)->value('id')]);

        return $user;
    }

    private function expectedProgramReferenceCount(): int
    {
        return count(file(database_path('seeders/data/program_pemerintahan.tsv'), FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES));
    }
}
