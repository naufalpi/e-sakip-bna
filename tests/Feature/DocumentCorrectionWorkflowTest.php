<?php

namespace Tests\Feature;

use App\Models\DpaOpd;
use App\Models\Opd;
use App\Models\PeriodeTahun;
use App\Models\RenjaOpd;
use App\Models\RenstraOpd;
use App\Models\Role;
use App\Models\Rpjmd;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DocumentCorrectionWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_correct_approved_renstra_and_draft_descendant_is_preserved_for_alignment(): void
    {
        $this->seed();
        [$opd, $periode, $superAdmin] = $this->context();
        $rpjmd = $this->rpjmd($periode);
        $renstra = $this->renstra($opd, $periode, $rpjmd, 'approved');
        $renja = RenjaOpd::create([
            'renstra_opd_id' => $renstra->id,
            'opd_id' => $opd->id,
            'periode_tahun_id' => $periode->id,
            'tahun' => $periode->tahun,
            'judul' => 'RENJA Turunan Draft',
            'status' => 'draft',
        ]);

        $this->actingAs($superAdmin)
            ->post(route('workflow.transition', ['module' => 'renstra_opd', 'id' => $renstra->id]), [
                'action' => 'correct',
                'note' => 'Target salah input 25, seharusnya 20.',
                'correction_reference' => 'RENSTRA 2025-2029 halaman 47',
            ])
            ->assertRedirect();

        $this->assertSame('revision', $renstra->fresh()->status);
        $this->assertNull($renstra->fresh()->disahkan_oleh);
        $this->assertNull($renstra->fresh()->disahkan_pada);
        $this->assertSame('revision', $renja->fresh()->status);
        $this->assertDatabaseHas('workflow_histories', [
            'related_table' => 'renstra_opd',
            'related_id' => $renstra->id,
            'action' => 'correct',
            'from_status' => 'approved',
            'to_status' => 'revision',
            'actor_id' => $superAdmin->id,
        ]);
        $this->assertDatabaseHas('workflow_histories', [
            'related_table' => 'renja_opd',
            'related_id' => $renja->id,
            'action' => 'source_correction',
            'from_status' => 'draft',
            'to_status' => 'revision',
        ]);

        $this->actingAs($superAdmin)
            ->post(route('workflow.transition', ['module' => 'renja_opd', 'id' => $renja->id]), [
                'action' => 'submit',
            ])
            ->assertSessionHasErrors('action');
        $this->assertSame('revision', $renja->fresh()->status);

        $this->actingAs($superAdmin)
            ->post(route('workflow.transition', ['module' => 'renstra_opd', 'id' => $renstra->id]), ['action' => 'submit'])
            ->assertRedirect();
        $this->actingAs($superAdmin)
            ->post(route('workflow.transition', ['module' => 'renstra_opd', 'id' => $renstra->id]), ['action' => 'approve'])
            ->assertRedirect();
        $this->actingAs($superAdmin)
            ->post(route('workflow.transition', ['module' => 'renja_opd', 'id' => $renja->id]), ['action' => 'submit'])
            ->assertRedirect();
        $this->assertSame('submitted', $renja->fresh()->status);
    }

    public function test_approved_descendant_blocks_parent_correction(): void
    {
        $this->seed();
        [$opd, $periode, $superAdmin] = $this->context();
        $rpjmd = $this->rpjmd($periode);
        $renstra = $this->renstra($opd, $periode, $rpjmd, 'approved');
        RenjaOpd::create([
            'renstra_opd_id' => $renstra->id,
            'opd_id' => $opd->id,
            'periode_tahun_id' => $periode->id,
            'tahun' => $periode->tahun,
            'judul' => 'RENJA Turunan Resmi',
            'status' => 'approved',
        ]);

        $this->actingAs($superAdmin)
            ->post(route('workflow.transition', ['module' => 'renstra_opd', 'id' => $renstra->id]), [
                'action' => 'correct',
                'note' => 'Koreksi target sesuai dokumen asli.',
                'correction_reference' => 'RENSTRA halaman 47',
            ])
            ->assertSessionHasErrors('action');

        $this->assertSame('approved', $renstra->fresh()->status);
        $this->assertDatabaseMissing('workflow_histories', [
            'related_table' => 'renstra_opd',
            'related_id' => $renstra->id,
            'action' => 'correct',
        ]);
    }

    public function test_only_super_admin_can_start_correction_and_reference_is_required(): void
    {
        $this->seed();
        [$opd, $periode, $superAdmin] = $this->context();
        $reviewer = User::factory()->create();
        $reviewer->roles()->sync([Role::where('name', 'admin_kabupaten_bagian_organisasi')->value('id')]);
        $dpa = DpaOpd::create([
            'opd_id' => $opd->id,
            'periode_tahun_id' => $periode->id,
            'tahun' => $periode->tahun,
            'judul' => 'DPA Uji Koreksi',
            'status' => 'approved',
        ]);

        $payload = [
            'action' => 'correct',
            'note' => 'Nominal salah salin.',
            'correction_reference' => 'DPA resmi halaman 12',
        ];

        $this->actingAs($reviewer)
            ->post(route('workflow.transition', ['module' => 'dpa_opd', 'id' => $dpa->id]), $payload)
            ->assertForbidden();
        $this->assertSame('approved', $dpa->fresh()->status);

        $this->actingAs($superAdmin)
            ->post(route('workflow.transition', ['module' => 'dpa_opd', 'id' => $dpa->id]), [
                'action' => 'correct',
                'note' => 'Nominal salah salin.',
            ])
            ->assertSessionHasErrors('correction_reference');
        $this->assertSame('approved', $dpa->fresh()->status);

        $this->actingAs($superAdmin)
            ->post(route('workflow.transition', ['module' => 'dpa_opd', 'id' => $dpa->id]), $payload)
            ->assertRedirect();
        $this->assertSame('revision', $dpa->fresh()->status);
    }

    /** @return array{0: Opd, 1: PeriodeTahun, 2: User} */
    private function context(): array
    {
        $opd = Opd::create(['kode' => '9.91', 'nama' => 'OPD Koreksi', 'status' => 'active']);
        $periode = PeriodeTahun::where('status', 'active')->firstOrFail();
        $superAdmin = User::where('email', 'admin@example.test')->firstOrFail();

        return [$opd, $periode, $superAdmin];
    }

    private function rpjmd(PeriodeTahun $periode): Rpjmd
    {
        return Rpjmd::create([
            'periode_tahun_id' => $periode->id,
            'judul' => 'RPJMD Acuan Koreksi',
            'tahun_awal' => $periode->tahun,
            'tahun_akhir' => $periode->tahun + 4,
            'status' => 'approved',
            'is_active_version' => true,
        ]);
    }

    private function renstra(Opd $opd, PeriodeTahun $periode, Rpjmd $rpjmd, string $status): RenstraOpd
    {
        return RenstraOpd::create([
            'opd_id' => $opd->id,
            'rpjmd_id' => $rpjmd->id,
            'periode_tahun_id' => $periode->id,
            'judul' => 'RENSTRA Uji Koreksi',
            'tahun_awal' => $periode->tahun,
            'tahun_akhir' => $periode->tahun + 4,
            'status' => $status,
            'is_active_version' => true,
            'disahkan_oleh' => User::where('email', 'admin@example.test')->value('id'),
            'disahkan_pada' => now(),
        ]);
    }
}
