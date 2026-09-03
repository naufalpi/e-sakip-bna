<?php

namespace Tests\Feature;

use App\Models\DpaOpd;
use App\Models\JabatanOrganisasi;
use App\Models\Opd;
use App\Models\Pegawai;
use App\Models\PeriodeTahun;
use App\Models\Permission;
use App\Models\RiwayatPejabatJabatan;
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

    public function test_dpa_is_created_as_budget_snapshot_without_monthly_cash_plan(): void
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
            'alokasi_tahun_sebelumnya' => '2000000.00',
            'pagu_rka' => '2500000.00',
            'pagu_dpa' => '2500000.00',
            'alokasi_tahun_berikutnya' => '2750000.00',
        ]);

        $item = $dpa->items->first();
        $this->assertCount(0, $item->cashPlans);
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

    public function test_dpa_readiness_does_not_require_apbd_legal_basis_or_monthly_cash_plan(): void
    {
        $dpa = $this->completeDpa();

        $readiness = app(DpaReadinessService::class)->inspect($dpa->fresh());

        $this->assertTrue($readiness['ready']);
        $this->assertNull($dpa->nomor_perda_apbd);
        $this->assertNull($dpa->nomor_perkada_penjabaran);
        $this->assertDatabaseCount('dpa_opd_cash_plans', 0);
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
            ->assertInertia(fn (Assert $page) => $page
                ->component('DpaOpd/Show')
                ->where('dpa.id', $dpa->id)
                ->has('items', 1)
                ->has('preview.rows', 6)
                ->where('preview.total.total', 2500000));
    }

    public function test_super_admin_uses_document_edit_mode_for_draft_and_verification_mode_after_submission(): void
    {
        $dpa = $this->completeDpa();
        $admin = $this->superAdmin();

        $this->actingAs($admin)->get(route('dpa-opd.edit', $dpa))->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('DpaOpd/Form')
                ->where('dpa.status', 'draft')
                ->where('canVerify', false));

        app(WorkflowTransitionService::class)->transition($dpa->fresh(), 'dpa_opd', 'submit', $admin);

        $this->actingAs($admin)->get(route('dpa-opd.edit', $dpa->fresh()))->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('DpaOpd/Form')
                ->where('dpa.status', 'submitted')
                ->where('canVerify', true));
    }

    public function test_dpa_signatories_are_selected_from_active_employee_placements_and_saved_as_snapshots(): void
    {
        $rka = $this->rka('murni', 'approved');
        [$budgetUser, $budgetPlacement] = $this->activeOfficial($rka->opd, 'Kepala Dinas Pengujian', 'jpt_pratama', '198001012000011001');
        $admin = $this->superAdmin();

        $this->actingAs($admin)
            ->get(route('dpa-opd.create'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('DpaOpd/Form')
                ->where('signatoryOptions.budgetUsers.0.placement_id', $budgetPlacement->id)
                ->where('signatoryOptions.budgetUsers.0.employee_id', $budgetUser->id));

        $this->actingAs($admin)
            ->post(route('dpa-opd.store'), [
                'rka_opd_id' => $rka->id,
                'judul' => 'DPA DINAS PENGUJIAN TAHUN ANGGARAN 2027',
                'pengguna_anggaran_penempatan_id' => $budgetPlacement->id,
            ])
            ->assertRedirect();

        $dpa = DpaOpd::query()->where('rka_opd_id', $rka->id)->firstOrFail();
        $this->assertSame($budgetUser->id, $dpa->pengguna_anggaran_pegawai_id);
        $this->assertSame($budgetPlacement->id, $dpa->pengguna_anggaran_penempatan_id);
        $this->assertSame($budgetUser->nama, $dpa->nama_pengguna_anggaran);
        $this->assertSame($budgetUser->nip, $dpa->nip_pengguna_anggaran);

        $bpkad = Opd::create(['kode' => '5.02', 'nama' => 'Badan Pengelolaan Keuangan dan Aset Daerah', 'singkatan' => 'BPKAD', 'status' => 'active']);
        [$ppkd, $ppkdPlacement] = $this->activeOfficial($bpkad, 'Kepala BPKAD selaku PPKD', 'jpt_pratama', '197001011990011001');
        $setda = Opd::create(['kode' => '4.01', 'nama' => 'Sekretariat Daerah', 'singkatan' => 'SETDA', 'status' => 'active']);
        [$secretary, $secretaryPlacement] = $this->activeOfficial($setda, 'Sekretaris Daerah', 'jpt_pratama', '197101011991011001');
        $dpa->update(['status' => 'submitted']);

        $this->actingAs($admin)
            ->put(route('dpa-opd.update', $dpa), [
                'judul' => $dpa->judul,
                'nomor_dpa' => 'DPA/A.1/TEST/2027',
                'tanggal_pengesahan' => '2026-12-30',
                'pengguna_anggaran_penempatan_id' => $budgetPlacement->id,
                'ppkd_penempatan_id' => $ppkdPlacement->id,
                'sekretaris_daerah_penempatan_id' => $secretaryPlacement->id,
            ])
            ->assertRedirect();

        $dpa->refresh();
        $this->assertSame($ppkd->id, $dpa->ppkd_pegawai_id);
        $this->assertSame($ppkdPlacement->id, $dpa->ppkd_penempatan_id);
        $this->assertSame($ppkd->nama, $dpa->nama_ppkd);
        $this->assertSame($secretary->id, $dpa->sekretaris_daerah_pegawai_id);
        $this->assertSame($secretaryPlacement->id, $dpa->sekretaris_daerah_penempatan_id);
        $this->assertSame($secretary->nip, $dpa->nip_sekretaris_daerah);
    }

    public function test_dpa_item_can_be_updated_without_monthly_cash_plan(): void
    {
        $dpa = $this->completeDpa();
        $item = $dpa->items->firstOrFail();

        $this->actingAs($this->superAdmin())
            ->put(route('dpa-opd.items.update', ['dpa_opd' => $dpa, 'item' => $item]), [
                'pagu_dpa' => '2.600.000',
                'alasan_penyesuaian' => 'Sesuai dokumen DPA resmi.',
                'catatan' => 'Pagu final.',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('dpa_opd_items', [
            'id' => $item->id,
            'pagu_dpa' => '2600000.00',
            'alasan_penyesuaian' => 'Sesuai dokumen DPA resmi.',
        ]);
    }

    public function test_draft_dpa_item_can_be_soft_deleted_without_deleting_rka_source(): void
    {
        $dpa = $this->completeDpa();
        $item = $dpa->items->firstOrFail();
        $rkaItemId = $item->rka_opd_item_id;

        $this->actingAs($this->superAdmin())
            ->delete(route('dpa-opd.items.destroy', ['dpa_opd' => $dpa, 'item' => $item]))
            ->assertRedirect()
            ->assertSessionHas('success', 'Sub kegiatan DPA berhasil dihapus.');

        $this->assertSoftDeleted('dpa_opd_items', ['id' => $item->id]);
        $this->assertDatabaseHas('rka_opd_items', ['id' => $rkaItemId, 'deleted_at' => null]);
    }

    public function test_dpa_item_cannot_be_deleted_when_document_is_not_editable(): void
    {
        $dpa = $this->completeDpa();
        $item = $dpa->items->firstOrFail();
        $dpa->update(['status' => 'submitted']);

        $this->actingAs($this->superAdmin())
            ->delete(route('dpa-opd.items.destroy', ['dpa_opd' => $dpa, 'item' => $item]))
            ->assertForbidden();

        $this->assertDatabaseHas('dpa_opd_items', ['id' => $item->id, 'deleted_at' => null]);
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
            'jenis_belanja' => 'operasi', 'alokasi_tahun_sebelumnya' => 2000000,
            'pagu_rka' => 2500000, 'alokasi_tahun_berikutnya' => 2750000, 'urutan' => 1,
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

    /** @return array{0: Pegawai, 1: RiwayatPejabatJabatan} */
    private function activeOfficial(Opd $opd, string $position, string $level, string $nip): array
    {
        $job = JabatanOrganisasi::create([
            'opd_id' => $opd->id,
            'nama' => $position,
            'level_jabatan' => $level,
            'status' => 'active',
            'verification_status' => 'verified',
        ]);
        $employee = Pegawai::create([
            'opd_id' => $opd->id,
            'nama' => str($position)->replace(' selaku PPKD', '')->toString(),
            'nip' => $nip,
            'jenis_pegawai' => 'pns',
            'status' => 'active',
        ]);
        $placement = $employee->penempatan()->create([
            'jabatan_organisasi_id' => $job->id,
            'nama_pejabat' => $employee->nama,
            'nip' => $employee->nip,
            'jenis_penugasan' => 'definitif',
            'tanggal_mulai' => '2026-01-01',
        ]);

        return [$employee, $placement];
    }
}
