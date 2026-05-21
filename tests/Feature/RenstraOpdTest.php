<?php

namespace Tests\Feature;

use App\Models\IndikatorOpdProgram;
use App\Models\IndikatorProgramRpjmd;
use App\Models\IndikatorSasaranDaerah;
use App\Models\IndikatorTujuanDaerah;
use App\Models\Opd;
use App\Models\OpdProgram;
use App\Models\PeriodeTahun;
use App\Models\ProgramRpjmd;
use App\Models\RenstraOpd;
use App\Models\Role;
use App\Models\Rpjmd;
use App\Models\RpjmdMisi;
use App\Models\RpjmdVisi;
use App\Models\SasaranDaerah;
use App\Models\SatuanIndikator;
use App\Models\StrategiDaerah;
use App\Models\TargetIndikatorOpdProgram;
use App\Models\TujuanDaerah;
use App\Models\TujuanOpd;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class RenstraOpdTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_opd_can_crud_only_own_renstra(): void
    {
        $this->seed();

        $opd = Opd::create(['kode' => '1.01', 'nama' => 'Dinas Relevan', 'status' => 'active']);
        $otherOpd = Opd::create(['kode' => '1.02', 'nama' => 'Dinas Lain', 'status' => 'active']);
        $rpjmd = Rpjmd::create(['judul' => 'RPJMD Kabupaten', 'tahun_awal' => 2026, 'tahun_akhir' => 2031, 'status' => 'approved']);

        $user = User::factory()->create(['opd_id' => $opd->id]);
        $user->roles()->sync([Role::where('name', 'admin_opd')->value('id')]);

        $this->actingAs($user)
            ->post(route('renstra-opd.store'), [
                'opd_id' => $opd->id,
                'rpjmd_id' => $rpjmd->id,
                'judul' => 'Renstra Dinas Relevan',
                'tahun_awal' => 2026,
                'tahun_akhir' => 2031,
                'status' => 'draft',
            ])
            ->assertRedirect();

        $ownRenstra = RenstraOpd::where('opd_id', $opd->id)->firstOrFail();

        $this->actingAs($user)
            ->post(route('renstra-opd.store'), [
                'opd_id' => $otherOpd->id,
                'rpjmd_id' => $rpjmd->id,
                'judul' => 'Renstra OPD Lain',
                'tahun_awal' => 2026,
                'tahun_akhir' => 2031,
                'status' => 'draft',
            ])
            ->assertForbidden();

        $otherRenstra = RenstraOpd::create([
            'opd_id' => $otherOpd->id,
            'rpjmd_id' => $rpjmd->id,
            'judul' => 'Renstra OPD Lain',
            'tahun_awal' => 2026,
            'tahun_akhir' => 2031,
            'status' => 'draft',
        ]);

        $this->actingAs($user)
            ->get(route('renstra-opd.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('RenstraOpd/Index')
                ->has('renstras.data', 1)
                ->where('renstras.data.0.id', $ownRenstra->id)
            );

        $this->actingAs($user)
            ->get(route('renstra-opd.show', $ownRenstra))
            ->assertOk();

        $this->actingAs($user)
            ->get(route('renstra-opd.show', $otherRenstra))
            ->assertForbidden();
    }

    public function test_cascading_opd_can_link_to_rpjmd_and_save_yearly_targets(): void
    {
        $this->seed();

        $opd = Opd::create(['kode' => '2.01', 'nama' => 'Dinas Perencanaan', 'status' => 'active']);
        $tree = $this->createRpjmdTree();
        $periode = PeriodeTahun::orderBy('tahun')->firstOrFail();
        $satuan = SatuanIndikator::firstOrFail();
        $renstra = RenstraOpd::create([
            'opd_id' => $opd->id,
            'rpjmd_id' => $tree['rpjmd']->id,
            'periode_tahun_id' => $periode->id,
            'judul' => 'Renstra Dinas Perencanaan',
            'tahun_awal' => 2026,
            'tahun_akhir' => 2031,
            'status' => 'draft',
        ]);

        $user = User::factory()->create(['opd_id' => $opd->id]);
        $user->roles()->sync([Role::where('name', 'admin_opd')->value('id')]);

        $this->actingAs($user)
            ->post(route('renstra-opd.nodes.store', $renstra), [
                'type' => 'tujuan',
                'tujuan_daerah_id' => $tree['tujuan_daerah']->id,
                'kode' => 'T1',
                'uraian' => 'Tujuan OPD Terhubung',
                'urutan' => 1,
            ])
            ->assertRedirect();

        $tujuanOpd = TujuanOpd::firstOrFail();

        $this->actingAs($user)
            ->post(route('renstra-opd.nodes.store', $renstra), [
                'type' => 'indikator_tujuan',
                'parent_id' => $tujuanOpd->id,
                'indikator_tujuan_daerah_id' => $tree['indikator_tujuan']->id,
                'satuan_indikator_id' => $satuan->id,
                'indikator' => 'Indikator Tujuan OPD',
            ])
            ->assertRedirect();

        $this->actingAs($user)
            ->post(route('renstra-opd.nodes.store', $renstra), [
                'type' => 'target_tujuan',
                'parent_id' => $tujuanOpd->indikator()->firstOrFail()->id,
                'periode_tahun_id' => $periode->id,
                'target' => 90,
                'target_text' => '90 persen',
            ])
            ->assertRedirect();

        $this->actingAs($user)
            ->post(route('renstra-opd.nodes.store', $renstra), [
                'type' => 'sasaran',
                'parent_id' => $tujuanOpd->id,
                'sasaran_daerah_id' => $tree['sasaran_daerah']->id,
                'kode' => 'S1',
                'uraian' => 'Sasaran OPD Terhubung',
            ])
            ->assertRedirect();

        $sasaranOpd = $tujuanOpd->sasaran()->firstOrFail();

        $this->actingAs($user)
            ->post(route('renstra-opd.nodes.store', $renstra), [
                'type' => 'program',
                'parent_id' => $sasaranOpd->id,
                'program_rpjmd_id' => $tree['program_rpjmd']->id,
                'kode' => 'P1',
                'uraian' => 'Program OPD Terhubung',
            ])
            ->assertRedirect();

        $programOpd = OpdProgram::firstOrFail();

        $this->actingAs($user)
            ->post(route('renstra-opd.nodes.store', $renstra), [
                'type' => 'indikator_program',
                'parent_id' => $programOpd->id,
                'indikator_program_rpjmd_id' => $tree['indikator_program']->id,
                'satuan_indikator_id' => $satuan->id,
                'indikator' => 'Indikator Program OPD',
            ])
            ->assertRedirect();

        $indikatorProgram = IndikatorOpdProgram::firstOrFail();

        $this->actingAs($user)
            ->post(route('renstra-opd.nodes.store', $renstra), [
                'type' => 'target_program',
                'parent_id' => $indikatorProgram->id,
                'periode_tahun_id' => $periode->id,
                'target' => 75,
                'target_text' => '75 persen',
                'pagu' => 1000000,
            ])
            ->assertRedirect();

        $targetProgram = TargetIndikatorOpdProgram::where('indikator_opd_program_id', $indikatorProgram->id)->firstOrFail();

        $this->actingAs($user)
            ->put(route('renstra-opd.nodes.update', [$renstra, 'target_program', $targetProgram->id]), [
                'type' => 'target_program',
                'parent_id' => $indikatorProgram->id,
                'periode_tahun_id' => $periode->id,
                'target' => 80,
                'target_text' => '80 persen',
                'pagu' => 1250000,
            ])
            ->assertRedirect();

        $this->actingAs($user)
            ->post(route('target-triwulan-indikator.store'), [
                'related_table' => 'indikator_opd_program',
                'related_id' => $indikatorProgram->id,
                'periode_tahun_id' => $periode->id,
                'triwulan' => 'tw1',
                'target_text' => '25 persen',
                'target_angka' => 25,
                'target_anggaran' => 250000,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('tujuan_opd', [
            'renstra_opd_id' => $renstra->id,
            'tujuan_daerah_id' => $tree['tujuan_daerah']->id,
        ]);

        $this->assertDatabaseHas('opd_program', [
            'renstra_opd_id' => $renstra->id,
            'program_rpjmd_id' => $tree['program_rpjmd']->id,
        ]);

        $this->assertDatabaseHas('target_indikator_opd_program', [
            'indikator_opd_program_id' => $indikatorProgram->id,
            'periode_tahun_id' => $periode->id,
            'target_text' => '80 persen',
            'pagu' => 1250000,
        ]);

        $this->assertDatabaseHas('target_triwulan_indikator', [
            'related_table' => 'indikator_opd_program',
            'related_id' => $indikatorProgram->id,
            'periode_tahun_id' => $periode->id,
            'triwulan' => 'tw1',
            'target_text' => '25 persen',
        ]);
    }

    public function test_kabupaten_monitoring_can_view_all_read_only(): void
    {
        $this->seed();

        $rpjmd = Rpjmd::create(['judul' => 'RPJMD Monitoring', 'tahun_awal' => 2026, 'tahun_akhir' => 2031, 'status' => 'approved']);
        $opdA = Opd::create(['kode' => '3.01', 'nama' => 'Dinas A', 'status' => 'active']);
        $opdB = Opd::create(['kode' => '3.02', 'nama' => 'Dinas B', 'status' => 'active']);
        RenstraOpd::create(['opd_id' => $opdA->id, 'rpjmd_id' => $rpjmd->id, 'judul' => 'Renstra A', 'tahun_awal' => 2026, 'tahun_akhir' => 2031, 'status' => 'draft']);
        RenstraOpd::create(['opd_id' => $opdB->id, 'rpjmd_id' => $rpjmd->id, 'judul' => 'Renstra B', 'tahun_awal' => 2026, 'tahun_akhir' => 2031, 'status' => 'draft']);

        $user = User::factory()->create();
        $user->roles()->sync([Role::where('name', 'admin_kabupaten_bagian_organisasi')->value('id')]);

        $this->actingAs($user)
            ->get(route('renstra-opd.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('RenstraOpd/Index')
                ->has('renstras.data', 2)
            );

        $this->actingAs($user)
            ->get(route('renstra-opd.create'))
            ->assertForbidden();
    }

    private function createRpjmdTree(): array
    {
        $rpjmd = Rpjmd::create(['judul' => 'RPJMD Link Renstra', 'tahun_awal' => 2026, 'tahun_akhir' => 2031, 'status' => 'approved']);
        $visi = RpjmdVisi::create(['rpjmd_id' => $rpjmd->id, 'visi' => 'Visi Kabupaten', 'urutan' => 1]);
        $misi = RpjmdMisi::create(['rpjmd_id' => $rpjmd->id, 'rpjmd_visi_id' => $visi->id, 'misi' => 'Misi Kabupaten', 'urutan' => 1]);
        $tujuan = TujuanDaerah::create(['rpjmd_misi_id' => $misi->id, 'tujuan' => 'Tujuan Daerah', 'urutan' => 1]);
        $indikatorTujuan = IndikatorTujuanDaerah::create(['tujuan_daerah_id' => $tujuan->id, 'indikator' => 'Indikator Tujuan Daerah', 'urutan' => 1]);
        $sasaran = SasaranDaerah::create(['tujuan_daerah_id' => $tujuan->id, 'sasaran' => 'Sasaran Daerah', 'urutan' => 1]);
        $indikatorSasaran = IndikatorSasaranDaerah::create(['sasaran_daerah_id' => $sasaran->id, 'indikator' => 'Indikator Sasaran Daerah', 'urutan' => 1]);
        $strategi = StrategiDaerah::create(['sasaran_daerah_id' => $sasaran->id, 'strategi' => 'Strategi Daerah', 'urutan' => 1]);
        $program = ProgramRpjmd::create(['strategi_daerah_id' => $strategi->id, 'sasaran_daerah_id' => $sasaran->id, 'nama' => 'Program RPJMD', 'status' => 'approved', 'urutan' => 1]);
        $indikatorProgram = IndikatorProgramRpjmd::create(['program_rpjmd_id' => $program->id, 'indikator' => 'Indikator Program RPJMD', 'urutan' => 1]);

        return [
            'rpjmd' => $rpjmd,
            'tujuan_daerah' => $tujuan,
            'indikator_tujuan' => $indikatorTujuan,
            'sasaran_daerah' => $sasaran,
            'indikator_sasaran' => $indikatorSasaran,
            'program_rpjmd' => $program,
            'indikator_program' => $indikatorProgram,
        ];
    }
}
