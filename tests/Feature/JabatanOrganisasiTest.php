<?php

namespace Tests\Feature;

use App\Models\JabatanOrganisasi;
use App\Models\Opd;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class JabatanOrganisasiTest extends TestCase
{
    use RefreshDatabase;

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

    public function test_admin_opd_only_views_own_scope_and_cannot_manage_master_jabatan(): void
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
                ->where('can.manage', false));

        $this->actingAs($adminOpd)
            ->get(route('master.jabatan-organisasi.create'))
            ->assertForbidden();
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

    private function userWithRole(string $roleName): User
    {
        $user = User::factory()->create();
        $user->roles()->sync([Role::where('name', $roleName)->value('id')]);

        return $user;
    }
}
