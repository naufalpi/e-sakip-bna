<?php

namespace Tests\Feature;

use App\Models\Opd;
use App\Models\PeriodeTahun;
use App\Models\RenjaOpd;
use App\Models\Rkpd;
use App\Models\RkpdItem;
use App\Models\User;
use App\Services\Perencanaan\RkpdVersionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RkpdVersioningTest extends TestCase
{
    use RefreshDatabase;

    public function test_approving_initial_rkpd_creates_established_snapshot_without_breaking_existing_relations(): void
    {
        [$rkpd, $opd, $period] = $this->initialRkpd();
        $approver = User::factory()->create();

        $item = RkpdItem::create([
            'rkpd_id' => $rkpd->id,
            'opd_id' => $opd->id,
            'kode' => '1.01.01',
            'nama_urusan_bidang_program_kegiatan_sub' => 'SUB KEGIATAN UJI',
            'target' => '100',
            'pagu_indikatif' => 1500000,
            'status' => 'approved',
            'urutan' => 1,
        ]);

        $renja = RenjaOpd::create([
            'rkpd_id' => $rkpd->id,
            'opd_id' => $opd->id,
            'periode_tahun_id' => $period->id,
            'tahun' => 2027,
            'judul' => 'RENJA OPD TAHUN 2027',
            'status' => 'draft',
        ]);

        $established = app(RkpdVersionService::class)->publishAfterApproval($rkpd, $approver);

        $this->assertSame('ditetapkan', $established->jenis_versi);
        $this->assertTrue($established->is_active_version);
        $this->assertSame($rkpd->id, $established->parent_version_id);
        $this->assertDatabaseHas('rkpd_items', [
            'rkpd_id' => $established->id,
            'kode' => $item->kode,
            'pagu_indikatif' => '1500000.00',
        ]);
        $this->assertDatabaseHas('renja_opd', [
            'id' => $renja->id,
            'rkpd_id' => $rkpd->id,
        ]);
        $this->assertFalse($rkpd->fresh()->is_active_version);
        $this->assertSame(2, RkpdItem::query()->count());
    }

    public function test_rkpd_change_is_cloned_and_only_becomes_active_after_approval(): void
    {
        [$initial] = $this->initialRkpd();
        $approver = User::factory()->create();
        $established = app(RkpdVersionService::class)->publishAfterApproval($initial, $approver);

        $change = app(RkpdVersionService::class)->createChange($established, [
            'alasan_perubahan' => 'Penyesuaian prioritas pembangunan daerah.',
            'dasar_perubahan' => 'Perubahan RKPD Tahun 2027',
            'tanggal_berlaku' => '2027-07-01',
        ]);

        $this->assertSame('perubahan', $change->jenis_versi);
        $this->assertSame('draft', $change->status);
        $this->assertFalse($change->is_active_version);
        $this->assertFalse($change->isArchivedVersion());
        $this->assertTrue($established->fresh()->is_active_version);

        $change->update(['status' => 'approved']);
        $publishedChange = app(RkpdVersionService::class)->publishAfterApproval($change, $approver);

        $this->assertTrue($publishedChange->is_active_version);
        $this->assertFalse($established->fresh()->is_active_version);
        $this->assertSame(3, Rkpd::query()->where('root_version_id', $initial->id)->count());
    }

    /** @return array{Rkpd, Opd, PeriodeTahun} */
    private function initialRkpd(): array
    {
        $period = PeriodeTahun::create([
            'tahun' => 2027,
            'nama' => 'Tahun 2027',
            'status' => 'active',
        ]);
        $opd = Opd::create([
            'kode' => '1.01',
            'nama' => 'Dinas Pengujian',
            'status' => 'active',
        ]);
        $rkpd = Rkpd::create([
            'periode_tahun_id' => $period->id,
            'tahun' => 2027,
            'judul' => 'RKPD KABUPATEN BANJARNEGARA TAHUN 2027',
            'status' => 'approved',
            'jenis_versi' => 'awal',
            'nomor_versi' => 1,
            'is_active_version' => true,
        ]);

        return [$rkpd, $opd, $period];
    }
}
