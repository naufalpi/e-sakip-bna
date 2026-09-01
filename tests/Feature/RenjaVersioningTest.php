<?php

namespace Tests\Feature;

use App\Models\Opd;
use App\Models\PeriodeTahun;
use App\Models\RenjaOpd;
use App\Models\RenjaOpdItem;
use App\Models\Rkpd;
use App\Models\User;
use App\Services\Perencanaan\RenjaVersionService;
use App\Services\Perencanaan\RkpdVersionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class RenjaVersioningTest extends TestCase
{
    use RefreshDatabase;

    public function test_approving_initial_renja_creates_established_snapshot_on_established_rkpd(): void
    {
        [$renja, $rkpdInitial, $item] = $this->initialRenja();
        $approver = User::factory()->create();
        $rkpdEstablished = app(RkpdVersionService::class)->publishAfterApproval($rkpdInitial, $approver);

        $established = app(RenjaVersionService::class)->publishAfterApproval($renja, $approver);

        $this->assertSame('ditetapkan', $established->jenis_versi);
        $this->assertSame($rkpdEstablished->id, $established->rkpd_id);
        $this->assertSame($renja->id, $established->parent_version_id);
        $this->assertTrue($established->is_active_version);
        $this->assertFalse($renja->fresh()->is_active_version);
        $this->assertDatabaseHas('renja_opd_items', [
            'renja_opd_id' => $established->id,
            'kode' => $item->kode,
            'pagu_indikatif' => '2500000.00',
        ]);
        $this->assertDatabaseHas('renja_opd_items', [
            'id' => $item->id,
            'renja_opd_id' => $renja->id,
        ]);
    }

    public function test_renja_change_uses_rkpd_change_and_only_becomes_active_after_approval(): void
    {
        [$renja, $rkpdInitial] = $this->initialRenja();
        $approver = User::factory()->create();
        $rkpdEstablished = app(RkpdVersionService::class)->publishAfterApproval($rkpdInitial, $approver);
        $renjaEstablished = app(RenjaVersionService::class)->publishAfterApproval($renja, $approver);

        $rkpdChange = app(RkpdVersionService::class)->createChange($rkpdEstablished, [
            'alasan_perubahan' => 'Penyesuaian prioritas daerah.',
        ]);

        $renjaChange = app(RenjaVersionService::class)->createChange($renjaEstablished, [
            'alasan_perubahan' => 'Penyesuaian target dan pagu OPD.',
            'dasar_perubahan' => 'RKPD Perubahan Tahun 2027',
            'tanggal_berlaku' => '2027-07-01',
        ]);

        $this->assertSame('perubahan', $renjaChange->jenis_versi);
        $this->assertSame($rkpdChange->id, $renjaChange->rkpd_id);
        $this->assertSame('draft', $renjaChange->status);
        $this->assertFalse($renjaChange->is_active_version);
        $this->assertFalse($renjaChange->isArchivedVersion());
        $this->assertTrue($renjaEstablished->fresh()->is_active_version);

        try {
            app(RenjaVersionService::class)->publishAfterApproval($renjaChange, $approver);
            $this->fail('RENJA Perubahan belum boleh ditetapkan sebelum RKPD Perubahan disetujui.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('document', $exception->errors());
        }

        $rkpdChange->update(['status' => 'approved']);
        app(RkpdVersionService::class)->publishAfterApproval($rkpdChange, $approver);

        $renjaChange->update(['status' => 'approved']);
        $published = app(RenjaVersionService::class)->publishAfterApproval($renjaChange, $approver);

        $this->assertTrue($published->is_active_version);
        $this->assertFalse($renjaEstablished->fresh()->is_active_version);
        $this->assertSame(3, RenjaOpd::query()->where('root_version_id', $renja->id)->count());
    }

    public function test_initial_renja_cannot_be_established_before_rkpd_is_established(): void
    {
        [$renja] = $this->initialRenja();

        $this->expectException(ValidationException::class);

        app(RenjaVersionService::class)->publishAfterApproval($renja, User::factory()->create());
    }

    /** @return array{RenjaOpd, Rkpd, RenjaOpdItem} */
    private function initialRenja(): array
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
        $renja = RenjaOpd::create([
            'rkpd_id' => $rkpd->id,
            'opd_id' => $opd->id,
            'periode_tahun_id' => $period->id,
            'tahun' => 2027,
            'judul' => 'RENJA DINAS PENGUJIAN TAHUN 2027',
            'status' => 'approved',
            'jenis_versi' => 'awal',
            'nomor_versi' => 1,
            'is_active_version' => true,
        ]);
        $item = RenjaOpdItem::create([
            'renja_opd_id' => $renja->id,
            'kode' => '1.01.01.2.01.0001',
            'nama_sub_kegiatan' => 'SUB KEGIATAN UJI',
            'indikator' => 'Persentase pengujian',
            'target' => '100%',
            'pagu_indikatif' => 2500000,
            'status' => 'approved',
            'urutan' => 1,
        ]);

        return [$renja, $rkpd, $item];
    }
}
