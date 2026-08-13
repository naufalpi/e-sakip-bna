<?php

namespace Tests\Feature;

use App\Models\AnggaranSubKegiatanRenstra;
use App\Models\IndikatorSubKegiatan;
use App\Models\IndikatorTujuanDaerah;
use App\Models\Opd;
use App\Models\OpdKegiatan;
use App\Models\OpdProgram;
use App\Models\OpdSubKegiatan;
use App\Models\PeriodeTahun;
use App\Models\RenstraOpd;
use App\Models\Rpjmd;
use App\Models\RpjmdMisi;
use App\Models\RpjmdVisi;
use App\Models\SasaranOpd;
use App\Models\TargetIndikatorSubKegiatan;
use App\Models\TargetIndikatorTujuanDaerah;
use App\Models\TujuanDaerah;
use App\Models\TujuanOpd;
use App\Models\User;
use App\Services\Perencanaan\CancelDocumentRevisionService;
use App\Services\Perencanaan\DocumentRevisionService;
use App\Services\Perencanaan\DocumentVersionActivationService;
use App\Services\Workflow\WorkflowTransitionService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class DocumentVersioningTest extends TestCase
{
    use RefreshDatabase;

    public function test_rpjmd_revision_clones_cascading_without_changing_murni_version(): void
    {
        $this->seed();

        $actor = User::factory()->create();
        $period = PeriodeTahun::query()->firstOrFail();
        $rpjmd = Rpjmd::create([
            'judul' => 'RPJMD Murni',
            'tahun_awal' => 2025,
            'tahun_akhir' => 2029,
            'status' => 'approved',
        ]);
        $visi = RpjmdVisi::create(['rpjmd_id' => $rpjmd->id, 'visi' => 'Visi Murni', 'urutan' => 1]);
        $misi = RpjmdMisi::create([
            'rpjmd_id' => $rpjmd->id,
            'rpjmd_visi_id' => $visi->id,
            'misi' => 'Misi Murni',
            'urutan' => 1,
        ]);
        $tujuan = TujuanDaerah::create(['rpjmd_visi_id' => $visi->id, 'tujuan' => 'Tujuan Murni', 'urutan' => 1]);
        $misi->tujuanTerkait()->attach($tujuan->id, ['urutan' => 1]);
        $indikator = IndikatorTujuanDaerah::create([
            'tujuan_daerah_id' => $tujuan->id,
            'indikator' => 'Indikator Tujuan Murni',
            'urutan' => 1,
        ]);
        TargetIndikatorTujuanDaerah::create([
            'indikator_tujuan_daerah_id' => $indikator->id,
            'periode_tahun_id' => $period->id,
            'target_text' => '14,39-13,90',
        ]);

        $revision = app(DocumentRevisionService::class)->createRpjmdRevision($rpjmd, [
            'alasan_perubahan' => 'Penyesuaian target setelah perubahan kebijakan.',
            'dasar_perubahan' => 'Perda Perubahan',
            'tanggal_berlaku' => '2027-01-01',
        ], $actor);

        $this->assertSame('approved', $rpjmd->refresh()->status);
        $this->assertTrue($rpjmd->is_active_version);
        $this->assertSame('murni', $rpjmd->jenis_versi);
        $this->assertSame('Murni', $rpjmd->versionLabel());

        $this->assertSame('draft', $revision->status);
        $this->assertSame('perubahan', $revision->jenis_versi);
        $this->assertSame(2, $revision->nomor_versi);
        $this->assertSame('Perubahan I', $revision->versionLabel());
        $this->assertSame($rpjmd->id, $revision->parent_version_id);
        $this->assertFalse($revision->is_active_version);

        $revisionVisi = RpjmdVisi::query()->where('rpjmd_id', $revision->id)->sole();
        $revisionMisi = RpjmdMisi::query()->where('rpjmd_id', $revision->id)->sole();
        $revisionTujuan = TujuanDaerah::query()->where('rpjmd_visi_id', $revisionVisi->id)->sole();
        $revisionIndikator = IndikatorTujuanDaerah::query()->where('tujuan_daerah_id', $revisionTujuan->id)->sole();

        $this->assertDatabaseHas('tujuan_daerah_misi', [
            'tujuan_daerah_id' => $revisionTujuan->id,
            'rpjmd_misi_id' => $revisionMisi->id,
            'urutan' => 1,
        ]);
        $this->assertSame('Indikator Tujuan Murni', $revisionIndikator->indikator);
        $this->assertDatabaseHas('target_indikator_tujuan_daerah', [
            'indikator_tujuan_daerah_id' => $revisionIndikator->id,
            'periode_tahun_id' => $period->id,
            'target_text' => '14,39-13,90',
        ]);

        $revision->forceFill(['status' => 'approved'])->save();
        app(DocumentVersionActivationService::class)->activateAfterApproval($revision, $actor);

        $this->assertFalse($rpjmd->refresh()->is_active_version);
        $this->assertTrue($revision->refresh()->is_active_version);
        $this->assertSame($actor->id, $revision->disahkan_oleh);
    }

    public function test_rpjmd_revision_clones_general_mission_to_purpose_structure(): void
    {
        $this->seed();

        $actor = User::factory()->create();
        $rpjmd = Rpjmd::create([
            'judul' => 'RPJMD Struktur Umum',
            'tahun_awal' => 2030,
            'tahun_akhir' => 2034,
            'status' => 'approved',
            'struktur_tujuan_mode' => 'tujuan_per_misi',
        ]);
        $visi = RpjmdVisi::create(['rpjmd_id' => $rpjmd->id, 'visi' => 'Visi Umum', 'urutan' => 1]);
        $misi = RpjmdMisi::create([
            'rpjmd_id' => $rpjmd->id,
            'rpjmd_visi_id' => $visi->id,
            'misi' => 'Misi Umum',
            'urutan' => 1,
        ]);
        $tujuan = TujuanDaerah::create([
            'rpjmd_misi_id' => $misi->id,
            'tujuan' => 'Tujuan Struktur Umum',
            'urutan' => 1,
        ]);

        $revision = app(DocumentRevisionService::class)->createRpjmdRevision($rpjmd, [
            'alasan_perubahan' => 'Menyesuaikan struktur umum.',
        ], $actor);

        $revisionMisi = RpjmdMisi::query()->where('rpjmd_id', $revision->id)->sole();
        $revisionTujuan = TujuanDaerah::query()->where('rpjmd_misi_id', $revisionMisi->id)->sole();

        $this->assertNull($revisionTujuan->rpjmd_visi_id);
        $this->assertSame('Tujuan Struktur Umum', $revisionTujuan->tujuan);
    }

    public function test_cannot_create_more_than_one_unresolved_revision_in_a_document_lineage(): void
    {
        $this->seed();

        $actor = User::factory()->create();
        $rpjmd = Rpjmd::create([
            'judul' => 'RPJMD Pengujian Perubahan',
            'tahun_awal' => 2025,
            'tahun_akhir' => 2029,
            'status' => 'approved',
        ]);

        $service = app(DocumentRevisionService::class);
        $service->createRpjmdRevision($rpjmd, ['alasan_perubahan' => 'Perubahan pertama.'], $actor);

        $this->expectException(ValidationException::class);
        $service->createRpjmdRevision($rpjmd, ['alasan_perubahan' => 'Perubahan kedua.'], $actor);
    }

    public function test_approved_rpjmd_revision_marks_active_renstra_for_alignment(): void
    {
        $this->seed();

        $actor = User::factory()->create();
        $opd = Opd::create(['kode' => '9.98', 'nama' => 'OPD Penyesuaian', 'status' => 'active']);
        $rpjmd = Rpjmd::create([
            'judul' => 'RPJMD Acuan Awal',
            'tahun_awal' => 2025,
            'tahun_akhir' => 2029,
            'status' => 'approved',
        ]);
        $renstra = RenstraOpd::create([
            'opd_id' => $opd->id,
            'rpjmd_id' => $rpjmd->id,
            'judul' => 'Renstra Acuan Awal',
            'tahun_awal' => 2025,
            'tahun_akhir' => 2029,
            'status' => 'approved',
        ]);

        $revision = app(DocumentRevisionService::class)->createRpjmdRevision($rpjmd, [
            'alasan_perubahan' => 'RPJMD disesuaikan.',
        ], $actor);
        $revision->forceFill(['status' => 'approved'])->save();

        app(DocumentVersionActivationService::class)->activateAfterApproval($revision, $actor);

        $renstra->refresh();
        $this->assertTrue($renstra->perlu_penyesuaian_rpjmd);
        $this->assertSame($revision->id, $renstra->rpjmd_perubahan_terbaru_id);
        $this->assertNotNull($renstra->rpjmd_penyesuaian_terdeteksi_pada);

        $renstraRevision = app(DocumentRevisionService::class)->createRenstraRevision($renstra, [
            'alasan_perubahan' => 'Menyesuaikan Renstra dengan RPJMD Perubahan.',
        ], $actor);

        $this->assertSame($revision->id, $renstraRevision->rpjmd_id);
        $this->assertFalse($renstraRevision->perlu_penyesuaian_rpjmd);
    }

    public function test_draft_rpjmd_revision_can_be_canceled_and_previous_version_reactivated(): void
    {
        $this->seed();

        $actor = User::factory()->create();
        $opd = Opd::create(['kode' => '9.97', 'nama' => 'OPD Terdampak Perubahan', 'status' => 'active']);
        $rpjmd = Rpjmd::create([
            'judul' => 'RPJMD Murni untuk Dibatalkan',
            'tahun_awal' => 2025,
            'tahun_akhir' => 2029,
            'status' => 'approved',
            'is_active_version' => true,
        ]);
        $renstra = RenstraOpd::create([
            'opd_id' => $opd->id,
            'rpjmd_id' => $rpjmd->id,
            'judul' => 'Renstra Terdampak Perubahan RPJMD',
            'tahun_awal' => 2025,
            'tahun_akhir' => 2029,
            'status' => 'approved',
            'is_active_version' => true,
        ]);

        $revision = app(DocumentRevisionService::class)->createRpjmdRevision($rpjmd, [
            'alasan_perubahan' => 'Draf perubahan dibatalkan.',
        ], $actor);
        $renstra->forceFill([
            'perlu_penyesuaian_rpjmd' => true,
            'rpjmd_perubahan_terbaru_id' => $revision->id,
            'rpjmd_penyesuaian_terdeteksi_pada' => now(),
        ])->save();

        $previous = app(CancelDocumentRevisionService::class)->cancelRpjmdRevision($revision, [
            'alasan_pembatalan' => 'Perubahan tidak dilanjutkan.',
        ], $actor);

        $this->assertSame($rpjmd->id, $previous?->id);
        $this->assertTrue($rpjmd->refresh()->is_active_version);
        $this->assertSoftDeleted('rpjmd', ['id' => $revision->id]);
        $this->assertDatabaseHas('rpjmd', [
            'id' => $revision->id,
            'dibatalkan_oleh' => $actor->id,
            'alasan_pembatalan' => 'Perubahan tidak dilanjutkan.',
        ]);
        $this->assertDatabaseHas('renstra_opd', [
            'id' => $renstra->id,
            'perlu_penyesuaian_rpjmd' => false,
            'rpjmd_perubahan_terbaru_id' => null,
            'rpjmd_penyesuaian_terdeteksi_pada' => null,
        ]);
    }

    public function test_draft_renstra_revision_can_be_canceled_and_previous_version_reactivated(): void
    {
        $this->seed();

        $actor = User::factory()->create();
        $opd = Opd::create(['kode' => '9.96', 'nama' => 'OPD Pembatalan Renstra', 'status' => 'active']);
        $rpjmd = Rpjmd::create([
            'judul' => 'RPJMD Acuan Renstra Pembatalan',
            'tahun_awal' => 2025,
            'tahun_akhir' => 2029,
            'status' => 'approved',
            'is_active_version' => true,
        ]);
        $renstra = RenstraOpd::create([
            'opd_id' => $opd->id,
            'rpjmd_id' => $rpjmd->id,
            'judul' => 'Renstra Murni untuk Dibatalkan',
            'tahun_awal' => 2025,
            'tahun_akhir' => 2029,
            'status' => 'approved',
            'is_active_version' => true,
        ]);

        $revision = app(DocumentRevisionService::class)->createRenstraRevision($renstra, [
            'alasan_perubahan' => 'Draf perubahan Renstra dibatalkan.',
        ], $actor);

        $previous = app(CancelDocumentRevisionService::class)->cancelRenstraRevision($revision, [
            'alasan_pembatalan' => 'Tidak jadi melakukan perubahan Renstra.',
        ], $actor);

        $this->assertSame($renstra->id, $previous?->id);
        $this->assertTrue($renstra->refresh()->is_active_version);
        $this->assertSoftDeleted('renstra_opd', ['id' => $revision->id]);
        $this->assertDatabaseHas('renstra_opd', [
            'id' => $revision->id,
            'dibatalkan_oleh' => $actor->id,
            'alasan_pembatalan' => 'Tidak jadi melakukan perubahan Renstra.',
        ]);
    }

    public function test_rpjmd_revision_can_be_created_again_after_previous_draft_was_canceled(): void
    {
        $this->seed();

        $actor = User::factory()->create();
        $rpjmd = Rpjmd::create([
            'judul' => 'RPJMD Murni Bisa Perubahan Ulang',
            'tahun_awal' => 2025,
            'tahun_akhir' => 2029,
            'status' => 'approved',
            'is_active_version' => true,
        ]);

        $revisionService = app(DocumentRevisionService::class);
        $cancelService = app(CancelDocumentRevisionService::class);

        $firstRevision = $revisionService->createRpjmdRevision($rpjmd, [
            'alasan_perubahan' => 'Perubahan pertama dibatalkan.',
        ], $actor);
        $cancelService->cancelRpjmdRevision($firstRevision, [
            'alasan_pembatalan' => 'Tidak jadi memakai Perubahan pertama.',
        ], $actor);

        $secondRevision = $revisionService->createRpjmdRevision($rpjmd->fresh(), [
            'alasan_perubahan' => 'Membuat Perubahan ulang.',
        ], $actor);

        $this->assertSame(2, $secondRevision->nomor_versi);
        $this->assertSame($rpjmd->id, $secondRevision->root_version_id);
        $this->assertNotSame($firstRevision->id, $secondRevision->id);
        $this->assertSoftDeleted('rpjmd', ['id' => $firstRevision->id]);
        $this->assertDatabaseHas('rpjmd', [
            'id' => $secondRevision->id,
            'jenis_versi' => 'perubahan',
            'nomor_versi' => 2,
            'deleted_at' => null,
        ]);
    }

    public function test_renstra_revision_can_be_created_again_after_previous_draft_was_canceled(): void
    {
        $this->seed();

        $actor = User::factory()->create();
        $opd = Opd::create(['kode' => '9.95', 'nama' => 'OPD Perubahan Ulang', 'status' => 'active']);
        $rpjmd = Rpjmd::create([
            'judul' => 'RPJMD Acuan Perubahan Ulang',
            'tahun_awal' => 2025,
            'tahun_akhir' => 2029,
            'status' => 'approved',
            'is_active_version' => true,
        ]);
        $renstra = RenstraOpd::create([
            'opd_id' => $opd->id,
            'rpjmd_id' => $rpjmd->id,
            'judul' => 'Renstra Murni Bisa Perubahan Ulang',
            'tahun_awal' => 2025,
            'tahun_akhir' => 2029,
            'status' => 'approved',
            'is_active_version' => true,
        ]);

        $revisionService = app(DocumentRevisionService::class);
        $cancelService = app(CancelDocumentRevisionService::class);

        $firstRevision = $revisionService->createRenstraRevision($renstra, [
            'alasan_perubahan' => 'Perubahan Renstra pertama dibatalkan.',
        ], $actor);
        $cancelService->cancelRenstraRevision($firstRevision, [
            'alasan_pembatalan' => 'Tidak jadi memakai Perubahan Renstra pertama.',
        ], $actor);

        $secondRevision = $revisionService->createRenstraRevision($renstra->fresh(), [
            'alasan_perubahan' => 'Membuat Perubahan Renstra ulang.',
        ], $actor);

        $this->assertSame(2, $secondRevision->nomor_versi);
        $this->assertSame($renstra->id, $secondRevision->root_version_id);
        $this->assertNotSame($firstRevision->id, $secondRevision->id);
        $this->assertSoftDeleted('renstra_opd', ['id' => $firstRevision->id]);
        $this->assertDatabaseHas('renstra_opd', [
            'id' => $secondRevision->id,
            'jenis_versi' => 'perubahan',
            'nomor_versi' => 2,
            'deleted_at' => null,
        ]);
    }

    public function test_archived_document_version_cannot_be_processed_by_workflow(): void
    {
        $this->seed();

        $archived = Rpjmd::create([
            'judul' => 'RPJMD Arsip',
            'tahun_awal' => 2020,
            'tahun_akhir' => 2024,
            'status' => 'approved',
            'is_active_version' => false,
        ]);

        $this->assertTrue($archived->isArchivedVersion());

        $this->expectException(AuthorizationException::class);
        app(WorkflowTransitionService::class)->transition(
            $archived,
            'rpjmd',
            'submit',
            User::factory()->create(),
        );
    }

    public function test_renstra_revision_clones_sub_kegiatan_target_and_budget_snapshot(): void
    {
        $this->seed();

        $actor = User::factory()->create();
        $period = PeriodeTahun::query()->firstOrFail();
        $opd = Opd::create(['kode' => '9.99', 'nama' => 'OPD Uji Versi', 'status' => 'active']);
        $rpjmd = Rpjmd::create([
            'judul' => 'RPJMD Acuan',
            'tahun_awal' => 2025,
            'tahun_akhir' => 2029,
            'status' => 'approved',
        ]);
        $renstra = RenstraOpd::create([
            'opd_id' => $opd->id,
            'rpjmd_id' => $rpjmd->id,
            'judul' => 'Renstra Murni',
            'tahun_awal' => 2025,
            'tahun_akhir' => 2029,
            'status' => 'approved',
        ]);
        $tujuan = TujuanOpd::create(['renstra_opd_id' => $renstra->id, 'tujuan' => 'Tujuan OPD', 'urutan' => 1]);
        $sasaran = SasaranOpd::create(['tujuan_opd_id' => $tujuan->id, 'sasaran' => 'Sasaran OPD', 'urutan' => 1]);
        $program = OpdProgram::create([
            'renstra_opd_id' => $renstra->id,
            'sasaran_opd_id' => $sasaran->id,
            'nama' => 'Program OPD',
            'urutan' => 1,
        ]);
        $kegiatan = OpdKegiatan::create(['opd_program_id' => $program->id, 'nama' => 'Kegiatan OPD', 'urutan' => 1]);
        $subKegiatan = OpdSubKegiatan::create(['opd_kegiatan_id' => $kegiatan->id, 'nama' => 'Sub Kegiatan OPD', 'urutan' => 1]);
        $indikator = IndikatorSubKegiatan::create([
            'opd_sub_kegiatan_id' => $subKegiatan->id,
            'indikator' => 'Indikator Sub Kegiatan',
            'urutan' => 1,
        ]);
        TargetIndikatorSubKegiatan::create([
            'indikator_sub_kegiatan_id' => $indikator->id,
            'periode_tahun_id' => $period->id,
            'target_text' => '10 Dokumen',
        ]);
        AnggaranSubKegiatanRenstra::create([
            'opd_sub_kegiatan_id' => $subKegiatan->id,
            'periode_tahun_id' => $period->id,
            'anggaran' => 10000000,
        ]);

        $revision = app(DocumentRevisionService::class)->createRenstraRevision($renstra, [
            'alasan_perubahan' => 'Menyesuaikan pagu sub kegiatan.',
        ], $actor);

        $this->assertSame('approved', $renstra->refresh()->status);
        $this->assertTrue($renstra->is_active_version);
        $this->assertSame('Murni', $renstra->versionLabel());
        $this->assertSame('perubahan', $revision->jenis_versi);
        $this->assertSame('Perubahan I', $revision->versionLabel());
        $this->assertFalse($revision->is_active_version);

        $revisionSubKegiatan = OpdSubKegiatan::query()
            ->whereHas('kegiatan.program', fn ($query) => $query->where('renstra_opd_id', $revision->id))
            ->sole();
        $revisionIndicator = IndikatorSubKegiatan::query()
            ->where('opd_sub_kegiatan_id', $revisionSubKegiatan->id)
            ->sole();

        $this->assertDatabaseHas('target_indikator_sub_kegiatan', [
            'indikator_sub_kegiatan_id' => $revisionIndicator->id,
            'periode_tahun_id' => $period->id,
            'target_text' => '10 Dokumen',
        ]);
        $this->assertDatabaseHas('anggaran_sub_kegiatan_renstra', [
            'opd_sub_kegiatan_id' => $revisionSubKegiatan->id,
            'periode_tahun_id' => $period->id,
            'anggaran' => 10000000,
        ]);
    }
}
