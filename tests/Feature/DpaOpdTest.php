<?php

namespace Tests\Feature;

use App\Models\DpaOpd;
use App\Models\Opd;
use App\Models\PeriodeTahun;
use App\Models\Permission;
use App\Models\RkaOpd;
use App\Models\RkaOpdItem;
use App\Models\Role;
use App\Models\User;
use App\Services\Penganggaran\DpaCreationService;
use App\Services\Penganggaran\DpaReadinessService;
use App\Services\Workflow\WorkflowTransitionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DpaOpdTest extends TestCase
{
    use RefreshDatabase;

    public function test_dpa_is_created_as_snapshot_with_complete_monthly_cash_plan(): void
    {
        $rka = $this->rka('murni', 'approved');
        $dpa = app(DpaCreationService::class)->createFromRka($rka, [
            'judul' => 'DPA DINAS PENGUJIAN TAHUN ANGGARAN 2027',
        ]);

        $this->assertSame('DPA-SKPD', $dpa->typeLabel());
        $this->assertSame($rka->id, $dpa->rka_opd_id);
        $this->assertDatabaseHas('dpa_opd_items', [
            'dpa_opd_id' => $dpa->id,
            'kode_sub_kegiatan' => '1.01.01.2.01.0001',
            'pagu_rka' => '2500000.00',
            'pagu_dpa' => '2500000.00',
        ]);

        $item = $dpa->items->first();
        $this->assertCount(12, $item->cashPlans);
        $this->assertEqualsWithDelta(2500000, (float) $item->cashPlans->sum('jumlah'), 0.01);
        $this->assertSame(0.0, (float) $item->cashPlans->firstWhere('bulan', 1)->jumlah);
        $this->assertGreaterThan(0, (float) $item->cashPlans->firstWhere('bulan', 3)->jumlah);
    }

    public function test_dppa_is_derived_from_approved_change_rka(): void
    {
        $dpa = app(DpaCreationService::class)->createFromRka($this->rka('perubahan', 'locked'), [
            'judul' => 'DPPA DINAS PENGUJIAN TAHUN ANGGARAN 2027',
        ]);

        $this->assertSame('perubahan', $dpa->jenis_anggaran);
        $this->assertSame('DPPA-SKPD', $dpa->typeLabel());
    }

    public function test_dpa_cannot_be_created_from_unapproved_rka_or_created_twice(): void
    {
        try {
            app(DpaCreationService::class)->createFromRka($this->rka('murni', 'draft'), ['judul' => 'DPA DRAFT']);
            $this->fail('DPA seharusnya tidak dapat dibuat dari RKA draft.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('rka_opd_id', $exception->errors());
        }

        $rka = $this->rka('murni', 'approved', 2028);
        app(DpaCreationService::class)->createFromRka($rka, ['judul' => 'DPA 2028']);
        $this->expectException(ValidationException::class);
        app(DpaCreationService::class)->createFromRka($rka, ['judul' => 'DPA DUPLIKAT']);
    }

    public function test_dpa_readiness_detects_cash_plan_mismatch(): void
    {
        $dpa = $this->completeDpa();
        $item = $dpa->items->first();
        $item->cashPlans->first()->increment('jumlah', 1);

        $readiness = app(DpaReadinessService::class)->inspect($dpa->fresh());

        $this->assertFalse($readiness['ready']);
        $this->assertSame(1, $readiness['mismatched_items']);
    }

    public function test_dpa_workflow_requires_legalization_identity_before_approval(): void
    {
        $dpa = $this->completeDpa();
        $admin = $this->superAdmin();
        $workflow = app(WorkflowTransitionService::class);

        $workflow->transition($dpa->fresh(), 'dpa_opd', 'submit', $admin);
        $workflow->transition($dpa->fresh(), 'dpa_opd', 'verify', $admin);

        try {
            $workflow->transition($dpa->fresh(), 'dpa_opd', 'approve', $admin);
            $this->fail('DPA tanpa identitas pengesahan seharusnya tidak dapat disetujui.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('Nomor DPA belum diisi', $exception->errors()['action'][0]);
            $this->assertStringContainsString('Nama PPKD belum diisi', $exception->errors()['action'][0]);
        }

        $dpa->update([
            'nomor_dpa' => 'DPA/A.1/TEST/2027',
            'tanggal_pengesahan' => '2026-12-30',
            'nama_ppkd' => 'Kepala BPKAD',
            'nip_ppkd' => '197001011990011001',
            'nama_sekretaris_daerah' => 'Sekretaris Daerah',
            'nip_sekretaris_daerah' => '197101011991011001',
        ]);
        $workflow->transition($dpa->fresh(), 'dpa_opd', 'approve', $admin);

        $this->assertSame('approved', $dpa->fresh()->status);
        $this->assertDatabaseCount('workflow_histories', 3);
    }

    public function test_super_admin_can_open_dpa_index_and_detail(): void
    {
        $dpa = $this->completeDpa();
        $admin = $this->superAdmin();

        $this->actingAs($admin)->get(route('dpa-opd.index'))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('DpaOpd/Index')->has('items.data', 1));
        $this->actingAs($admin)->get(route('dpa-opd.show', $dpa))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('DpaOpd/Show')->where('dpa.id', $dpa->id)->has('items', 1)->has('summary.monthly_totals', 12));
    }

    public function test_bpkad_verifies_dpa_while_bapperida_only_monitors(): void
    {
        $dpa = $this->completeDpa();
        app(WorkflowTransitionService::class)->transition($dpa->fresh(), 'dpa_opd', 'submit', $this->superAdmin());

        $bpkad = User::factory()->create(['status' => 'active', 'email_verified_at' => now()]);
        $bpkad->roles()->attach(Role::query()->where('name', 'admin_kabupaten_bpkad')->firstOrFail());
        $bapperida = User::factory()->create(['status' => 'active', 'email_verified_at' => now()]);
        $bapperidaRole = Role::firstOrCreate(
            ['name' => 'admin_kabupaten_bapperida'],
            ['label' => 'Admin Kabupaten Bapperida', 'is_system' => true],
        );
        $bapperidaRole->permissions()->syncWithoutDetaching([
            Permission::query()->where('name', 'dpa.view')->firstOrFail()->id,
        ]);
        $bapperida->roles()->attach($bapperidaRole);

        $this->assertTrue($bpkad->can('verifyBudget', $dpa->fresh()));
        $this->assertFalse($bapperida->can('verifyBudget', $dpa->fresh()));
        $this->assertTrue($bapperida->can('view', $dpa->fresh()));
    }

    private function completeDpa(): DpaOpd
    {
        return app(DpaCreationService::class)->createFromRka($this->rka('murni', 'approved'), [
            'judul' => 'DPA DINAS PENGUJIAN TAHUN ANGGARAN 2027',
            'nomor_perda_apbd' => 'PERDA 1 TAHUN 2026',
            'tanggal_perda_apbd' => '2026-12-20',
            'nomor_perkada_penjabaran' => 'PERBUP 2 TAHUN 2026',
            'tanggal_perkada_penjabaran' => '2026-12-22',
            'nama_pengguna_anggaran' => 'Kepala Dinas Pengujian',
            'nip_pengguna_anggaran' => '198001012000011001',
        ]);
    }

    private function rka(string $type, string $status, int $year = 2027): RkaOpd
    {
        $period = PeriodeTahun::create(['tahun' => $year, 'nama' => "Tahun {$year}", 'status' => 'active']);
        $opd = Opd::create(['kode' => "1.01.{$year}", 'nama' => 'Dinas Pengujian', 'singkatan' => 'DISUJI', 'status' => 'active']);
        $rka = RkaOpd::create([
            'opd_id' => $opd->id, 'periode_tahun_id' => $period->id, 'tahun' => $year,
            'jenis_anggaran' => $type, 'judul' => "RKA DISUJI {$year}", 'status' => $status,
        ]);
        RkaOpdItem::create([
            'rka_opd_id' => $rka->id,
            'kode_urusan' => '1.01', 'nama_urusan' => 'Urusan Pengujian',
            'kode_program' => '1.01.01', 'nama_program' => 'Program Pengujian',
            'kode_kegiatan' => '1.01.01.2.01', 'nama_kegiatan' => 'Kegiatan Pengujian',
            'kode_sub_kegiatan' => '1.01.01.2.01.0001', 'nama_sub_kegiatan' => 'Sub Kegiatan Pengujian',
            'tolok_ukur_kinerja' => 'Jumlah dokumen pengujian', 'target_kinerja' => '12', 'satuan_kinerja' => 'Dokumen',
            'sumber_pendanaan' => 'Dana Alokasi Umum', 'lokasi' => 'Kabupaten Banjarnegara',
            'kelompok_sasaran' => 'Perangkat Daerah', 'bulan_mulai' => 3, 'bulan_selesai' => 11,
            'jenis_belanja' => 'operasi', 'pagu_hasil_verifikasi' => 2500000, 'urutan' => 1,
        ]);

        return $rka;
    }

    private function superAdmin(): User
    {
        $user = User::factory()->create(['status' => 'active', 'email_verified_at' => now()]);
        $role = Role::firstOrCreate(['name' => 'super_admin'], ['label' => 'Super Admin', 'is_system' => true]);
        $user->roles()->syncWithoutDetaching($role);

        return $user;
    }
}
