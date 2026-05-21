<?php

namespace Tests\Feature;

use App\Models\EvaluasiSakip;
use App\Models\Opd;
use App\Models\PeriodeTahun;
use App\Models\PerjanjianKinerja;
use App\Models\RealisasiKinerja;
use App\Models\RealisasiProgram;
use App\Models\RekomendasiEvaluasi;
use App\Models\RencanaAksi;
use App\Models\RenstraOpd;
use App\Models\Role;
use App\Models\Rpjmd;
use App\Models\User;
use App\Models\WorkflowSubmission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page()
    {
        $response = $this->get('/dashboard');
        $response->assertRedirect('/login');
    }

    public function test_authenticated_users_can_visit_the_dashboard()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->get('/dashboard');
        $response->assertStatus(200);
    }

    public function test_kabupaten_dashboard_shows_monitoring_summary(): void
    {
        $this->seed();

        $scenario = $this->dashboardScenario();
        $monitor = User::factory()->create();
        $monitor->roles()->sync([Role::where('name', 'admin_kabupaten_bagian_organisasi')->value('id')]);

        $this->actingAs($monitor)
            ->get(route('dashboard', ['tahun' => $scenario['periode']->tahun]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                ->where('dashboard.type', 'kabupaten')
                ->where('dashboard.can_filter_opd', true)
                ->where('stats.opd_count', 2)
                ->where('stats.rpjmd_linked_opd_count', 1)
                ->where('stats.renstra_opd_count', 1)
                ->where('stats.perjanjian_kinerja_opd_count', 1)
                ->where('stats.rencana_aksi_opd_count', 1)
                ->where('stats.realisasi_opd_count', 1)
                ->where('stats.evaluasi_opd_count', 1)
                ->where('stats.rekomendasi_terbuka_count', 1)
                ->where('stats.rekomendasi_overdue_count', 1)
                ->where('stats.opd_belum_realisasi_count', 1)
                ->where('stats.indikator_kuning_count', 1)
                ->has('progressOpd', 2)
                ->has('achievementByYear', 1)
                ->has('achievementStatusDistribution', 3)
                ->has('efficiencyStatusDistribution', 3)
                ->has('quarterlyAchievement', 4)
                ->has('opdsWithoutRealization', 1)
                ->has('overdueRecommendations', 1)
                ->has('workflowStatus', 1)
                ->has('evaluationRanking', 1)
                ->has('openRecommendations', 1)
            );
    }

    public function test_admin_opd_dashboard_is_limited_to_own_opd(): void
    {
        $this->seed();

        $scenario = $this->dashboardScenario();
        $adminOpd = $scenario['admin_opd'];

        $this->actingAs($adminOpd)
            ->get(route('dashboard', [
                'tahun' => $scenario['periode']->tahun,
                'opd_id' => $scenario['other_opd']->id,
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                ->where('dashboard.type', 'opd')
                ->where('dashboard.can_filter_opd', false)
                ->where('stats.opd_count', 1)
                ->where('stats.renstra_opd_count', 1)
                ->where('stats.perjanjian_kinerja_opd_count', 1)
                ->where('progressOpd.0.opd_id', $scenario['opd']->id)
            );
    }

    public function test_pimpinan_gets_executive_dashboard(): void
    {
        $this->seed();

        $scenario = $this->dashboardScenario();
        $pimpinan = User::factory()->create();
        $pimpinan->roles()->sync([Role::where('name', 'pimpinan')->value('id')]);

        $this->actingAs($pimpinan)
            ->get(route('dashboard', ['tahun' => $scenario['periode']->tahun]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                ->where('dashboard.type', 'pimpinan')
                ->where('stats.opd_count', 2)
            );
    }

    public function test_inspektorat_gets_evaluation_dashboard(): void
    {
        $this->seed();

        $scenario = $this->dashboardScenario();
        $inspektorat = User::factory()->create();
        $inspektorat->roles()->sync([Role::where('name', 'admin_kabupaten_inspektorat')->value('id')]);

        $this->actingAs($inspektorat)
            ->get(route('dashboard', ['tahun' => $scenario['periode']->tahun]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                ->where('dashboard.type', 'evaluasi')
                ->where('stats.evaluasi_opd_count', 1)
                ->where('stats.rekomendasi_terbuka_count', 1)
            );
    }

    /**
     * @return array<string, mixed>
     */
    private function dashboardScenario(): array
    {
        $periode = PeriodeTahun::where('status', 'active')->firstOrFail();
        $opd = Opd::create(['kode' => '1.01', 'nama' => 'Dinas Kesehatan', 'singkatan' => 'Dinkes', 'status' => 'active']);
        $otherOpd = Opd::create(['kode' => '1.02', 'nama' => 'Dinas Pendidikan', 'singkatan' => 'Dindik', 'status' => 'active']);

        $rpjmd = Rpjmd::create([
            'periode_tahun_id' => $periode->id,
            'judul' => 'RPJMD Kabupaten',
            'tahun_awal' => $periode->tahun,
            'tahun_akhir' => $periode->tahun + 4,
            'status' => 'approved',
        ]);

        $programRpjmdId = DB::table('program_rpjmd')->insertGetId([
            'nama' => 'Program Peningkatan Layanan Dasar',
            'status' => 'approved',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('program_rpjmd_opd_penanggung_jawab')->insert([
            'program_rpjmd_id' => $programRpjmdId,
            'opd_id' => $opd->id,
            'peran' => 'penanggung_jawab',
            'is_utama' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $renstra = RenstraOpd::create([
            'opd_id' => $opd->id,
            'rpjmd_id' => $rpjmd->id,
            'periode_tahun_id' => $periode->id,
            'judul' => 'Renstra Dinkes',
            'tahun_awal' => $periode->tahun,
            'tahun_akhir' => $periode->tahun + 4,
            'status' => 'approved',
        ]);

        $pk = PerjanjianKinerja::create([
            'opd_id' => $opd->id,
            'renstra_opd_id' => $renstra->id,
            'periode_tahun_id' => $periode->id,
            'tahun' => $periode->tahun,
            'judul' => 'PK Dinkes',
            'status' => 'submitted',
        ]);

        $rencanaAksi = RencanaAksi::create([
            'opd_id' => $opd->id,
            'perjanjian_kinerja_id' => $pk->id,
            'periode_tahun_id' => $periode->id,
            'tahun' => $periode->tahun,
            'judul' => 'Rencana Aksi Dinkes',
            'status' => 'verified',
        ]);

        $realisasi = RealisasiKinerja::create([
            'opd_id' => $opd->id,
            'perjanjian_kinerja_id' => $pk->id,
            'rencana_aksi_id' => $rencanaAksi->id,
            'periode_tahun_id' => $periode->id,
            'tahun' => $periode->tahun,
            'periode_realisasi' => 'triwulan',
            'triwulan' => 'tw1',
            'status' => 'verified',
        ]);

        RealisasiProgram::create([
            'realisasi_kinerja_id' => $realisasi->id,
            'indikator' => 'Cakupan layanan dasar',
            'target' => 100,
            'realisasi' => 80,
            'capaian_persen' => 80,
            'status_capaian' => 'kuning',
            'anggaran' => 100000000,
            'realisasi_anggaran' => 70000000,
            'serapan_anggaran_persen' => 70,
            'status_efisiensi' => 'efisien',
            'urutan' => 1,
        ]);

        $evaluasi = EvaluasiSakip::create([
            'opd_id' => $opd->id,
            'periode_tahun_id' => $periode->id,
            'tahun' => $periode->tahun,
            'status' => 'approved',
            'nilai_akhir' => 82,
            'predikat' => 'A',
        ]);

        RekomendasiEvaluasi::create([
            'evaluasi_sakip_id' => $evaluasi->id,
            'opd_id' => $opd->id,
            'rekomendasi' => 'Lengkapi bukti dukung capaian indikator.',
            'prioritas' => 'tinggi',
            'status_tindak_lanjut' => 'belum',
            'target_tanggal' => now()->subDay()->toDateString(),
        ]);

        $adminOpd = User::factory()->create(['opd_id' => $opd->id]);
        $adminOpd->roles()->sync([Role::where('name', 'admin_opd')->value('id')]);

        WorkflowSubmission::create([
            'related_table' => $pk->getTable(),
            'related_id' => $pk->id,
            'module' => 'perjanjian_kinerja',
            'status' => 'submitted',
            'submitted_by' => $adminOpd->id,
            'submitted_at' => now(),
        ]);

        return [
            'periode' => $periode,
            'opd' => $opd,
            'other_opd' => $otherOpd,
            'admin_opd' => $adminOpd,
        ];
    }
}
