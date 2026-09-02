<?php

namespace Tests\Feature;

use App\Models\IndikatorSasaranOpd;
use App\Models\JabatanOrganisasi;
use App\Models\Opd;
use App\Models\Pegawai;
use App\Models\PeriodeTahun;
use App\Models\PerjanjianKinerja;
use App\Models\PerjanjianKinerjaItem;
use App\Models\RenstraOpd;
use App\Models\Role;
use App\Models\Rpjmd;
use App\Models\SasaranOpd;
use App\Models\TargetIndikatorSasaranOpd;
use App\Models\TujuanOpd;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PegawaiPenempatanTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_kepala_daerah_identity_can_be_updated_without_opd(): void
    {
        $this->seed();
        $opd = Opd::query()->where('status', 'active')->firstOrFail();
        $admin = $this->userWithRole('super_admin', $opd);
        $jabatan = JabatanOrganisasi::create([
            'nama' => 'Bupati Banjarnegara',
            'level_jabatan' => 'kepala_daerah',
            'status' => 'active',
        ]);
        $pegawai = Pegawai::create([
            'opd_id' => $opd->id,
            'nama' => 'Bupati Uji',
            'jenis_pegawai' => 'pejabat_negara',
            'status' => 'active',
        ]);
        $pegawai->penempatan()->create([
            'jabatan_organisasi_id' => $jabatan->id,
            'nama_pejabat' => $pegawai->nama,
            'jenis_penugasan' => 'definitif',
            'tanggal_mulai' => now()->subDay()->toDateString(),
        ]);

        $this->actingAs($admin)
            ->get(route('master.pegawai.edit', $pegawai))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Master/Pegawai/Form')
                ->where('isKepalaDaerah', true));

        $this->actingAs($admin)
            ->put(route('master.pegawai.update', $pegawai), [
                'opd_id' => null,
                'opd_unit_id' => null,
                'user_id' => null,
                'nama' => 'Bupati Uji Diperbarui',
                'nip' => null,
                'pangkat_golongan' => null,
                'jenis_pegawai' => 'pejabat_negara',
                'status' => 'active',
                'jabatan_organisasi_id' => $jabatan->id,
                'jenis_penugasan' => 'definitif',
            ])
            ->assertRedirect()
            ->assertSessionDoesntHaveErrors();

        $this->assertDatabaseHas('pegawai', [
            'id' => $pegawai->id,
            'opd_id' => null,
            'nama' => 'Bupati Uji Diperbarui',
        ]);
    }

    public function test_employee_and_initial_position_can_be_saved_in_one_step(): void
    {
        $this->seed();
        $opd = Opd::query()->where('status', 'active')->firstOrFail();
        $adminOpd = $this->userWithRole('admin_opd', $opd);
        $jabatan = JabatanOrganisasi::create([
            'opd_id' => $opd->id,
            'nama' => 'Kepala Bidang Layanan Digital',
            'level_jabatan' => 'administrator',
            'urutan' => 1,
            'status' => 'active',
        ]);

        $this->actingAs($adminOpd)
            ->post(route('master.pegawai.store'), [
                'opd_id' => $opd->id,
                'nama' => 'Pegawai Satu Langkah',
                'nip' => '198801012010011003',
                'jenis_pegawai' => 'pns',
                'status' => 'active',
                'jabatan_organisasi_id' => $jabatan->id,
                'jenis_penugasan' => 'definitif',
            ])
            ->assertRedirect()
            ->assertSessionDoesntHaveErrors();

        $pegawai = Pegawai::query()->where('nama', 'Pegawai Satu Langkah')->firstOrFail();
        $this->assertDatabaseHas('riwayat_pejabat_jabatan', [
            'pegawai_id' => $pegawai->id,
            'jabatan_organisasi_id' => $jabatan->id,
            'tanggal_mulai' => now()->startOfDay()->toDateTimeString(),
            'nomor_sk' => null,
        ]);

        $this->actingAs($adminOpd)
            ->get(route('master.pegawai.edit', $pegawai))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Master/Pegawai/Form')
                ->where('item.jabatan_organisasi_id', $jabatan->id)
                ->where('item.jenis_penugasan', 'definitif'));
    }

    public function test_active_employee_requires_position_and_admin_opd_cannot_select_regent_position(): void
    {
        $this->seed();
        $opd = Opd::query()->where('status', 'active')->firstOrFail();
        $adminOpd = $this->userWithRole('admin_opd', $opd);
        $regentJob = JabatanOrganisasi::create([
            'nama' => 'Bupati Banjarnegara',
            'level_jabatan' => 'kepala_daerah',
            'status' => 'active',
        ]);

        $this->actingAs($adminOpd)
            ->get(route('master.pegawai.create'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Master/Pegawai/Form')
                ->where('jabatanOptions', fn ($options) => collect($options)
                    ->doesntContain(fn (array $option) => $option['level_jabatan'] === 'kepala_daerah')));

        $this->actingAs($adminOpd)
            ->post(route('master.pegawai.store'), [
                'opd_id' => $opd->id,
                'nama' => 'Pegawai Tanpa Jabatan',
                'nip' => '198901012019011001',
                'jenis_pegawai' => 'pns',
                'status' => 'active',
            ])
            ->assertSessionHasErrors('jabatan_organisasi_id');

        $this->assertDatabaseMissing('pegawai', ['nama' => 'Pegawai Tanpa Jabatan']);

        $this->actingAs($adminOpd)
            ->post(route('master.pegawai.store'), [
                'opd_id' => $opd->id,
                'nama' => 'Bupati dari Admin OPD',
                'jenis_pegawai' => 'pejabat_negara',
                'status' => 'active',
                'jabatan_organisasi_id' => $regentJob->id,
                'jenis_penugasan' => 'definitif',
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('pegawai', ['nama' => 'Bupati dari Admin OPD']);
    }

    public function test_admin_opd_manages_own_employees_and_multiple_holder_positions(): void
    {
        $this->seed();
        $opd = Opd::query()->where('status', 'active')->firstOrFail();
        $otherOpd = Opd::query()->where('status', 'active')->whereKeyNot($opd->id)->firstOrFail();
        $adminOpd = $this->userWithRole('admin_opd', $opd);
        $functional = JabatanOrganisasi::create([
            'opd_id' => $opd->id,
            'nama' => 'Analis Kebijakan Ahli Pertama',
            'level_jabatan' => 'fungsional',
            'urutan' => 1,
            'status' => 'active',
        ]);

        $this->actingAs($adminOpd)
            ->post(route('master.pegawai.store'), [
                'opd_id' => $otherOpd->id,
                'nama' => 'Pegawai Pertama',
                'nip' => '199001012020011001',
                'jenis_pegawai' => 'pns',
                'status' => 'active',
                'jabatan_organisasi_id' => $functional->id,
                'jenis_penugasan' => 'definitif',
            ])
            ->assertRedirect();

        $first = Pegawai::query()->where('nama', 'Pegawai Pertama')->firstOrFail();
        $this->assertSame($opd->id, $first->opd_id);

        $second = Pegawai::create([
            'opd_id' => $opd->id,
            'nama' => 'Pegawai Kedua',
            'nip' => '199001012020011002',
            'jenis_pegawai' => 'pppk',
            'status' => 'active',
        ]);
        foreach ([$second] as $pegawai) {
            $this->actingAs($adminOpd)
                ->post(route('master.pegawai.penempatan.store', $pegawai), [
                    'jabatan_organisasi_id' => $functional->id,
                    'jenis_penugasan' => 'definitif',
                    'tanggal_mulai' => '2026-01-01',
                ])
                ->assertRedirect()
                ->assertSessionDoesntHaveErrors();
        }

        $this->assertDatabaseCount('riwayat_pejabat_jabatan', 2);
        $this->actingAs($adminOpd)
            ->get(route('master.pegawai.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Master/Pegawai/Index')
                ->has('items.data', 2)
                ->where('can.manage', true));
        $this->actingAs($adminOpd)
            ->get(route('master.pegawai.show', $first))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Master/Pegawai/Show')
                ->where('item.nama', 'Pegawai Pertama')
                ->has('item.penempatan', 1)
                ->where('can.manage', true)
                ->where('can.delete', false));
        $this->actingAs($adminOpd)
            ->delete(route('master.pegawai.penempatan.destroy', [$first, $first->penempatan()->firstOrFail()]))
            ->assertForbidden();
    }

    public function test_structure_and_employee_indexes_follow_the_opd_hierarchy(): void
    {
        $this->seed();
        $opd = Opd::query()->where('status', 'active')->firstOrFail();
        $adminOpd = $this->userWithRole('admin_opd', $opd);

        $kabid = JabatanOrganisasi::create([
            'opd_id' => $opd->id,
            'nama' => 'Kepala Bidang Pengujian',
            'level_jabatan' => 'administrator',
            'urutan' => 1,
            'status' => 'active',
        ]);
        $kepala = JabatanOrganisasi::create([
            'opd_id' => $opd->id,
            'nama' => 'Kepala Dinas Pengujian',
            'level_jabatan' => 'jpt_pratama',
            'urutan' => 9,
            'status' => 'active',
        ]);
        $sekretaris = JabatanOrganisasi::create([
            'opd_id' => $opd->id,
            'nama' => 'Sekretaris Dinas Pengujian',
            'level_jabatan' => 'administrator',
            'urutan' => 9,
            'status' => 'active',
        ]);

        foreach ([
            [$kabid, 'A Pegawai Kabid', '198001012010011011'],
            [$kepala, 'Z Pegawai Kepala', '198001012010011012'],
            [$sekretaris, 'M Pegawai Sekretaris', '198001012010011013'],
        ] as [$jabatan, $nama, $nip]) {
            $pegawai = $this->employee($opd, $nama, $nip);
            $pegawai->penempatan()->create([
                'jabatan_organisasi_id' => $jabatan->id,
                'nama_pejabat' => $pegawai->nama,
                'nip' => $pegawai->nip,
                'jenis_penugasan' => 'definitif',
                'tanggal_mulai' => now()->subDay()->toDateString(),
            ]);
        }

        $this->actingAs($adminOpd)
            ->get(route('master.jabatan-organisasi.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('items.data.0.nama', 'Kepala Dinas Pengujian')
                ->where('items.data.1.nama', 'Sekretaris Dinas Pengujian')
                ->where('items.data.2.nama', 'Kepala Bidang Pengujian'));

        $this->actingAs($adminOpd)
            ->get(route('master.pegawai.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('items.data.0.nama', 'Z Pegawai Kepala')
                ->where('items.data.1.nama', 'M Pegawai Sekretaris')
                ->where('items.data.2.nama', 'A Pegawai Kabid'));
    }

    public function test_structural_position_rejects_overlapping_holder(): void
    {
        $this->seed();
        $opd = Opd::query()->where('status', 'active')->firstOrFail();
        $adminOpd = $this->userWithRole('admin_opd', $opd);
        $first = $this->employee($opd, 'Kepala Pertama', '198001012010011001');
        $second = $this->employee($opd, 'Kepala Kedua', '198001012010011002');
        $structural = JabatanOrganisasi::create([
            'opd_id' => $opd->id,
            'nama' => 'Kepala Bidang Pengujian',
            'level_jabatan' => 'administrator',
            'urutan' => 1,
            'status' => 'active',
        ]);

        $payload = [
            'jabatan_organisasi_id' => $structural->id,
            'jenis_penugasan' => 'definitif',
            'tanggal_mulai' => '2026-01-01',
        ];

        $this->actingAs($adminOpd)
            ->post(route('master.pegawai.penempatan.store', $first), $payload)
            ->assertRedirect();

        $this->actingAs($adminOpd)
            ->post(route('master.pegawai.penempatan.store', $second), $payload)
            ->assertSessionHasErrors('jabatan_organisasi_id');

        $this->assertSame(1, $structural->riwayatPejabat()->count());
    }

    public function test_deactivating_employee_closes_current_position_without_disabling_linked_account(): void
    {
        $this->seed();
        $opd = Opd::query()->where('status', 'active')->firstOrFail();
        $admin = $this->userWithRole('admin_opd', $opd);
        $jabatan = JabatanOrganisasi::create([
            'opd_id' => $opd->id,
            'nama' => 'Kepala Bidang Status Pegawai',
            'level_jabatan' => 'administrator',
            'status' => 'active',
        ]);
        $pegawai = Pegawai::create([
            'opd_id' => $opd->id,
            'user_id' => $admin->id,
            'nama' => 'Pegawai Akan Nonaktif',
            'nip' => '198101012011011001',
            'jenis_pegawai' => 'pns',
            'status' => 'active',
        ]);
        $placement = $pegawai->penempatan()->create([
            'jabatan_organisasi_id' => $jabatan->id,
            'nama_pejabat' => $pegawai->nama,
            'jenis_penugasan' => 'definitif',
            'tanggal_mulai' => now()->subMonth()->toDateString(),
        ]);

        $this->actingAs($admin)
            ->put(route('master.pegawai.update', $pegawai), [
                'opd_id' => $opd->id,
                'user_id' => $admin->id,
                'nama' => $pegawai->nama,
                'nip' => $pegawai->nip,
                'pangkat_golongan' => null,
                'jenis_pegawai' => 'pns',
                'status' => 'inactive',
            ])
            ->assertRedirect()
            ->assertSessionDoesntHaveErrors();

        $this->assertDatabaseHas('pegawai', ['id' => $pegawai->id, 'status' => 'inactive']);
        $this->assertDatabaseHas('users', ['id' => $admin->id, 'status' => 'active']);
        $this->assertDatabaseHas('riwayat_pejabat_jabatan', [
            'id' => $placement->id,
            'tanggal_selesai' => now()->subDay()->startOfDay()->toDateTimeString(),
        ]);

        $this->actingAs($admin)
            ->post(route('master.pegawai.penempatan.store', $pegawai), [
                'jabatan_organisasi_id' => $jabatan->id,
                'jenis_penugasan' => 'definitif',
            ])
            ->assertSessionHasErrors('jabatan_organisasi_id');
    }

    public function test_active_position_can_be_ended_without_entering_an_end_date(): void
    {
        $this->seed();
        $opd = Opd::query()->where('status', 'active')->firstOrFail();
        $admin = $this->userWithRole('admin_opd', $opd);
        $pegawai = $this->employee($opd, 'Pegawai Selesai Jabatan', '198201012012011001');
        $jabatan = JabatanOrganisasi::create([
            'opd_id' => $opd->id,
            'nama' => 'Kepala Subbagian Selesai',
            'level_jabatan' => 'pengawas',
            'status' => 'active',
        ]);
        $placement = $pegawai->penempatan()->create([
            'jabatan_organisasi_id' => $jabatan->id,
            'nama_pejabat' => $pegawai->nama,
            'jenis_penugasan' => 'definitif',
            'tanggal_mulai' => now()->subMonth()->toDateString(),
        ]);

        $this->actingAs($admin)
            ->patch(route('master.pegawai.penempatan.end', [$pegawai, $placement]))
            ->assertRedirect()
            ->assertSessionDoesntHaveErrors();

        $this->assertDatabaseHas('riwayat_pejabat_jabatan', [
            'id' => $placement->id,
            'tanggal_selesai' => now()->subDay()->startOfDay()->toDateTimeString(),
        ]);
    }

    public function test_changing_position_from_employee_edit_preserves_previous_position_history(): void
    {
        $this->seed();
        $opd = Opd::query()->where('status', 'active')->firstOrFail();
        $admin = $this->userWithRole('admin_opd', $opd);
        $pegawai = $this->employee($opd, 'Pegawai Pindah Jabatan', '198301012013011001');
        $oldJob = JabatanOrganisasi::create([
            'opd_id' => $opd->id,
            'nama' => 'Kepala Bidang Lama',
            'level_jabatan' => 'administrator',
            'status' => 'active',
        ]);
        $newJob = JabatanOrganisasi::create([
            'opd_id' => $opd->id,
            'nama' => 'Kepala Bidang Baru',
            'level_jabatan' => 'administrator',
            'status' => 'active',
        ]);
        $oldPlacement = $pegawai->penempatan()->create([
            'jabatan_organisasi_id' => $oldJob->id,
            'nama_pejabat' => $pegawai->nama,
            'jenis_penugasan' => 'definitif',
            'tanggal_mulai' => now()->subMonth()->toDateString(),
        ]);

        $this->actingAs($admin)
            ->put(route('master.pegawai.update', $pegawai), [
                'opd_id' => $opd->id,
                'user_id' => null,
                'nama' => $pegawai->nama,
                'nip' => $pegawai->nip,
                'pangkat_golongan' => null,
                'jenis_pegawai' => 'pns',
                'status' => 'active',
                'jabatan_organisasi_id' => $newJob->id,
                'jenis_penugasan' => 'plt',
            ])
            ->assertRedirect()
            ->assertSessionDoesntHaveErrors();

        $this->assertDatabaseHas('riwayat_pejabat_jabatan', [
            'id' => $oldPlacement->id,
            'tanggal_selesai' => now()->subDay()->startOfDay()->toDateTimeString(),
        ]);
        $this->assertDatabaseHas('riwayat_pejabat_jabatan', [
            'pegawai_id' => $pegawai->id,
            'jabatan_organisasi_id' => $newJob->id,
            'jenis_penugasan' => 'plt',
            'tanggal_mulai' => now()->startOfDay()->toDateTimeString(),
            'tanggal_selesai' => null,
        ]);
        $this->assertSame(2, $pegawai->penempatan()->count());
    }

    public function test_pending_job_can_be_used_but_rejected_job_cannot_receive_new_placement(): void
    {
        $this->seed();
        $opd = Opd::query()->where('status', 'active')->firstOrFail();
        $adminOpd = $this->userWithRole('admin_opd', $opd);
        $pegawai = $this->employee($opd, 'Pegawai Verifikasi Jabatan', '198501012015011001');

        $pending = JabatanOrganisasi::create([
            'opd_id' => $opd->id,
            'nama' => 'Analis Pending',
            'level_jabatan' => 'fungsional',
            'urutan' => 1,
            'status' => 'active',
            'verification_status' => 'pending',
            'proposed_by' => $adminOpd->id,
        ]);

        $this->actingAs($adminOpd)
            ->post(route('master.pegawai.penempatan.store', $pegawai), [
                'jabatan_organisasi_id' => $pending->id,
                'jenis_penugasan' => 'definitif',
                'tanggal_mulai' => '2026-01-01',
            ])
            ->assertRedirect()
            ->assertSessionDoesntHaveErrors();

        $rejected = JabatanOrganisasi::create([
            'opd_id' => $opd->id,
            'nama' => 'Analis Ditolak',
            'level_jabatan' => 'fungsional',
            'urutan' => 2,
            'status' => 'active',
            'verification_status' => 'rejected',
            'verification_note' => 'Nomenklatur belum sesuai.',
        ]);

        $this->actingAs($adminOpd)
            ->post(route('master.pegawai.penempatan.store', $pegawai), [
                'jabatan_organisasi_id' => $rejected->id,
                'jenis_penugasan' => 'definitif',
                'tanggal_mulai' => '2027-01-01',
            ])
            ->assertSessionHasErrors('jabatan_organisasi_id');
    }

    public function test_updating_employee_synchronizes_current_structure_identity_without_changing_pk_snapshot(): void
    {
        $this->seed();
        $opd = Opd::query()->where('status', 'active')->firstOrFail();
        $periode = PeriodeTahun::query()->firstOrFail();
        $admin = $this->userWithRole('super_admin', $opd);
        $jabatan = JabatanOrganisasi::create([
            'opd_id' => $opd->id,
            'nama' => 'Kepala Bidang Sinkronisasi',
            'level_jabatan' => 'administrator',
            'status' => 'active',
        ]);
        $pegawai = Pegawai::create([
            'opd_id' => $opd->id,
            'nama' => 'Nama Lama',
            'nip' => '198001012010011099',
            'jenis_pegawai' => 'pns',
            'status' => 'active',
        ]);
        $placement = $pegawai->penempatan()->create([
            'jabatan_organisasi_id' => $jabatan->id,
            'nama_pejabat' => 'Nama Lama',
            'jenis_penugasan' => 'definitif',
            'tanggal_mulai' => now()->subYear()->toDateString(),
        ]);
        $pk = PerjanjianKinerja::create([
            'opd_id' => $opd->id,
            'pegawai_id' => $pegawai->id,
            'penempatan_pegawai_id' => $placement->id,
            'periode_tahun_id' => $periode->id,
            'tahun' => $periode->tahun,
            'tipe_pk' => 'cascading',
            'level_pk' => 'struktural',
            'nama_pegawai_snapshot' => 'Nama Lama',
            'nip_snapshot' => '198001012010011099',
            'jabatan_snapshot' => $jabatan->nama,
            'judul' => 'PK Snapshot Lama',
            'status' => 'approved',
        ]);

        $this->actingAs($admin)
            ->put(route('master.pegawai.update', $pegawai), [
                'opd_id' => $opd->id,
                'opd_unit_id' => null,
                'user_id' => null,
                'nama' => 'Nama Pegawai Terbaru',
                'nip' => '198001012010011099',
                'pangkat_golongan' => 'Pembina / IV.a',
                'jenis_pegawai' => 'pns',
                'status' => 'active',
                'jabatan_organisasi_id' => $jabatan->id,
                'jenis_penugasan' => 'plt',
            ])
            ->assertRedirect()
            ->assertSessionDoesntHaveErrors();

        $this->assertDatabaseHas('riwayat_pejabat_jabatan', [
            'id' => $placement->id,
            'nama_pejabat' => 'Nama Pegawai Terbaru',
            'nip' => '198001012010011099',
            'pangkat_golongan' => 'Pembina / IV.a',
            'jenis_penugasan' => 'plt',
        ]);
        $this->assertDatabaseHas('perjanjian_kinerja', [
            'id' => $pk->id,
            'nama_pegawai_snapshot' => 'Nama Lama',
            'nip_snapshot' => '198001012010011099',
            'jabatan_snapshot' => $jabatan->nama,
        ]);
    }

    public function test_pk_selects_cascading_scope_directly_and_manual_individual_pk_is_excluded_from_action_plan(): void
    {
        $this->seed();
        $opd = Opd::query()->where('status', 'active')->firstOrFail();
        $periode = PeriodeTahun::query()->firstOrFail();
        $adminOpd = $this->userWithRole('admin_opd', $opd);
        $assigned = $this->employee($opd, 'Pegawai Cascading', '197901012009011001');
        $individual = $this->employee($opd, 'Pegawai Individu', '197901012009011002');
        $rpjmd = Rpjmd::create([
            'periode_tahun_id' => $periode->id,
            'judul' => 'RPJMD Pengujian',
            'tahun_awal' => $periode->tahun,
            'tahun_akhir' => $periode->tahun + 4,
            'status' => 'approved',
        ]);
        $renstra = RenstraOpd::create([
            'opd_id' => $opd->id,
            'rpjmd_id' => $rpjmd->id,
            'periode_tahun_id' => $periode->id,
            'judul' => 'Renstra Pengujian',
            'tahun_awal' => $periode->tahun,
            'tahun_akhir' => $periode->tahun + 4,
            'status' => 'approved',
        ]);
        $tujuan = TujuanOpd::create([
            'renstra_opd_id' => $renstra->id,
            'tujuan' => 'Meningkatkan kualitas layanan',
            'urutan' => 1,
        ]);
        $sasaran = SasaranOpd::create([
            'tujuan_opd_id' => $tujuan->id,
            'sasaran' => 'Meningkatnya kualitas layanan',
            'urutan' => 1,
        ]);
        $indikator = IndikatorSasaranOpd::create([
            'sasaran_opd_id' => $sasaran->id,
            'indikator' => 'Indeks kualitas layanan',
            'urutan' => 1,
        ]);
        TargetIndikatorSasaranOpd::create([
            'indikator_sasaran_opd_id' => $indikator->id,
            'periode_tahun_id' => $periode->id,
            'target' => 90,
            'target_text' => '90 persen',
        ]);
        $renstra->forceFill(['is_active_version' => true])->save();

        $basePayload = [
            'opd_id' => $opd->id,
            'periode_tahun_id' => $periode->id,
            'tahun' => $periode->tahun,
            'judul' => 'PK Cascading Pengujian',
            'status' => 'draft',
            'tipe_pk' => 'cascading',
            'level_pk' => 'struktural',
            'renstra_opd_id' => $renstra->id,
            'lingkup_kinerja_snapshot' => ['sasaran_opd:'.$sasaran->id],
        ];

        $this->actingAs($adminOpd)
            ->post(route('perjanjian-kinerja.store'), [...$basePayload, 'pegawai_id' => $individual->id])
            ->assertRedirect()
            ->assertSessionDoesntHaveErrors();
        $this->actingAs($adminOpd)
            ->post(route('perjanjian-kinerja.store'), [...$basePayload, 'pegawai_id' => $assigned->id])
            ->assertRedirect()
            ->assertSessionDoesntHaveErrors();

        $cascadingPk = PerjanjianKinerja::query()->where('pegawai_id', $assigned->id)->firstOrFail();
        $this->actingAs($adminOpd)
            ->put(route('perjanjian-kinerja.update', $cascadingPk), [
                ...$basePayload,
                'pegawai_id' => $assigned->id,
                'lingkup_kinerja_snapshot' => [],
                'nomor_dokumen' => 'PK/EDIT/001',
            ])
            ->assertRedirect()
            ->assertSessionDoesntHaveErrors();
        $this->assertSame(
            ['sasaran_opd:'.$sasaran->id],
            $cascadingPk->refresh()->lingkup_kinerja_snapshot,
        );

        $this->actingAs($adminOpd)
            ->post(route('perjanjian-kinerja.store'), [
                ...$basePayload,
                'pegawai_id' => $individual->id,
                'tipe_pk' => 'individual',
                'level_pk' => 'individu',
                'renstra_opd_id' => null,
                'judul' => 'PK Individu Pengujian',
                'status' => 'approved',
            ])
            ->assertRedirect();

        $individualPk = PerjanjianKinerja::query()->where('tipe_pk', 'individual')->firstOrFail();
        PerjanjianKinerjaItem::create([
            'perjanjian_kinerja_id' => $individualPk->id,
            'sumber_item' => 'manual',
            'sasaran' => 'Hasil kerja individu',
            'indikator' => 'Dokumen selesai',
            'target_text' => '12 dokumen',
            'urutan' => 1,
        ]);

        $this->actingAs($adminOpd)
            ->post(route('rencana-aksi.store'), [
                'opd_id' => $opd->id,
                'perjanjian_kinerja_id' => $individualPk->id,
                'periode_tahun_id' => $periode->id,
                'tahun' => $periode->tahun,
                'judul' => 'Rencana Aksi yang Ditolak',
                'status' => 'draft',
            ])
            ->assertSessionHasErrors('perjanjian_kinerja_id');
    }

    private function employee(Opd $opd, string $name, string $nip): Pegawai
    {
        return Pegawai::create([
            'opd_id' => $opd->id,
            'nama' => $name,
            'nip' => $nip,
            'jenis_pegawai' => 'pns',
            'status' => 'active',
        ]);
    }

    private function userWithRole(string $roleName, Opd $opd): User
    {
        $user = User::factory()->create(['opd_id' => $opd->id]);
        $user->roles()->sync([Role::query()->where('name', $roleName)->value('id')]);

        return $user;
    }
}
