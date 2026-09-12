<?php

namespace Tests\Feature;

use App\Models\IndikatorOpdKegiatan;
use App\Models\IndikatorOpdProgram;
use App\Models\IndikatorSasaranOpd;
use App\Models\IndikatorTujuanOpd;
use App\Models\Opd;
use App\Models\OpdKegiatan;
use App\Models\OpdProgram;
use App\Models\PeriodeTahun;
use App\Models\RenjaOpd;
use App\Models\RenstraOpd;
use App\Models\Role;
use App\Models\Rpjmd;
use App\Models\SasaranOpd;
use App\Models\TargetIndikatorOpdKegiatan;
use App\Models\TargetIndikatorOpdProgram;
use App\Models\TargetIndikatorSasaranOpd;
use App\Models\TargetIndikatorTujuanOpd;
use App\Models\TujuanOpd;
use App\Models\User;
use App\Services\Perencanaan\RenjaAnnualTargetReadinessService;
use App\Services\Perencanaan\RenjaAnnualTargetService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class RenjaAnnualTargetTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['features.renja_annual_targets' => true]);
    }

    public function test_target_tahunan_disalin_dari_semua_level_renstra_dan_dapat_disesuaikan(): void
    {
        [$renja, $indicators] = $this->renjaWithAnnualIndicators();
        $service = app(RenjaAnnualTargetService::class);

        $result = $service->bootstrap($renja);

        $this->assertSame(4, $result['created']);
        $this->assertSame(
            ['tujuan_opd', 'sasaran_opd', 'program_opd', 'kegiatan_opd'],
            $renja->annualTargets()->pluck('level')->all(),
        );
        $this->assertSame([
            'total' => 4,
            'complete' => 4,
            'missing' => 0,
            'adjusted' => 0,
            'following' => 4,
        ], $service->summary($renja));

        $programTarget = $renja->annualTargets()
            ->where('indicator_type', $indicators['program']->getTable())
            ->where('indicator_id', $indicators['program']->id)
            ->firstOrFail();

        try {
            $service->updateTargets($renja, [[
                'id' => $programTarget->id,
                'target_text' => '75',
                'alasan_penyesuaian' => '',
            ]]);
            $this->fail('Perubahan target tanpa alasan seharusnya ditolak.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('targets.0.alasan_penyesuaian', $exception->errors());
        }

        $service->updateTargets($renja, [[
            'id' => $programTarget->id,
            'target_text' => '75',
            'alasan_penyesuaian' => 'Penajaman target pada tahun berjalan.',
        ]]);

        $this->assertDatabaseHas('renja_opd_annual_targets', [
            'id' => $programTarget->id,
            'target_renja' => 75,
            'target_renja_text' => '75',
            'is_adjusted' => true,
            'alasan_penyesuaian' => 'Penajaman target pada tahun berjalan.',
        ]);
        $this->assertSame(1, $service->summary($renja)['adjusted']);
        app(RenjaAnnualTargetReadinessService::class)->ensureReady($renja);
    }

    public function test_renja_tidak_dapat_diajukan_saat_target_tahunan_masih_kosong(): void
    {
        [$renja] = $this->renjaWithAnnualIndicators(false);

        try {
            app(RenjaAnnualTargetReadinessService::class)->ensureReady($renja);
            $this->fail('RENJA dengan target tahunan kosong seharusnya belum siap diajukan.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('action', $exception->errors());
        }

        $this->assertSame(4, $renja->annualTargets()->count());
        $this->assertSame(4, app(RenjaAnnualTargetService::class)->summary($renja)['missing']);
    }

    public function test_pengelola_renja_dapat_membuka_dan_menyimpan_matriks_target_tahunan(): void
    {
        $this->seed();
        [$renja, $indicators] = $this->renjaWithAnnualIndicators();
        $user = User::factory()->create(['opd_id' => $renja->opd_id]);
        $user->roles()->sync([Role::query()->where('name', 'super_admin')->value('id')]);

        $this->actingAs($user)
            ->get(route('renja-opd.annual-targets.index', $renja))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('RenjaOpd/AnnualTargets')
                ->where('summary.total', 4)
                ->where('summary.complete', 4)
                ->where('levelCounts.program_opd', 1)
                ->has('items.data', 4)
                ->where('can.manage', true));

        $target = $renja->annualTargets()
            ->where('indicator_type', $indicators['kegiatan']->getTable())
            ->where('indicator_id', $indicators['kegiatan']->id)
            ->firstOrFail();

        $this->actingAs($user)
            ->put(route('renja-opd.annual-targets.update', $renja), [
                'targets' => [[
                    'id' => $target->id,
                    'target_text' => '96',
                    'alasan_penyesuaian' => 'Penyesuaian target kegiatan tahunan.',
                ]],
            ])
            ->assertRedirect()
            ->assertSessionDoesntHaveErrors();

        $this->assertDatabaseHas('renja_opd_annual_targets', [
            'id' => $target->id,
            'target_renja' => 96,
            'is_adjusted' => true,
        ]);
    }

    /** @return array{RenjaOpd, array<string, mixed>} */
    private function renjaWithAnnualIndicators(bool $withTargets = true): array
    {
        $periode = PeriodeTahun::create(['tahun' => 2093, 'nama' => 'Tahun 2093', 'status' => 'active']);
        $opd = Opd::create(['kode' => '9.93', 'nama' => 'Dinas Uji Target Tahunan', 'status' => 'active']);
        $rpjmd = Rpjmd::create([
            'periode_tahun_id' => $periode->id,
            'judul' => 'RPJMD Target Tahunan',
            'tahun_awal' => 2090,
            'tahun_akhir' => 2094,
            'status' => 'approved',
        ]);
        $renstra = RenstraOpd::create([
            'opd_id' => $opd->id,
            'rpjmd_id' => $rpjmd->id,
            'periode_tahun_id' => $periode->id,
            'judul' => 'RENSTRA Target Tahunan',
            'tahun_awal' => 2090,
            'tahun_akhir' => 2094,
            'status' => 'approved',
            'is_active_version' => true,
        ]);
        $tujuan = TujuanOpd::create(['renstra_opd_id' => $renstra->id, 'kode' => 'T.1', 'tujuan' => 'Pelayanan meningkat', 'urutan' => 1]);
        $indikatorTujuan = IndikatorTujuanOpd::create(['tujuan_opd_id' => $tujuan->id, 'indikator' => 'Indeks pelayanan', 'urutan' => 1]);
        $sasaran = SasaranOpd::create(['tujuan_opd_id' => $tujuan->id, 'kode' => 'S.1', 'sasaran' => 'Layanan berkualitas', 'urutan' => 1]);
        $indikatorSasaran = IndikatorSasaranOpd::create(['sasaran_opd_id' => $sasaran->id, 'indikator' => 'Nilai layanan', 'urutan' => 1]);
        $program = OpdProgram::create([
            'renstra_opd_id' => $renstra->id,
            'sasaran_opd_id' => $sasaran->id,
            'kode' => 'P.1',
            'nama' => 'PROGRAM PELAYANAN',
            'sasaran_program' => 'Program terlaksana',
            'urutan' => 1,
        ]);
        $indikatorProgram = IndikatorOpdProgram::create(['opd_program_id' => $program->id, 'indikator' => 'Persentase program', 'urutan' => 1]);
        $kegiatan = OpdKegiatan::create([
            'opd_program_id' => $program->id,
            'kode' => 'K.1',
            'nama' => 'KEGIATAN PELAYANAN',
            'sasaran_kegiatan' => 'Kegiatan terlaksana',
            'urutan' => 1,
        ]);
        $indikatorKegiatan = IndikatorOpdKegiatan::create(['opd_kegiatan_id' => $kegiatan->id, 'indikator' => 'Persentase kegiatan', 'urutan' => 1]);

        if ($withTargets) {
            TargetIndikatorTujuanOpd::create(['indikator_tujuan_opd_id' => $indikatorTujuan->id, 'periode_tahun_id' => $periode->id, 'target' => 80]);
            TargetIndikatorSasaranOpd::create(['indikator_sasaran_opd_id' => $indikatorSasaran->id, 'periode_tahun_id' => $periode->id, 'target' => 85]);
            TargetIndikatorOpdProgram::create(['indikator_opd_program_id' => $indikatorProgram->id, 'periode_tahun_id' => $periode->id, 'target' => 70]);
            TargetIndikatorOpdKegiatan::create(['indikator_opd_kegiatan_id' => $indikatorKegiatan->id, 'periode_tahun_id' => $periode->id, 'target' => 90]);
        }

        $renja = RenjaOpd::create([
            'renstra_opd_id' => $renstra->id,
            'opd_id' => $opd->id,
            'periode_tahun_id' => $periode->id,
            'tahun' => 2093,
            'judul' => 'RENJA Target Tahunan 2093',
            'status' => 'draft',
        ]);

        return [$renja, [
            'tujuan' => $indikatorTujuan,
            'sasaran' => $indikatorSasaran,
            'program' => $indikatorProgram,
            'kegiatan' => $indikatorKegiatan,
        ]];
    }
}
