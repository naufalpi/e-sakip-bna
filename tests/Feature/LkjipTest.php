<?php

namespace Tests\Feature;

use App\Models\Dokumen;
use App\Models\Lkjip;
use App\Models\Opd;
use App\Models\PeriodeTahun;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class LkjipTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_opd_can_manage_only_own_lkjip_with_default_bab(): void
    {
        $this->seed();

        [$opd, $otherOpd, $periode, $adminOpd] = $this->basicActors();

        $this->actingAs($adminOpd)
            ->post(route('lkjip.store'), [
                'opd_id' => $opd->id,
                'periode_tahun_id' => $periode->id,
                'tahun' => $periode->tahun,
                'judul' => 'LKJIP Dinas Kesehatan',
                'nomor_dokumen' => 'LKJIP/001',
                'ringkasan_eksekutif' => 'Ringkasan capaian kinerja.',
                'status' => 'draft',
            ])
            ->assertRedirect();

        $lkjip = Lkjip::query()->where('opd_id', $opd->id)->firstOrFail();

        $this->assertDatabaseCount('lkjip_bab', 5);
        $this->assertDatabaseHas('lkjip_bab', [
            'lkjip_id' => $lkjip->id,
            'kode' => 'BAB III',
            'judul' => 'Akuntabilitas Kinerja',
        ]);

        $this->actingAs($adminOpd)
            ->post(route('lkjip.store'), [
                'opd_id' => $otherOpd->id,
                'periode_tahun_id' => $periode->id,
                'tahun' => $periode->tahun,
                'judul' => 'LKJIP OPD Lain',
                'status' => 'draft',
            ])
            ->assertForbidden();

        $otherLkjip = Lkjip::create([
            'opd_id' => $otherOpd->id,
            'periode_tahun_id' => $periode->id,
            'tahun' => $periode->tahun,
            'judul' => 'LKJIP OPD Lain',
            'status' => 'draft',
        ]);

        $this->actingAs($adminOpd)
            ->get(route('lkjip.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Lkjip/Index')
                ->has('items.data', 1)
                ->where('items.data.0.id', $lkjip->id)
            );

        $this->actingAs($adminOpd)
            ->get(route('lkjip.show', $lkjip))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Lkjip/Show')
                ->has('item.bab', 5)
            );

        $this->actingAs($adminOpd)
            ->get(route('lkjip.show', $otherLkjip))
            ->assertForbidden();
    }

    public function test_lkjip_workflow_and_locked_protection(): void
    {
        $this->seed();

        [$opd, , $periode, $adminOpd] = $this->basicActors();
        $reviewer = User::factory()->create();
        $reviewer->roles()->sync([Role::where('name', 'admin_kabupaten_bagian_organisasi')->value('id')]);

        $lkjip = Lkjip::create([
            'opd_id' => $opd->id,
            'periode_tahun_id' => $periode->id,
            'tahun' => $periode->tahun,
            'judul' => 'LKJIP Workflow',
            'status' => 'draft',
        ]);

        $this->actingAs($adminOpd)
            ->post(route('workflow.transition', ['module' => 'lkjip', 'id' => $lkjip->id]), [
                'action' => 'submit',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('workflow_histories', [
            'related_table' => 'lkjip',
            'related_id' => $lkjip->id,
            'action' => 'submit',
            'to_status' => 'submitted',
        ]);

        $this->actingAs($reviewer)
            ->post(route('workflow.transition', ['module' => 'lkjip', 'id' => $lkjip->id]), [
                'action' => 'approve',
                'note' => 'LKJIP disetujui.',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('lkjip', [
            'id' => $lkjip->id,
            'status' => 'approved',
        ]);

        $this->actingAs($reviewer)
            ->post(route('workflow.transition', ['module' => 'lkjip', 'id' => $lkjip->id]), [
                'action' => 'lock',
            ])
            ->assertForbidden();

        $superAdmin = User::where('username', 'superadmin')->firstOrFail();

        $this->actingAs($superAdmin)
            ->post(route('workflow.transition', ['module' => 'lkjip', 'id' => $lkjip->id]), [
                'action' => 'lock',
            ])
            ->assertRedirect();

        $this->actingAs($adminOpd)
            ->put(route('lkjip.update', $lkjip), [
                'opd_id' => $opd->id,
                'periode_tahun_id' => $periode->id,
                'tahun' => $periode->tahun,
                'judul' => 'Perubahan LKJIP Terkunci',
                'status' => 'draft',
            ])
            ->assertForbidden();
    }

    public function test_lkjip_can_be_used_as_document_relation(): void
    {
        Storage::fake('local');
        $this->seed();

        [$opd, , $periode, $adminOpd] = $this->basicActors();

        $lkjip = Lkjip::create([
            'opd_id' => $opd->id,
            'periode_tahun_id' => $periode->id,
            'tahun' => $periode->tahun,
            'judul' => 'LKJIP Dokumen',
            'status' => 'draft',
        ]);

        $this->actingAs($adminOpd)
            ->post(route('dokumen.store'), [
                'opd_id' => $opd->id,
                'periode_tahun_id' => $periode->id,
                'jenis' => 'lkjip',
                'judul' => 'Dokumen LKJIP',
                'status' => 'draft',
                'related_type' => 'lkjip',
                'related_id' => $lkjip->id,
                'file' => UploadedFile::fake()->create('lkjip.pdf', 64, 'application/pdf'),
            ])
            ->assertRedirect();

        $dokumen = Dokumen::firstOrFail();

        $this->assertDatabaseHas('dokumen_relations', [
            'dokumen_id' => $dokumen->id,
            'related_type' => Lkjip::class,
            'related_id' => $lkjip->id,
        ]);
    }

    private function basicActors(): array
    {
        $opd = Opd::create(['kode' => '1.01', 'nama' => 'Dinas Kesehatan', 'status' => 'active']);
        $otherOpd = Opd::create(['kode' => '1.02', 'nama' => 'Dinas Pendidikan', 'status' => 'active']);
        $periode = PeriodeTahun::orderBy('tahun')->firstOrFail();

        $adminOpd = User::factory()->create(['opd_id' => $opd->id]);
        $adminOpd->roles()->sync([Role::where('name', 'admin_opd')->value('id')]);

        return [$opd, $otherOpd, $periode, $adminOpd];
    }
}
