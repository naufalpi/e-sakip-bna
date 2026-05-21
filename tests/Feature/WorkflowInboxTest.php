<?php

namespace Tests\Feature;

use App\Models\EvaluasiSakip;
use App\Models\Opd;
use App\Models\PeriodeTahun;
use App\Models\PerjanjianKinerja;
use App\Models\Role;
use App\Models\Rpjmd;
use App\Models\User;
use App\Models\WorkflowSubmission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class WorkflowInboxTest extends TestCase
{
    use RefreshDatabase;

    public function test_reviewer_can_open_workflow_inbox_and_see_review_queue(): void
    {
        $this->seed();

        [$opd, $periode, $adminOpd, $reviewer] = $this->actors();

        $pk = PerjanjianKinerja::create([
            'opd_id' => $opd->id,
            'periode_tahun_id' => $periode->id,
            'tahun' => $periode->tahun,
            'judul' => 'PK Inbox Review',
            'status' => 'submitted',
        ]);

        WorkflowSubmission::create([
            'related_table' => 'perjanjian_kinerja',
            'related_id' => $pk->id,
            'module' => 'perjanjian_kinerja',
            'status' => 'submitted',
            'submitted_by' => $adminOpd->id,
            'current_reviewer_id' => $reviewer->id,
            'submitted_at' => now(),
        ]);

        $this->actingAs($reviewer)
            ->get(route('workflow.inbox'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Workflow/Inbox')
                ->where('items.total', 1)
                ->where('items.data.0.module', 'perjanjian_kinerja')
                ->where('items.data.0.context.title', 'PK Inbox Review')
                ->where('items.data.0.context.opd.singkatan', 'Dinkes')
                ->where('summary.need_review', 1)
            );
    }

    public function test_admin_opd_only_sees_own_opd_workflow_submissions(): void
    {
        $this->seed();

        [$opd, $periode, $adminOpd, $reviewer] = $this->actors();
        $otherOpd = Opd::create(['kode' => '2.01', 'nama' => 'Dinas Pendidikan', 'singkatan' => 'Disdik', 'status' => 'active']);
        $otherAdmin = User::factory()->create(['opd_id' => $otherOpd->id]);
        $otherAdmin->roles()->sync([Role::where('name', 'admin_opd')->value('id')]);

        $ownPk = PerjanjianKinerja::create([
            'opd_id' => $opd->id,
            'periode_tahun_id' => $periode->id,
            'tahun' => $periode->tahun,
            'judul' => 'PK OPD Sendiri',
            'status' => 'submitted',
        ]);
        $otherPk = PerjanjianKinerja::create([
            'opd_id' => $otherOpd->id,
            'periode_tahun_id' => $periode->id,
            'tahun' => $periode->tahun,
            'judul' => 'PK OPD Lain',
            'status' => 'submitted',
        ]);

        foreach ([[$ownPk, $adminOpd], [$otherPk, $otherAdmin]] as [$pk, $submitter]) {
            WorkflowSubmission::create([
                'related_table' => 'perjanjian_kinerja',
                'related_id' => $pk->id,
                'module' => 'perjanjian_kinerja',
                'status' => 'submitted',
                'submitted_by' => $submitter->id,
                'current_reviewer_id' => $reviewer->id,
                'submitted_at' => now(),
            ]);
        }

        $this->actingAs($adminOpd)
            ->get(route('workflow.inbox'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Workflow/Inbox')
                ->where('filters.scope', 'mine')
                ->where('items.total', 1)
                ->where('items.data.0.context.title', 'PK OPD Sendiri')
            );
    }

    public function test_bapperida_inbox_is_limited_to_planning_workflows(): void
    {
        $this->seed();

        [$opd, $periode, $adminOpd] = $this->actors();
        $bapperida = User::factory()->create();
        $bapperida->roles()->sync([Role::where('name', 'admin_kabupaten_bapperida')->value('id')]);

        $rpjmd = Rpjmd::create([
            'periode_tahun_id' => $periode->id,
            'judul' => 'RPJMD Inbox',
            'tahun_awal' => $periode->tahun,
            'tahun_akhir' => $periode->tahun + 4,
            'status' => 'submitted',
        ]);
        $evaluasi = EvaluasiSakip::create([
            'opd_id' => $opd->id,
            'periode_tahun_id' => $periode->id,
            'tahun' => $periode->tahun,
            'status' => 'submitted',
        ]);

        foreach ([['rpjmd', 'rpjmd', $rpjmd->id], ['evaluasi_sakip', 'evaluasi_sakip', $evaluasi->id]] as [$module, $table, $id]) {
            WorkflowSubmission::create([
                'related_table' => $table,
                'related_id' => $id,
                'module' => $module,
                'status' => 'submitted',
                'submitted_by' => $adminOpd->id,
                'current_reviewer_id' => $bapperida->id,
                'submitted_at' => now(),
            ]);
        }

        $this->actingAs($bapperida)
            ->get(route('workflow.inbox', ['scope' => 'all']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Workflow/Inbox')
                ->where('items.total', 1)
                ->where('items.data.0.module', 'rpjmd')
                ->where('items.data.0.context.title', 'RPJMD Inbox')
                ->missing('moduleOptions.2')
            );
    }

    public function test_pimpinan_cannot_open_workflow_inbox(): void
    {
        $this->seed();

        $pimpinan = User::factory()->create();
        $pimpinan->roles()->sync([Role::where('name', 'pimpinan')->value('id')]);

        $this->actingAs($pimpinan)
            ->get(route('workflow.inbox'))
            ->assertForbidden();
    }

    /**
     * @return array{0: Opd, 1: PeriodeTahun, 2: User, 3: User}
     */
    private function actors(): array
    {
        $opd = Opd::create(['kode' => '1.01', 'nama' => 'Dinas Kesehatan', 'singkatan' => 'Dinkes', 'status' => 'active']);
        $periode = PeriodeTahun::where('status', 'active')->firstOrFail();

        $adminOpd = User::factory()->create(['opd_id' => $opd->id]);
        $adminOpd->roles()->sync([Role::where('name', 'admin_opd')->value('id')]);

        $reviewer = User::factory()->create();
        $reviewer->roles()->sync([Role::where('name', 'admin_kabupaten_bagian_organisasi')->value('id')]);

        return [$opd, $periode, $adminOpd, $reviewer];
    }
}
