<?php

namespace Tests\Feature;

use App\Models\IndikatorTujuanOpd;
use App\Models\Opd;
use App\Models\PeriodeTahun;
use App\Models\RenstraOpd;
use App\Models\Role;
use App\Models\Rpjmd;
use App\Models\TargetIndikatorTujuanOpd;
use App\Models\TargetRevision;
use App\Models\TujuanOpd;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TargetRevisionTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_opd_can_submit_target_revision_and_reviewer_can_approve_it(): void
    {
        $this->seed();

        [$adminOpd, $reviewer, $target] = $this->scenario();

        $this->actingAs($adminOpd)
            ->post(route('target-revisions.store'), [
                'target_table' => 'target_indikator_tujuan_opd',
                'target_id' => $target->id,
                'new_values' => [
                    'target' => 91,
                    'target_text' => '91 persen',
                ],
                'reason' => 'Penyesuaian target berdasarkan revisi resmi Renstra.',
                'document_number' => 'REV/001/2026',
                'document_date' => '2026-03-15',
            ])
            ->assertRedirect();

        $revision = TargetRevision::query()->latest('id')->firstOrFail();

        $this->assertSame('submitted', $revision->status);
        $this->assertSame('renstra_opd', $revision->module);
        $this->assertEquals(90, (float) $revision->old_values['target']);
        $this->assertSame(91, $revision->new_values['target']);

        $this->actingAs($reviewer)
            ->patch(route('target-revisions.approve', $revision), ['note' => 'Disetujui sesuai dokumen revisi.'])
            ->assertRedirect();

        $target->refresh();
        $revision->refresh();

        $this->assertSame('approved', $revision->status);
        $this->assertSame('91.0000', $target->target);
        $this->assertSame('91 persen', $target->target_text);
        $this->assertNotNull($revision->applied_at);
    }

    public function test_admin_opd_cannot_review_target_revision_and_reject_requires_note(): void
    {
        $this->seed();

        [$adminOpd, $reviewer, $target] = $this->scenario();

        $this->actingAs($adminOpd)
            ->post(route('target-revisions.store'), [
                'target_table' => 'target_indikator_tujuan_opd',
                'target_id' => $target->id,
                'new_values' => ['target_text' => '92 persen'],
                'reason' => 'Revisi tanpa reviewer OPD.',
            ])
            ->assertRedirect();

        $revision = TargetRevision::query()->latest('id')->firstOrFail();

        $this->actingAs($adminOpd)
            ->patch(route('target-revisions.approve', $revision), ['note' => 'Tidak boleh.'])
            ->assertSessionHasErrors('reviewer');

        $this->actingAs($reviewer)
            ->patch(route('target-revisions.reject', $revision), ['note' => ''])
            ->assertSessionHasErrors('note');

        $this->actingAs($reviewer)
            ->patch(route('target-revisions.reject', $revision), ['note' => 'Dokumen dasar belum lengkap.'])
            ->assertRedirect();

        $this->assertSame('rejected', $revision->fresh()->status);
        $this->assertSame('90 persen', $target->fresh()->target_text);
    }

    public function test_target_revision_index_is_scoped_for_admin_opd(): void
    {
        $this->seed();

        [$adminOpd, , $target] = $this->scenario();

        $this->actingAs($adminOpd)
            ->post(route('target-revisions.store'), [
                'target_table' => 'target_indikator_tujuan_opd',
                'target_id' => $target->id,
                'new_values' => ['target_text' => '93 persen'],
                'reason' => 'Revisi target OPD sendiri.',
            ])
            ->assertRedirect();

        $this->actingAs($adminOpd)
            ->get(route('target-revisions.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Perencanaan/TargetRevision/Index')
                ->has('revisions.data', 1)
                ->where('revisions.data.0.reason', 'Revisi target OPD sendiri.')
            );
    }

    /**
     * @return array{0: User, 1: User, 2: TargetIndikatorTujuanOpd}
     */
    private function scenario(): array
    {
        $periode = PeriodeTahun::where('status', 'active')->firstOrFail();
        $opd = Opd::create(['kode' => '9.01', 'nama' => 'Dinas Revisi Target', 'status' => 'active']);
        $rpjmd = Rpjmd::create([
            'judul' => 'RPJMD Revisi Target',
            'periode_tahun_id' => $periode->id,
            'tahun_awal' => $periode->tahun,
            'tahun_akhir' => $periode->tahun + 4,
            'status' => 'locked',
        ]);
        $renstra = RenstraOpd::create([
            'opd_id' => $opd->id,
            'rpjmd_id' => $rpjmd->id,
            'periode_tahun_id' => $periode->id,
            'judul' => 'Renstra Revisi Target',
            'tahun_awal' => $periode->tahun,
            'tahun_akhir' => $periode->tahun + 4,
            'status' => 'locked',
        ]);
        $tujuan = TujuanOpd::create([
            'renstra_opd_id' => $renstra->id,
            'kode' => 'T-REV',
            'tujuan' => 'Tujuan revisi target',
            'urutan' => 1,
        ]);
        $indikator = IndikatorTujuanOpd::create([
            'tujuan_opd_id' => $tujuan->id,
            'kode' => 'IT-REV',
            'indikator' => 'Indikator revisi target',
            'tipe_indikator' => 'positif',
            'urutan' => 1,
        ]);
        $target = TargetIndikatorTujuanOpd::create([
            'indikator_tujuan_opd_id' => $indikator->id,
            'periode_tahun_id' => $periode->id,
            'target' => 90,
            'target_text' => '90 persen',
        ]);

        $adminOpd = User::factory()->create(['opd_id' => $opd->id]);
        $adminOpd->roles()->sync([Role::where('name', 'admin_opd')->value('id')]);

        $reviewer = User::factory()->create();
        $reviewer->roles()->sync([Role::where('name', 'admin_kabupaten_bagian_organisasi')->value('id')]);

        return [$adminOpd, $reviewer, $target];
    }
}
