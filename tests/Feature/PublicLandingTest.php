<?php

namespace Tests\Feature;

use App\Models\Dokumen;
use App\Models\Opd;
use App\Models\PeriodeTahun;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PublicLandingTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_open_public_landing_page(): void
    {
        Storage::fake('local');

        $periode = PeriodeTahun::create([
            'tahun' => 2026,
            'nama' => 'Tahun 2026',
            'status' => 'active',
        ]);

        $opd = Opd::create([
            'kode' => '1.01',
            'nama' => 'Dinas Publik Demo',
            'singkatan' => 'DPD',
            'status' => 'active',
        ]);

        $document = $this->createDocument($periode, $opd, 'renstra', 'approved');

        $this->get('/')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('PublicSite/Landing')
                ->where('active_section', null)
                ->where('filters.tahun', 2026)
                ->where('available_years.0', 2026)
                ->where('meta.tahun', 2026)
                ->where('stats.opd_count', 1)
                ->where('section_urls.perencanaan', route('public.section', ['section' => 'perencanaan', 'tahun' => 2026]))
                ->where('tables.perencanaan', [])
            );

        $this->get(route('public.section', ['section' => 'perencanaan', 'tahun' => 2026]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('PublicSite/Landing')
                ->where('active_section', 'perencanaan')
                ->where('tables.perencanaan.0.opd.nama', 'Dinas Publik Demo')
                ->where('tables.perencanaan.0.cells.renstra.dokumen.id', $document->id)
            );
    }

    public function test_approved_public_document_can_be_viewed_and_downloaded(): void
    {
        Storage::fake('local');

        $periode = PeriodeTahun::create([
            'tahun' => 2026,
            'nama' => 'Tahun 2026',
            'status' => 'active',
        ]);
        $opd = Opd::create([
            'kode' => '1.02',
            'nama' => 'Dinas Dokumen Publik',
            'status' => 'active',
        ]);
        $document = $this->createDocument($periode, $opd, 'lhe', 'approved');

        $this->get(route('public.dokumen.view', $document))
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff');

        $this->get(route('public.dokumen.download', $document))
            ->assertOk()
            ->assertDownload($document->original_filename);
    }

    public function test_draft_document_is_not_publicly_available(): void
    {
        Storage::fake('local');

        $periode = PeriodeTahun::create([
            'tahun' => 2026,
            'nama' => 'Tahun 2026',
            'status' => 'active',
        ]);
        $opd = Opd::create([
            'kode' => '1.03',
            'nama' => 'Dinas Draft',
            'status' => 'active',
        ]);
        $document = $this->createDocument($periode, $opd, 'lkjip', 'draft');

        $this->get(route('public.dokumen.view', $document))->assertNotFound();
        $this->get(route('public.dokumen.download', $document))->assertNotFound();
    }

    private function createDocument(PeriodeTahun $periode, Opd $opd, string $jenis, string $status): Dokumen
    {
        Storage::disk('local')->put("public-test/{$jenis}-{$status}.txt", 'Dokumen publik test');

        return Dokumen::create([
            'opd_id' => $opd->id,
            'periode_tahun_id' => $periode->id,
            'jenis' => $jenis,
            'judul' => 'Dokumen '.strtoupper($jenis),
            'status' => $status,
            'original_filename' => "{$jenis}-{$status}.txt",
            'mime_type' => 'text/plain',
            'file_size' => 19,
            'file_hash' => hash('sha256', 'Dokumen publik test'),
            'storage_disk' => 'local',
            'storage_path' => "public-test/{$jenis}-{$status}.txt",
        ]);
    }
}
