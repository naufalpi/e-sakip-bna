<?php

namespace Tests\Feature;

use App\Models\BidangUrusan;
use App\Models\Opd;
use App\Models\OpdUnit;
use App\Models\PeriodeTahun;
use App\Models\ProgramPemerintahan;
use App\Models\Role;
use App\Models\SatuanIndikator;
use App\Models\StrategiDaerah;
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
        $this->assertSame(33, BidangUrusan::query()->whereIn('kode', [
            '1.01',
            '1.02',
            '1.03',
            '1.04',
            '1.06',
            '2.07',
            '2.08',
            '2.09',
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
            '3.30',
            '3.31',
            '3.32',
            '5.01',
            '5.02',
            '5.03',
            '5.04',
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
            ->assertRedirect(route('master.opd-units.index'));

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
                'bidang_urusan_id' => $bidang->id,
                'kode' => '8.01.01.2.01',
                'nama' => 'Program Referensi Test',
                'status' => 'active',
            ])
            ->assertRedirect(route('master.program-pemerintahan.index'));

        $program = ProgramPemerintahan::where('kode', '8.01.01.2.01')->firstOrFail();

        $this->actingAs($admin)
            ->post(route('master.program-pemerintahan.store'), [
                'type' => 'kegiatan',
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

    public function test_admin_opd_can_only_view_own_opd_units(): void
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
            ->get(route('master.opd-units.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Master/OpdUnit/Index')
                ->has('items.data', 1)
                ->where('items.data.0.kode', 'OWN')
                ->where('can.manage', true)
            );

        $this->actingAs($adminOpd)
            ->post(route('master.opd-units.store'), [
                'opd_id' => $opd->id,
                'kode' => 'OWN-NEW',
                'nama' => 'Unit Baru',
                'status' => 'active',
            ])
            ->assertRedirect(route('master.opd-units.index'));

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
            ->assertOk();

        $this->actingAs($adminOpd)
            ->get(route('master.opd-units.edit', $otherUnit))
            ->assertForbidden();

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

    private function userWithRole(string $roleName): User
    {
        $user = User::factory()->create();
        $user->roles()->sync([Role::where('name', $roleName)->value('id')]);

        return $user;
    }
}
