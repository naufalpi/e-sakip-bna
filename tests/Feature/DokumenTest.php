<?php

namespace Tests\Feature;

use App\Models\Dokumen;
use App\Models\Opd;
use App\Models\PeriodeTahun;
use App\Models\RealisasiKinerja;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DokumenTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_opd_can_upload_document_to_own_realisasi_and_download_via_authorized_route(): void
    {
        Storage::fake('local');
        $this->seed();

        [$opd, , $periode, $adminOpd] = $this->basicActors();
        $realisasi = RealisasiKinerja::create([
            'opd_id' => $opd->id,
            'periode_tahun_id' => $periode->id,
            'tahun' => $periode->tahun,
            'periode_realisasi' => 'triwulan',
            'triwulan' => 'tw1',
            'status' => 'draft',
        ]);

        $file = UploadedFile::fake()->create('bukti-realisasi.pdf', 128, 'application/pdf');

        $this->actingAs($adminOpd)
            ->post(route('dokumen.store'), [
                'opd_id' => $opd->id,
                'periode_tahun_id' => $periode->id,
                'jenis' => 'bukti_dukung',
                'judul' => 'Bukti Realisasi TW1',
                'nomor_dokumen' => 'BR/TW1',
                'deskripsi' => 'Bukti dukung realisasi triwulan pertama.',
                'status' => 'draft',
                'related_type' => 'realisasi_kinerja',
                'related_id' => $realisasi->id,
                'file' => $file,
            ])
            ->assertRedirect();

        $dokumen = Dokumen::firstOrFail();

        Storage::disk('local')->assertExists($dokumen->storage_path);

        $this->assertDatabaseHas('dokumen', [
            'id' => $dokumen->id,
            'opd_id' => $opd->id,
            'jenis' => 'bukti_dukung',
            'original_filename' => 'bukti-realisasi.pdf',
            'storage_disk' => 'local',
            'uploaded_by' => $adminOpd->id,
        ]);

        $this->assertDatabaseHas('dokumen_relations', [
            'dokumen_id' => $dokumen->id,
            'related_type' => RealisasiKinerja::class,
            'related_id' => $realisasi->id,
        ]);

        $this->actingAs($adminOpd)
            ->get(route('dokumen.download', $dokumen))
            ->assertOk();
    }

    public function test_admin_opd_cannot_download_other_opd_document(): void
    {
        Storage::fake('local');
        $this->seed();

        [$opd, $otherOpd, $periode, $adminOpd] = $this->basicActors();
        $otherAdmin = User::factory()->create(['opd_id' => $otherOpd->id]);
        $otherAdmin->roles()->sync([Role::where('name', 'admin_opd')->value('id')]);

        $path = 'dokumen/bukti_dukung/test.pdf';
        Storage::disk('local')->put($path, 'dokumen rahasia');

        $dokumen = Dokumen::create([
            'opd_id' => $opd->id,
            'periode_tahun_id' => $periode->id,
            'jenis' => 'bukti_dukung',
            'judul' => 'Dokumen OPD Lain',
            'status' => 'draft',
            'original_filename' => 'test.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 14,
            'file_hash' => hash('sha256', 'dokumen rahasia'),
            'storage_disk' => 'local',
            'storage_path' => $path,
            'uploaded_by' => $adminOpd->id,
        ]);

        $this->actingAs($otherAdmin)
            ->get(route('dokumen.download', $dokumen))
            ->assertForbidden();
    }

    public function test_document_index_is_limited_to_admin_opd_own_documents(): void
    {
        Storage::fake('local');
        $this->seed();

        [$opd, $otherOpd, $periode, $adminOpd] = $this->basicActors();
        $otherUploader = User::factory()->create(['opd_id' => $otherOpd->id]);
        $otherUploader->roles()->sync([Role::where('name', 'admin_opd')->value('id')]);

        $ownDokumen = $this->createDokumen($opd, $periode, $adminOpd, 'Dokumen Sendiri');
        $this->createDokumen($otherOpd, $periode, $otherUploader, 'Dokumen OPD Lain');

        $this->actingAs($adminOpd)
            ->get(route('dokumen.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dokumen/Index')
                ->has('dokumen.data', 1)
                ->where('dokumen.data.0.id', $ownDokumen->id)
            );
    }

    public function test_admin_opd_can_check_public_document_completeness_for_own_opd(): void
    {
        Storage::fake('local');
        $this->seed();

        [$opd, , $periode, $adminOpd] = $this->basicActors();
        $this->createDokumen($opd, $periode, $adminOpd, 'Pohon Kinerja Publik', [
            'jenis' => 'pohon_kinerja',
            'status' => 'verified',
        ]);

        $this->actingAs($adminOpd)
            ->get(route('dokumen-publik.index', ['tahun' => $periode->tahun]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dokumen/PublikChecklist')
                ->where('filters.opd_id', $opd->id)
                ->where('isAggregate', false)
                ->where('sections.0.key', 'perencanaan')
                ->where('sections.0.items.0.key', 'pohon_kinerja')
                ->where('sections.0.items.0.details.0.state', 'complete')
            );
    }

    public function test_pimpinan_without_document_permission_cannot_access_documents(): void
    {
        $this->seed();

        $pimpinan = User::factory()->create();
        $pimpinan->roles()->sync([Role::where('name', 'pimpinan')->value('id')]);

        $this->actingAs($pimpinan)
            ->get(route('dokumen.index'))
            ->assertForbidden();
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

    private function createDokumen(Opd $opd, PeriodeTahun $periode, User $uploadedBy, string $judul, array $overrides = []): Dokumen
    {
        $path = 'dokumen/bukti_dukung/'.str($judul)->slug().'.txt';
        Storage::disk('local')->put($path, $judul);

        return Dokumen::create(array_merge([
            'opd_id' => $opd->id,
            'periode_tahun_id' => $periode->id,
            'jenis' => 'bukti_dukung',
            'judul' => $judul,
            'status' => 'draft',
            'original_filename' => basename($path),
            'mime_type' => 'text/plain',
            'file_size' => strlen($judul),
            'file_hash' => hash('sha256', $judul),
            'storage_disk' => 'local',
            'storage_path' => $path,
            'uploaded_by' => $uploadedBy->id,
        ], $overrides));
    }
}
