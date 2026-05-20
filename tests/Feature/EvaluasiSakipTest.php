<?php

namespace Tests\Feature;

use App\Models\EvaluasiSakip;
use App\Models\EvaluasiSakipItem;
use App\Models\KriteriaEvaluasi;
use App\Models\Opd;
use App\Models\PeriodeTahun;
use App\Models\RekomendasiEvaluasi;
use App\Models\Role;
use App\Models\TindakLanjutRekomendasi;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class EvaluasiSakipTest extends TestCase
{
    use RefreshDatabase;

    public function test_inspektorat_can_create_evaluation_score_lhe_recommendation_and_verify_followup(): void
    {
        $this->seed();

        [$opd, , $periode, $inspektorat, $adminOpd] = $this->basicActors();
        $kriteria = KriteriaEvaluasi::firstOrFail();

        $this->actingAs($inspektorat)
            ->post(route('evaluasi-sakip.store'), [
                'opd_id' => $opd->id,
                'periode_tahun_id' => $periode->id,
                'tahun' => $periode->tahun,
                'tanggal_evaluasi' => "{$periode->tahun}-05-01",
                'status' => 'draft',
                'catatan_umum' => 'Evaluasi awal.',
            ])
            ->assertRedirect();

        $evaluasi = EvaluasiSakip::firstOrFail();

        $this->actingAs($inspektorat)
            ->post(route('evaluasi-sakip.items.store', $evaluasi), [
                'kriteria_evaluasi_id' => $kriteria->id,
                'nilai' => 80,
                'catatan' => 'Cukup memadai.',
                'rekomendasi_text' => 'Perkuat bukti dukung.',
            ])
            ->assertRedirect();

        $item = EvaluasiSakipItem::firstOrFail();
        $this->assertGreaterThan(0, (float) $item->skor);
        $this->assertGreaterThan(0, (float) $evaluasi->fresh()->nilai_akhir);

        $this->actingAs($inspektorat)
            ->post(route('evaluasi-sakip.lhe.store', $evaluasi), [
                'nomor_lhe' => 'LHE/001/2026',
                'tanggal_lhe' => "{$periode->tahun}-06-01",
                'ringkasan' => 'Ringkasan hasil evaluasi.',
                'status' => 'approved',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('lhe', [
            'evaluasi_sakip_id' => $evaluasi->id,
            'nomor_lhe' => 'LHE/001/2026',
            'disusun_oleh' => $inspektorat->id,
        ]);

        $this->actingAs($inspektorat)
            ->post(route('evaluasi-sakip.rekomendasi.store', $evaluasi), [
                'evaluasi_sakip_item_id' => $item->id,
                'nomor' => 'R-001',
                'rekomendasi' => 'Lengkapi bukti dukung realisasi kinerja.',
                'prioritas' => 'tinggi',
                'target_tanggal' => "{$periode->tahun}-07-01",
            ])
            ->assertRedirect();

        $rekomendasi = RekomendasiEvaluasi::firstOrFail();

        $this->actingAs($adminOpd)
            ->post(route('rekomendasi-evaluasi.tindak-lanjut.store', $rekomendasi), [
                'uraian_tindak_lanjut' => 'OPD melengkapi dokumen bukti dukung.',
                'status_tindak_lanjut' => 'selesai',
                'tanggal_tindak_lanjut' => "{$periode->tahun}-06-15",
                'catatan_opd' => 'Dokumen sudah diunggah.',
            ])
            ->assertRedirect();

        $tindakLanjut = TindakLanjutRekomendasi::firstOrFail();

        $this->actingAs($inspektorat)
            ->patch(route('tindak-lanjut-rekomendasi.verify', $tindakLanjut), [
                'status_tindak_lanjut' => 'selesai',
                'catatan_verifikator' => 'Tindak lanjut diterima.',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('rekomendasi_evaluasi', [
            'id' => $rekomendasi->id,
            'status_tindak_lanjut' => 'selesai',
        ]);

        $this->assertDatabaseHas('tindak_lanjut_rekomendasi', [
            'id' => $tindakLanjut->id,
            'status_tindak_lanjut' => 'selesai',
            'diverifikasi_oleh' => $inspektorat->id,
        ]);
    }

    public function test_admin_opd_can_view_own_evaluation_and_fill_followup_only(): void
    {
        $this->seed();

        [$opd, $otherOpd, $periode, $inspektorat, $adminOpd] = $this->basicActors();
        $ownEvaluasi = EvaluasiSakip::create([
            'opd_id' => $opd->id,
            'periode_tahun_id' => $periode->id,
            'tahun' => $periode->tahun,
            'evaluator_id' => $inspektorat->id,
            'status' => 'approved',
        ]);
        $otherEvaluasi = EvaluasiSakip::create([
            'opd_id' => $otherOpd->id,
            'periode_tahun_id' => $periode->id,
            'tahun' => $periode->tahun,
            'evaluator_id' => $inspektorat->id,
            'status' => 'approved',
        ]);

        $this->actingAs($adminOpd)
            ->get(route('evaluasi-sakip.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('EvaluasiSakip/Index')
                ->has('evaluasi.data', 1)
                ->where('evaluasi.data.0.id', $ownEvaluasi->id)
            );

        $this->actingAs($adminOpd)
            ->get(route('evaluasi-sakip.show', $ownEvaluasi))
            ->assertOk();

        $this->actingAs($adminOpd)
            ->get(route('evaluasi-sakip.show', $otherEvaluasi))
            ->assertForbidden();

        $this->actingAs($adminOpd)
            ->get(route('evaluasi-sakip.create'))
            ->assertForbidden();
    }

    public function test_bagian_organisasi_can_monitor_all_evaluation_read_only(): void
    {
        $this->seed();

        [$opd, $otherOpd, $periode, $inspektorat] = $this->basicActors();

        EvaluasiSakip::create([
            'opd_id' => $opd->id,
            'periode_tahun_id' => $periode->id,
            'tahun' => $periode->tahun,
            'evaluator_id' => $inspektorat->id,
            'status' => 'approved',
        ]);
        EvaluasiSakip::create([
            'opd_id' => $otherOpd->id,
            'periode_tahun_id' => $periode->id,
            'tahun' => $periode->tahun,
            'evaluator_id' => $inspektorat->id,
            'status' => 'approved',
        ]);

        $monitor = User::factory()->create();
        $monitor->roles()->sync([Role::where('name', 'admin_kabupaten_bagian_organisasi')->value('id')]);

        $this->actingAs($monitor)
            ->get(route('evaluasi-sakip.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('EvaluasiSakip/Index')
                ->has('evaluasi.data', 2)
                ->where('can.manage', false)
            );

        $this->actingAs($monitor)
            ->get(route('evaluasi-sakip.create'))
            ->assertForbidden();
    }

    private function basicActors(): array
    {
        $opd = Opd::create(['kode' => '1.01', 'nama' => 'Dinas Kesehatan', 'status' => 'active']);
        $otherOpd = Opd::create(['kode' => '1.02', 'nama' => 'Dinas Pendidikan', 'status' => 'active']);
        $periode = PeriodeTahun::orderBy('tahun')->firstOrFail();

        $inspektorat = User::factory()->create();
        $inspektorat->roles()->sync([Role::where('name', 'admin_kabupaten_inspektorat')->value('id')]);

        $adminOpd = User::factory()->create(['opd_id' => $opd->id]);
        $adminOpd->roles()->sync([Role::where('name', 'admin_opd')->value('id')]);

        return [$opd, $otherOpd, $periode, $inspektorat, $adminOpd];
    }
}
