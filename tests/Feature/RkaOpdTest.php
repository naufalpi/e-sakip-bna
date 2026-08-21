<?php

namespace Tests\Feature;

use App\Models\BidangUrusan;
use App\Models\KegiatanPemerintahan;
use App\Models\Opd;
use App\Models\PeriodeTahun;
use App\Models\ProgramPemerintahan;
use App\Models\RenjaOpd;
use App\Models\RenjaOpdItem;
use App\Models\Rkpd;
use App\Models\Role;
use App\Models\SubKegiatanPemerintahan;
use App\Models\UrusanPemerintahan;
use App\Models\User;
use App\Services\Penganggaran\RkaCreationService;
use App\Services\Workflow\WorkflowTransitionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class RkaOpdTest extends TestCase
{
    use RefreshDatabase;

    public function test_rka_is_created_as_a_complete_snapshot_from_established_renja(): void
    {
        [$renja, $item] = $this->renja('ditetapkan', 'approved');

        $rka = app(RkaCreationService::class)->createFromRenja($renja, [
            'judul' => 'RKA DINAS PENGUJIAN TAHUN ANGGARAN 2027',
            'nomor_kua' => 'KUA/2027/01',
            'nomor_ppas' => 'PPAS/2027/01',
        ]);

        $this->assertSame('murni', $rka->jenis_anggaran);
        $this->assertSame($renja->id, $rka->renja_opd_id);
        $this->assertSame('draft', $rka->status);
        $this->assertDatabaseHas('rka_opd_items', [
            'rka_opd_id' => $rka->id,
            'renja_opd_item_id' => $item->id,
            'kode_program' => '1.01.01',
            'kode_kegiatan' => '1.01.01.2.01',
            'kode_sub_kegiatan' => '1.01.01.2.01.0001',
            'pagu_renja' => '2500000.00',
            'pagu_usulan' => '2500000.00',
            'pagu_hasil_verifikasi' => '2500000.00',
        ]);
    }

    public function test_rka_change_is_derived_from_approved_renja_change(): void
    {
        [$renja] = $this->renja('perubahan', 'locked');

        $rka = app(RkaCreationService::class)->createFromRenja($renja, [
            'judul' => 'PERUBAHAN RKA DINAS PENGUJIAN TAHUN ANGGARAN 2027',
        ]);

        $this->assertSame('perubahan', $rka->jenis_anggaran);
        $this->assertSame('RKA Perubahan APBD', $rka->typeLabel());
    }

    public function test_rka_cannot_be_created_from_non_official_renja_or_created_twice(): void
    {
        [$draftRenja] = $this->renja('awal', 'draft');

        try {
            app(RkaCreationService::class)->createFromRenja($draftRenja, ['judul' => 'RKA UJI']);
            $this->fail('RKA seharusnya tidak dapat dibuat dari RENJA Awal draft.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('renja_opd_id', $exception->errors());
        }

        [$officialRenja] = $this->renja('ditetapkan', 'approved', 2028);
        app(RkaCreationService::class)->createFromRenja($officialRenja, ['judul' => 'RKA UJI 2028']);

        $this->expectException(ValidationException::class);
        app(RkaCreationService::class)->createFromRenja($officialRenja, ['judul' => 'RKA DUPLIKAT']);
    }

    public function test_super_admin_can_open_rka_index_and_detail_pages(): void
    {
        [$renja] = $this->renja('ditetapkan', 'approved');
        $rka = app(RkaCreationService::class)->createFromRenja($renja, ['judul' => 'RKA DINAS PENGUJIAN 2027']);
        $admin = User::factory()->create(['status' => 'active', 'email_verified_at' => now()]);
        $role = Role::create(['name' => 'super_admin', 'label' => 'Super Admin', 'is_system' => true]);
        $admin->roles()->attach($role);

        $this->actingAs($admin)
            ->get(route('rka-opd.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('RkaOpd/Index')->has('items.data', 1));

        $this->actingAs($admin)
            ->get(route('rka-opd.show', $rka))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('RkaOpd/Show')->where('rka.id', $rka->id)->has('items', 1));
    }

    public function test_rka_uses_submit_verify_and_approve_workflow(): void
    {
        [$renja] = $this->renja('ditetapkan', 'approved');
        $rka = app(RkaCreationService::class)->createFromRenja($renja, [
            'judul' => 'RKA DINAS PENGUJIAN 2027',
            'nomor_kua' => 'KUA/2027/01',
            'tanggal_kua' => '2026-11-01',
            'nomor_ppas' => 'PPAS/2027/01',
            'tanggal_ppas' => '2026-11-15',
        ]);
        $rka->items()->update(['jenis_belanja' => 'operasi']);
        $rka = $rka->fresh();
        $admin = User::factory()->create(['status' => 'active', 'email_verified_at' => now()]);
        $role = Role::create(['name' => 'super_admin', 'label' => 'Super Admin', 'is_system' => true]);
        $admin->roles()->attach($role);
        $workflow = app(WorkflowTransitionService::class);

        $workflow->transition($rka, 'rka_opd', 'submit', $admin);
        $workflow->transition($rka->fresh(), 'rka_opd', 'verify', $admin);
        $workflow->transition($rka->fresh(), 'rka_opd', 'approve', $admin);

        $this->assertSame('approved', $rka->fresh()->status);
        $this->assertDatabaseCount('workflow_histories', 3);
    }

    public function test_incomplete_rka_cannot_be_submitted(): void
    {
        [$renja] = $this->renja('ditetapkan', 'approved');
        $rka = app(RkaCreationService::class)->createFromRenja($renja, ['judul' => 'RKA BELUM LENGKAP']);
        $admin = User::factory()->create(['status' => 'active', 'email_verified_at' => now()]);
        $role = Role::create(['name' => 'super_admin', 'label' => 'Super Admin', 'is_system' => true]);
        $admin->roles()->attach($role);

        try {
            app(WorkflowTransitionService::class)->transition($rka, 'rka_opd', 'submit', $admin);
            $this->fail('RKA yang belum lengkap seharusnya tidak dapat diajukan.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('Nomor KUA belum diisi', $exception->errors()['action'][0]);
            $this->assertStringContainsString('jenis belanja', $exception->errors()['action'][0]);
        }

        $this->assertSame('draft', $rka->fresh()->status);
        $this->assertDatabaseCount('workflow_histories', 0);
    }

    /** @return array{RenjaOpd, RenjaOpdItem} */
    private function renja(string $version, string $status, int $year = 2027): array
    {
        $period = PeriodeTahun::create(['tahun' => $year, 'nama' => "Tahun {$year}", 'status' => 'active']);
        $urusan = UrusanPemerintahan::create(['kode' => "1.{$year}", 'nama' => 'Urusan Pengujian', 'status' => 'active']);
        $bidang = BidangUrusan::create(['urusan_pemerintahan_id' => $urusan->id, 'kode' => '1.01', 'nama' => 'Bidang Pengujian', 'status' => 'active']);
        $program = ProgramPemerintahan::create(['bidang_urusan_id' => $bidang->id, 'tahun_awal' => $year, 'tahun_akhir' => $year, 'kode' => '1.01.01', 'nama' => 'Program Pengujian', 'status' => 'active']);
        $kegiatan = KegiatanPemerintahan::create(['periode_tahun_id' => $period->id, 'program_pemerintahan_id' => $program->id, 'kode' => '1.01.01.2.01', 'nama' => 'Kegiatan Pengujian', 'status' => 'active']);
        $subKegiatan = SubKegiatanPemerintahan::create(['periode_tahun_id' => $period->id, 'kegiatan_pemerintahan_id' => $kegiatan->id, 'kode' => '1.01.01.2.01.0001', 'nama' => 'Sub Kegiatan Pengujian', 'indikator_sub_kegiatan' => 'Jumlah pengujian', 'status' => 'active']);
        $opd = Opd::create(['kode' => "1.01.{$year}", 'nama' => 'Dinas Pengujian', 'singkatan' => 'DISUJI', 'status' => 'active']);
        $rkpd = Rkpd::create(['periode_tahun_id' => $period->id, 'tahun' => $year, 'judul' => "RKPD {$year}", 'status' => 'approved', 'jenis_versi' => $version === 'perubahan' ? 'perubahan' : 'ditetapkan', 'nomor_versi' => 2, 'is_active_version' => true]);
        $renja = RenjaOpd::create(['rkpd_id' => $rkpd->id, 'opd_id' => $opd->id, 'periode_tahun_id' => $period->id, 'tahun' => $year, 'judul' => "RENJA DISUJI {$year}", 'status' => $status, 'jenis_versi' => $version, 'nomor_versi' => 2, 'is_active_version' => true]);
        $item = RenjaOpdItem::create([
            'renja_opd_id' => $renja->id,
            'program_pemerintahan_id' => $program->id,
            'kegiatan_pemerintahan_id' => $kegiatan->id,
            'sub_kegiatan_pemerintahan_id' => $subKegiatan->id,
            'kode' => $subKegiatan->kode,
            'nama_sub_kegiatan' => $subKegiatan->nama,
            'indikator' => 'Jumlah pengujian selesai',
            'target' => '12 Dokumen',
            'pagu_indikatif' => 2500000,
            'lokasi' => 'Kabupaten Banjarnegara',
            'sumber_dana' => 'Dana Alokasi Umum',
            'kelompok_sasaran' => 'Perangkat Daerah',
            'prakiraan_maju_pagu_indikatif' => 2750000,
            'status' => 'approved',
            'urutan' => 1,
        ]);

        return [$renja, $item];
    }
}
