<?php

namespace Tests\Feature;

use App\Models\Opd;
use App\Models\PeriodeTahun;
use App\Models\RenjaOpd;
use App\Models\RkaOpd;
use App\Models\Rkpd;
use App\Models\Role;
use App\Models\User;
use App\Models\WorkflowSubmission;
use App\Services\Perencanaan\RenjaVersionService;
use App\Services\Perencanaan\RkpdVersionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DocumentEstablishmentCancellationTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_cancel_renja_establishment_and_restore_initial_version_to_draft(): void
    {
        [$rkpdInitial, $rkpdEstablished, $renjaInitial, $renjaEstablished] = $this->establishedDocuments();
        $superAdmin = $this->superAdmin();
        WorkflowSubmission::create([
            'related_table' => 'renja_opd',
            'related_id' => $renjaInitial->id,
            'module' => 'renja_opd',
            'status' => 'approved',
            'submitted_by' => $superAdmin->id,
            'submitted_at' => now(),
            'reviewed_at' => now(),
        ]);

        $this->actingAs($superAdmin)
            ->post(route('renja-opd.establishment.cancel', $renjaEstablished), [
                'alasan_pembatalan' => 'Penetapan dilakukan sebelum proses sinkronisasi selesai.',
                'konfirmasi' => 'BATALKAN',
            ])
            ->assertRedirect(route('renja-opd.show', $renjaInitial));

        $renjaInitial->refresh();
        $this->assertSame('draft', $renjaInitial->status);
        $this->assertTrue($renjaInitial->is_active_version);
        $this->assertNull($renjaInitial->submitted_by);
        $this->assertNull($renjaInitial->disahkan_oleh);
        $this->assertSoftDeleted('renja_opd', ['id' => $renjaEstablished->id]);
        $this->assertDatabaseHas('workflow_submissions', [
            'related_table' => 'renja_opd',
            'related_id' => $renjaInitial->id,
            'module' => 'renja_opd',
            'status' => 'draft',
            'submitted_by' => null,
        ]);
        $this->assertDatabaseHas('workflow_histories', [
            'related_table' => 'renja_opd',
            'related_id' => $renjaInitial->id,
            'action' => 'cancel_establishment',
            'from_status' => 'approved',
            'to_status' => 'draft',
            'actor_id' => $superAdmin->id,
        ]);

        $this->assertSame('approved', $rkpdInitial->fresh()->status);
        $this->assertTrue($rkpdEstablished->fresh()->is_active_version);
    }

    public function test_renja_establishment_cannot_be_cancelled_while_rka_exists(): void
    {
        [, , $renjaInitial, $renjaEstablished, $period, $opd] = $this->establishedDocuments();
        $superAdmin = $this->superAdmin();
        RkaOpd::create([
            'renja_opd_id' => $renjaEstablished->id,
            'rkpd_id' => $renjaEstablished->rkpd_id,
            'opd_id' => $opd->id,
            'periode_tahun_id' => $period->id,
            'tahun' => $period->tahun,
            'jenis_anggaran' => 'murni',
            'judul' => 'RKA Turunan RENJA',
            'status' => 'draft',
        ]);

        $this->actingAs($superAdmin)
            ->from(route('renja-opd.show', $renjaEstablished))
            ->post(route('renja-opd.establishment.cancel', $renjaEstablished), [
                'alasan_pembatalan' => 'Penetapan dilakukan sebelum proses sinkronisasi selesai.',
                'konfirmasi' => 'BATALKAN',
            ])
            ->assertRedirect(route('renja-opd.show', $renjaEstablished))
            ->assertSessionHasErrors('document');

        $this->assertSame('approved', $renjaInitial->fresh()->status);
        $this->assertTrue($renjaEstablished->fresh()->is_active_version);
        $this->assertNull($renjaEstablished->fresh()->deleted_at);
    }

    public function test_rkpd_establishment_can_only_be_cancelled_after_renja_establishment(): void
    {
        [$rkpdInitial, $rkpdEstablished, $renjaInitial, $renjaEstablished] = $this->establishedDocuments();
        $superAdmin = $this->superAdmin();
        $payload = [
            'alasan_pembatalan' => 'Penetapan dilakukan sebelum kompilasi RENJA diselesaikan.',
            'konfirmasi' => 'BATALKAN',
        ];

        $this->actingAs($superAdmin)
            ->from(route('rkpd.show', $rkpdEstablished))
            ->post(route('rkpd.establishment.cancel', $rkpdEstablished), $payload)
            ->assertSessionHasErrors('document');

        $this->actingAs($superAdmin)
            ->post(route('renja-opd.establishment.cancel', $renjaEstablished), $payload)
            ->assertRedirect(route('renja-opd.show', $renjaInitial));

        $renjaInitial->update(['status' => 'submitted']);

        $this->actingAs($superAdmin)
            ->post(route('rkpd.establishment.cancel', $rkpdEstablished), $payload)
            ->assertRedirect(route('rkpd.show', $rkpdInitial));

        $this->assertSame('draft', $rkpdInitial->fresh()->status);
        $this->assertTrue($rkpdInitial->fresh()->is_active_version);
        $this->assertSoftDeleted('rkpd', ['id' => $rkpdEstablished->id]);
        $this->assertSame('submitted', $renjaInitial->fresh()->status);

        $rkpdInitial->update(['status' => 'approved']);
        $republished = app(RkpdVersionService::class)->publishAfterApproval($rkpdInitial, $superAdmin);
        $this->assertNotSame($rkpdEstablished->id, $republished->id);
        $this->assertSame('ditetapkan', $republished->jenis_versi);
    }

    public function test_non_super_admin_cannot_cancel_document_establishment(): void
    {
        [, , , $renjaEstablished] = $this->establishedDocuments();

        $this->actingAs(User::factory()->create())
            ->post(route('renja-opd.establishment.cancel', $renjaEstablished), [
                'alasan_pembatalan' => 'Penetapan dilakukan sebelum proses sinkronisasi selesai.',
                'konfirmasi' => 'BATALKAN',
            ])
            ->assertForbidden();

        $this->assertTrue($renjaEstablished->fresh()->is_active_version);
    }

    /** @return array{Rkpd, Rkpd, RenjaOpd, RenjaOpd, PeriodeTahun, Opd} */
    private function establishedDocuments(): array
    {
        $period = PeriodeTahun::create([
            'tahun' => 2094,
            'nama' => 'Tahun 2094',
            'status' => 'active',
        ]);
        $opd = Opd::create([
            'kode' => '9.94',
            'nama' => 'Dinas Uji Pembatalan Penetapan',
            'status' => 'active',
        ]);
        $approver = User::factory()->create();
        $rkpdInitial = Rkpd::create([
            'periode_tahun_id' => $period->id,
            'tahun' => $period->tahun,
            'judul' => 'RKPD Awal Tahun 2094',
            'status' => 'approved',
            'jenis_versi' => 'awal',
            'nomor_versi' => 1,
            'is_active_version' => true,
        ]);
        $rkpdEstablished = app(RkpdVersionService::class)->publishAfterApproval($rkpdInitial, $approver);
        $renjaInitial = RenjaOpd::create([
            'rkpd_id' => $rkpdInitial->id,
            'opd_id' => $opd->id,
            'periode_tahun_id' => $period->id,
            'tahun' => $period->tahun,
            'judul' => 'RENJA Awal Tahun 2094',
            'status' => 'approved',
            'jenis_versi' => 'awal',
            'nomor_versi' => 1,
            'is_active_version' => true,
        ]);
        $renjaEstablished = app(RenjaVersionService::class)->publishAfterApproval($renjaInitial, $approver);

        return [$rkpdInitial, $rkpdEstablished, $renjaInitial, $renjaEstablished, $period, $opd];
    }

    private function superAdmin(): User
    {
        $role = Role::firstOrCreate(
            ['name' => 'super_admin'],
            ['label' => 'Super Admin', 'is_system' => true],
        );
        $user = User::factory()->create();
        $user->roles()->sync([$role->id]);

        return $user;
    }
}
