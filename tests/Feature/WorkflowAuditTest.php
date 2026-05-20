<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\EvaluasiSakip;
use App\Models\Notification;
use App\Models\Opd;
use App\Models\PeriodeTahun;
use App\Models\PerjanjianKinerja;
use App\Models\RenstraOpd;
use App\Models\Role;
use App\Models\Rpjmd;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class WorkflowAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_generic_workflow_records_history_notifications_and_locks_data(): void
    {
        $this->seed();

        [$opd, $periode, $adminOpd, $reviewer, $superAdmin] = $this->actors();
        $pk = PerjanjianKinerja::create([
            'opd_id' => $opd->id,
            'periode_tahun_id' => $periode->id,
            'tahun' => $periode->tahun,
            'judul' => 'PK Workflow',
            'status' => 'draft',
        ]);

        $this->actingAs($adminOpd)
            ->post(route('workflow.transition', ['module' => 'perjanjian_kinerja', 'id' => $pk->id]), [
                'action' => 'submit',
                'current_reviewer_id' => $reviewer->id,
                'note' => 'Mohon direview.',
            ])
            ->assertRedirect();

        $this->assertSame('submitted', $pk->fresh()->status);
        $this->assertDatabaseHas('workflow_submissions', [
            'related_table' => 'perjanjian_kinerja',
            'related_id' => $pk->id,
            'module' => 'perjanjian_kinerja',
            'status' => 'submitted',
            'submitted_by' => $adminOpd->id,
            'current_reviewer_id' => $reviewer->id,
        ]);
        $this->assertDatabaseHas('workflow_histories', [
            'related_table' => 'perjanjian_kinerja',
            'related_id' => $pk->id,
            'action' => 'submit',
            'from_status' => 'draft',
            'to_status' => 'submitted',
            'actor_id' => $adminOpd->id,
        ]);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $reviewer->id,
            'type' => 'workflow',
        ]);

        $this->actingAs($reviewer)
            ->post(route('workflow.transition', ['module' => 'perjanjian_kinerja', 'id' => $pk->id]), [
                'action' => 'approve',
                'note' => 'Disetujui.',
            ])
            ->assertRedirect();

        $this->assertSame('approved', $pk->fresh()->status);
        $this->assertDatabaseHas('workflow_histories', [
            'related_table' => 'perjanjian_kinerja',
            'related_id' => $pk->id,
            'action' => 'approve',
            'from_status' => 'submitted',
            'to_status' => 'approved',
            'actor_id' => $reviewer->id,
        ]);
        $this->assertTrue(Notification::query()->where('user_id', $adminOpd->id)->exists());

        foreach (['revision' => 'revision', 'reject' => 'rejected'] as $action => $status) {
            $this->actingAs($reviewer)
                ->post(route('workflow.transition', ['module' => 'perjanjian_kinerja', 'id' => $pk->id]), [
                    'action' => $action,
                ])
                ->assertRedirect();

            $this->assertSame($status, $pk->fresh()->status);
            $this->assertDatabaseHas('workflow_histories', [
                'related_table' => 'perjanjian_kinerja',
                'related_id' => $pk->id,
                'action' => $action,
                'to_status' => $status,
            ]);
        }

        $this->actingAs($superAdmin)
            ->post(route('workflow.transition', ['module' => 'perjanjian_kinerja', 'id' => $pk->id]), [
                'action' => 'lock',
            ])
            ->assertRedirect();

        $this->assertSame('locked', $pk->fresh()->status);

        $this->actingAs($adminOpd)
            ->patch(route('perjanjian-kinerja.update', $pk), [
                'opd_id' => $opd->id,
                'renstra_opd_id' => null,
                'periode_tahun_id' => $periode->id,
                'tahun' => $periode->tahun,
                'judul' => 'PK Tidak Boleh Diubah',
                'nomor_dokumen' => null,
                'status' => 'draft',
                'catatan' => null,
            ])
            ->assertForbidden();

        $this->actingAs($superAdmin)
            ->patch(route('perjanjian-kinerja.update', $pk), [
                'opd_id' => $opd->id,
                'renstra_opd_id' => null,
                'periode_tahun_id' => $periode->id,
                'tahun' => $periode->tahun,
                'judul' => 'PK Diubah Super Admin',
                'nomor_dokumen' => null,
                'status' => 'locked',
                'catatan' => null,
            ])
            ->assertRedirect();
    }

    public function test_workflow_supports_rpjmd_renstra_and_evaluasi_details_show_history(): void
    {
        $this->seed();

        [$opd, $periode, , , $superAdmin] = $this->actors();
        $rpjmd = Rpjmd::create([
            'periode_tahun_id' => $periode->id,
            'judul' => 'RPJMD Workflow',
            'tahun_awal' => $periode->tahun,
            'tahun_akhir' => $periode->tahun + 4,
            'status' => 'draft',
        ]);
        $renstra = RenstraOpd::create([
            'opd_id' => $opd->id,
            'rpjmd_id' => $rpjmd->id,
            'periode_tahun_id' => $periode->id,
            'judul' => 'Renstra Workflow',
            'tahun_awal' => $periode->tahun,
            'tahun_akhir' => $periode->tahun + 4,
            'status' => 'draft',
        ]);
        $evaluasi = EvaluasiSakip::create([
            'opd_id' => $opd->id,
            'periode_tahun_id' => $periode->id,
            'tahun' => $periode->tahun,
            'status' => 'draft',
        ]);

        foreach ([
            ['module' => 'rpjmd', 'model' => $rpjmd],
            ['module' => 'renstra_opd', 'model' => $renstra],
            ['module' => 'evaluasi_sakip', 'model' => $evaluasi],
        ] as $target) {
            $this->actingAs($superAdmin)
                ->post(route('workflow.transition', ['module' => $target['module'], 'id' => $target['model']->id]), [
                    'action' => 'submit',
                ])
                ->assertRedirect();

            $this->assertSame('submitted', $target['model']->fresh()->status);
            $this->assertDatabaseHas('workflow_histories', [
                'module' => $target['module'],
                'related_id' => $target['model']->id,
                'action' => 'submit',
            ]);
        }

        $this->actingAs($superAdmin)
            ->get(route('rpjmd.show', $rpjmd))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Rpjmd/Show')
                ->where('workflow.histories.0.action', 'submit')
            );
    }

    public function test_activity_log_records_create_update_delete_automatically(): void
    {
        $this->seed();

        $superAdmin = User::where('email', 'admin@example.test')->firstOrFail();

        $this->actingAs($superAdmin);
        $opd = Opd::create([
            'kode' => '9.99',
            'nama' => 'OPD Audit',
            'status' => 'active',
        ]);
        $opd->update(['nama' => 'OPD Audit Updated']);
        $opd->delete();

        $this->assertTrue(ActivityLog::query()->where('model_type', Opd::class)->where('model_id', $opd->id)->where('action', 'created')->exists());
        $this->assertTrue(ActivityLog::query()->where('model_type', Opd::class)->where('model_id', $opd->id)->where('action', 'updated')->exists());
        $this->assertTrue(ActivityLog::query()->where('model_type', Opd::class)->where('model_id', $opd->id)->where('action', 'deleted')->exists());

        $updatedLog = ActivityLog::query()
            ->where('model_type', Opd::class)
            ->where('model_id', $opd->id)
            ->where('action', 'updated')
            ->firstOrFail();

        $this->assertSame('OPD Audit Updated', $updatedLog->new_values['nama']);
    }

    /**
     * @return array{0: Opd, 1: PeriodeTahun, 2: User, 3: User, 4: User}
     */
    private function actors(): array
    {
        $opd = Opd::create(['kode' => '1.01', 'nama' => 'Dinas Kesehatan', 'status' => 'active']);
        $periode = PeriodeTahun::where('status', 'active')->firstOrFail();

        $adminOpd = User::factory()->create(['opd_id' => $opd->id]);
        $adminOpd->roles()->sync([Role::where('name', 'admin_opd')->value('id')]);

        $reviewer = User::factory()->create();
        $reviewer->roles()->sync([Role::where('name', 'admin_kabupaten_bagian_organisasi')->value('id')]);

        $superAdmin = User::where('email', 'admin@example.test')->firstOrFail();

        return [$opd, $periode, $adminOpd, $reviewer, $superAdmin];
    }
}
