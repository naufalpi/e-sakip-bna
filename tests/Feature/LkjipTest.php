<?php

namespace Tests\Feature;

use App\Jobs\ExportLkjipDocumentJob;
use App\Jobs\GenerateLkjipDraftDocumentJob;
use App\Models\Dokumen;
use App\Models\EvaluasiSakip;
use App\Models\Lkjip;
use App\Models\Opd;
use App\Models\PeriodeTahun;
use App\Models\PerjanjianKinerja;
use App\Models\RealisasiKinerja;
use App\Models\RekomendasiEvaluasi;
use App\Models\Role;
use App\Models\User;
use App\Services\Dokumen\DokumenStorageService;
use App\Services\Lkjip\LkjipDocumentRenderService;
use App\Services\Lkjip\LkjipDraftContentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class LkjipTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_opd_can_manage_only_own_lkjip_with_default_bab(): void
    {
        $this->seed();

        [$opd, $otherOpd, $periode, $adminOpd] = $this->basicActors();

        $this->actingAs($adminOpd)
            ->post(route('lkjip.store'), [
                'opd_id' => $opd->id,
                'periode_tahun_id' => $periode->id,
                'tahun' => $periode->tahun,
                'judul' => 'LKJIP Dinas Kesehatan',
                'nomor_dokumen' => 'LKJIP/001',
                'ringkasan_eksekutif' => 'Ringkasan capaian kinerja.',
                'status' => 'draft',
            ])
            ->assertRedirect();

        $lkjip = Lkjip::query()->where('opd_id', $opd->id)->firstOrFail();

        $this->assertDatabaseCount('lkjip_bab', 5);
        $this->assertDatabaseHas('lkjip_bab', [
            'lkjip_id' => $lkjip->id,
            'kode' => 'BAB III',
            'judul' => 'Akuntabilitas Kinerja',
        ]);

        $this->actingAs($adminOpd)
            ->post(route('lkjip.store'), [
                'opd_id' => $otherOpd->id,
                'periode_tahun_id' => $periode->id,
                'tahun' => $periode->tahun,
                'judul' => 'LKJIP OPD Lain',
                'status' => 'draft',
            ])
            ->assertForbidden();

        $otherLkjip = Lkjip::create([
            'opd_id' => $otherOpd->id,
            'periode_tahun_id' => $periode->id,
            'tahun' => $periode->tahun,
            'judul' => 'LKJIP OPD Lain',
            'status' => 'draft',
        ]);

        $this->actingAs($adminOpd)
            ->get(route('lkjip.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Lkjip/Index')
                ->has('items.data', 1)
                ->where('items.data.0.id', $lkjip->id)
            );

        $this->actingAs($adminOpd)
            ->get(route('lkjip.show', $lkjip))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Lkjip/Show')
                ->has('item.bab', 5)
            );

        $this->actingAs($adminOpd)
            ->get(route('lkjip.show', $otherLkjip))
            ->assertForbidden();
    }

    public function test_lkjip_workflow_and_locked_protection(): void
    {
        $this->seed();

        [$opd, , $periode, $adminOpd] = $this->basicActors();
        $reviewer = User::factory()->create();
        $reviewer->roles()->sync([Role::where('name', 'admin_kabupaten_bagian_organisasi')->value('id')]);

        $lkjip = Lkjip::create([
            'opd_id' => $opd->id,
            'periode_tahun_id' => $periode->id,
            'tahun' => $periode->tahun,
            'judul' => 'LKJIP Workflow',
            'status' => 'draft',
        ]);

        $this->actingAs($adminOpd)
            ->post(route('workflow.transition', ['module' => 'lkjip', 'id' => $lkjip->id]), [
                'action' => 'submit',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('workflow_histories', [
            'related_table' => 'lkjip',
            'related_id' => $lkjip->id,
            'action' => 'submit',
            'to_status' => 'submitted',
        ]);

        $this->actingAs($reviewer)
            ->post(route('workflow.transition', ['module' => 'lkjip', 'id' => $lkjip->id]), [
                'action' => 'approve',
                'note' => 'LKJIP disetujui.',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('lkjip', [
            'id' => $lkjip->id,
            'status' => 'approved',
        ]);

        $this->actingAs($reviewer)
            ->post(route('workflow.transition', ['module' => 'lkjip', 'id' => $lkjip->id]), [
                'action' => 'lock',
            ])
            ->assertForbidden();

        $superAdmin = User::where('username', 'superadmin')->firstOrFail();

        $this->actingAs($superAdmin)
            ->post(route('workflow.transition', ['module' => 'lkjip', 'id' => $lkjip->id]), [
                'action' => 'lock',
            ])
            ->assertRedirect();

        $this->actingAs($adminOpd)
            ->put(route('lkjip.update', $lkjip), [
                'opd_id' => $opd->id,
                'periode_tahun_id' => $periode->id,
                'tahun' => $periode->tahun,
                'judul' => 'Perubahan LKJIP Terkunci',
                'status' => 'draft',
            ])
            ->assertForbidden();
    }

    public function test_lkjip_can_be_used_as_document_relation(): void
    {
        Storage::fake('local');
        $this->seed();

        [$opd, , $periode, $adminOpd] = $this->basicActors();

        $lkjip = Lkjip::create([
            'opd_id' => $opd->id,
            'periode_tahun_id' => $periode->id,
            'tahun' => $periode->tahun,
            'judul' => 'LKJIP Dokumen',
            'status' => 'draft',
        ]);

        $this->actingAs($adminOpd)
            ->post(route('dokumen.store'), [
                'opd_id' => $opd->id,
                'periode_tahun_id' => $periode->id,
                'jenis' => 'lkjip',
                'judul' => 'Dokumen LKJIP',
                'status' => 'draft',
                'related_type' => 'lkjip',
                'related_id' => $lkjip->id,
                'file' => UploadedFile::fake()->create('lkjip.pdf', 64, 'application/pdf'),
            ])
            ->assertRedirect();

        $dokumen = Dokumen::firstOrFail();

        $this->assertDatabaseHas('dokumen_relations', [
            'dokumen_id' => $dokumen->id,
            'related_type' => Lkjip::class,
            'related_id' => $lkjip->id,
        ]);
    }

    public function test_lkjip_draft_generation_is_dispatched_to_queue(): void
    {
        Queue::fake();
        $this->seed();

        [$opd, , $periode, $adminOpd] = $this->basicActors();

        $lkjip = Lkjip::create([
            'opd_id' => $opd->id,
            'periode_tahun_id' => $periode->id,
            'tahun' => $periode->tahun,
            'judul' => 'LKJIP Queue',
            'status' => 'draft',
        ]);

        $this->actingAs($adminOpd)
            ->post(route('lkjip.generate-draft', $lkjip))
            ->assertRedirect();

        Queue::assertPushed(GenerateLkjipDraftDocumentJob::class, fn (GenerateLkjipDraftDocumentJob $job) => $job->lkjipId === $lkjip->id
            && $job->requestedById === $adminOpd->id);
    }

    public function test_lkjip_export_generation_is_dispatched_to_queue(): void
    {
        Queue::fake();
        $this->seed();

        [$opd, , $periode, $adminOpd] = $this->basicActors();

        $lkjip = Lkjip::create([
            'opd_id' => $opd->id,
            'periode_tahun_id' => $periode->id,
            'tahun' => $periode->tahun,
            'judul' => 'LKJIP Export Queue',
            'status' => 'draft',
        ]);

        $this->actingAs($adminOpd)
            ->post(route('lkjip.export', $lkjip), ['format' => 'pdf'])
            ->assertRedirect();

        Queue::assertPushed(ExportLkjipDocumentJob::class, fn (ExportLkjipDocumentJob $job) => $job->lkjipId === $lkjip->id
            && $job->requestedById === $adminOpd->id
            && $job->format === 'pdf');
    }

    public function test_lkjip_draft_job_updates_bab_and_stores_private_document(): void
    {
        config(['filesystems.documents_disk' => 'local']);
        Storage::fake('local');
        $this->seed();

        [$opd, , $periode, $adminOpd] = $this->basicActors();

        $perjanjianKinerja = PerjanjianKinerja::create([
            'opd_id' => $opd->id,
            'periode_tahun_id' => $periode->id,
            'tahun' => $periode->tahun,
            'judul' => 'Perjanjian Kinerja Dinas Kesehatan',
            'status' => 'approved',
        ]);

        $perjanjianKinerja->items()->create([
            'sasaran' => 'Meningkatnya kualitas layanan kesehatan',
            'indikator' => 'Persentase layanan kesehatan sesuai standar',
            'target' => 95,
            'target_text' => '95%',
            'urutan' => 1,
        ]);

        $realisasi = RealisasiKinerja::create([
            'opd_id' => $opd->id,
            'perjanjian_kinerja_id' => $perjanjianKinerja->id,
            'periode_tahun_id' => $periode->id,
            'tahun' => $periode->tahun,
            'periode_realisasi' => 'triwulan',
            'triwulan' => 'tw4',
            'status' => 'approved',
            'target_anggaran' => 100000000,
            'realisasi_anggaran' => 75000000,
            'serapan_anggaran_persen' => 75,
            'capaian_persen' => 92,
            'status_capaian' => 'hijau',
            'status_efisiensi' => 'efisien',
            'analisis_efisiensi' => 'Capaian lebih tinggi dibanding serapan anggaran.',
        ]);

        $realisasi->programs()->create([
            'indikator' => 'Persentase layanan kesehatan sesuai standar',
            'target' => 95,
            'target_text' => '95%',
            'realisasi' => 92,
            'realisasi_text' => '92%',
            'capaian_persen' => 96.84,
            'status_capaian' => 'hijau',
            'anggaran' => 100000000,
            'realisasi_anggaran' => 75000000,
            'serapan_anggaran_persen' => 75,
            'status_efisiensi' => 'efisien',
            'analisis_efisiensi' => 'Output tercapai dengan serapan terkendali.',
            'kendala' => 'Distribusi layanan belum merata.',
            'tindak_lanjut' => 'Penguatan layanan puskesmas.',
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
            'rekomendasi' => 'Perkuat kualitas indikator kinerja.',
            'status_tindak_lanjut' => 'proses',
        ]);

        $lkjip = Lkjip::create([
            'opd_id' => $opd->id,
            'periode_tahun_id' => $periode->id,
            'perjanjian_kinerja_id' => $perjanjianKinerja->id,
            'realisasi_kinerja_id' => $realisasi->id,
            'evaluasi_sakip_id' => $evaluasi->id,
            'tahun' => $periode->tahun,
            'judul' => 'LKJIP Dinas Kesehatan',
            'ringkasan_eksekutif' => 'Ringkasan awal LKJIP.',
            'status' => 'draft',
        ]);

        (new GenerateLkjipDraftDocumentJob($lkjip->id, $adminOpd->id))->handle(
            app(LkjipDraftContentService::class),
            app(DokumenStorageService::class),
        );

        $dokumen = Dokumen::query()->where('jenis', 'lkjip')->firstOrFail();

        Storage::disk($dokumen->storage_disk)->assertExists($dokumen->storage_path);

        $content = Storage::disk($dokumen->storage_disk)->get($dokumen->storage_path);

        $this->assertStringContainsString('BAB III - Akuntabilitas Kinerja', $content);
        $this->assertStringContainsString('Status efisiensi: Efisien', $content);
        $this->assertStringContainsString('Perkuat kualitas indikator kinerja.', $content);
        $this->assertDatabaseHas('lkjip_bab', [
            'lkjip_id' => $lkjip->id,
            'kode' => 'BAB III',
            'judul' => 'Akuntabilitas Kinerja',
        ]);
        $this->assertDatabaseHas('dokumen_relations', [
            'dokumen_id' => $dokumen->id,
            'related_type' => Lkjip::class,
            'related_id' => $lkjip->id,
        ]);
    }

    public function test_lkjip_export_job_stores_pdf_and_word_documents(): void
    {
        config(['filesystems.documents_disk' => 'local']);
        Storage::fake('local');
        $this->seed();

        [$opd, , $periode, $adminOpd] = $this->basicActors();

        $lkjip = Lkjip::create([
            'opd_id' => $opd->id,
            'periode_tahun_id' => $periode->id,
            'tahun' => $periode->tahun,
            'judul' => 'LKJIP Export Dokumen',
            'ringkasan_eksekutif' => 'Ringkasan LKJIP untuk export.',
            'status' => 'draft',
        ]);

        foreach (['pdf', 'word'] as $format) {
            (new ExportLkjipDocumentJob($lkjip->id, $adminOpd->id, $format))->handle(
                app(LkjipDraftContentService::class),
                app(LkjipDocumentRenderService::class),
                app(DokumenStorageService::class),
            );
        }

        $pdf = Dokumen::query()->where('mime_type', 'application/pdf')->firstOrFail();
        $word = Dokumen::query()->where('mime_type', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document')->firstOrFail();

        Storage::disk($pdf->storage_disk)->assertExists($pdf->storage_path);
        Storage::disk($word->storage_disk)->assertExists($word->storage_path);

        $this->assertStringStartsWith('%PDF', Storage::disk($pdf->storage_disk)->get($pdf->storage_path));
        $this->assertStringStartsWith("PK\x03\x04", Storage::disk($word->storage_disk)->get($word->storage_path));
        $this->assertDatabaseHas('dokumen_relations', [
            'dokumen_id' => $pdf->id,
            'related_type' => Lkjip::class,
            'related_id' => $lkjip->id,
        ]);
        $this->assertDatabaseHas('dokumen_relations', [
            'dokumen_id' => $word->id,
            'related_type' => Lkjip::class,
            'related_id' => $lkjip->id,
        ]);
    }

    private function basicActors(): array
    {
        $opd = Opd::create(['kode' => '1.01', 'nama' => 'Dinas Kesehatan', 'status' => 'active']);
        $otherOpd = Opd::create(['kode' => '1.02', 'nama' => 'Dinas Pendidikan', 'status' => 'active']);
        $periode = PeriodeTahun::orderBy('tahun')->firstOrFail();

        $adminOpd = User::factory()->create(['opd_id' => $opd->id]);
        $adminOpd->roles()->sync([Role::where('name', 'admin_opd')->value('id')]);

        return [$opd, $otherOpd, $periode, $adminOpd];
    }
}
