<?php

namespace Tests\Feature;

use App\Models\Opd;
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

    private function createRpjmdWithProgramForOpd(Opd $opd, string $judul): Rpjmd
    {
        $rpjmd = Rpjmd::create([
            'judul' => $judul,
            'tahun_awal' => 2026,
            'tahun_akhir' => 2031,
            'status' => 'approved',
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
