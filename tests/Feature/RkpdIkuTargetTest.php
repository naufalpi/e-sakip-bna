<?php

namespace Tests\Feature;

use App\Models\IndikatorSasaranDaerah;
use App\Models\IndikatorTujuanDaerah;
use App\Models\PeriodeTahun;
use App\Models\Rkpd;
use App\Models\RkpdIkuTarget;
use App\Models\Role;
use App\Models\Rpjmd;
use App\Models\RpjmdVisi;
use App\Models\SasaranDaerah;
use App\Models\TargetIndikatorSasaranDaerah;
use App\Models\TargetIndikatorTujuanDaerah;
use App\Models\TujuanDaerah;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class RkpdIkuTargetTest extends TestCase
{
    use RefreshDatabase;

    public function test_bapperida_can_fill_rkpd_iku_target_from_rpjmd_indicators(): void
    {
        $this->seed();

        $periode = PeriodeTahun::query()->updateOrCreate(
            ['tahun' => 2026],
            ['nama' => 'Tahun 2026', 'status' => 'active'],
        );

        $rpjmd = Rpjmd::create([
            'judul' => 'RPJMD IKU RKPD',
            'tahun_awal' => 2025,
            'tahun_akhir' => 2029,
            'status' => 'approved',
        ]);
        $visi = RpjmdVisi::create(['rpjmd_id' => $rpjmd->id, 'visi' => 'Visi IKU RKPD', 'urutan' => 1]);
        $tujuan = TujuanDaerah::create(['rpjmd_visi_id' => $visi->id, 'tujuan' => 'Tujuan IKU RKPD', 'urutan' => 1]);
        $indikatorTujuan = IndikatorTujuanDaerah::create([
            'tujuan_daerah_id' => $tujuan->id,
            'indikator' => 'Indeks Tujuan RKPD',
            'urutan' => 1,
        ]);
        TargetIndikatorTujuanDaerah::create([
            'indikator_tujuan_daerah_id' => $indikatorTujuan->id,
            'periode_tahun_id' => $periode->id,
            'target_text' => '88,50',
        ]);

        $sasaran = SasaranDaerah::create(['tujuan_daerah_id' => $tujuan->id, 'sasaran' => 'Sasaran IKU RKPD', 'urutan' => 1]);
        $indikatorSasaran = IndikatorSasaranDaerah::create([
            'sasaran_daerah_id' => $sasaran->id,
            'indikator' => 'Indeks Sasaran RKPD',
            'urutan' => 1,
        ]);
        TargetIndikatorSasaranDaerah::create([
            'indikator_sasaran_daerah_id' => $indikatorSasaran->id,
            'periode_tahun_id' => $periode->id,
            'target_text' => '91',
        ]);

        $rkpd = Rkpd::create([
            'rpjmd_id' => $rpjmd->id,
            'periode_tahun_id' => $periode->id,
            'tahun' => 2026,
            'judul' => 'RKPD IKU Kabupaten',
            'status' => 'draft',
        ]);

        $user = User::factory()->create();
        $user->roles()->sync([Role::where('name', 'admin_kabupaten_bapperida')->value('id')]);

        $this->actingAs($user)
            ->get(route('rkpd.show', $rkpd))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Rkpd/Show')
                ->has('ikuRows', 2)
                ->where('ikuRows.0.iku', 'Indeks Tujuan RKPD')
                ->where('ikuRows.0.target_rpjmd', '88,50')
                ->where('ikuRows.1.iku', 'Indeks Sasaran RKPD')
                ->where('ikuRows.1.target_rpjmd', '91')
            );

        $this->actingAs($user)
            ->post(route('rkpd.iku-targets.store', $rkpd), [
                'indikator_type' => 'indikator_tujuan_daerah',
                'indikator_id' => $indikatorTujuan->id,
                'target_rkpd' => '89,00',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('rkpd_iku_targets', [
            'rkpd_id' => $rkpd->id,
            'indikator_type' => 'indikator_tujuan_daerah',
            'indikator_id' => $indikatorTujuan->id,
            'target_rkpd' => '89,00',
        ]);

        $this->actingAs($user)
            ->get(route('rkpd.show', $rkpd))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('ikuRows.0.target_rkpd', '89,00')
            );

        $savedTarget = RkpdIkuTarget::query()->firstOrFail();

        $this->actingAs($user)
            ->delete(route('rkpd.iku-targets.destroy', [$rkpd, $savedTarget]))
            ->assertRedirect();

        $this->actingAs($user)
            ->post(route('rkpd.iku-targets.store', $rkpd), [
                'indikator_type' => 'indikator_tujuan_daerah',
                'indikator_id' => $indikatorTujuan->id,
                'target_rkpd' => '90,00',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('rkpd_iku_targets', [
            'id' => $savedTarget->id,
            'target_rkpd' => '90,00',
            'deleted_at' => null,
        ]);
    }
}
