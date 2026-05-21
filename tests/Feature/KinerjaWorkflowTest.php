<?php

namespace Tests\Feature;

use App\Models\Opd;
use App\Models\PeriodeTahun;
use App\Models\PerjanjianKinerja;
use App\Models\PerjanjianKinerjaItem;
use App\Models\RealisasiKinerja;
use App\Models\RealisasiProgram;
use App\Models\RencanaAksi;
use App\Models\RencanaAksiItem;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class KinerjaWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_opd_can_manage_only_own_perjanjian_kinerja(): void
    {
        $this->seed();

        [$opd, $otherOpd, $periode, $adminOpd] = $this->basicActors();

        $this->actingAs($adminOpd)
            ->post(route('perjanjian-kinerja.store'), [
                'opd_id' => $opd->id,
                'periode_tahun_id' => $periode->id,
                'tahun' => $periode->tahun,
                'judul' => 'PK Dinas Kesehatan',
                'nomor_dokumen' => 'PK/001',
                'status' => 'draft',
            ])
            ->assertRedirect();

        $ownPk = PerjanjianKinerja::where('opd_id', $opd->id)->firstOrFail();

        $this->actingAs($adminOpd)
            ->post(route('perjanjian-kinerja.store'), [
                'opd_id' => $otherOpd->id,
                'periode_tahun_id' => $periode->id,
                'tahun' => $periode->tahun,
                'judul' => 'PK OPD Lain',
                'status' => 'draft',
            ])
            ->assertForbidden();

        $otherPk = PerjanjianKinerja::create([
            'opd_id' => $otherOpd->id,
            'periode_tahun_id' => $periode->id,
            'tahun' => $periode->tahun,
            'judul' => 'PK OPD Lain',
            'status' => 'draft',
        ]);

        $this->actingAs($adminOpd)
            ->get(route('perjanjian-kinerja.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Kinerja/PerjanjianKinerja/Index')
                ->has('items.data', 1)
                ->where('items.data.0.id', $ownPk->id)
            );

        $this->actingAs($adminOpd)
            ->get(route('perjanjian-kinerja.show', $ownPk))
            ->assertOk();

        $this->actingAs($adminOpd)
            ->get(route('perjanjian-kinerja.show', $otherPk))
            ->assertForbidden();
    }

    public function test_workflow_submit_and_verify_records_submission_history(): void
    {
        $this->seed();

        [$opd, , $periode, $adminOpd] = $this->basicActors();
        $reviewer = User::factory()->create();
        $reviewer->roles()->sync([Role::where('name', 'admin_kabupaten_bagian_organisasi')->value('id')]);

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
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('perjanjian_kinerja', [
            'id' => $pk->id,
            'status' => 'submitted',
            'submitted_by' => $adminOpd->id,
        ]);

        $this->actingAs($reviewer)
            ->post(route('workflow.transition', ['module' => 'perjanjian_kinerja', 'id' => $pk->id]), [
                'action' => 'verify',
                'note' => 'Data sudah sesuai.',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('perjanjian_kinerja', [
            'id' => $pk->id,
            'status' => 'verified',
        ]);

        $this->assertDatabaseHas('workflow_submissions', [
            'related_table' => 'perjanjian_kinerja',
            'related_id' => $pk->id,
            'module' => 'perjanjian_kinerja',
            'status' => 'verified',
        ]);

        $this->assertDatabaseHas('workflow_histories', [
            'related_table' => 'perjanjian_kinerja',
            'related_id' => $pk->id,
            'action' => 'submit',
            'to_status' => 'submitted',
        ]);

        $this->assertDatabaseHas('workflow_histories', [
            'related_table' => 'perjanjian_kinerja',
            'related_id' => $pk->id,
            'action' => 'verify',
            'to_status' => 'verified',
            'actor_id' => $reviewer->id,
        ]);
    }

    public function test_rencana_aksi_and_realisasi_items_can_be_saved(): void
    {
        $this->seed();

        [$opd, , $periode, $adminOpd] = $this->basicActors();

        $pk = PerjanjianKinerja::create([
            'opd_id' => $opd->id,
            'periode_tahun_id' => $periode->id,
            'tahun' => $periode->tahun,
            'judul' => 'PK Kinerja',
            'status' => 'draft',
        ]);

        $this->actingAs($adminOpd)
            ->post(route('perjanjian-kinerja.items.store', $pk), [
                'sasaran' => 'Meningkatnya kualitas layanan',
                'indikator' => 'Indeks layanan',
                'target' => 90,
                'target_text' => '90 persen',
                'urutan' => 1,
            ])
            ->assertRedirect();

        $pkItem = PerjanjianKinerjaItem::firstOrFail();

        $this->actingAs($adminOpd)
            ->put(route('perjanjian-kinerja.items.update', [$pk, $pkItem]), [
                'sasaran' => 'Meningkatnya kualitas layanan publik',
                'indikator' => 'Indeks layanan publik',
                'target' => 91,
                'target_text' => '91 persen',
                'urutan' => 2,
            ])
            ->assertRedirect();

        $pkItem->refresh();

        $this->actingAs($adminOpd)
            ->post(route('rencana-aksi.store'), [
                'opd_id' => $opd->id,
                'perjanjian_kinerja_id' => $pk->id,
                'periode_tahun_id' => $periode->id,
                'tahun' => $periode->tahun,
                'judul' => 'Rencana Aksi Kinerja Belum Valid',
                'status' => 'draft',
            ])
            ->assertSessionHasErrors('perjanjian_kinerja_id');

        $pk->forceFill(['status' => 'approved'])->save();

        $this->actingAs($adminOpd)
            ->put(route('perjanjian-kinerja.items.update', [$pk, $pkItem]), [
                'sasaran' => 'Target approved tidak boleh diubah langsung',
                'indikator' => 'Indeks layanan publik',
                'target' => 92,
                'target_text' => '92 persen',
                'urutan' => 3,
            ])
            ->assertForbidden();

        $this->actingAs($adminOpd)
            ->post(route('rencana-aksi.store'), [
                'opd_id' => $opd->id,
                'perjanjian_kinerja_id' => $pk->id,
                'periode_tahun_id' => $periode->id,
                'tahun' => $periode->tahun,
                'judul' => 'Rencana Aksi Kinerja',
                'status' => 'draft',
            ])
            ->assertRedirect();

        $rencanaAksi = RencanaAksi::firstOrFail();

        $this->actingAs($adminOpd)
            ->post(route('rencana-aksi.items.store', $rencanaAksi), [
                'perjanjian_kinerja_item_id' => $pkItem->id,
                'periode_realisasi' => 'triwulan',
                'triwulan' => 'tw1',
                'aksi' => 'Pelaksanaan layanan triwulan pertama',
                'indikator' => 'Layanan selesai',
                'target' => 25,
                'target_text' => '25 persen',
                'anggaran' => 1000000,
                'status' => 'draft',
                'urutan' => 1,
            ])
            ->assertRedirect();

        $rencanaAksiItem = RencanaAksiItem::where('rencana_aksi_id', $rencanaAksi->id)->firstOrFail();

        $this->actingAs($adminOpd)
            ->put(route('rencana-aksi.items.update', [$rencanaAksi, $rencanaAksiItem]), [
                'perjanjian_kinerja_item_id' => $pkItem->id,
                'periode_realisasi' => 'triwulan',
                'triwulan' => 'tw2',
                'aksi' => 'Pelaksanaan layanan triwulan kedua',
                'indikator' => 'Layanan selesai tepat waktu',
                'target' => 50,
                'target_text' => '50 persen',
                'anggaran' => 1500000,
                'status' => 'draft',
                'urutan' => 2,
            ])
            ->assertRedirect();

        $this->actingAs($adminOpd)
            ->post(route('realisasi-kinerja.store'), [
                'opd_id' => $opd->id,
                'perjanjian_kinerja_id' => $pk->id,
                'rencana_aksi_id' => $rencanaAksi->id,
                'periode_tahun_id' => $periode->id,
                'tahun' => $periode->tahun,
                'periode_realisasi' => 'triwulan',
                'triwulan' => 'tw1',
                'status' => 'draft',
            ])
            ->assertSessionHasErrors('rencana_aksi_id');

        $rencanaAksi->forceFill(['status' => 'approved'])->save();

        $this->actingAs($adminOpd)
            ->put(route('rencana-aksi.items.update', [$rencanaAksi, $rencanaAksiItem]), [
                'perjanjian_kinerja_item_id' => $pkItem->id,
                'periode_realisasi' => 'triwulan',
                'triwulan' => 'tw3',
                'aksi' => 'Target RA approved tidak boleh diubah langsung',
                'indikator' => 'Layanan selesai tepat waktu',
                'target' => 75,
                'target_text' => '75 persen',
                'anggaran' => 2000000,
                'status' => 'draft',
                'urutan' => 3,
            ])
            ->assertForbidden();

        $this->actingAs($adminOpd)
            ->post(route('realisasi-kinerja.store'), [
                'opd_id' => $opd->id,
                'perjanjian_kinerja_id' => $pk->id,
                'rencana_aksi_id' => $rencanaAksi->id,
                'periode_tahun_id' => $periode->id,
                'tahun' => $periode->tahun,
                'periode_realisasi' => 'triwulan',
                'triwulan' => 'tw1',
                'status' => 'draft',
            ])
            ->assertRedirect();

        $realisasi = RealisasiKinerja::firstOrFail();

        $this->actingAs($adminOpd)
            ->post(route('realisasi-kinerja.programs.store', $realisasi), [
                'perjanjian_kinerja_item_id' => $pkItem->id,
                'indikator' => 'Indeks layanan',
                'target' => 90,
                'target_text' => '90 persen',
                'realisasi' => 88,
                'realisasi_text' => '88 persen',
                'capaian_persen' => 97.78,
                'anggaran' => 1000000,
                'realisasi_anggaran' => 900000,
                'kendala' => 'Data pendukung belum lengkap',
                'tindak_lanjut' => 'Perbaikan data',
                'urutan' => 1,
            ])
            ->assertRedirect();

        $realisasiProgram = RealisasiProgram::where('realisasi_kinerja_id', $realisasi->id)->firstOrFail();

        $this->actingAs($adminOpd)
            ->put(route('realisasi-kinerja.programs.update', [$realisasi, $realisasiProgram]), [
                'perjanjian_kinerja_item_id' => $pkItem->id,
                'indikator' => 'Indeks layanan publik',
                'target' => 90,
                'target_text' => '90 persen',
                'realisasi' => 80,
                'realisasi_text' => '80 persen',
                'anggaran' => 1000000,
                'realisasi_anggaran' => 700000,
                'kendala' => 'Kendala diperbarui',
                'tindak_lanjut' => 'Tindak lanjut diperbarui',
                'urutan' => 2,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('rencana_aksi_items', [
            'rencana_aksi_id' => $rencanaAksi->id,
            'perjanjian_kinerja_item_id' => $pkItem->id,
            'triwulan' => 'tw2',
            'aksi' => 'Pelaksanaan layanan triwulan kedua',
        ]);

        $this->assertDatabaseHas('perjanjian_kinerja_items', [
            'id' => $pkItem->id,
            'sasaran' => 'Meningkatnya kualitas layanan publik',
            'indikator' => 'Indeks layanan publik',
        ]);

        $this->assertDatabaseHas('realisasi_program', [
            'realisasi_kinerja_id' => $realisasi->id,
            'perjanjian_kinerja_item_id' => $pkItem->id,
            'status_capaian' => 'kuning',
            'status_efisiensi' => 'efisien',
            'kendala' => 'Kendala diperbarui',
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
