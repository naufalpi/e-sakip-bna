<?php

namespace Tests\Feature;

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
use App\Models\TujuanOpd;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PegawaiPenempatanTest extends TestCase
{
    use RefreshDatabase;

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
                'tanggal_mulai' => '2026-01-01',
                'nomor_sk' => '800/01/2026',
                'tanggal_sk' => '2025-12-30',
            ])
            ->assertRedirect()
            ->assertSessionDoesntHaveErrors();

        $pegawai = Pegawai::query()->where('nama', 'Pegawai Satu Langkah')->firstOrFail();
        $this->assertDatabaseHas('riwayat_pejabat_jabatan', [
            'pegawai_id' => $pegawai->id,
            'jabatan_organisasi_id' => $jabatan->id,
            'tanggal_mulai' => '2026-01-01 00:00:00',
            'nomor_sk' => '800/01/2026',
        ]);
    }

    public function test_admin_opd_manages_own_employees_and_multiple_holder_positions(): void
    {
        $this->seed();
        $opd = Opd::query()->where('status', 'active')->firstOrFail();
        $otherOpd = Opd::query()->where('status', 'active')->whereKeyNot($opd->id)->firstOrFail();
        $adminOpd = $this->userWithRole('admin_opd', $opd);

        $this->actingAs($adminOpd)
            ->post(route('master.pegawai.store'), [
                'opd_id' => $otherOpd->id,
                'nama' => 'Pegawai Pertama',
                'nip' => '199001012020011001',
                'jenis_pegawai' => 'pns',
                'status' => 'active',
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
        $functional = JabatanOrganisasi::create([
            'opd_id' => $opd->id,
            'nama' => 'Analis Kebijakan Ahli Pertama',
            'level_jabatan' => 'fungsional',
            'urutan' => 1,
            'status' => 'active',
        ]);

        foreach ([$first, $second] as $pegawai) {
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
            ->assertSessionHasErrors('tanggal_mulai');

        $this->assertSame(1, $structural->riwayatPejabat()->count());
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

    public function test_annual_assignment_controls_cascading_pk_and_individual_pk_is_excluded_from_action_plan(): void
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

        $assignmentPayload = [
            'periode_tahun_id' => $periode->id,
            'sumber_kinerja_type' => 'sasaran',
            'sumber_kinerja_id' => $sasaran->id,
            'peran' => 'penanggung_jawab',
        ];
        $this->actingAs($adminOpd)
            ->post(route('master.pegawai.pengampu-kinerja.store', $assigned), $assignmentPayload)
            ->assertRedirect()
            ->assertSessionDoesntHaveErrors();
        $this->actingAs($adminOpd)
            ->post(route('master.pegawai.pengampu-kinerja.store', $assigned), $assignmentPayload)
            ->assertSessionHasErrors('sumber_kinerja_id');

        $basePayload = [
            'opd_id' => $opd->id,
            'periode_tahun_id' => $periode->id,
            'tahun' => $periode->tahun,
            'judul' => 'PK Cascading Pengujian',
            'status' => 'draft',
            'tipe_pk' => 'cascading',
            'renstra_opd_id' => $renstra->id,
        ];

        $this->actingAs($adminOpd)
            ->post(route('perjanjian-kinerja.store'), [...$basePayload, 'pegawai_id' => $individual->id])
            ->assertSessionHasErrors('pegawai_id');
        $this->actingAs($adminOpd)
            ->post(route('perjanjian-kinerja.store'), [...$basePayload, 'pegawai_id' => $assigned->id])
            ->assertRedirect()
            ->assertSessionDoesntHaveErrors();

        $this->actingAs($adminOpd)
            ->post(route('perjanjian-kinerja.store'), [
                ...$basePayload,
                'pegawai_id' => $individual->id,
                'tipe_pk' => 'individual',
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
