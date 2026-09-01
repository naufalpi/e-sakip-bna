<?php

namespace Tests\Feature;

use App\Models\BidangUrusan;
use App\Models\KegiatanPemerintahan;
use App\Models\Opd;
use App\Models\PeriodeTahun;
use App\Models\PlanningSyncBatch;
use App\Models\ProgramPemerintahan;
use App\Models\RenjaOpd;
use App\Models\RenjaOpdItem;
use App\Models\Rkpd;
use App\Models\RkpdItem;
use App\Models\Role;
use App\Models\Rpjmd;
use App\Models\SubKegiatanPemerintahan;
use App\Models\UrusanPemerintahan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlanningSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_rkpd_can_preview_and_apply_rows_from_renja(): void
    {
        $this->seed();

        $fixture = $this->createPlanningFixture();
        $user = $this->createSuperAdmin();

        $fixture['renja']->update(['status' => 'submitted']);
        $renjaItem = RenjaOpdItem::create([
            'renja_opd_id' => $fixture['renja']->id,
            'program_pemerintahan_id' => $fixture['program']->id,
            'kegiatan_pemerintahan_id' => $fixture['kegiatan']->id,
            'sub_kegiatan_pemerintahan_id' => $fixture['sub_kegiatan']->id,
            'kode' => '9.99.03.2.01.0001',
            'nama_sub_kegiatan' => 'Pengelolaan Nama Domain',
            'indikator' => 'Jumlah dokumen pengelolaan domain',
            'target' => '12 dokumen',
            'pagu_indikatif' => 1500000,
            'lokasi' => 'Kabupaten Banjarnegara',
            'sumber_dana' => 'PAD',
            'prioritas_daerah' => 'Transformasi digital',
            'kelompok_sasaran' => 'Perangkat daerah',
            'prakiraan_maju_target' => '13 dokumen',
            'prakiraan_maju_pagu_indikatif' => 1750000,
            'status' => 'draft',
            'urutan' => 1,
        ]);

        $this->actingAs($user)
            ->post(route('rkpd.sync-renja.preview', $fixture['rkpd']))
            ->assertRedirect();

        $batch = PlanningSyncBatch::query()
            ->where('source_module', 'renja_opd')
            ->where('target_module', 'rkpd')
            ->firstOrFail();
        $row = $batch->rows()->firstOrFail();

        $this->assertSame('previewed', $batch->status);
        $this->assertSame('create', $row->action);
        $this->assertSame($renjaItem->id, $row->source_id);

        $this->actingAs($user)
            ->post(route('rkpd.sync-renja.apply', [$fixture['rkpd'], $batch]), [
                'selected_rows' => [$row->id],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('rkpd_items', [
            'rkpd_id' => $fixture['rkpd']->id,
            'renja_opd_id' => $fixture['renja']->id,
            'renja_opd_item_id' => $renjaItem->id,
            'opd_id' => $fixture['opd']->id,
            'sub_kegiatan_pemerintahan_id' => $fixture['sub_kegiatan']->id,
            'indikator' => 'Jumlah dokumen pengelolaan domain',
            'target' => '12 dokumen',
            'perangkat_daerah_penanggung_jawab' => 'Dinas Komunikasi dan Informatika',
        ]);
        $this->assertDatabaseHas('planning_sync_batches', [
            'id' => $batch->id,
            'status' => 'applied',
            'applied_by' => $user->id,
        ]);
        $this->assertDatabaseHas('activity_logs', [
            'action' => 'planning_sync_applied',
            'model_type' => PlanningSyncBatch::class,
            'model_id' => $batch->id,
        ]);
    }

    public function test_renja_can_preview_and_apply_rows_from_rkpd_for_its_opd(): void
    {
        $this->seed();

        $fixture = $this->createPlanningFixture();
        $user = $this->createSuperAdmin();

        $rkpdItem = RkpdItem::create([
            'rkpd_id' => $fixture['rkpd_official']->id,
            'opd_id' => $fixture['opd']->id,
            'program_pemerintahan_id' => $fixture['program']->id,
            'kegiatan_pemerintahan_id' => $fixture['kegiatan']->id,
            'sub_kegiatan_pemerintahan_id' => $fixture['sub_kegiatan']->id,
            'kode' => '9.99.03.2.01.0001',
            'nama_urusan_bidang_program_kegiatan_sub' => 'Pengelolaan Nama Domain',
            'indikator' => 'Jumlah dokumen pengelolaan domain RKPD',
            'target' => '10 dokumen',
            'pagu_indikatif' => 1250000,
            'lokasi' => 'Kabupaten Banjarnegara',
            'sumber_dana' => 'PAD',
            'prioritas_daerah' => 'Transformasi digital',
            'kelompok_sasaran' => 'Perangkat daerah',
            'prakiraan_maju_target' => '11 dokumen',
            'prakiraan_maju_pagu_indikatif' => 1400000,
            'perangkat_daerah_penanggung_jawab' => 'Dinas Komunikasi dan Informatika',
            'status' => 'draft',
            'urutan' => 1,
        ]);

        $this->actingAs($user)
            ->post(route('renja-opd.sync-rkpd.preview', $fixture['renja']))
            ->assertRedirect();

        $batch = PlanningSyncBatch::query()
            ->where('source_module', 'rkpd')
            ->where('target_module', 'renja_opd')
            ->firstOrFail();
        $row = $batch->rows()->firstOrFail();

        $this->assertSame('previewed', $batch->status);
        $this->assertSame('create', $row->action);
        $this->assertSame($rkpdItem->id, $row->source_id);

        $this->actingAs($user)
            ->post(route('renja-opd.sync-rkpd.apply', [$fixture['renja'], $batch]), [
                'selected_rows' => [$row->id],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('renja_opd_items', [
            'renja_opd_id' => $fixture['renja']->id,
            'program_pemerintahan_id' => $fixture['program']->id,
            'kegiatan_pemerintahan_id' => $fixture['kegiatan']->id,
            'sub_kegiatan_pemerintahan_id' => $fixture['sub_kegiatan']->id,
            'indikator' => 'Jumlah dokumen pengelolaan domain RKPD',
            'target' => '10 dokumen',
        ]);
        $this->assertDatabaseHas('planning_sync_batches', [
            'id' => $batch->id,
            'status' => 'applied',
            'applied_by' => $user->id,
        ]);
    }

    public function test_sync_rejects_draft_sources_and_non_editable_targets(): void
    {
        $this->seed();

        $fixture = $this->createPlanningFixture();
        $user = $this->createSuperAdmin();

        $this->actingAs($user)
            ->post(route('rkpd.sync-renja.preview', $fixture['rkpd']))
            ->assertSessionHasErrors('sync');

        $fixture['rkpd_official']->update(['status' => 'draft']);

        $this->actingAs($user)
            ->post(route('renja-opd.sync-rkpd.preview', $fixture['renja']))
            ->assertSessionHasErrors('sync');

        $fixture['rkpd']->update(['status' => 'submitted']);

        $this->actingAs($user)
            ->post(route('rkpd.sync-renja.preview', $fixture['rkpd']))
            ->assertSessionHasErrors('sync');
    }

    /**
     * @return array<string, mixed>
     */
    private function createPlanningFixture(): array
    {
        $periode = PeriodeTahun::query()->updateOrCreate(
            ['tahun' => 2026],
            ['nama' => 'Tahun 2026', 'status' => 'active'],
        );
        $urusan = UrusanPemerintahan::create([
            'kode' => '9',
            'nama' => 'Urusan Wajib Non Pelayanan Dasar',
            'status' => 'active',
        ]);
        $bidang = BidangUrusan::create([
            'urusan_pemerintahan_id' => $urusan->id,
            'kode' => '9.99',
            'nama' => 'Urusan Pemerintahan Bidang Komunikasi dan Informatika',
            'status' => 'active',
        ]);
        $opd = Opd::create([
            'urusan_pemerintahan_id' => $urusan->id,
            'kode' => '9.99.0.00.0.00.01.0000',
            'nama' => 'Dinas Komunikasi dan Informatika',
            'singkatan' => 'Diskominfo',
            'status' => 'active',
        ]);
        $program = ProgramPemerintahan::create([
            'bidang_urusan_id' => $bidang->id,
            'tahun_awal' => 2025,
            'tahun_akhir' => 2029,
            'kode' => '9.99.03',
            'nama' => 'Program Pengelolaan Aplikasi Informatika',
            'status' => 'active',
        ]);
        $kegiatan = KegiatanPemerintahan::create([
            'periode_tahun_id' => $periode->id,
            'program_pemerintahan_id' => $program->id,
            'kode' => '9.99.03.2.01',
            'nama' => 'Pengelolaan Nama Domain',
            'status' => 'active',
        ]);
        $subKegiatan = SubKegiatanPemerintahan::create([
            'periode_tahun_id' => $periode->id,
            'kegiatan_pemerintahan_id' => $kegiatan->id,
            'kode' => '9.99.03.2.01.0001',
            'nama' => 'Pengelolaan Nama Domain Pemerintah Daerah',
            'indikator_sub_kegiatan' => 'Jumlah dokumen pengelolaan domain',
            'status' => 'active',
        ]);
        $rpjmd = Rpjmd::create([
            'judul' => 'RPJMD Sinkronisasi',
            'tahun_awal' => 2025,
            'tahun_akhir' => 2029,
            'status' => 'approved',
        ]);
        $rkpd = Rkpd::create([
            'rpjmd_id' => $rpjmd->id,
            'periode_tahun_id' => $periode->id,
            'tahun' => 2026,
            'judul' => 'RKPD Sinkronisasi',
            'status' => 'draft',
            'jenis_versi' => 'awal',
            'nomor_versi' => 1,
            'is_active_version' => true,
        ]);
        $rkpdOfficial = Rkpd::create([
            'rpjmd_id' => $rpjmd->id,
            'periode_tahun_id' => $periode->id,
            'tahun' => 2026,
            'judul' => 'RKPD Ditetapkan Sinkronisasi',
            'status' => 'approved',
            'jenis_versi' => 'ditetapkan',
            'nomor_versi' => 2,
            'parent_version_id' => $rkpd->id,
            'root_version_id' => $rkpd->id,
            'is_active_version' => true,
        ]);
        $renja = RenjaOpd::create([
            'rkpd_id' => $rkpdOfficial->id,
            'opd_id' => $opd->id,
            'periode_tahun_id' => $periode->id,
            'tahun' => 2026,
            'judul' => 'RENJA Sinkronisasi',
            'status' => 'draft',
            'jenis_versi' => 'awal',
            'nomor_versi' => 1,
            'is_active_version' => false,
        ]);
        $renjaOfficial = RenjaOpd::create([
            'rkpd_id' => $rkpdOfficial->id,
            'opd_id' => $opd->id,
            'periode_tahun_id' => $periode->id,
            'tahun' => 2026,
            'judul' => 'RENJA Ditetapkan Sinkronisasi',
            'status' => 'approved',
            'jenis_versi' => 'ditetapkan',
            'nomor_versi' => 2,
            'parent_version_id' => $renja->id,
            'root_version_id' => $renja->id,
            'is_active_version' => true,
        ]);

        return [
            'periode' => $periode,
            'urusan' => $urusan,
            'bidang' => $bidang,
            'opd' => $opd,
            'program' => $program,
            'kegiatan' => $kegiatan,
            'sub_kegiatan' => $subKegiatan,
            'rpjmd' => $rpjmd,
            'rkpd' => $rkpd,
            'rkpd_official' => $rkpdOfficial,
            'renja' => $renja,
            'renja_official' => $renjaOfficial,
        ];
    }

    private function createSuperAdmin(): User
    {
        $user = User::factory()->create();
        $user->roles()->sync([Role::where('name', 'super_admin')->value('id')]);

        return $user;
    }
}
