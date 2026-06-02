<?php

namespace Tests\Feature;

use App\Jobs\ExportKinerjaReportDocumentJob;
use App\Models\Dokumen;
use App\Models\Opd;
use App\Models\PeriodeTahun;
use App\Models\PerjanjianKinerja;
use App\Models\PerjanjianKinerjaItem;
use App\Models\RealisasiKinerja;
use App\Models\RealisasiProgram;
use App\Models\RencanaAksi;
use App\Models\RencanaAksiItem;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class KinerjaReportExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_kinerja_report_exports_are_dispatched_to_queue(): void
    {
        $this->seed();
        Queue::fake();

        [$adminOpd, $pk, $rencanaAksi, $realisasi] = $this->scenario();

        $this->actingAs($adminOpd)
            ->post(route('perjanjian-kinerja.export', $pk), ['format' => 'pdf'])
            ->assertRedirect();
        $this->actingAs($adminOpd)
            ->post(route('rencana-aksi.export', $rencanaAksi), ['format' => 'word'])
            ->assertRedirect();
        $this->actingAs($adminOpd)
            ->post(route('realisasi-kinerja.export', $realisasi), ['format' => 'pdf'])
            ->assertRedirect();

        Queue::assertPushed(ExportKinerjaReportDocumentJob::class, fn (ExportKinerjaReportDocumentJob $job) => $job->module === 'perjanjian_kinerja' && $job->modelId === $pk->id && $job->format === 'pdf');
        Queue::assertPushed(ExportKinerjaReportDocumentJob::class, fn (ExportKinerjaReportDocumentJob $job) => $job->module === 'rencana_aksi' && $job->modelId === $rencanaAksi->id && $job->format === 'word');
        Queue::assertPushed(ExportKinerjaReportDocumentJob::class, fn (ExportKinerjaReportDocumentJob $job) => $job->module === 'realisasi_kinerja' && $job->modelId === $realisasi->id && $job->format === 'pdf');
    }

    public function test_kinerja_report_job_stores_generated_document_and_relation(): void
    {
        $this->seed();
        Storage::fake('local');
        config(['filesystems.documents_disk' => 'local']);

        [$adminOpd, $pk, $rencanaAksi, $realisasi] = $this->scenario();

        foreach ([
            ['module' => 'perjanjian_kinerja', 'model' => $pk, 'jenis' => 'perjanjian_kinerja'],
            ['module' => 'rencana_aksi', 'model' => $rencanaAksi, 'jenis' => 'rencana_aksi'],
            ['module' => 'realisasi_kinerja', 'model' => $realisasi, 'jenis' => 'realisasi_kinerja'],
        ] as $target) {
            app()->call([new ExportKinerjaReportDocumentJob($target['module'], $target['model']->id, $adminOpd->id, 'pdf'), 'handle']);

            $dokumen = Dokumen::query()
                ->where('jenis', $target['jenis'])
                ->where('uploaded_by', $adminOpd->id)
                ->latest('id')
                ->firstOrFail();

            Storage::disk('local')->assertExists($dokumen->storage_path);
            $this->assertSame('application/pdf', $dokumen->mime_type);
            $this->assertTrue((bool) data_get($dokumen->metadata, 'generated'));
            $this->assertSame($target['module'], data_get($dokumen->metadata, 'module'));

            $this->assertDatabaseHas('dokumen_relations', [
                'dokumen_id' => $dokumen->id,
                'related_type' => $target['model']::class,
                'related_id' => $target['model']->id,
            ]);
        }
    }

    /**
     * @return array{0: User, 1: PerjanjianKinerja, 2: RencanaAksi, 3: RealisasiKinerja}
     */
    private function scenario(): array
    {
        $periode = PeriodeTahun::where('status', 'active')->firstOrFail();
        $opd = Opd::create([
            'kode' => '1.01',
            'nama' => 'Dinas Kesehatan',
            'singkatan' => 'Dinkes',
            'nama_kepala' => 'Kepala Dinas',
            'nip_kepala' => '196001011990031001',
            'status' => 'active',
        ]);
        $adminOpd = User::factory()->create(['opd_id' => $opd->id]);
        $adminOpd->roles()->sync([Role::where('name', 'admin_opd')->value('id')]);

        $pk = PerjanjianKinerja::create([
            'opd_id' => $opd->id,
            'periode_tahun_id' => $periode->id,
            'tahun' => $periode->tahun,
            'judul' => 'Perjanjian Kinerja Dinkes',
            'nomor_dokumen' => 'PK/001',
            'status' => 'approved',
        ]);
        $pkItem = PerjanjianKinerjaItem::create([
            'perjanjian_kinerja_id' => $pk->id,
            'sasaran' => 'Meningkatnya kualitas layanan kesehatan',
            'indikator' => 'Indeks layanan kesehatan',
            'target' => 90,
            'target_text' => '90 persen',
            'urutan' => 1,
        ]);

        $rencanaAksi = RencanaAksi::create([
            'opd_id' => $opd->id,
            'perjanjian_kinerja_id' => $pk->id,
            'periode_tahun_id' => $periode->id,
            'tahun' => $periode->tahun,
            'judul' => 'Rencana Aksi Dinkes',
            'status' => 'approved',
        ]);
        $rencanaAksiItem = RencanaAksiItem::create([
            'rencana_aksi_id' => $rencanaAksi->id,
            'perjanjian_kinerja_item_id' => $pkItem->id,
            'periode_realisasi' => 'triwulan',
            'triwulan' => 'tw1',
            'aksi' => 'Pelaksanaan layanan triwulan pertama',
            'indikator' => 'Layanan selesai',
            'target' => 25,
            'target_text' => '25 persen',
            'anggaran' => 1000000,
            'penanggung_jawab' => 'Sekretariat',
            'status' => 'draft',
            'urutan' => 1,
        ]);

        $realisasi = RealisasiKinerja::create([
            'opd_id' => $opd->id,
            'perjanjian_kinerja_id' => $pk->id,
            'rencana_aksi_id' => $rencanaAksi->id,
            'periode_tahun_id' => $periode->id,
            'tahun' => $periode->tahun,
            'periode_realisasi' => 'triwulan',
            'triwulan' => 'tw1',
            'capaian_persen' => 88,
            'target_anggaran' => 1000000,
            'realisasi_anggaran' => 850000,
            'serapan_anggaran_persen' => 85,
            'status_capaian' => 'kuning',
            'status_efisiensi' => 'efisien',
            'analisis_efisiensi' => 'Capaian lebih tinggi dibanding serapan anggaran.',
            'status' => 'verified',
        ]);
        RealisasiProgram::create([
            'realisasi_kinerja_id' => $realisasi->id,
            'perjanjian_kinerja_item_id' => $pkItem->id,
            'rencana_aksi_item_id' => $rencanaAksiItem->id,
            'indikator' => 'Indeks layanan kesehatan',
            'target' => 90,
            'target_text' => '90 persen',
            'realisasi' => 88,
            'realisasi_text' => '88 persen',
            'capaian_persen' => 97.78,
            'status_capaian' => 'hijau',
            'anggaran' => 1000000,
            'realisasi_anggaran' => 850000,
            'serapan_anggaran_persen' => 85,
            'status_efisiensi' => 'efisien',
            'kendala' => 'Data sebagian manual',
            'tindak_lanjut' => 'Sinkronisasi data',
            'urutan' => 1,
        ]);

        return [$adminOpd, $pk, $rencanaAksi, $realisasi];
    }
}
