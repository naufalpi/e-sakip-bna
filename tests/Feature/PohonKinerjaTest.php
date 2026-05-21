<?php

namespace Tests\Feature;

use App\Models\IndikatorOpdProgram;
use App\Models\IndikatorProgramRpjmd;
use App\Models\Opd;
use App\Models\OpdKegiatan;
use App\Models\OpdProgram;
use App\Models\OpdSubKegiatan;
use App\Models\PeriodeTahun;
use App\Models\ProgramRpjmd;
use App\Models\ProgramRpjmdOpdPenanggungJawab;
use App\Models\RenstraOpd;
use App\Models\Role;
use App\Models\Rpjmd;
use App\Models\RpjmdMisi;
use App\Models\RpjmdVisi;
use App\Models\SasaranDaerah;
use App\Models\SasaranOpd;
use App\Models\StrategiDaerah;
use App\Models\TargetIndikatorOpdProgram;
use App\Models\TargetIndikatorProgramRpjmd;
use App\Models\TargetTriwulanIndikator;
use App\Models\TujuanDaerah;
use App\Models\TujuanOpd;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PohonKinerjaTest extends TestCase
{
    use RefreshDatabase;

    public function test_kabupaten_pohon_kinerja_json_contains_rpjmd_tree(): void
    {
        $this->seed();

        $opd = Opd::create(['kode' => '1.01', 'nama' => 'Dinas Kesehatan', 'singkatan' => 'Dinkes', 'status' => 'active']);
        $tree = $this->createRpjmdTree($opd);
        $user = User::factory()->create();
        $user->roles()->sync([Role::where('name', 'admin_kabupaten_bapperida')->value('id')]);

        $this->actingAs($user)
            ->getJson(route('pohon-kinerja.kabupaten', $tree['rpjmd']))
            ->assertOk()
            ->assertJsonPath('mode', 'kabupaten')
            ->assertJsonPath('tree.type', 'rpjmd')
            ->assertJsonPath('stats.target_tahunan_nodes', 1)
            ->assertJsonPath('stats.target_triwulan_nodes', 1)
            ->assertJsonFragment(['type' => 'program_rpjmd'])
            ->assertJsonFragment(['type' => 'opd_penanggung_jawab'])
            ->assertJsonFragment(['label' => 'Indikator Program RPJMD']);
    }

    public function test_pohon_kinerja_index_renders_for_monitoring_role(): void
    {
        $this->seed();

        $opd = Opd::create(['kode' => '1.02', 'nama' => 'Dinas Pendidikan', 'singkatan' => 'Dindik', 'status' => 'active']);
        $tree = $this->createRpjmdTree($opd);
        $user = User::factory()->create();
        $user->roles()->sync([Role::where('name', 'admin_kabupaten_bagian_organisasi')->value('id')]);

        $this->actingAs($user)
            ->get(route('pohon-kinerja.index', ['mode' => 'kabupaten', 'rpjmd_id' => $tree['rpjmd']->id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Perencanaan/PohonKinerja')
                ->where('mode', 'kabupaten')
                ->where('tree.type', 'rpjmd')
            );
    }

    public function test_user_without_planning_permission_cannot_open_pohon_kinerja_index(): void
    {
        $this->seed();

        $user = User::factory()->create();
        $user->roles()->sync([Role::where('name', 'admin_kabupaten_dinkominfo')->value('id')]);

        $this->actingAs($user)
            ->get(route('pohon-kinerja.index'))
            ->assertForbidden();
    }

    public function test_admin_opd_can_view_own_cascading_tree_and_cannot_view_other_opd(): void
    {
        $this->seed();

        $opd = Opd::create(['kode' => '2.01', 'nama' => 'Dinas Perencanaan', 'singkatan' => 'Dapper', 'status' => 'active']);
        $otherOpd = Opd::create(['kode' => '2.02', 'nama' => 'Dinas Lain', 'status' => 'active']);
        $rpjmdTree = $this->createRpjmdTree($opd);
        $renstra = $this->createRenstraTree($opd, $rpjmdTree);
        $adminOpd = User::factory()->create(['opd_id' => $opd->id]);
        $adminOpd->roles()->sync([Role::where('name', 'admin_opd')->value('id')]);
        $otherAdmin = User::factory()->create(['opd_id' => $otherOpd->id]);
        $otherAdmin->roles()->sync([Role::where('name', 'admin_opd')->value('id')]);

        $this->actingAs($adminOpd)
            ->getJson(route('pohon-kinerja.cascading-opd', $renstra))
            ->assertOk()
            ->assertJsonPath('mode', 'cascading')
            ->assertJsonPath('tree.type', 'cascading_opd_rpjmd')
            ->assertJsonFragment(['type' => 'renstra_opd'])
            ->assertJsonFragment(['type' => 'indikator_opd_program'])
            ->assertJsonFragment(['type' => 'indikator_program_rpjmd']);

        $this->actingAs($otherAdmin)
            ->getJson(route('pohon-kinerja.opd', $renstra))
            ->assertForbidden();
    }

    /**
     * @return array<string, mixed>
     */
    private function createRpjmdTree(Opd $opd): array
    {
        $periode = PeriodeTahun::orderBy('tahun')->firstOrFail();
        $rpjmd = Rpjmd::create(['periode_tahun_id' => $periode->id, 'judul' => 'RPJMD Pohon Kinerja', 'tahun_awal' => 2026, 'tahun_akhir' => 2031, 'status' => 'approved']);
        $visi = RpjmdVisi::create(['rpjmd_id' => $rpjmd->id, 'visi' => 'Visi Kabupaten', 'urutan' => 1]);
        $misi = RpjmdMisi::create(['rpjmd_id' => $rpjmd->id, 'rpjmd_visi_id' => $visi->id, 'misi' => 'Misi Kabupaten', 'urutan' => 1]);
        $tujuan = TujuanDaerah::create(['rpjmd_misi_id' => $misi->id, 'tujuan' => 'Tujuan Daerah', 'urutan' => 1]);
        $sasaran = SasaranDaerah::create(['tujuan_daerah_id' => $tujuan->id, 'sasaran' => 'Sasaran Daerah', 'urutan' => 1]);
        $strategi = StrategiDaerah::create(['sasaran_daerah_id' => $sasaran->id, 'strategi' => 'Strategi Daerah', 'urutan' => 1]);
        $program = ProgramRpjmd::create([
            'strategi_daerah_id' => $strategi->id,
            'sasaran_daerah_id' => $sasaran->id,
            'nama' => 'Program RPJMD',
            'pagu_indikatif' => 1000000,
            'status' => 'approved',
            'urutan' => 1,
        ]);
        $indikatorProgram = IndikatorProgramRpjmd::create(['program_rpjmd_id' => $program->id, 'indikator' => 'Indikator Program RPJMD', 'urutan' => 1]);
        TargetIndikatorProgramRpjmd::create(['indikator_program_rpjmd_id' => $indikatorProgram->id, 'periode_tahun_id' => $periode->id, 'target' => 90, 'target_text' => '90 persen', 'pagu' => 1000000]);
        TargetTriwulanIndikator::create(['related_table' => 'indikator_program_rpjmd', 'related_id' => $indikatorProgram->id, 'periode_tahun_id' => $periode->id, 'triwulan' => 'tw1', 'target_text' => '25 persen', 'target_angka' => 25, 'target_anggaran' => 250000]);
        ProgramRpjmdOpdPenanggungJawab::create(['program_rpjmd_id' => $program->id, 'opd_id' => $opd->id, 'peran' => 'penanggung_jawab', 'is_utama' => true]);

        return [
            'rpjmd' => $rpjmd,
            'tujuan_daerah' => $tujuan,
            'sasaran_daerah' => $sasaran,
            'program_rpjmd' => $program,
            'indikator_program' => $indikatorProgram,
        ];
    }

    /**
     * @param  array<string, mixed>  $rpjmdTree
     */
    private function createRenstraTree(Opd $opd, array $rpjmdTree): RenstraOpd
    {
        $periode = PeriodeTahun::orderBy('tahun')->firstOrFail();
        $renstra = RenstraOpd::create([
            'opd_id' => $opd->id,
            'rpjmd_id' => $rpjmdTree['rpjmd']->id,
            'periode_tahun_id' => $periode->id,
            'judul' => 'Renstra Pohon Kinerja',
            'tahun_awal' => 2026,
            'tahun_akhir' => 2031,
            'status' => 'draft',
        ]);
        $tujuan = TujuanOpd::create(['renstra_opd_id' => $renstra->id, 'tujuan_daerah_id' => $rpjmdTree['tujuan_daerah']->id, 'tujuan' => 'Tujuan OPD', 'urutan' => 1]);
        $sasaran = SasaranOpd::create(['tujuan_opd_id' => $tujuan->id, 'sasaran_daerah_id' => $rpjmdTree['sasaran_daerah']->id, 'sasaran' => 'Sasaran OPD', 'urutan' => 1]);
        $program = OpdProgram::create([
            'renstra_opd_id' => $renstra->id,
            'sasaran_opd_id' => $sasaran->id,
            'program_rpjmd_id' => $rpjmdTree['program_rpjmd']->id,
            'nama' => 'Program OPD',
            'pagu_indikatif' => 800000,
            'status' => 'draft',
            'urutan' => 1,
        ]);
        $indikator = IndikatorOpdProgram::create([
            'opd_program_id' => $program->id,
            'indikator_program_rpjmd_id' => $rpjmdTree['indikator_program']->id,
            'indikator' => 'Indikator Program OPD',
            'urutan' => 1,
        ]);
        TargetIndikatorOpdProgram::create(['indikator_opd_program_id' => $indikator->id, 'periode_tahun_id' => $periode->id, 'target' => 75, 'target_text' => '75 persen', 'pagu' => 800000]);
        $kegiatan = OpdKegiatan::create(['opd_program_id' => $program->id, 'nama' => 'Kegiatan OPD', 'pagu_indikatif' => 500000, 'urutan' => 1]);
        OpdSubKegiatan::create(['opd_kegiatan_id' => $kegiatan->id, 'nama' => 'Sub Kegiatan OPD', 'pagu_indikatif' => 500000, 'urutan' => 1]);

        return $renstra;
    }
}
