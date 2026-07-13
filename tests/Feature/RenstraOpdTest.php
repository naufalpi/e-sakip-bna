<?php

namespace Tests\Feature;

use App\Models\ImportBatch;
use App\Models\IndikatorOpdProgram;
use App\Models\IndikatorProgramRpjmd;
use App\Models\IndikatorSasaranDaerah;
use App\Models\IndikatorTujuanDaerah;
use App\Models\Opd;
use App\Models\OpdProgram;
use App\Models\PeriodeTahun;
use App\Models\ProgramRpjmd;
use App\Models\RenstraOpd;
use App\Models\Role;
use App\Models\Rpjmd;
use App\Models\RpjmdMisi;
use App\Models\RpjmdVisi;
use App\Models\SasaranDaerah;
use App\Models\SatuanIndikator;
use App\Models\StrategiDaerah;
use App\Models\TargetIndikatorOpdProgram;
use App\Models\TujuanDaerah;
use App\Models\TujuanOpd;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class RenstraOpdTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_opd_can_crud_only_own_renstra(): void
    {
        $this->seed();

        $opd = Opd::create(['kode' => '1.01', 'nama' => 'Dinas Relevan', 'status' => 'active']);
        $otherOpd = Opd::create(['kode' => '1.02', 'nama' => 'Dinas Lain', 'status' => 'active']);
        $rpjmd = Rpjmd::create(['judul' => 'RPJMD Kabupaten', 'tahun_awal' => 2026, 'tahun_akhir' => 2031, 'status' => 'approved']);

        $user = User::factory()->create(['opd_id' => $opd->id]);
        $user->roles()->sync([Role::where('name', 'admin_opd')->value('id')]);

        $this->actingAs($user)
            ->post(route('renstra-opd.store'), [
                'opd_id' => $opd->id,
                'rpjmd_id' => $rpjmd->id,
                'judul' => 'Renstra Dinas Relevan',
                'tahun_awal' => 2026,
                'tahun_akhir' => 2031,
                'status' => 'draft',
            ])
            ->assertRedirect();

        $ownRenstra = RenstraOpd::where('opd_id', $opd->id)->firstOrFail();

        $this->actingAs($user)
            ->post(route('renstra-opd.store'), [
                'opd_id' => $otherOpd->id,
                'rpjmd_id' => $rpjmd->id,
                'judul' => 'Renstra OPD Lain',
                'tahun_awal' => 2026,
                'tahun_akhir' => 2031,
                'status' => 'draft',
            ])
            ->assertForbidden();

        $otherRenstra = RenstraOpd::create([
            'opd_id' => $otherOpd->id,
            'rpjmd_id' => $rpjmd->id,
            'judul' => 'Renstra OPD Lain',
            'tahun_awal' => 2026,
            'tahun_akhir' => 2031,
            'status' => 'draft',
        ]);

        $this->actingAs($user)
            ->get(route('renstra-opd.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('RenstraOpd/Index')
                ->has('renstras.data', 1)
                ->where('renstras.data.0.id', $ownRenstra->id)
            );

        $this->actingAs($user)
            ->get(route('renstra-opd.show', $ownRenstra))
            ->assertOk();

        $this->actingAs($user)
            ->get(route('renstra-opd.show', $otherRenstra))
            ->assertForbidden();
    }

    public function test_cascading_opd_can_link_to_rpjmd_and_save_yearly_targets(): void
    {
        $this->seed();

        $opd = Opd::create(['kode' => '2.01', 'nama' => 'Dinas Perencanaan', 'status' => 'active']);
        $tree = $this->createRpjmdTree();
        $periode = PeriodeTahun::orderBy('tahun')->firstOrFail();
        $satuan = SatuanIndikator::firstOrFail();
        $renstra = RenstraOpd::create([
            'opd_id' => $opd->id,
            'rpjmd_id' => $tree['rpjmd']->id,
            'periode_tahun_id' => $periode->id,
            'judul' => 'Renstra Dinas Perencanaan',
            'tahun_awal' => 2026,
            'tahun_akhir' => 2031,
            'status' => 'draft',
        ]);

        $user = User::factory()->create(['opd_id' => $opd->id]);
        $user->roles()->sync([Role::where('name', 'admin_opd')->value('id')]);

        $this->actingAs($user)
            ->post(route('renstra-opd.nodes.store', $renstra), [
                'type' => 'tujuan',
                'tujuan_daerah_id' => $tree['tujuan_daerah']->id,
                'kode' => 'T1',
                'uraian' => 'Tujuan OPD Terhubung',
                'urutan' => 1,
            ])
            ->assertRedirect();

        $tujuanOpd = TujuanOpd::firstOrFail();

        $this->actingAs($user)
            ->post(route('renstra-opd.nodes.store', $renstra), [
                'type' => 'indikator_tujuan',
                'parent_id' => $tujuanOpd->id,
                'indikator_tujuan_daerah_id' => $tree['indikator_tujuan']->id,
                'satuan_indikator_id' => $satuan->id,
                'indikator' => 'Indikator Tujuan OPD',
            ])
            ->assertRedirect();

        $this->actingAs($user)
            ->post(route('renstra-opd.nodes.store', $renstra), [
                'type' => 'target_tujuan',
                'parent_id' => $tujuanOpd->indikator()->firstOrFail()->id,
                'periode_tahun_id' => $periode->id,
                'target' => 90,
                'target_text' => '90 persen',
            ])
            ->assertRedirect();

        $this->actingAs($user)
            ->post(route('renstra-opd.nodes.store', $renstra), [
                'type' => 'sasaran',
                'parent_id' => $tujuanOpd->id,
                'sasaran_daerah_id' => $tree['sasaran_daerah']->id,
                'kode' => 'S1',
                'uraian' => 'Sasaran OPD Terhubung',
            ])
            ->assertRedirect();

        $sasaranOpd = $tujuanOpd->sasaran()->firstOrFail();

        $this->actingAs($user)
            ->post(route('renstra-opd.nodes.store', $renstra), [
                'type' => 'program',
                'parent_id' => $sasaranOpd->id,
                'program_rpjmd_id' => $tree['program_rpjmd']->id,
                'kode' => 'P1',
                'uraian' => 'Program OPD Terhubung',
            ])
            ->assertRedirect();

        $programOpd = OpdProgram::firstOrFail();

        $this->actingAs($user)
            ->post(route('renstra-opd.nodes.store', $renstra), [
                'type' => 'indikator_program',
                'parent_id' => $programOpd->id,
                'indikator_program_rpjmd_id' => $tree['indikator_program']->id,
                'satuan_indikator_id' => $satuan->id,
                'indikator' => 'Indikator Program OPD',
            ])
            ->assertRedirect();

        $indikatorProgram = IndikatorOpdProgram::firstOrFail();

        $this->actingAs($user)
            ->post(route('renstra-opd.nodes.store', $renstra), [
                'type' => 'target_program',
                'parent_id' => $indikatorProgram->id,
                'periode_tahun_id' => $periode->id,
                'target' => 75,
                'target_text' => '75 persen',
                'pagu' => 1000000,
            ])
            ->assertRedirect();

        $targetProgram = TargetIndikatorOpdProgram::where('indikator_opd_program_id', $indikatorProgram->id)->firstOrFail();

        $this->actingAs($user)
            ->put(route('renstra-opd.nodes.update', [$renstra, 'target_program', $targetProgram->id]), [
                'type' => 'target_program',
                'parent_id' => $indikatorProgram->id,
                'periode_tahun_id' => $periode->id,
                'target' => 80,
                'target_text' => '80 persen',
                'pagu' => 1250000,
            ])
            ->assertRedirect();

        $this->actingAs($user)
            ->post(route('target-triwulan-indikator.bulk-store'), [
                'related_table' => 'indikator_opd_program',
                'related_id' => $indikatorProgram->id,
                'periode_tahun_id' => $periode->id,
                'targets' => [
                    ['triwulan' => 'tw1', 'target_text' => '25 persen', 'target_angka' => 25, 'target_anggaran' => 250000],
                    ['triwulan' => 'tw2', 'target_text' => '50 persen', 'target_angka' => 50, 'target_anggaran' => 500000],
                    ['triwulan' => 'tw3', 'target_text' => '75 persen', 'target_angka' => 75, 'target_anggaran' => 750000],
                    ['triwulan' => 'tw4', 'target_text' => '100 persen', 'target_angka' => 100, 'target_anggaran' => 1000000],
                ],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('tujuan_opd', [
            'renstra_opd_id' => $renstra->id,
            'tujuan_daerah_id' => $tree['tujuan_daerah']->id,
        ]);

        $this->assertDatabaseHas('opd_program', [
            'renstra_opd_id' => $renstra->id,
            'program_rpjmd_id' => $tree['program_rpjmd']->id,
        ]);

        $this->assertDatabaseHas('target_indikator_opd_program', [
            'indikator_opd_program_id' => $indikatorProgram->id,
            'periode_tahun_id' => $periode->id,
            'target_text' => '80 persen',
            'pagu' => 1250000,
        ]);

        $this->assertDatabaseHas('target_triwulan_indikator', [
            'related_table' => 'indikator_opd_program',
            'related_id' => $indikatorProgram->id,
            'periode_tahun_id' => $periode->id,
            'triwulan' => 'tw1',
            'target_text' => '25 persen',
        ]);

        $this->assertDatabaseHas('target_triwulan_indikator', [
            'related_table' => 'indikator_opd_program',
            'related_id' => $indikatorProgram->id,
            'periode_tahun_id' => $periode->id,
            'triwulan' => 'tw4',
            'target_text' => '100 persen',
            'target_anggaran' => 1000000,
        ]);
    }

    public function test_renstra_node_autosave_updates_existing_cascading_data(): void
    {
        $this->seed();

        $opd = Opd::create(['kode' => '2.02', 'nama' => 'Dinas Autosave', 'status' => 'active']);
        $tree = $this->createRpjmdTree();
        $renstra = RenstraOpd::create([
            'opd_id' => $opd->id,
            'rpjmd_id' => $tree['rpjmd']->id,
            'judul' => 'Renstra Autosave',
            'tahun_awal' => 2026,
            'tahun_akhir' => 2031,
            'status' => 'draft',
        ]);
        $user = User::factory()->create(['opd_id' => $opd->id]);
        $user->roles()->sync([Role::where('name', 'admin_opd')->value('id')]);

        $tujuan = TujuanOpd::create([
            'renstra_opd_id' => $renstra->id,
            'tujuan_daerah_id' => $tree['tujuan_daerah']->id,
            'kode' => 'T1',
            'tujuan' => 'Tujuan Lama',
            'urutan' => 1,
        ]);
        $sasaran = $tujuan->sasaran()->create([
            'sasaran_daerah_id' => $tree['sasaran_daerah']->id,
            'kode' => 'S1',
            'sasaran' => 'Sasaran Lama',
            'urutan' => 1,
        ]);
        $program = OpdProgram::create([
            'renstra_opd_id' => $renstra->id,
            'sasaran_opd_id' => $sasaran->id,
            'program_rpjmd_id' => $tree['program_rpjmd']->id,
            'kode' => 'P1',
            'nama' => 'Program Lama',
            'status' => 'draft',
            'urutan' => 1,
        ]);

        $this->actingAs($user)
            ->patchJson(route('renstra-opd.nodes.autosave', [$renstra, 'program', $program->id]), [
                'type' => 'program',
                'parent_id' => $sasaran->id,
                'program_rpjmd_id' => $tree['program_rpjmd']->id,
                'kode' => 'P1A',
                'uraian' => 'Program Hasil Autosave',
                'pagu_indikatif' => 2500000,
                'urutan' => 2,
            ])
            ->assertOk()
            ->assertJsonPath('status', 'saved');

        $this->assertDatabaseHas('opd_program', [
            'id' => $program->id,
            'kode' => 'P1A',
            'nama' => 'Program Hasil Autosave',
            'pagu_indikatif' => 2500000,
            'urutan' => 2,
        ]);
    }

    public function test_renstra_node_autosave_can_create_new_cascading_data(): void
    {
        $this->seed();

        $opd = Opd::create(['kode' => '2.03', 'nama' => 'Dinas Bulk Create', 'status' => 'active']);
        $tree = $this->createRpjmdTree();
        $renstra = RenstraOpd::create([
            'opd_id' => $opd->id,
            'rpjmd_id' => $tree['rpjmd']->id,
            'judul' => 'Renstra Bulk Create',
            'tahun_awal' => 2026,
            'tahun_akhir' => 2031,
            'status' => 'draft',
        ]);
        $user = User::factory()->create(['opd_id' => $opd->id]);
        $user->roles()->sync([Role::where('name', 'admin_opd')->value('id')]);

        $response = $this->actingAs($user)
            ->postJson(route('renstra-opd.nodes.autosave-store', $renstra), [
                'type' => 'tujuan',
                'tujuan_daerah_id' => $tree['tujuan_daerah']->id,
                'kode' => 'TB1',
                'uraian' => 'Tujuan Dibuat Dari Bulk',
                'urutan' => 1,
            ])
            ->assertCreated()
            ->assertJsonPath('status', 'saved');

        $tujuanId = $response->json('id');

        $this->actingAs($user)
            ->postJson(route('renstra-opd.nodes.autosave-store', $renstra), [
                'type' => 'sasaran',
                'parent_id' => $tujuanId,
                'sasaran_daerah_id' => $tree['sasaran_daerah']->id,
                'kode' => 'SB1',
                'uraian' => 'Sasaran Dibuat Dari Bulk',
                'urutan' => 1,
            ])
            ->assertCreated()
            ->assertJsonPath('status', 'saved');

        $this->assertDatabaseHas('tujuan_opd', [
            'renstra_opd_id' => $renstra->id,
            'kode' => 'TB1',
            'tujuan' => 'Tujuan Dibuat Dari Bulk',
        ]);

        $this->assertDatabaseHas('sasaran_opd', [
            'tujuan_opd_id' => $tujuanId,
            'kode' => 'SB1',
            'sasaran' => 'Sasaran Dibuat Dari Bulk',
        ]);
    }

    public function test_kabupaten_monitoring_can_view_all_read_only(): void
    {
        $this->seed();

        $rpjmd = Rpjmd::create(['judul' => 'RPJMD Monitoring', 'tahun_awal' => 2026, 'tahun_akhir' => 2031, 'status' => 'approved']);
        $opdA = Opd::create(['kode' => '3.01', 'nama' => 'Dinas A', 'status' => 'active']);
        $opdB = Opd::create(['kode' => '3.02', 'nama' => 'Dinas B', 'status' => 'active']);
        RenstraOpd::create(['opd_id' => $opdA->id, 'rpjmd_id' => $rpjmd->id, 'judul' => 'Renstra A', 'tahun_awal' => 2026, 'tahun_akhir' => 2031, 'status' => 'draft']);
        RenstraOpd::create(['opd_id' => $opdB->id, 'rpjmd_id' => $rpjmd->id, 'judul' => 'Renstra B', 'tahun_awal' => 2026, 'tahun_akhir' => 2031, 'status' => 'draft']);

        $user = User::factory()->create();
        $user->roles()->sync([Role::where('name', 'admin_kabupaten_bagian_organisasi')->value('id')]);

        $this->actingAs($user)
            ->get(route('renstra-opd.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('RenstraOpd/Index')
                ->has('renstras.data', 2)
            );

        $this->actingAs($user)
            ->get(route('renstra-opd.create'))
            ->assertForbidden();
    }

    public function test_renstra_import_preview_and_apply_creates_cascading_data(): void
    {
        $this->seed();
        Storage::fake('local');
        config(['filesystems.default' => 'local']);

        $opd = Opd::create(['kode' => '4.01', 'nama' => 'Dinas Import', 'status' => 'active']);
        $tree = $this->createRpjmdTree();
        $periode = PeriodeTahun::where('tahun', 2026)->firstOrFail();
        $user = User::factory()->create(['opd_id' => $opd->id]);
        $user->roles()->sync([Role::where('name', 'admin_opd')->value('id')]);

        $csv = $this->renstraImportCsv([
            ['level', 'opd_kode', 'rpjmd_id', 'renstra_judul', 'tahun_awal', 'tahun_akhir', 'kode', 'uraian', 'tahun_target', 'target', 'target_text', 'pagu', 'triwulan', 'target_anggaran'],
            ['renstra', '4.01', $tree['rpjmd']->id, 'Renstra Import Dinas', 2026, 2031, '', '', '', '', '', '', '', ''],
            ['tujuan', '', '', '', '', '', 'T1', 'Tujuan Import', '', '', '', '', '', ''],
            ['indikator_tujuan', '', '', '', '', '', 'IT1', 'Indikator Tujuan Import', 2026, 80, '80 persen', '', '', ''],
            ['sasaran', '', '', '', '', '', 'S1', 'Sasaran Import', '', '', '', '', '', ''],
            ['indikator_sasaran', '', '', '', '', '', 'IS1', 'Indikator Sasaran Import', 2026, 75, '75 persen', '', '', ''],
            ['program', '', '', '', '', '', 'P1', 'Program Import', '', '', '', 1000000, '', ''],
            ['indikator_program', '', '', '', '', '', 'IP1', 'Indikator Program Import', 2026, 70, '70 persen', 500000, '', ''],
            ['kegiatan', '', '', '', '', '', 'K1', 'Kegiatan Import', '', '', '', 250000, '', ''],
            ['sub_kegiatan', '', '', '', '', '', 'SK1', 'Sub Kegiatan Import', '', '', '', 200000, '', ''],
            ['indikator_sub_kegiatan', '', '', '', '', '', 'ISK1', 'Indikator Sub Kegiatan Import', '', '', '', '', '', ''],
            ['target_triwulan', '', '', '', '', '', '', '', 2026, 25, '25 persen', '', 'tw1', 50000],
        ]);

        $this->actingAs($user)
            ->post(route('renstra-opd.import.store'), [
                'file' => UploadedFile::fake()->createWithContent('renstra-import.csv', $csv),
            ])
            ->assertRedirect();

        $batch = ImportBatch::where('module', 'renstra_opd')->latest('id')->firstOrFail();

        $this->assertSame('previewed', $batch->status);
        $this->assertSame(12, $batch->rows()->count());

        $this->actingAs($user)
            ->post(route('renstra-opd.import.apply', $batch))
            ->assertRedirect(route('renstra-opd.import.show', $batch));

        $batch->refresh();

        $this->assertSame('imported', $batch->status);
        $this->assertDatabaseHas('renstra_opd', ['opd_id' => $opd->id, 'judul' => 'Renstra Import Dinas']);
        $this->assertDatabaseHas('tujuan_opd', ['kode' => 'T1', 'tujuan' => 'Tujuan Import']);
        $this->assertDatabaseHas('indikator_tujuan_opd', ['kode' => 'IT1', 'indikator' => 'Indikator Tujuan Import']);
        $this->assertDatabaseHas('target_indikator_tujuan_opd', ['periode_tahun_id' => $periode->id, 'target_text' => '80 persen']);
        $this->assertDatabaseHas('sasaran_opd', ['kode' => 'S1', 'sasaran' => 'Sasaran Import']);
        $this->assertDatabaseHas('indikator_sasaran_opd', ['kode' => 'IS1', 'indikator' => 'Indikator Sasaran Import']);
        $this->assertDatabaseHas('opd_program', ['kode' => 'P1', 'nama' => 'Program Import']);
        $this->assertDatabaseHas('indikator_opd_program', ['kode' => 'IP1', 'indikator' => 'Indikator Program Import']);
        $this->assertDatabaseHas('target_indikator_opd_program', ['periode_tahun_id' => $periode->id, 'target_text' => '70 persen', 'pagu' => 500000]);
        $this->assertDatabaseHas('opd_kegiatan', ['kode' => 'K1', 'nama' => 'Kegiatan Import']);
        $this->assertDatabaseHas('opd_sub_kegiatan', ['kode' => 'SK1', 'nama' => 'Sub Kegiatan Import']);
        $this->assertDatabaseHas('indikator_sub_kegiatan', ['kode' => 'ISK1', 'indikator' => 'Indikator Sub Kegiatan Import']);
        $this->assertDatabaseHas('target_triwulan_indikator', [
            'related_table' => 'indikator_sub_kegiatan',
            'periode_tahun_id' => $periode->id,
            'triwulan' => 'tw1',
            'target_text' => '25 persen',
            'target_anggaran' => 50000,
        ]);
    }

    public function test_renstra_import_rolls_back_when_any_row_fails(): void
    {
        $this->seed();
        Storage::fake('local');
        config(['filesystems.default' => 'local']);

        $opd = Opd::create(['kode' => '4.02', 'nama' => 'Dinas Rollback', 'status' => 'active']);
        $tree = $this->createRpjmdTree();
        $user = User::factory()->create(['opd_id' => $opd->id]);
        $user->roles()->sync([Role::where('name', 'admin_opd')->value('id')]);

        $csv = $this->renstraImportCsv([
            ['level', 'opd_kode', 'rpjmd_id', 'renstra_judul', 'tahun_awal', 'tahun_akhir', 'kode', 'uraian'],
            ['renstra', '4.02', $tree['rpjmd']->id, 'Renstra Rollback', 2026, 2031, '', ''],
            ['program', '', '', '', '', '', 'P1', 'Program Tanpa Sasaran'],
        ]);

        $this->actingAs($user)
            ->post(route('renstra-opd.import.store'), [
                'file' => UploadedFile::fake()->createWithContent('renstra-rollback.csv', $csv),
            ])
            ->assertRedirect();

        $batch = ImportBatch::where('module', 'renstra_opd')->latest('id')->firstOrFail();

        $this->actingAs($user)
            ->post(route('renstra-opd.import.apply', $batch))
            ->assertRedirect(route('renstra-opd.import.show', $batch));

        $batch->refresh();

        $this->assertSame('failed', $batch->status);
        $this->assertTrue((bool) data_get($batch->metadata, 'applied.rolled_back'));
        $this->assertDatabaseMissing('renstra_opd', ['judul' => 'Renstra Rollback']);
        $this->assertDatabaseHas('import_batch_rows', [
            'import_batch_id' => $batch->id,
            'row_number' => 3,
            'status' => 'failed',
        ]);
    }

    public function test_admin_opd_cannot_apply_renstra_import_for_other_opd(): void
    {
        $this->seed();
        Storage::fake('local');
        config(['filesystems.default' => 'local']);

        $ownOpd = Opd::create(['kode' => '4.03', 'nama' => 'Dinas Sendiri', 'status' => 'active']);
        Opd::create(['kode' => '4.04', 'nama' => 'Dinas Lain Import', 'status' => 'active']);
        $tree = $this->createRpjmdTree();
        $user = User::factory()->create(['opd_id' => $ownOpd->id]);
        $user->roles()->sync([Role::where('name', 'admin_opd')->value('id')]);

        $csv = $this->renstraImportCsv([
            ['level', 'opd_kode', 'rpjmd_id', 'renstra_judul', 'tahun_awal', 'tahun_akhir'],
            ['renstra', '4.04', $tree['rpjmd']->id, 'Renstra OPD Lain Dari Import', 2026, 2031],
        ]);

        $this->actingAs($user)
            ->post(route('renstra-opd.import.store'), [
                'file' => UploadedFile::fake()->createWithContent('renstra-opd-lain.csv', $csv),
            ])
            ->assertRedirect();

        $batch = ImportBatch::where('module', 'renstra_opd')->latest('id')->firstOrFail();

        $this->actingAs($user)
            ->post(route('renstra-opd.import.apply', $batch))
            ->assertRedirect(route('renstra-opd.import.show', $batch));

        $batch->refresh();

        $this->assertSame('failed', $batch->status);
        $this->assertDatabaseMissing('renstra_opd', ['judul' => 'Renstra OPD Lain Dari Import']);
    }

    private function createRpjmdTree(): array
    {
        $rpjmd = Rpjmd::create(['judul' => 'RPJMD Link Renstra', 'tahun_awal' => 2026, 'tahun_akhir' => 2031, 'status' => 'approved']);
        $visi = RpjmdVisi::create(['rpjmd_id' => $rpjmd->id, 'visi' => 'Visi Kabupaten', 'urutan' => 1]);
        $misi = RpjmdMisi::create(['rpjmd_id' => $rpjmd->id, 'rpjmd_visi_id' => $visi->id, 'misi' => 'Misi Kabupaten', 'urutan' => 1]);
        $tujuan = TujuanDaerah::create(['rpjmd_visi_id' => $visi->id, 'rpjmd_misi_id' => null, 'tujuan' => 'Tujuan Daerah', 'urutan' => 1]);
        $indikatorTujuan = IndikatorTujuanDaerah::create(['tujuan_daerah_id' => $tujuan->id, 'indikator' => 'Indikator Tujuan Daerah', 'urutan' => 1]);
        $sasaran = SasaranDaerah::create(['tujuan_daerah_id' => $tujuan->id, 'sasaran' => 'Sasaran Daerah', 'urutan' => 1]);
        $indikatorSasaran = IndikatorSasaranDaerah::create(['sasaran_daerah_id' => $sasaran->id, 'indikator' => 'Indikator Sasaran Daerah', 'urutan' => 1]);
        $strategi = StrategiDaerah::create(['strategi' => 'Strategi Daerah', 'status' => 'active']);
        $program = ProgramRpjmd::create([
            'strategi_daerah_id' => $strategi->id,
            'sasaran_daerah_id' => $sasaran->id,
            'indikator_sasaran_daerah_id' => $indikatorSasaran->id,
            'nama' => 'Program RPJMD',
            'status' => 'approved',
            'urutan' => 1,
        ]);
        $indikatorProgram = IndikatorProgramRpjmd::create(['program_rpjmd_id' => $program->id, 'indikator' => 'Indikator Program RPJMD', 'urutan' => 1]);

        return [
            'rpjmd' => $rpjmd,
            'tujuan_daerah' => $tujuan,
            'indikator_tujuan' => $indikatorTujuan,
            'sasaran_daerah' => $sasaran,
            'indikator_sasaran' => $indikatorSasaran,
            'program_rpjmd' => $program,
            'indikator_program' => $indikatorProgram,
        ];
    }

    /**
     * @param  array<int, array<int, mixed>>  $rows
     */
    private function renstraImportCsv(array $rows): string
    {
        return collect($rows)
            ->map(fn (array $row) => collect($row)
                ->map(fn (mixed $value) => $this->csvValue($value))
                ->implode(',')
            )
            ->implode("\n");
    }

    private function csvValue(mixed $value): string
    {
        $value = (string) $value;

        if (str_contains($value, ',') || str_contains($value, '"') || str_contains($value, "\n")) {
            return '"'.str_replace('"', '""', $value).'"';
        }

        return $value;
    }
}
