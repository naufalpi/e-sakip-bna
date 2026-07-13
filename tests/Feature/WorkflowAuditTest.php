<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\EvaluasiSakip;
use App\Models\IndikatorTujuanOpd;
use App\Models\Notification;
use App\Models\Opd;
use App\Models\PeriodeTahun;
use App\Models\PerjanjianKinerja;
use App\Models\PredikatEvaluasi;
use App\Models\ProgramRpjmd;
use App\Models\ProgramRpjmdOpdPenanggungJawab;
use App\Models\RekomendasiEvaluasi;
use App\Models\RenstraOpd;
use App\Models\Role;
use App\Models\Rpjmd;
use App\Models\TargetIndikatorTujuanOpd;
use App\Models\TujuanOpd;
use App\Models\User;
use App\Services\Notifications\RekomendasiDeadlineReminderService;
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
            'type' => 'workflow_submitted',
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
        $this->assertTrue(Notification::query()->where('user_id', $adminOpd->id)->where('type', 'workflow_approved')->exists());

        $this->actingAs($superAdmin)
            ->post(route('workflow.transition', ['module' => 'perjanjian_kinerja', 'id' => $pk->id]), [
                'action' => 'lock',
            ])
            ->assertRedirect();

        $this->assertSame('locked', $pk->fresh()->status);
        $this->assertTrue(Notification::query()->where('user_id', $adminOpd->id)->where('type', 'workflow_locked')->exists());

        foreach (['revision' => 'revision', 'reject' => 'rejected'] as $action => $status) {
            $reviewPk = PerjanjianKinerja::create([
                'opd_id' => $opd->id,
                'periode_tahun_id' => $periode->id,
                'tahun' => $periode->tahun,
                'judul' => "PK Workflow {$action}",
                'status' => 'submitted',
            ]);

            $this->actingAs($reviewer)
                ->post(route('workflow.transition', ['module' => 'perjanjian_kinerja', 'id' => $reviewPk->id]), [
                    'action' => $action,
                    'note' => "Catatan {$action}.",
                ])
                ->assertRedirect();

            $this->assertSame($status, $reviewPk->fresh()->status);
            $this->assertDatabaseHas('workflow_histories', [
                'related_table' => 'perjanjian_kinerja',
                'related_id' => $reviewPk->id,
                'action' => $action,
                'from_status' => 'submitted',
                'to_status' => $status,
            ]);
            $this->assertTrue(Notification::query()
                ->where('user_id', $adminOpd->id)
                ->where('type', $action === 'revision' ? 'workflow_revision' : 'workflow_rejected')
                ->exists());
        }

        $this->actingAs($reviewer)
            ->post(route('workflow.transition', ['module' => 'perjanjian_kinerja', 'id' => $pk->id]), [
                'action' => 'revision',
                'note' => 'Tidak boleh revisi data terkunci.',
            ])
            ->assertForbidden();

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

    public function test_workflow_rejects_invalid_status_transition(): void
    {
        $this->seed();

        [$opd, $periode, , $reviewer] = $this->actors();
        $pk = PerjanjianKinerja::create([
            'opd_id' => $opd->id,
            'periode_tahun_id' => $periode->id,
            'tahun' => $periode->tahun,
            'judul' => 'PK Invalid Transition',
            'status' => 'draft',
        ]);

        $this->actingAs($reviewer)
            ->post(route('workflow.transition', ['module' => 'perjanjian_kinerja', 'id' => $pk->id]), [
                'action' => 'approve',
            ])
            ->assertSessionHasErrors('action');

        $this->assertSame('draft', $pk->fresh()->status);
        $this->assertDatabaseMissing('workflow_histories', [
            'related_table' => 'perjanjian_kinerja',
            'related_id' => $pk->id,
            'action' => 'approve',
        ]);
    }

    public function test_workflow_requires_note_for_revision_and_rejection(): void
    {
        $this->seed();

        [$opd, $periode, , $reviewer] = $this->actors();
        $pk = PerjanjianKinerja::create([
            'opd_id' => $opd->id,
            'periode_tahun_id' => $periode->id,
            'tahun' => $periode->tahun,
            'judul' => 'PK Butuh Catatan',
            'status' => 'submitted',
        ]);

        $this->actingAs($reviewer)
            ->post(route('workflow.transition', ['module' => 'perjanjian_kinerja', 'id' => $pk->id]), [
                'action' => 'revision',
            ])
            ->assertSessionHasErrors('note');

        $this->assertSame('submitted', $pk->fresh()->status);
        $this->assertDatabaseMissing('workflow_histories', [
            'related_table' => 'perjanjian_kinerja',
            'related_id' => $pk->id,
            'action' => 'revision',
        ]);
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

    public function test_activity_log_covers_strategic_target_predicate_and_cascading_assignment_models(): void
    {
        $this->seed();

        [$opd, $periode, , , $superAdmin] = $this->actors();
        $this->actingAs($superAdmin);

        $rpjmd = Rpjmd::create([
            'periode_tahun_id' => $periode->id,
            'judul' => 'RPJMD Audit Coverage',
            'tahun_awal' => $periode->tahun,
            'tahun_akhir' => $periode->tahun + 4,
            'status' => 'approved',
        ]);
        $program = ProgramRpjmd::create([
            'nama' => 'Program Audit Cascading',
            'status' => 'approved',
            'urutan' => 1,
        ]);
        $assignment = ProgramRpjmdOpdPenanggungJawab::create([
            'program_rpjmd_id' => $program->id,
            'opd_id' => $opd->id,
            'peran' => 'penanggung_jawab',
            'is_utama' => true,
        ]);

        $renstra = RenstraOpd::create([
            'opd_id' => $opd->id,
            'rpjmd_id' => $rpjmd->id,
            'periode_tahun_id' => $periode->id,
            'judul' => 'Renstra Audit Target',
            'tahun_awal' => $periode->tahun,
            'tahun_akhir' => $periode->tahun + 4,
            'status' => 'approved',
        ]);
        $tujuan = TujuanOpd::create([
            'renstra_opd_id' => $renstra->id,
            'kode' => 'T-AUDIT',
            'tujuan' => 'Tujuan audit target',
            'urutan' => 1,
        ]);
        $indikator = IndikatorTujuanOpd::create([
            'tujuan_opd_id' => $tujuan->id,
            'kode' => 'IT-AUDIT',
            'indikator' => 'Indikator audit target',
            'tipe_indikator' => 'positif',
            'urutan' => 1,
        ]);
        $target = TargetIndikatorTujuanOpd::create([
            'indikator_tujuan_opd_id' => $indikator->id,
            'periode_tahun_id' => $periode->id,
            'target' => 90,
            'target_text' => '90 persen',
        ]);
        $predikat = PredikatEvaluasi::create([
            'kode' => 'ZZ',
            'nama' => 'Predikat Audit',
            'nilai_min' => 95,
            'nilai_max' => 100,
            'warna' => 'emerald',
            'is_active' => true,
        ]);

        $target->update(['target_text' => '91 persen']);
        $predikat->delete();

        foreach ([
            ProgramRpjmdOpdPenanggungJawab::class => $assignment->id,
            TargetIndikatorTujuanOpd::class => $target->id,
            PredikatEvaluasi::class => $predikat->id,
        ] as $class => $id) {
            $this->assertDatabaseHas('activity_logs', [
                'model_type' => $class,
                'model_id' => $id,
                'action' => 'created',
            ]);
        }

        $this->assertDatabaseHas('activity_logs', [
            'model_type' => TargetIndikatorTujuanOpd::class,
            'model_id' => $target->id,
            'action' => 'updated',
        ]);
        $this->assertDatabaseHas('activity_logs', [
            'model_type' => PredikatEvaluasi::class,
            'model_id' => $predikat->id,
            'action' => 'deleted',
        ]);
    }

    public function test_rekomendasi_deadline_reminder_creates_deduplicated_notifications(): void
    {
        $this->seed();

        [$opd, $periode, $adminOpd, $reviewer] = $this->actors();
        $evaluasi = EvaluasiSakip::create([
            'opd_id' => $opd->id,
            'periode_tahun_id' => $periode->id,
            'tahun' => $periode->tahun,
            'status' => 'approved',
            'nilai_akhir' => 80,
            'predikat' => 'A',
        ]);
        $rekomendasi = RekomendasiEvaluasi::create([
            'evaluasi_sakip_id' => $evaluasi->id,
            'opd_id' => $opd->id,
            'nomor' => 'REC/001',
            'rekomendasi' => 'Lengkapi tindak lanjut rekomendasi.',
            'prioritas' => 'tinggi',
            'status_tindak_lanjut' => 'belum',
            'target_tanggal' => now()->addDays(3)->toDateString(),
        ]);

        $service = app(RekomendasiDeadlineReminderService::class);

        $this->assertSame(2, $service->send(7));
        $this->assertSame(0, $service->send(7));

        foreach ([$adminOpd->id, $reviewer->id] as $userId) {
            $this->assertDatabaseHas('notifications', [
                'user_id' => $userId,
                'type' => 'rekomendasi_deadline',
                'title' => 'Pengingat deadline tindak lanjut rekomendasi',
            ]);
        }

        $this->assertSame(2, Notification::query()
            ->where('type', 'rekomendasi_deadline')
            ->where('data->rekomendasi_id', $rekomendasi->id)
            ->count());
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
