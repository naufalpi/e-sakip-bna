<?php

namespace Tests\Feature;

use App\Jobs\ExportKinerjaReportDocumentJob;
use App\Models\Dokumen;
use App\Models\JabatanOrganisasi;
use App\Models\Opd;
use App\Models\Pegawai;
use App\Models\PeriodeTahun;
use App\Models\PerjanjianKinerja;
use App\Models\PerjanjianKinerjaItem;
use App\Models\RealisasiKinerja;
use App\Models\RealisasiProgram;
use App\Models\RencanaAksi;
use App\Models\RencanaAksiItem;
use App\Models\Role;
use App\Models\User;
use App\Services\Kinerja\PerjanjianKinerjaDocumentService;
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

    public function test_pk_print_opens_real_pdf_and_docx_downloads_directly(): void
    {
        $this->seed();
        [$adminOpd, $pk] = $this->scenario();

        $pdf = $this->actingAs($adminOpd)->get(route('perjanjian-kinerja.print', $pk));
        $pdf->assertOk()
            ->assertHeader('content-type', 'application/pdf');
        $this->assertStringContainsString('inline;', (string) $pdf->headers->get('content-disposition'));
        $this->assertStringContainsString('.pdf', (string) $pdf->headers->get('content-disposition'));
        $this->assertStringStartsWith('%PDF', $pdf->getContent());

        $word = $this->actingAs($adminOpd)->get(route('perjanjian-kinerja.download.docx', $pk));
        $word->assertOk()
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document')
            ->assertDownload();
        $contents = $word->streamedContent();
        $this->assertStringStartsWith('PK', $contents);

        $temporaryPath = tempnam(sys_get_temp_dir(), 'pk-docx-');
        file_put_contents($temporaryPath, $contents);
        $archive = new \ZipArchive;

        try {
            $this->assertTrue($archive->open($temporaryPath) === true);
            $documentXml = (string) $archive->getFromName('word/document.xml');
            $stylesXml = (string) $archive->getFromName('word/styles.xml');

            $this->assertStringContainsString('<w:sz w:val="36"/>', $documentXml);
            $this->assertStringContainsString('<w:sz w:val="32"/>', $documentXml);
            $this->assertStringContainsString('<w:sz w:val="30"/>', $documentXml);
            $this->assertStringContainsString('<w:sz w:val="24"/>', $documentXml);
            $this->assertStringContainsString('<w:sz w:val="22"/>', $documentXml);
            $this->assertStringContainsString('w:color="FFFFFF"', $documentXml);
            $this->assertStringContainsString('w:line="360"', $documentXml);
            $this->assertStringContainsString('w:ascii="Arial"', $stylesXml);
        } finally {
            $archive->close();
            @unlink($temporaryPath);
        }
    }

    public function test_kepala_opd_print_uses_official_two_page_format(): void
    {
        $html = view('reports.perjanjian-kinerja', [
            'report' => ['metadata' => ['pk_document' => [
                'level' => 'kepala_opd',
                'year' => 2026,
                'title' => 'PERJANJIAN KINERJA TAHUN 2026',
                'office_name' => 'DINAS KOMUNIKASI DAN INFORMATIKA',
                'address' => 'Jl. Dipayuda No. 5 Banjarnegara',
                'telephone' => '(0286) 591218',
                'email' => 'dinkominfo@banjarnegarakab.go.id',
                'place_date' => 'Banjarnegara, 5 Januari 2026',
                'first_party' => [
                    'name' => 'Kepala Dinas',
                    'nip' => '199001012010011001',
                    'rank' => 'Pembina Tingkat I / IV.b',
                    'position' => 'Kepala Dinas Komunikasi dan Informatika',
                ],
                'second_party' => [
                    'name' => 'Bupati Banjarnegara',
                    'nip' => null,
                    'rank' => null,
                    'position' => 'Bupati Banjarnegara',
                ],
                'performance_groups' => [[
                    'number' => 1,
                    'type_label' => 'Sasaran OPD',
                    'performance' => 'Meningkatnya layanan informasi publik',
                    'indicators' => [[
                        'name' => 'Indeks layanan informasi publik',
                        'target' => '90',
                        'unit' => 'Persen',
                    ]],
                ]],
                'programs' => [[
                    'code' => '2.16.03',
                    'name' => 'Program Pengelolaan Informasi dan Komunikasi Publik',
                    'budget_label' => 'Rp 804.329.200',
                    'note' => 'APBD',
                ]],
            ]]],
            'browserPrint' => true,
        ])->render();

        $this->assertStringContainsString('TUJUAN DAN SASARAN STRATEGIS *', strtoupper($html));
        $this->assertStringContainsString('DINAS KOMUNIKASI DAN INFORMATIKA', $html);
        $this->assertStringContainsString('Pembina Tingkat I / IV.b', $html);
        $this->assertStringContainsString('<span class="program-number">1.</span>Program Pengelolaan Informasi dan Komunikasi Publik', $html);
        $this->assertStringNotContainsString('Total Anggaran</td>', $html);
        $this->assertStringNotContainsString('Sumber data:', $html);
        $this->assertStringContainsString('font-family: Arial, Helvetica, sans-serif; font-size: 12pt; line-height: 1.5;', $html);
        $this->assertStringContainsString('height: 24mm;', $html);
        $this->assertStringContainsString('margin: auto; width: auto;', $html);
        $this->assertStringContainsString('.letterhead-copy .government { font-size: 15pt;', $html);
        $this->assertStringContainsString('.letterhead-copy .office { font-size: 18pt; font-weight: 700;', $html);
        $this->assertStringContainsString('.letterhead-copy .address { font-size: 10pt;', $html);
        $this->assertStringContainsString('.letterhead-copy .city { font-size: 11pt;', $html);
        $this->assertStringContainsString('h1 { font-size: 16pt; font-weight: 700;', $html);
        $this->assertStringContainsString('.page-break { font-size: 11pt; line-height: 1.5;', $html);
    }

    public function test_structural_pk_print_uses_direct_supervisor_and_official_folio_format(): void
    {
        $this->seed();
        $opd = Opd::query()->where('status', 'active')->firstOrFail();
        $periode = PeriodeTahun::query()->firstOrFail();
        $kepalaDinas = JabatanOrganisasi::create([
            'opd_id' => $opd->id,
            'nama' => 'Kepala Dinas Pengujian',
            'level_jabatan' => 'jpt_pratama',
            'status' => 'active',
        ]);
        $kepalaBidang = JabatanOrganisasi::create([
            'opd_id' => $opd->id,
            'parent_id' => $kepalaDinas->id,
            'nama' => 'Kepala Bidang Pengujian',
            'level_jabatan' => 'administrator',
            'status' => 'active',
        ]);
        $atasan = Pegawai::create([
            'opd_id' => $opd->id,
            'nama' => 'Atasan Pengujian',
            'nip' => '197001012000011001',
            'pangkat_golongan' => 'Pembina Utama Muda / IV.c',
            'jenis_pegawai' => 'pns',
            'status' => 'active',
        ]);
        $pegawai = Pegawai::create([
            'opd_id' => $opd->id,
            'nama' => 'Kabid Pengujian',
            'nip' => '198001012010011001',
            'pangkat_golongan' => 'Pembina / IV.a',
            'jenis_pegawai' => 'pns',
            'status' => 'active',
        ]);
        $atasan->penempatan()->create([
            'jabatan_organisasi_id' => $kepalaDinas->id,
            'nama_pejabat' => $atasan->nama,
            'nip' => $atasan->nip,
            'jenis_penugasan' => 'definitif',
            'tanggal_mulai' => '2026-01-01',
        ]);
        $penempatan = $pegawai->penempatan()->create([
            'jabatan_organisasi_id' => $kepalaBidang->id,
            'nama_pejabat' => $pegawai->nama,
            'nip' => $pegawai->nip,
            'jenis_penugasan' => 'definitif',
            'tanggal_mulai' => '2026-01-01',
        ]);
        $pk = PerjanjianKinerja::create([
            'opd_id' => $opd->id,
            'pegawai_id' => $pegawai->id,
            'penempatan_pegawai_id' => $penempatan->id,
            'atasan_pegawai_id' => $atasan->id,
            'periode_tahun_id' => $periode->id,
            'tahun' => 2026,
            'tipe_pk' => 'cascading',
            'level_pk' => 'struktural',
            'nama_pegawai_snapshot' => $pegawai->nama,
            'nip_snapshot' => $pegawai->nip,
            'jabatan_snapshot' => $kepalaBidang->nama,
            'nama_atasan_snapshot' => $atasan->nama,
            'nip_atasan_snapshot' => $atasan->nip,
            'jabatan_atasan_snapshot' => 'BUPATI BANJARNEGARA',
            'judul' => 'PK Kepala Bidang Pengujian',
            'tanggal_dokumen' => '2026-01-05',
            'status' => 'draft',
        ]);

        $document = app(PerjanjianKinerjaDocumentService::class)->build($pk);
        $this->assertSame('Kepala Dinas Pengujian', $document['second_party']['position']);

        $html = view('reports.perjanjian-kinerja', [
            'report' => ['metadata' => ['pk_document' => $document]],
            'browserPrint' => true,
        ])->render();

        $this->assertStringContainsString('size: 210mm 330mm', $html);
        $this->assertStringContainsString('Sasaran Program dan Sasaran Kegiatan **', $html);
        $this->assertStringContainsString('Nama Pejabat', $html);
        $this->assertStringContainsString('Unit Kerja', $html);
        $this->assertStringContainsString('Kepala Dinas Pengujian', $html);
        $this->assertStringContainsString('Pihak Kedua', $html);
        $this->assertStringContainsString('Untuk disesuaikan dengan kondisi pada masing-masing Perangkat Daerah', $html);
        $this->assertStringNotContainsString('Total Anggaran</td>', $html);
        $this->assertStringNotContainsString('Sumber data:', $html);
    }

    public function test_lower_cascading_pk_uses_activity_and_sub_activity_official_format(): void
    {
        $html = view('reports.perjanjian-kinerja', [
            'report' => ['metadata' => ['pk_document' => [
                'level' => 'individu',
                'is_lower_cascading' => true,
                'year' => 2026,
                'title' => 'PERJANJIAN KINERJA TAHUN 2026',
                'office_name' => 'DINAS PENGUJIAN',
                'employee_name' => 'Kasi Pengujian',
                'work_unit' => 'Seksi Pengujian',
                'place_date' => 'Banjarnegara, 5 Januari 2026',
                'first_party' => ['name' => 'Kasi Pengujian', 'position' => 'Kepala Seksi Pengujian'],
                'second_party' => ['name' => 'Kabid Pengujian', 'position' => 'Kepala Bidang Pengujian'],
                'performance_groups' => [[
                    'number' => 1,
                    'performance' => 'Meningkatnya mutu kegiatan',
                    'indicators' => [['name' => 'Indeks mutu kegiatan', 'target' => '92', 'unit' => 'persen']],
                ]],
                'activity_budget_groups' => [[
                    'name' => 'Kegiatan Pengujian',
                    'budget_label' => 'Rp 100.000.000',
                    'note' => 'APBD',
                    'sub_activities' => [[
                        'name' => 'Sub Kegiatan Pengujian',
                        'budget_label' => 'Rp 100.000.000',
                    ]],
                ]],
            ]]],
            'browserPrint' => true,
        ])->render();

        $this->assertStringContainsString('size: 210mm 330mm', $html);
        $this->assertStringContainsString('Sasaran Kegiatan dan Sasaran Sub Kegiatan ***', $html);
        $this->assertStringContainsString('Kegiatan dan Sub Kegiatan', $html);
        $this->assertStringContainsString('Kegiatan Pengujian', $html);
        $this->assertStringContainsString('Sub Kegiatan Pengujian', $html);
        $this->assertStringContainsString('Kolom kedua disesuaikan dengan kondisi yang dilaksanakan oleh pejabat pengawas', $html);
    }

    public function test_manual_individual_pk_uses_official_manual_format(): void
    {
        $html = view('reports.perjanjian-kinerja', [
            'report' => ['metadata' => ['pk_document' => [
                'level' => 'individu',
                'is_manual_individual' => true,
                'year' => 2026,
                'title' => 'PERJANJIAN KINERJA TAHUN 2026',
                'office_name' => 'DINAS PENGUJIAN',
                'employee_name' => 'Pelaksana Pengujian',
                'work_unit' => 'Bidang Pengujian',
                'place_date' => 'Banjarnegara, 5 Januari 2026',
                'first_party' => ['name' => 'Pelaksana Pengujian', 'position' => 'Analis Pengujian'],
                'second_party' => ['name' => 'Kabid Pengujian', 'position' => 'Kepala Bidang Pengujian'],
                'performance_groups' => [[
                    'number' => 1,
                    'performance' => 'Tersusunnya hasil pengujian',
                    'indicators' => [['name' => 'Jumlah laporan pengujian', 'target' => '12', 'unit' => 'dokumen']],
                ]],
                'activity_budget_groups' => [],
            ]]],
            'browserPrint' => false,
        ])->render();

        $this->assertStringContainsString('size: 210mm 330mm', $html);
        $this->assertStringContainsString('Sasaran Kegiatan dan Sasaran Sub Kegiatan', $html);
        $this->assertStringNotContainsString('Sasaran Kegiatan dan Sasaran Sub Kegiatan ***', $html);
        $this->assertStringContainsString('Kegiatan dan Sub Kegiatan', $html);
        $this->assertStringContainsString('Nama Pejabat', $html);
        $this->assertStringContainsString('Unit Kerja', $html);
        $this->assertStringContainsString('Pihak Kedua', $html);
        $this->assertStringContainsString('Pihak Pertama', $html);
        $this->assertStringContainsString('Belum ada kegiatan atau sub kegiatan.', $html);
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
