<?php

namespace Tests\Feature;

use App\Models\ImportBatch;
use App\Models\IndikatorProgramRpjmd;
use App\Models\Opd;
use App\Models\PeriodeTahun;
use App\Models\ProgramRpjmd;
use App\Models\ProgramRpjmdOpdPenanggungJawab;
use App\Models\Role;
use App\Models\Rpjmd;
use App\Models\RpjmdMisi;
use App\Models\RpjmdVisi;
use App\Models\SasaranDaerah;
use App\Models\StrategiDaerah;
use App\Models\TujuanDaerah;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class RpjmdAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_bapperida_can_create_rpjmd_and_add_visi(): void
    {
        $this->seed();

        $user = User::factory()->create();
        $user->roles()->sync([Role::where('name', 'admin_kabupaten_bapperida')->value('id')]);

        $this->actingAs($user)
            ->post(route('rpjmd.store'), [
                'judul' => 'RPJMD Kabupaten Banjarnegara 2026-2031',
                'tahun_awal' => 2026,
                'tahun_akhir' => 2031,
                'status' => 'draft',
            ])
            ->assertRedirect();

        $rpjmd = Rpjmd::where('judul', 'RPJMD Kabupaten Banjarnegara 2026-2031')->firstOrFail();

        $this->actingAs($user)
            ->post(route('rpjmd.nodes.store', $rpjmd), [
                'type' => 'visi',
                'uraian' => 'Banjarnegara maju dan berdaya saing',
                'urutan' => 1,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('rpjmd_visi', [
            'rpjmd_id' => $rpjmd->id,
            'visi' => 'Banjarnegara maju dan berdaya saing',
        ]);
    }

    public function test_bagian_organisasi_can_view_but_cannot_manage_rpjmd(): void
    {
        $this->seed();

        $rpjmd = Rpjmd::create([
            'judul' => 'RPJMD Monitoring',
            'tahun_awal' => 2026,
            'tahun_akhir' => 2031,
            'status' => 'draft',
        ]);

        $user = User::factory()->create();
        $user->roles()->sync([Role::where('name', 'admin_kabupaten_bagian_organisasi')->value('id')]);

        $this->actingAs($user)
            ->get(route('rpjmd.index'))
            ->assertOk();

        $this->actingAs($user)
            ->get(route('rpjmd.show', $rpjmd))
            ->assertOk();

        $this->actingAs($user)
            ->get(route('rpjmd.create'))
            ->assertForbidden();
    }

    public function test_pimpinan_can_view_rpjmd_read_only(): void
    {
        $this->seed();

        $rpjmd = Rpjmd::create([
            'judul' => 'RPJMD Pimpinan',
            'tahun_awal' => 2026,
            'tahun_akhir' => 2031,
            'status' => 'approved',
        ]);

        $user = User::factory()->create();
        $user->roles()->sync([Role::where('name', 'pimpinan')->value('id')]);

        $this->actingAs($user)
            ->get(route('rpjmd.index'))
            ->assertOk();

        $this->actingAs($user)
            ->get(route('rpjmd.show', $rpjmd))
            ->assertOk();

        $this->actingAs($user)
            ->post(route('rpjmd.nodes.store', $rpjmd), [
                'type' => 'visi',
                'uraian' => 'Tidak boleh masuk',
            ])
            ->assertForbidden();
    }

    public function test_inspektorat_can_view_rpjmd_read_only(): void
    {
        $this->seed();

        $rpjmd = Rpjmd::create([
            'judul' => 'RPJMD Bahan Evaluasi',
            'tahun_awal' => 2026,
            'tahun_akhir' => 2031,
            'status' => 'approved',
        ]);

        $user = User::factory()->create();
        $user->roles()->sync([Role::where('name', 'admin_kabupaten_inspektorat')->value('id')]);

        $this->actingAs($user)
            ->get(route('rpjmd.index'))
            ->assertOk();

        $this->actingAs($user)
            ->get(route('rpjmd.show', $rpjmd))
            ->assertOk();

        $this->actingAs($user)
            ->get(route('rpjmd.create'))
            ->assertForbidden();
    }

    public function test_admin_opd_only_views_relevant_rpjmd(): void
    {
        $this->seed();

        $opd = Opd::create(['kode' => '1.01', 'nama' => 'Dinas Relevan', 'status' => 'active']);
        $otherOpd = Opd::create(['kode' => '1.02', 'nama' => 'Dinas Lain', 'status' => 'active']);
        $relevantRpjmd = $this->createRpjmdWithProgramForOpd($opd, 'RPJMD Relevan OPD');
        $otherRpjmd = $this->createRpjmdWithProgramForOpd($otherOpd, 'RPJMD OPD Lain');

        $user = User::factory()->create(['opd_id' => $opd->id]);
        $user->roles()->sync([Role::where('name', 'admin_opd')->value('id')]);

        $this->actingAs($user)
            ->get(route('rpjmd.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Rpjmd/Index')
                ->has('rpjmds.data', 1)
                ->where('rpjmds.data.0.id', $relevantRpjmd->id)
            );

        $this->actingAs($user)
            ->get(route('rpjmd.show', $relevantRpjmd))
            ->assertOk();

        $this->actingAs($user)
            ->get(route('rpjmd.show', $otherRpjmd))
            ->assertForbidden();

        $this->actingAs($user)
            ->post(route('rpjmd.nodes.store', $relevantRpjmd), [
                'type' => 'visi',
                'uraian' => 'Tidak boleh mengubah',
            ])
            ->assertForbidden();
    }

    public function test_program_rpjmd_can_have_multiple_opd_penanggung_jawab(): void
    {
        $this->seed();

        $opdA = Opd::create(['kode' => '2.01', 'nama' => 'Dinas A', 'status' => 'active']);
        $opdB = Opd::create(['kode' => '2.02', 'nama' => 'Dinas B', 'status' => 'active']);
        $rpjmd = $this->createRpjmdWithProgramForOpd($opdA, 'RPJMD Multi OPD');
        $program = ProgramRpjmd::firstOrFail();

        $user = User::factory()->create();
        $user->roles()->sync([Role::where('name', 'admin_kabupaten_bapperida')->value('id')]);

        $this->actingAs($user)
            ->post(route('rpjmd.nodes.store', $rpjmd), [
                'type' => 'program_opd',
                'parent_id' => $program->id,
                'opd_id' => $opdB->id,
                'peran' => 'pendukung',
                'is_utama' => false,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('program_rpjmd_opd_penanggung_jawab', [
            'program_rpjmd_id' => $program->id,
            'opd_id' => $opdA->id,
        ]);

        $this->assertDatabaseHas('program_rpjmd_opd_penanggung_jawab', [
            'program_rpjmd_id' => $program->id,
            'opd_id' => $opdB->id,
            'peran' => 'pendukung',
        ]);

        $this->assertCount(2, $program->fresh()->opdPenanggungJawab);
    }

    public function test_bapperida_can_update_rpjmd_cascading_nodes(): void
    {
        $this->seed();

        $opd = Opd::create(['kode' => '2.04', 'nama' => 'Dinas Update', 'status' => 'active']);
        $rpjmd = $this->createRpjmdWithProgramForOpd($opd, 'RPJMD Update Node');
        $visi = RpjmdVisi::where('rpjmd_id', $rpjmd->id)->firstOrFail();
        $misi = RpjmdMisi::where('rpjmd_id', $rpjmd->id)->firstOrFail();
        $program = ProgramRpjmd::firstOrFail();
        $pivot = ProgramRpjmdOpdPenanggungJawab::where('program_rpjmd_id', $program->id)->firstOrFail();

        $user = User::factory()->create();
        $user->roles()->sync([Role::where('name', 'admin_kabupaten_bapperida')->value('id')]);

        $this->actingAs($user)
            ->put(route('rpjmd.nodes.update', [$rpjmd, 'misi', $misi->id]), [
                'type' => 'misi',
                'parent_id' => $visi->id,
                'kode' => 'M.UPD',
                'uraian' => 'Misi RPJMD diperbarui',
                'urutan' => 2,
            ])
            ->assertRedirect();

        $this->actingAs($user)
            ->put(route('rpjmd.nodes.update', [$rpjmd, 'program_opd', $pivot->id]), [
                'type' => 'program_opd',
                'parent_id' => $program->id,
                'opd_id' => $opd->id,
                'peran' => 'koordinator',
                'is_utama' => true,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('rpjmd_misi', [
            'id' => $misi->id,
            'kode' => 'M.UPD',
            'misi' => 'Misi RPJMD diperbarui',
            'urutan' => 2,
        ]);

        $this->assertDatabaseHas('program_rpjmd_opd_penanggung_jawab', [
            'id' => $pivot->id,
            'peran' => 'koordinator',
        ]);
    }

    public function test_bapperida_can_save_target_triwulan_for_rpjmd_indicator(): void
    {
        $this->seed();

        $opd = Opd::create(['kode' => '2.03', 'nama' => 'Dinas C', 'status' => 'active']);
        $this->createRpjmdWithProgramForOpd($opd, 'RPJMD Target Triwulan');
        $periode = PeriodeTahun::orderBy('tahun')->firstOrFail();
        $program = ProgramRpjmd::firstOrFail();
        $indikator = IndikatorProgramRpjmd::create([
            'program_rpjmd_id' => $program->id,
            'indikator' => 'Indikator Program Target Triwulan',
            'urutan' => 1,
        ]);

        $user = User::factory()->create();
        $user->roles()->sync([Role::where('name', 'admin_kabupaten_bapperida')->value('id')]);

        $this->actingAs($user)
            ->post(route('target-triwulan-indikator.store'), [
                'related_table' => 'indikator_program_rpjmd',
                'related_id' => $indikator->id,
                'periode_tahun_id' => $periode->id,
                'triwulan' => 'tw1',
                'target_text' => '25 persen',
                'target_angka' => 25,
                'target_anggaran' => 1000000,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('target_triwulan_indikator', [
            'related_table' => 'indikator_program_rpjmd',
            'related_id' => $indikator->id,
            'periode_tahun_id' => $periode->id,
            'triwulan' => 'tw1',
            'target_text' => '25 persen',
        ]);

        $adminOpd = User::factory()->create(['opd_id' => $opd->id]);
        $adminOpd->roles()->sync([Role::where('name', 'admin_opd')->value('id')]);

        $this->actingAs($adminOpd)
            ->post(route('target-triwulan-indikator.store'), [
                'related_table' => 'indikator_program_rpjmd',
                'related_id' => $indikator->id,
                'periode_tahun_id' => $periode->id,
                'triwulan' => 'tw2',
                'target_text' => '50 persen',
            ])
            ->assertForbidden();
    }

    public function test_approved_rpjmd_cannot_be_changed_by_bapperida_without_revision(): void
    {
        $this->seed();

        $rpjmd = Rpjmd::create([
            'judul' => 'RPJMD Final',
            'tahun_awal' => 2026,
            'tahun_akhir' => 2031,
            'status' => 'approved',
        ]);

        $user = User::factory()->create();
        $user->roles()->sync([Role::where('name', 'admin_kabupaten_bapperida')->value('id')]);

        $this->actingAs($user)
            ->post(route('rpjmd.nodes.store', $rpjmd), [
                'type' => 'visi',
                'uraian' => 'Perubahan tanpa revisi',
                'urutan' => 1,
            ])
            ->assertForbidden();
    }

    public function test_rpjmd_import_csv_saves_batch_rows_and_preview(): void
    {
        $this->seed();
        Storage::fake('local');

        $user = User::factory()->create();
        $user->roles()->sync([Role::where('name', 'admin_kabupaten_bapperida')->value('id')]);

        $file = UploadedFile::fake()->createWithContent(
            'rpjmd.csv',
            "level,kode,uraian\nvisi,,Visi Kabupaten\nmisi,M1,Misi Pertama\n"
        );

        $this->actingAs($user)
            ->post(route('rpjmd.import.store'), ['file' => $file])
            ->assertRedirect();

        $this->assertDatabaseHas('import_batches', [
            'module' => 'rpjmd',
            'import_type' => 'cascading_rpjmd',
            'status' => 'previewed',
            'original_filename' => 'rpjmd.csv',
            'total_rows' => 3,
        ]);

        $this->assertDatabaseCount('import_batch_rows', 3);
        $this->assertDatabaseHas('import_batch_rows', [
            'row_number' => 2,
            'status' => 'preview',
        ]);
    }

    public function test_rpjmd_import_preview_can_be_applied_to_cascading_tables(): void
    {
        $this->seed();
        Storage::fake('local');

        $user = User::factory()->create();
        $user->roles()->sync([Role::where('name', 'admin_kabupaten_bapperida')->value('id')]);
        $opd = Opd::create(['kode' => '1.99', 'nama' => 'Dinas Import RPJMD', 'status' => 'active']);

        $csv = implode("\n", [
            'level,rpjmd_judul,tahun_awal,tahun_akhir,kode,uraian,tahun_target,target,target_text,pagu,opd_kode',
            'rpjmd,RPJMD Import Apply,2026,2030,,,,,,,',
            'visi,,,,,Visi Import,,,,,',
            'misi,,,,M1,Misi Import,,,,,',
            'tujuan,,,,T1,Tujuan Import,,,,,',
            'indikator_tujuan,,,,IT1,Indeks Tujuan Import,2026,80,80 poin,,',
            'sasaran,,,,S1,Sasaran Import,,,,,',
            'indikator_sasaran,,,,IS1,Indeks Sasaran Import,2026,75,75 poin,,',
            'strategi,,,,ST1,Strategi Import,,,,,',
            'program,,,,P1,Program Import,,,,1000000,',
            'indikator_program,,,,IP1,Indikator Program Import,2026,70,70 persen,500000,',
            "opd_penanggung_jawab,,,,,,,,,,{$opd->kode}",
        ]);

        $file = UploadedFile::fake()->createWithContent('rpjmd-apply.csv', $csv);

        $this->actingAs($user)
            ->post(route('rpjmd.import.store'), ['file' => $file])
            ->assertRedirect();

        $batch = ImportBatch::query()->latest('id')->firstOrFail();

        $this->actingAs($user)
            ->post(route('rpjmd.import.apply', $batch))
            ->assertRedirect(route('rpjmd.import.show', $batch));

        $batch->refresh();

        $this->assertSame('imported', $batch->status);
        $this->assertDatabaseHas('rpjmd', ['judul' => 'RPJMD Import Apply', 'tahun_awal' => 2026, 'tahun_akhir' => 2030]);
        $this->assertDatabaseHas('rpjmd_visi', ['visi' => 'Visi Import']);
        $this->assertDatabaseHas('rpjmd_misi', ['kode' => 'M1', 'misi' => 'Misi Import']);
        $this->assertDatabaseHas('tujuan_daerah', ['kode' => 'T1', 'tujuan' => 'Tujuan Import']);
        $this->assertDatabaseHas('indikator_tujuan_daerah', ['kode' => 'IT1', 'indikator' => 'Indeks Tujuan Import']);
        $this->assertDatabaseHas('target_indikator_tujuan_daerah', ['target_text' => '80 poin']);
        $this->assertDatabaseHas('sasaran_daerah', ['kode' => 'S1', 'sasaran' => 'Sasaran Import']);
        $this->assertDatabaseHas('indikator_sasaran_daerah', ['kode' => 'IS1', 'indikator' => 'Indeks Sasaran Import']);
        $this->assertDatabaseHas('target_indikator_sasaran_daerah', ['target_text' => '75 poin']);
        $this->assertDatabaseHas('strategi_daerah', ['kode' => 'ST1', 'strategi' => 'Strategi Import']);
        $this->assertDatabaseHas('program_rpjmd', ['kode' => 'P1', 'nama' => 'Program Import']);
        $this->assertDatabaseHas('indikator_program_rpjmd', ['kode' => 'IP1', 'indikator' => 'Indikator Program Import']);
        $this->assertDatabaseHas('target_indikator_program_rpjmd', ['target_text' => '70 persen']);

        $program = ProgramRpjmd::query()->where('kode', 'P1')->firstOrFail();
        $this->assertDatabaseHas('program_rpjmd_opd_penanggung_jawab', [
            'program_rpjmd_id' => $program->id,
            'opd_id' => $opd->id,
        ]);
        $this->assertDatabaseHas('import_batch_rows', [
            'import_batch_id' => $batch->id,
            'row_number' => 1,
            'status' => 'skipped',
        ]);
    }

    private function createRpjmdWithProgramForOpd(Opd $opd, string $judul): Rpjmd
    {
        $rpjmd = Rpjmd::create([
            'judul' => $judul,
            'tahun_awal' => 2026,
            'tahun_akhir' => 2031,
            'status' => 'active',
        ]);

        $visi = RpjmdVisi::create([
            'rpjmd_id' => $rpjmd->id,
            'visi' => "Visi {$judul}",
            'urutan' => 1,
        ]);

        $misi = RpjmdMisi::create([
            'rpjmd_id' => $rpjmd->id,
            'rpjmd_visi_id' => $visi->id,
            'misi' => "Misi {$judul}",
            'urutan' => 1,
        ]);

        $tujuan = TujuanDaerah::create([
            'rpjmd_misi_id' => $misi->id,
            'tujuan' => "Tujuan {$judul}",
            'urutan' => 1,
        ]);

        $sasaran = SasaranDaerah::create([
            'tujuan_daerah_id' => $tujuan->id,
            'sasaran' => "Sasaran {$judul}",
            'urutan' => 1,
        ]);

        $strategi = StrategiDaerah::create([
            'sasaran_daerah_id' => $sasaran->id,
            'strategi' => "Strategi {$judul}",
            'urutan' => 1,
        ]);

        $program = ProgramRpjmd::create([
            'strategi_daerah_id' => $strategi->id,
            'sasaran_daerah_id' => $sasaran->id,
            'nama' => "Program {$judul}",
            'status' => 'approved',
            'urutan' => 1,
        ]);

        ProgramRpjmdOpdPenanggungJawab::create([
            'program_rpjmd_id' => $program->id,
            'opd_id' => $opd->id,
            'peran' => 'penanggung_jawab',
            'is_utama' => true,
        ]);

        return $rpjmd;
    }
}
