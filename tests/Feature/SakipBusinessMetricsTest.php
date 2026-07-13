<?php

namespace Tests\Feature;

use App\Models\Opd;
use App\Models\PeriodeTahun;
use App\Models\PredikatEvaluasi;
use App\Models\RealisasiKinerja;
use App\Models\Role;
use App\Models\TargetTriwulanIndikator;
use App\Models\User;
use App\Services\Evaluasi\EvaluasiSakipScoreService;
use App\Services\Kinerja\CapaianKinerjaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SakipBusinessMetricsTest extends TestCase
{
    use RefreshDatabase;

    public function test_business_metric_schema_and_predikat_seed_exist(): void
    {
        $this->seed();

        foreach ([
            'indikator_tujuan_daerah',
            'indikator_sasaran_daerah',
            'indikator_program_rpjmd',
        ] as $table) {
            $this->assertFalse(Schema::hasColumn($table, 'tipe_indikator'), "Kolom tipe_indikator seharusnya sudah tidak ada pada {$table}.");
            $this->assertFalse(Schema::hasColumn($table, 'formula'), "Kolom formula seharusnya sudah diganti pada {$table}.");

            foreach (['definisi_operasional', 'alasan_pemilihan', 'formulasi_pengukuran', 'tipe_perhitungan', 'sumber_data', 'opd_id'] as $column) {
                $this->assertTrue(Schema::hasColumn($table, $column), "Kolom {$column} belum ada pada {$table}.");
            }
        }

        foreach ([
            'indikator_tujuan_opd',
            'indikator_sasaran_opd',
            'indikator_opd_program',
            'indikator_sub_kegiatan',
        ] as $table) {
            $this->assertTrue(Schema::hasColumn($table, 'tipe_indikator'), "Kolom tipe_indikator belum ada pada {$table}.");
        }

        foreach (['target_anggaran', 'realisasi_anggaran', 'serapan_anggaran_persen', 'capaian_persen', 'status_capaian', 'status_efisiensi', 'analisis_efisiensi'] as $column) {
            $this->assertTrue(Schema::hasColumn('realisasi_kinerja', $column), "Kolom {$column} belum ada pada realisasi_kinerja.");
        }

        $periode = PeriodeTahun::query()->firstOrFail();
        TargetTriwulanIndikator::create([
            'related_table' => 'indikator_sasaran_opd',
            'related_id' => 10,
            'periode_tahun_id' => $periode->id,
            'triwulan' => 'tw1',
            'target_text' => '25 persen',
            'target_angka' => 25,
            'target_anggaran' => 1000000,
        ]);

        $this->assertDatabaseHas('target_triwulan_indikator', [
            'related_table' => 'indikator_sasaran_opd',
            'related_id' => 10,
            'triwulan' => 'tw1',
        ]);

        $this->assertDatabaseHas('predikat_evaluasi', ['kode' => 'AA', 'is_active' => true]);
        $this->assertSame('A', app(EvaluasiSakipScoreService::class)->predikat(85));
    }

    public function test_capaian_service_calculates_positive_negative_status_serapan_and_efficiency(): void
    {
        $service = app(CapaianKinerjaService::class);

        $this->assertSame(80.0, $service->calculateCapaian(100, 80, 'positif'));
        $this->assertSame(120.0, $service->calculateCapaian(100, 80, 'negatif'));
        $this->assertSame('merah', $service->determineStatusCapaian(69.99));
        $this->assertSame('kuning', $service->determineStatusCapaian(90));
        $this->assertSame('hijau', $service->determineStatusCapaian(90.01));
        $this->assertSame(75.0, $service->calculateSerapanAnggaran(100000000, 75000000));
        $this->assertSame('efisien', $service->determineEfisiensi(95, 80));
        $this->assertSame('cukup_efisien', $service->determineEfisiensi(80, 75));
        $this->assertSame('tidak_efisien', $service->determineEfisiensi(70, 90));
    }

    public function test_realisasi_program_auto_calculates_metrics_and_header_summary(): void
    {
        $this->seed();

        $opd = Opd::create(['kode' => '1.01', 'nama' => 'Dinas Kesehatan', 'status' => 'active']);
        $periode = PeriodeTahun::query()->firstOrFail();
        $adminOpd = User::factory()->create(['opd_id' => $opd->id]);
        $adminOpd->roles()->sync([Role::where('name', 'admin_opd')->value('id')]);

        $realisasi = RealisasiKinerja::create([
            'opd_id' => $opd->id,
            'periode_tahun_id' => $periode->id,
            'tahun' => $periode->tahun,
            'periode_realisasi' => 'triwulan',
            'triwulan' => 'tw1',
            'status' => 'draft',
        ]);

        $this->actingAs($adminOpd)
            ->post(route('realisasi-kinerja.programs.store', $realisasi), [
                'tipe_indikator' => 'positif',
                'indikator' => 'Persentase layanan selesai',
                'target' => 100,
                'target_text' => '100 persen',
                'realisasi' => 80,
                'realisasi_text' => '80 persen',
                'anggaran' => 100000000,
                'realisasi_anggaran' => 75000000,
                'urutan' => 1,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('realisasi_program', [
            'realisasi_kinerja_id' => $realisasi->id,
            'tipe_indikator' => 'positif',
            'capaian_persen' => 80,
            'status_capaian' => 'kuning',
            'serapan_anggaran_persen' => 75,
            'status_efisiensi' => 'cukup_efisien',
        ]);

        $this->assertDatabaseHas('realisasi_kinerja', [
            'id' => $realisasi->id,
            'target_anggaran' => 100000000,
            'realisasi_anggaran' => 75000000,
            'serapan_anggaran_persen' => 75,
            'capaian_persen' => 80,
            'status_capaian' => 'kuning',
            'status_efisiensi' => 'cukup_efisien',
        ]);
    }

    public function test_predikat_evaluasi_model_can_resolve_configured_range(): void
    {
        $this->seed();

        $predikat = PredikatEvaluasi::query()
            ->where('is_active', true)
            ->where('nilai_min', '<=', 72)
            ->where('nilai_max', '>=', 72)
            ->firstOrFail();

        $this->assertSame('BB', $predikat->kode);
    }
}
