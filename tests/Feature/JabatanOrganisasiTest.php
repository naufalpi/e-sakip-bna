<?php

namespace Tests\Feature;

use App\Models\JabatanOrganisasi;
use App\Models\Opd;
use App\Models\OpdUnit;
use App\Models\Pegawai;
use App\Models\ReferensiJabatan;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class JabatanOrganisasiTest extends TestCase
{
    use RefreshDatabase;

    public function test_reference_catalog_is_managed_centrally_and_visible_to_admin_opd(): void
    {
        $this->seed();
        $manager = $this->userWithRole('admin_kabupaten_bagian_organisasi');

        $this->actingAs($manager)
            ->post(route('master.referensi-jabatan.store'), [
                'kode' => 'JF-ARSIP-AP',
                'nama' => 'Arsiparis',
                'jenis_jabatan' => 'fungsional',
                'jenjang' => 'Ahli Pertama',
                'kelas_jabatan' => 8,
                'dasar_hukum' => 'Referensi resmi pengujian',
                'verification_status' => 'verified',
                'status' => 'active',
            ])
            ->assertRedirect(route('master.referensi-jabatan.index'))
            ->assertSessionDoesntHaveErrors();

        $this->assertDatabaseHas('referensi_jabatan', [
            'kode' => 'JF-ARSIP-AP',
            'nama' => 'Arsiparis',
            'verification_status' => 'verified',
        ]);

        $adminOpd = $this->userWithRole('admin_opd');
        $this->actingAs($adminOpd)
            ->get(route('master.referensi-jabatan.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Master/ReferensiJabatan/Index')
                ->where('can.manage', false)
                ->has('items.data', 1));

        $this->actingAs($adminOpd)
            ->get(route('master.referensi-jabatan.create'))
            ->assertForbidden();
    }

    public function test_admin_opd_can_place_verified_global_job_reference_with_direct_supervisor(): void
    {
        $this->seed();
        $opd = Opd::query()->where('status', 'active')->firstOrFail();
        $unit = OpdUnit::create([
            'opd_id' => $opd->id,
            'kode' => 'BID-TEST',
            'nama' => 'Bidang Pengujian',
            'jenis_unit' => 'bidang',
            'status' => 'active',
        ]);
        $head = JabatanOrganisasi::create([
            'opd_id' => $opd->id,
            'opd_unit_id' => $unit->id,
            'nama' => 'Kepala Bidang Pengujian',
            'level_jabatan' => 'administrator',
            'urutan' => 1,
            'status' => 'active',
            'verification_status' => 'verified',
        ]);
        $reference = ReferensiJabatan::create([
            'identity_key' => ReferensiJabatan::makeIdentityKey('fungsional', 'Pranata Komputer', 'Ahli Pertama'),
            'kode' => 'JF-PRAKOM-AP',
            'nama' => 'Pranata Komputer',
            'jenis_jabatan' => 'fungsional',
            'jenjang' => 'Ahli Pertama',
            'kelas_jabatan' => 8,
            'verification_status' => 'verified',
            'status' => 'active',
        ]);
        $admin = User::factory()->create(['opd_id' => $opd->id]);
        $admin->roles()->sync([Role::where('name', 'admin_opd')->value('id')]);

        $this->actingAs($admin)
            ->post(route('master.jabatan-organisasi.store'), [
                'opd_id' => $opd->id,
                'opd_unit_id' => $unit->id,
                'parent_id' => $head->id,
                'referensi_jabatan_id' => $reference->id,
                'nama' => 'Nama bebas harus diabaikan',
                'level_jabatan' => 'fungsional',
                'urutan' => 2,
                'status' => 'active',
            ])
            ->assertRedirect()
            ->assertSessionDoesntHaveErrors();

        $placement = JabatanOrganisasi::query()->where('referensi_jabatan_id', $reference->id)->firstOrFail();
        $this->assertSame('Pranata Komputer', $placement->nama);
        $this->assertSame($head->id, $placement->parent_id);
        $this->assertSame($unit->id, $placement->opd_unit_id);
        $this->assertSame('verified', $placement->verification_status);

        $this->actingAs($admin)
            ->post(route('master.jabatan-organisasi.store'), [
                'opd_id' => $opd->id,
                'opd_unit_id' => $unit->id,
                'parent_id' => $head->id,
                'nama' => 'Jabatan Fungsional Bebas',
                'level_jabatan' => 'fungsional',
                'urutan' => 3,
                'status' => 'active',
            ])
            ->assertSessionHasErrors('referensi_jabatan_id');
    }

    public function test_bagian_organisasi_can_create_hierarchy_and_official_history(): void
    {
        $this->seed();
        $admin = $this->userWithRole('admin_kabupaten_bagian_organisasi');
        $opd = Opd::query()->where('status', 'active')->firstOrFail();

        $this->actingAs($admin)
            ->post(route('master.jabatan-organisasi.store'), [
                'opd_id' => null,
                'opd_unit_id' => null,
                'parent_id' => null,
                'nama' => 'Bupati Banjarnegara',
                'level_jabatan' => 'kepala_daerah',
                'eselon' => null,
                'urutan' => 1,
                'status' => 'active',
            ])
            ->assertRedirect();

        $bupati = JabatanOrganisasi::where('nama', 'Bupati Banjarnegara')->firstOrFail();

        $this->actingAs($admin)
            ->post(route('master.jabatan-organisasi.store'), [
                'opd_id' => $opd->id,
                'opd_unit_id' => null,
                'parent_id' => $bupati->id,
                'nama' => 'Kepala Perangkat Daerah Pengujian',
                'level_jabatan' => 'jpt_pratama',
                'eselon' => 'ii_b',
                'urutan' => 1,
                'status' => 'active',
            ])
            ->assertRedirect();

        $jpt = JabatanOrganisasi::where('nama', 'Kepala Perangkat Daerah Pengujian')->firstOrFail();

        $this->actingAs($admin)
            ->post(route('master.jabatan-organisasi.pejabat.store', $jpt), [
                'user_id' => null,
                'nama_pejabat' => 'Pejabat Pengujian',
                'nip' => '198001012010011001',
                'pangkat_golongan' => 'Pembina, IV/a',
                'jenis_penugasan' => 'definitif',
                'nomor_sk' => 'SK/001/2026',
                'tanggal_sk' => '2026-01-02',
                'tanggal_mulai' => '2026-01-03',
                'tanggal_selesai' => null,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('jabatan_organisasi', [
            'id' => $jpt->id,
            'parent_id' => $bupati->id,
            'level_jabatan' => 'jpt_pratama',
        ]);
        $this->assertDatabaseHas('riwayat_pejabat_jabatan', [
            'jabatan_organisasi_id' => $jpt->id,
            'nama_pejabat' => 'Pejabat Pengujian',
            'jenis_penugasan' => 'definitif',
        ]);

        $this->actingAs($admin)
            ->get(route('master.jabatan-organisasi.show', $jpt))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Master/JabatanOrganisasi/Show')
                ->where('item.nama', 'Kepala Perangkat Daerah Pengujian')
                ->where('item.current_pejabat.nama_pejabat', 'Pejabat Pengujian')
                ->has('item.riwayat_pejabat', 1));
    }

    public function test_invalid_parent_level_and_overlapping_assignment_are_rejected(): void
    {
        $this->seed();
        $admin = $this->userWithRole('admin_kabupaten_dinkominfo');
        $opd = Opd::query()->where('status', 'active')->firstOrFail();
        $bupati = JabatanOrganisasi::create([
            'nama' => 'Bupati Banjarnegara',
            'level_jabatan' => 'kepala_daerah',
            'urutan' => 1,
            'status' => 'active',
        ]);

        $this->actingAs($admin)
            ->post(route('master.jabatan-organisasi.store'), [
                'opd_id' => $opd->id,
                'parent_id' => $bupati->id,
                'nama' => 'Administrator Tidak Valid',
                'level_jabatan' => 'administrator',
                'urutan' => 1,
                'status' => 'active',
            ])
            ->assertSessionHasErrors('parent_id');

        $jpt = JabatanOrganisasi::create([
            'opd_id' => $opd->id,
            'parent_id' => $bupati->id,
            'nama' => 'Kepala Perangkat Daerah',
            'level_jabatan' => 'jpt_pratama',
            'urutan' => 1,
            'status' => 'active',
        ]);
        $jpt->riwayatPejabat()->create([
            'nama_pejabat' => 'Pejabat Pertama',
            'jenis_penugasan' => 'definitif',
            'tanggal_mulai' => '2026-01-01',
            'tanggal_selesai' => '2026-12-31',
        ]);

        $this->actingAs($admin)
            ->post(route('master.jabatan-organisasi.pejabat.store', $jpt), [
                'nama_pejabat' => 'Pejabat Kedua',
                'jenis_penugasan' => 'plt',
                'tanggal_mulai' => '2026-06-01',
                'tanggal_selesai' => '2027-01-31',
            ])
            ->assertSessionHasErrors('tanggal_mulai');
    }

    public function test_admin_opd_can_propose_own_jobs_while_verified_structure_remains_locked(): void
    {
        $this->seed();
        $ownOpd = Opd::query()->where('status', 'active')->firstOrFail();
        $otherOpd = Opd::query()->where('status', 'active')->whereKeyNot($ownOpd->id)->firstOrFail();

        JabatanOrganisasi::create([
            'opd_id' => $ownOpd->id,
            'nama' => 'Jabatan OPD Sendiri',
            'level_jabatan' => 'jpt_pratama',
            'urutan' => 1,
            'status' => 'active',
        ]);
        JabatanOrganisasi::create([
            'opd_id' => $otherOpd->id,
            'nama' => 'Jabatan OPD Lain',
            'level_jabatan' => 'jpt_pratama',
            'urutan' => 1,
            'status' => 'active',
        ]);

        $adminOpd = User::factory()->create(['opd_id' => $ownOpd->id]);
        $adminOpd->roles()->sync([Role::where('name', 'admin_opd')->value('id')]);

        $this->actingAs($adminOpd)
            ->get(route('master.jabatan-organisasi.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Master/JabatanOrganisasi/Index')
                ->has('items.data', 1)
                ->where('items.data.0.nama', 'Jabatan OPD Sendiri')
                ->where('items.data.0.can_edit', false)
                ->where('can.create', true)
                ->where('can.opd_scoped', true));

        $this->actingAs($adminOpd)
            ->get(route('master.jabatan-organisasi.create'))
            ->assertOk();

        $this->actingAs($adminOpd)
            ->post(route('master.jabatan-organisasi.store'), [
                'opd_id' => $otherOpd->id,
                'parent_id' => JabatanOrganisasi::query()->where('nama', 'Jabatan OPD Sendiri')->value('id'),
                'nama' => 'Kepala Bidang Usulan OPD',
                'level_jabatan' => 'administrator',
                'urutan' => 2,
                'status' => 'active',
            ])
            ->assertRedirect()
            ->assertSessionDoesntHaveErrors();

        $proposal = JabatanOrganisasi::query()->where('nama', 'Kepala Bidang Usulan OPD')->firstOrFail();
        $this->assertSame($ownOpd->id, $proposal->opd_id);
        $this->assertSame('pending', $proposal->verification_status);
        $this->assertSame($adminOpd->id, $proposal->proposed_by);

        $this->actingAs($adminOpd)
            ->get(route('master.jabatan-organisasi.edit', JabatanOrganisasi::query()->where('nama', 'Jabatan OPD Sendiri')->firstOrFail()))
            ->assertForbidden();

        $reviewer = $this->userWithRole('admin_kabupaten_bagian_organisasi');
        $this->actingAs($reviewer)
            ->patch(route('master.jabatan-organisasi.verify', $proposal), [
                'verification_status' => 'verified',
                'verification_note' => null,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('jabatan_organisasi', [
            'id' => $proposal->id,
            'verification_status' => 'verified',
            'verified_by' => $reviewer->id,
        ]);
    }

    public function test_admin_opd_manages_people_separately_and_legacy_history_endpoint_remains_scoped(): void
    {
        $this->seed();
        $ownOpd = Opd::query()->where('status', 'active')->firstOrFail();
        $otherOpd = Opd::query()->where('status', 'active')->whereKeyNot($ownOpd->id)->firstOrFail();

        $ownJabatan = JabatanOrganisasi::create([
            'opd_id' => $ownOpd->id,
            'nama' => 'Kepala OPD Sendiri',
            'level_jabatan' => 'jpt_pratama',
            'urutan' => 1,
            'status' => 'active',
        ]);
        $otherJabatan = JabatanOrganisasi::create([
            'opd_id' => $otherOpd->id,
            'nama' => 'Kepala OPD Lain',
            'level_jabatan' => 'jpt_pratama',
            'urutan' => 1,
            'status' => 'active',
        ]);

        $adminOpd = User::factory()->create(['opd_id' => $ownOpd->id]);
        $adminOpd->roles()->sync([Role::where('name', 'admin_opd')->value('id')]);

        $this->actingAs($adminOpd)
            ->get(route('master.jabatan-organisasi.show', $ownJabatan))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('can.manage_structure', false)
                ->where('can.manage_officials', false)
                ->where('can.manage_people', true)
                ->where('can.delete_officials', false));

        $this->actingAs($adminOpd)
            ->post(route('master.jabatan-organisasi.pejabat.store', $ownJabatan), [
                'nama_pejabat' => 'Pejabat OPD Sendiri',
                'jenis_penugasan' => 'definitif',
                'tanggal_mulai' => '2026-01-01',
                'tanggal_selesai' => null,
            ])
            ->assertRedirect();

        $riwayat = $ownJabatan->riwayatPejabat()->firstOrFail();

        $this->actingAs($adminOpd)
            ->put(route('master.jabatan-organisasi.pejabat.update', [$ownJabatan, $riwayat]), [
                'nama_pejabat' => 'Pejabat OPD Diperbarui',
                'jenis_penugasan' => 'definitif',
                'tanggal_mulai' => '2026-01-01',
                'tanggal_selesai' => '2026-12-31',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('riwayat_pejabat_jabatan', [
            'id' => $riwayat->id,
            'nama_pejabat' => 'Pejabat OPD Diperbarui',
            'tanggal_selesai' => '2026-12-31 00:00:00',
        ]);

        $this->actingAs($adminOpd)
            ->post(route('master.jabatan-organisasi.pejabat.store', $otherJabatan), [
                'nama_pejabat' => 'Pejabat OPD Lain',
                'jenis_penugasan' => 'definitif',
                'tanggal_mulai' => '2026-01-01',
            ])
            ->assertForbidden();

        $this->actingAs($adminOpd)
            ->delete(route('master.jabatan-organisasi.pejabat.destroy', [$ownJabatan, $riwayat]))
            ->assertForbidden();

        $this->assertDatabaseHas('riwayat_pejabat_jabatan', ['id' => $riwayat->id]);
    }

    public function test_jabatan_with_children_or_history_cannot_be_deleted(): void
    {
        $this->seed();
        $admin = $this->userWithRole('admin_kabupaten_bagian_organisasi');
        $opd = Opd::query()->where('status', 'active')->firstOrFail();
        $bupati = JabatanOrganisasi::create([
            'nama' => 'Bupati Banjarnegara',
            'level_jabatan' => 'kepala_daerah',
            'urutan' => 1,
            'status' => 'active',
        ]);
        $jpt = JabatanOrganisasi::create([
            'opd_id' => $opd->id,
            'parent_id' => $bupati->id,
            'nama' => 'Kepala Perangkat Daerah',
            'level_jabatan' => 'jpt_pratama',
            'urutan' => 1,
            'status' => 'active',
        ]);

        $this->actingAs($admin)
            ->delete(route('master.jabatan-organisasi.destroy', $bupati))
            ->assertSessionHas('error');
        $this->assertNotSoftDeleted($bupati);

        $jpt->riwayatPejabat()->create([
            'nama_pejabat' => 'Pejabat Tercatat',
            'jenis_penugasan' => 'definitif',
            'tanggal_mulai' => '2026-01-01',
        ]);

        $this->actingAs($admin)
            ->delete(route('master.jabatan-organisasi.destroy', $jpt))
            ->assertSessionHas('error');
        $this->assertNotSoftDeleted($jpt);
    }

    public function test_structure_workspace_filters_return_only_matching_visible_records(): void
    {
        $this->seed();
        $admin = $this->userWithRole('admin_kabupaten_bagian_organisasi');
        $opds = Opd::query()->where('status', 'active')->orderBy('id')->take(2)->get();
        $this->assertCount(2, $opds);
        // Pakai OPD non-ID 1 agar regresi boolean `true` menjadi ID 1 ikut terdeteksi.
        [$firstOpd, $secondOpd] = [$opds[1], $opds[0]];

        $matchingUnit = OpdUnit::create([
            'opd_id' => $firstOpd->id,
            'kode' => 'FILTER-BIDANG',
            'nama' => 'Bidang Filter Utama',
            'jenis_unit' => 'bidang',
            'nama_pimpinan' => 'Pimpinan Filter',
            'status' => 'active',
        ]);
        OpdUnit::create([
            'opd_id' => $firstOpd->id,
            'kode' => 'FILTER-SEKSI',
            'nama' => 'Seksi Filter Nonaktif',
            'jenis_unit' => 'seksi',
            'status' => 'inactive',
        ]);
        OpdUnit::create([
            'opd_id' => $secondOpd->id,
            'kode' => 'FILTER-BIDANG-LAIN',
            'nama' => 'Bidang Filter OPD Lain',
            'jenis_unit' => 'bidang',
            'status' => 'active',
        ]);

        $this->actingAs($admin)
            ->get(route('master.opd-units.index', [
                'search' => 'Pimpinan Filter',
                'opd_id' => $firstOpd->id,
                'jenis_unit' => 'bidang',
                'status' => 'active',
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Master/OpdUnit/Index')
                ->where('items.total', 1)
                ->where('items.data.0.id', $matchingUnit->id)
                ->where('filters.opd_id', (string) $firstOpd->id)
                ->where('filters.jenis_unit', 'bidang')
                ->where('filters.status', 'active'));

        $occupied = JabatanOrganisasi::create([
            'opd_id' => $firstOpd->id,
            'opd_unit_id' => $matchingUnit->id,
            'nama' => 'Jabatan Filter Terisi',
            'level_jabatan' => 'administrator',
            'urutan' => 1,
            'status' => 'active',
            'verification_status' => 'verified',
        ]);
        $occupied->riwayatPejabat()->create([
            'nama_pejabat' => 'Pegawai Filter Aktif',
            'jenis_penugasan' => 'definitif',
            'tanggal_mulai' => now()->subDay()->toDateString(),
        ]);

        $historical = JabatanOrganisasi::create([
            'opd_id' => $firstOpd->id,
            'opd_unit_id' => $matchingUnit->id,
            'nama' => 'Jabatan Dengan Riwayat Lama',
            'level_jabatan' => 'administrator',
            'urutan' => 2,
            'status' => 'active',
            'verification_status' => 'verified',
        ]);
        $historical->riwayatPejabat()->create([
            'nama_pejabat' => 'Pegawai Filter Lama',
            'jenis_penugasan' => 'definitif',
            'tanggal_mulai' => now()->subYears(2)->toDateString(),
            'tanggal_selesai' => now()->subYear()->toDateString(),
        ]);
        JabatanOrganisasi::create([
            'opd_id' => $firstOpd->id,
            'opd_unit_id' => $matchingUnit->id,
            'nama' => 'Usulan Filter Menunggu',
            'level_jabatan' => 'pengawas',
            'urutan' => 3,
            'status' => 'active',
            'verification_status' => 'pending',
        ]);
        JabatanOrganisasi::create([
            'opd_id' => $secondOpd->id,
            'nama' => 'Usulan Filter Perbaikan',
            'level_jabatan' => 'pengawas',
            'urutan' => 4,
            'status' => 'active',
            'verification_status' => 'rejected',
        ]);

        $this->actingAs($admin)
            ->get(route('master.jabatan-organisasi.index', [
                'search' => 'Pegawai Filter Aktif',
                'opd_id' => $firstOpd->id,
                'opd_unit_id' => $matchingUnit->id,
                'level_jabatan' => 'administrator',
                'status' => 'active',
                'keterisian' => 'terisi',
                'verification_status' => 'verified',
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Master/JabatanOrganisasi/Index')
                ->where('items.total', 1)
                ->where('items.data.0.id', $occupied->id)
                ->where('filters.opd_unit_id', (string) $matchingUnit->id));

        $this->actingAs($admin)
            ->get(route('master.jabatan-organisasi.index', ['search' => 'Pegawai Filter Lama']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('items.total', 0));

        $this->actingAs($admin)
            ->get(route('master.jabatan-organisasi.index', ['verification_status' => 'proposal']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('items.total', 2)
                ->where('filters.verification_status', 'proposal'));

        $this->actingAs($admin)
            ->get(route('master.jabatan-organisasi.index', ['keterisian' => 'nilai-tidak-valid']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('items.total', 4)
                ->where('filters.keterisian', ''));

        $matchingEmployee = Pegawai::create([
            'opd_id' => $firstOpd->id,
            'opd_unit_id' => $matchingUnit->id,
            'nama' => 'Pegawai Struktur Pertama',
            'nip' => '199901012026091201',
            'jenis_pegawai' => 'pns',
            'status' => 'active',
        ]);
        Pegawai::create([
            'opd_id' => $secondOpd->id,
            'nama' => 'Pegawai Struktur Kedua',
            'nip' => '199901012026091202',
            'jenis_pegawai' => 'pns',
            'status' => 'active',
        ]);

        $this->actingAs($admin)
            ->get(route('master.pegawai.index', [
                'search' => 'Pegawai Struktur',
                'opd_id' => $firstOpd->id,
                'opd_unit_id' => $matchingUnit->id,
                'jenis_pegawai' => 'pns',
                'status' => 'active',
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Master/Pegawai/Index')
                ->where('items.total', 1)
                ->where('items.data.0.id', $matchingEmployee->id)
                ->where('filters.opd_id', (string) $firstOpd->id)
                ->where('filters.opd_unit_id', (string) $matchingUnit->id));
    }

    private function userWithRole(string $roleName): User
    {
        $user = User::factory()->create();
        $user->roles()->sync([Role::where('name', $roleName)->value('id')]);

        return $user;
    }
}
