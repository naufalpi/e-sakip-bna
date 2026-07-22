<?php

namespace Tests\Feature;

use App\Models\BidangUrusan;
use App\Models\ImportBatch;
use App\Models\IndikatorProgramRpjmd;
use App\Models\IndikatorSasaranDaerah;
use App\Models\IndikatorTujuanDaerah;
use App\Models\Opd;
use App\Models\PeriodeTahun;
use App\Models\ProgramPemerintahan;
use App\Models\ProgramRpjmd;
use App\Models\ProgramRpjmdOpdPenanggungJawab;
use App\Models\Role;
use App\Models\Rpjmd;
use App\Models\RpjmdMisi;
use App\Models\RpjmdVisi;
use App\Models\SasaranDaerah;
use App\Models\SatuanIndikator;
use App\Models\StrategiDaerah;
use App\Models\SystemSetting;
use App\Models\TargetIndikatorTujuanDaerah;
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
        $this->assertSame('tujuan_lintas_misi', $rpjmd->struktur_tujuan_mode);
        $this->assertSame('sasaran_langsung_tujuan', $rpjmd->struktur_sasaran_mode);

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

    public function test_bapperida_cannot_override_rpjmd_structure_modes_from_create_request(): void
    {
        $this->seed();

        SystemSetting::where('key', 'rpjmd.default_struktur_tujuan_mode')
            ->firstOrFail()
            ->update(['value' => 'tujuan_lintas_misi']);
        SystemSetting::where('key', 'rpjmd.default_struktur_sasaran_mode')
            ->firstOrFail()
            ->update(['value' => 'sasaran_langsung_tujuan']);

        $user = User::factory()->create();
        $user->roles()->sync([Role::where('name', 'admin_kabupaten_bapperida')->value('id')]);

        $this->actingAs($user)
            ->post(route('rpjmd.store'), [
                'judul' => 'RPJMD Struktur Tidak Boleh Diubah Admin',
                'tahun_awal' => 2025,
                'tahun_akhir' => 2029,
                'struktur_tujuan_mode' => 'tujuan_per_misi',
                'struktur_sasaran_mode' => 'campuran',
            ])
            ->assertRedirect();

        $rpjmd = Rpjmd::where('judul', 'RPJMD Struktur Tidak Boleh Diubah Admin')->firstOrFail();

        $this->assertSame('tujuan_lintas_misi', $rpjmd->struktur_tujuan_mode);
        $this->assertSame('sasaran_langsung_tujuan', $rpjmd->struktur_sasaran_mode);
    }

    public function test_only_super_admin_can_manage_rpjmd_structure_settings(): void
    {
        $this->seed();

        $setting = SystemSetting::where('key', 'rpjmd.default_struktur_tujuan_mode')->firstOrFail();
        $dinkominfo = User::factory()->create();
        $dinkominfo->roles()->sync([Role::where('name', 'admin_kabupaten_dinkominfo')->value('id')]);
        $superAdmin = User::factory()->create();
        $superAdmin->roles()->sync([Role::where('name', 'super_admin')->value('id')]);

        $this->actingAs($dinkominfo)
            ->put(route('master.system-settings.update', $setting), [
                'group' => 'rpjmd',
                'key' => 'rpjmd.default_struktur_tujuan_mode',
                'label' => 'Pola Tujuan Default',
                'type' => 'string',
                'value' => 'tujuan_per_misi',
                'is_public' => false,
            ])
            ->assertForbidden();

        $this->assertSame('tujuan_lintas_misi', $setting->refresh()->value);

        $this->actingAs($superAdmin)
            ->put(route('master.system-settings.update', $setting), [
                'group' => 'rpjmd',
                'key' => 'rpjmd.default_struktur_tujuan_mode',
                'label' => 'Pola Tujuan Default',
                'type' => 'string',
                'value' => 'tujuan_per_misi',
                'is_public' => false,
            ])
            ->assertRedirect(route('master.system-settings.index'));

        $this->assertSame('tujuan_per_misi', $setting->refresh()->value);
    }

    public function test_bapperida_can_bulk_create_rpjmd_nodes(): void
    {
        $this->seed();

        $user = User::factory()->create();
        $user->roles()->sync([Role::where('name', 'admin_kabupaten_bapperida')->value('id')]);

        $rpjmd = Rpjmd::create([
            'judul' => 'RPJMD Bulk Input',
            'tahun_awal' => 2026,
            'tahun_akhir' => 2031,
            'status' => 'draft',
        ]);
        $visi = RpjmdVisi::create([
            'rpjmd_id' => $rpjmd->id,
            'visi' => 'Visi Bulk RPJMD',
            'urutan' => 1,
        ]);

        $this->actingAs($user)
            ->post(route('rpjmd.nodes.bulk-store', $rpjmd), [
                'type' => 'tujuan',
                'parent_id' => $visi->id,
                'rows' => [
                    ['kode' => 'T.1', 'uraian' => 'Tujuan bulk pertama', 'urutan' => 1],
                    ['kode' => 'T.2', 'uraian' => 'Tujuan bulk kedua', 'urutan' => 2],
                    ['kode' => '', 'uraian' => '', 'urutan' => 3],
                ],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('tujuan_daerah', [
            'rpjmd_visi_id' => $visi->id,
            'kode' => 'T.1',
            'tujuan' => 'Tujuan bulk pertama',
        ]);
        $this->assertDatabaseHas('tujuan_daerah', [
            'rpjmd_visi_id' => $visi->id,
            'kode' => 'T.2',
            'tujuan' => 'Tujuan bulk kedua',
        ]);
        $this->assertDatabaseMissing('tujuan_daerah', [
            'rpjmd_visi_id' => $visi->id,
            'urutan' => 3,
        ]);
    }

    public function test_bapperida_can_bulk_create_rpjmd_indicator_metadata(): void
    {
        $this->seed();

        $user = User::factory()->create();
        $user->roles()->sync([Role::where('name', 'admin_kabupaten_bapperida')->value('id')]);

        $opd = Opd::create(['kode' => '1.98', 'nama' => 'Dinas Metadata RPJMD', 'status' => 'active']);
        $satuan = SatuanIndikator::query()->firstOrFail();
        $rpjmd = Rpjmd::create([
            'judul' => 'RPJMD Bulk Indikator',
            'tahun_awal' => 2025,
            'tahun_akhir' => 2029,
            'status' => 'draft',
        ]);
        $visi = RpjmdVisi::create(['rpjmd_id' => $rpjmd->id, 'visi' => 'Visi Indikator', 'urutan' => 1]);
        $tujuan = TujuanDaerah::create(['rpjmd_visi_id' => $visi->id, 'tujuan' => 'Tujuan Indikator', 'urutan' => 1]);

        $this->actingAs($user)
            ->post(route('rpjmd.nodes.bulk-store', $rpjmd), [
                'type' => 'indikator_tujuan',
                'parent_id' => $tujuan->id,
                'rows' => [[
                    'indikator' => 'Indikator Metadata',
                    'satuan_indikator_id' => $satuan->id,
                    'opd_id' => $opd->id,
                    'definisi_operasional' => 'Definisi indikator metadata.',
                    'alasan_pemilihan' => 'Alasan indikator dipilih.',
                    'formulasi_pengukuran' => '(Realisasi / Target) x 100',
                    'tipe_perhitungan' => 'kumulatif',
                    'sumber_data' => 'E-SAKIP',
                    'urutan' => 1,
                ]],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('indikator_tujuan_daerah', [
            'tujuan_daerah_id' => $tujuan->id,
            'indikator' => 'Indikator Metadata',
            'satuan_indikator_id' => $satuan->id,
            'opd_id' => $opd->id,
            'definisi_operasional' => 'Definisi indikator metadata.',
            'alasan_pemilihan' => 'Alasan indikator dipilih.',
            'formulasi_pengukuran' => '(Realisasi / Target) x 100',
            'tipe_perhitungan' => 'kumulatif',
            'sumber_data' => 'E-SAKIP',
        ]);
    }

    public function test_bapperida_can_bulk_save_target_tujuan_for_multiple_periods(): void
    {
        $this->seed();

        $user = User::factory()->create();
        $user->roles()->sync([Role::where('name', 'admin_kabupaten_bapperida')->value('id')]);

        $rpjmd = Rpjmd::create([
            'judul' => 'RPJMD Bulk Target Tahunan',
            'tahun_awal' => 2025,
            'tahun_akhir' => 2029,
            'status' => 'draft',
        ]);
        $visi = RpjmdVisi::create(['rpjmd_id' => $rpjmd->id, 'visi' => 'Visi Target Tahunan', 'urutan' => 1]);
        $tujuan = TujuanDaerah::create(['rpjmd_visi_id' => $visi->id, 'tujuan' => 'Tujuan Target Tahunan', 'urutan' => 1]);
        $indikator = IndikatorTujuanDaerah::create([
            'tujuan_daerah_id' => $tujuan->id,
            'indikator' => 'Indikator Target Tahunan',
            'urutan' => 1,
        ]);
        $periodes = collect(range(2025, 2027))->map(fn (int $year) => PeriodeTahun::updateOrCreate(
            ['tahun' => $year],
            [
                'nama' => "Tahun {$year}",
                'tanggal_mulai' => "{$year}-01-01",
                'tanggal_selesai' => "{$year}-12-31",
                'status' => 'draft',
            ],
        ));

        $this->actingAs($user)
            ->post(route('rpjmd.nodes.bulk-store', $rpjmd), [
                'type' => 'target_tujuan',
                'parent_id' => $indikator->id,
                'rows' => [
                    ['periode_tahun_id' => $periodes[0]->id, 'target' => '75 persen', 'urutan' => 1],
                    ['periode_tahun_id' => $periodes[1]->id, 'target' => '80,5', 'urutan' => 2],
                    ['periode_tahun_id' => $periodes[2]->id, 'target' => '', 'urutan' => 3],
                ],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('target_indikator_tujuan_daerah', [
            'indikator_tujuan_daerah_id' => $indikator->id,
            'periode_tahun_id' => $periodes[0]->id,
            'target' => '75 persen',
        ]);
        $this->assertDatabaseHas('target_indikator_tujuan_daerah', [
            'indikator_tujuan_daerah_id' => $indikator->id,
            'periode_tahun_id' => $periodes[1]->id,
            'target' => '80,5',
        ]);
        $this->assertDatabaseMissing('target_indikator_tujuan_daerah', [
            'indikator_tujuan_daerah_id' => $indikator->id,
            'periode_tahun_id' => $periodes[2]->id,
        ]);

        $existing = TargetIndikatorTujuanDaerah::query()
            ->where('indikator_tujuan_daerah_id', $indikator->id)
            ->where('periode_tahun_id', $periodes[1]->id)
            ->firstOrFail();

        $this->actingAs($user)
            ->post(route('rpjmd.nodes.bulk-store', $rpjmd), [
                'type' => 'target_tujuan',
                'parent_id' => $indikator->id,
                'rows' => [
                    [
                        'existing_target_id' => $existing->id,
                        'periode_tahun_id' => $periodes[1]->id,
                        'target' => '',
                        'urutan' => 2,
                    ],
                ],
            ])
            ->assertRedirect();

        $this->assertNull($existing->fresh()->target);
    }

    public function test_bapperida_can_link_one_tujuan_to_multiple_misi(): void
    {
        $this->seed();

        $user = User::factory()->create();
        $user->roles()->sync([Role::where('name', 'admin_kabupaten_bapperida')->value('id')]);

        $rpjmd = Rpjmd::create([
            'judul' => 'RPJMD Dinamis Banjarnegara',
            'tahun_awal' => 2025,
            'tahun_akhir' => 2029,
            'status' => 'draft',
            'struktur_tujuan_mode' => 'tujuan_lintas_misi',
            'struktur_sasaran_mode' => 'sasaran_langsung_tujuan',
        ]);
        $visi = RpjmdVisi::create(['rpjmd_id' => $rpjmd->id, 'visi' => 'Visi Daerah', 'urutan' => 1]);
        $misiIds = collect(range(1, 5))->map(fn (int $index) => RpjmdMisi::create([
            'rpjmd_id' => $rpjmd->id,
            'rpjmd_visi_id' => $visi->id,
            'misi' => "Misi {$index}",
            'urutan' => $index,
        ])->id)->all();

        $this->actingAs($user)
            ->post(route('rpjmd.nodes.store', $rpjmd), [
                'type' => 'tujuan',
                'parent_id' => $visi->id,
                'misi_ids' => $misiIds,
                'uraian' => 'Tujuan lintas lima misi',
                'urutan' => 1,
            ])
            ->assertRedirect();

        $tujuan = TujuanDaerah::where('tujuan', 'Tujuan lintas lima misi')->firstOrFail();

        foreach ($misiIds as $misiId) {
            $this->assertDatabaseHas('tujuan_daerah_misi', [
                'tujuan_daerah_id' => $tujuan->id,
                'rpjmd_misi_id' => $misiId,
            ]);
        }

        $this->assertSame($misiIds[0], $tujuan->fresh()->rpjmd_misi_id);
    }

    public function test_sasaran_can_be_mapped_to_indikator_tujuan_for_common_structure(): void
    {
        $this->seed();

        $user = User::factory()->create();
        $user->roles()->sync([Role::where('name', 'admin_kabupaten_bapperida')->value('id')]);

        $rpjmd = Rpjmd::create([
            'judul' => 'RPJMD Struktur Umum',
            'tahun_awal' => 2030,
            'tahun_akhir' => 2034,
            'status' => 'draft',
            'struktur_tujuan_mode' => 'tujuan_per_misi',
            'struktur_sasaran_mode' => 'sasaran_melalui_indikator_tujuan',
        ]);
        $visi = RpjmdVisi::create(['rpjmd_id' => $rpjmd->id, 'visi' => 'Visi Umum', 'urutan' => 1]);
        $misi = RpjmdMisi::create(['rpjmd_id' => $rpjmd->id, 'rpjmd_visi_id' => $visi->id, 'misi' => 'Misi Umum', 'urutan' => 1]);
        $tujuan = TujuanDaerah::create(['rpjmd_visi_id' => $visi->id, 'rpjmd_misi_id' => $misi->id, 'tujuan' => 'Tujuan Umum', 'urutan' => 1]);
        $tujuan->misiTerkait()->sync([$misi->id => ['urutan' => 1]]);
        $indikatorTujuan = IndikatorTujuanDaerah::create([
            'tujuan_daerah_id' => $tujuan->id,
            'indikator' => 'Indikator Tujuan Umum',
            'urutan' => 1,
        ]);

        $this->actingAs($user)
            ->post(route('rpjmd.nodes.store', $rpjmd), [
                'type' => 'sasaran',
                'parent_id' => $tujuan->id,
                'indikator_tujuan_ids' => [$indikatorTujuan->id],
                'uraian' => 'Sasaran melalui indikator tujuan',
                'urutan' => 1,
            ])
            ->assertRedirect();

        $sasaran = SasaranDaerah::where('sasaran', 'Sasaran melalui indikator tujuan')->firstOrFail();

        $this->assertDatabaseHas('sasaran_daerah_indikator_tujuan', [
            'sasaran_daerah_id' => $sasaran->id,
            'indikator_tujuan_daerah_id' => $indikatorTujuan->id,
        ]);
    }

    public function test_parent_options_are_grouped_and_sorted_by_rpjmd_hierarchy(): void
    {
        $this->seed();

        $user = User::factory()->create();
        $user->roles()->sync([Role::where('name', 'admin_kabupaten_bapperida')->value('id')]);
        $rpjmd = Rpjmd::create([
            'judul' => 'RPJMD Opsi Hierarkis',
            'tahun_awal' => 2025,
            'tahun_akhir' => 2029,
            'status' => 'draft',
        ]);
        $visi = RpjmdVisi::create(['rpjmd_id' => $rpjmd->id, 'visi' => 'Visi Opsi', 'urutan' => 1]);
        $tujuan = TujuanDaerah::create(['rpjmd_visi_id' => $visi->id, 'tujuan' => 'Tujuan Opsi', 'urutan' => 1]);
        $sasaranKedua = SasaranDaerah::create(['tujuan_daerah_id' => $tujuan->id, 'sasaran' => 'Sasaran Kedua', 'urutan' => 2]);
        $sasaranPertama = SasaranDaerah::create(['tujuan_daerah_id' => $tujuan->id, 'sasaran' => 'Sasaran Pertama', 'urutan' => 1]);
        IndikatorSasaranDaerah::create([
            'sasaran_daerah_id' => $sasaranKedua->id,
            'indikator' => 'Indikator Sasaran Kedua',
            'urutan' => 1,
        ]);
        IndikatorSasaranDaerah::create([
            'sasaran_daerah_id' => $sasaranPertama->id,
            'indikator' => 'Indikator Sasaran Pertama',
            'urutan' => 1,
        ]);

        $this->actingAs($user)
            ->get(route('rpjmd.show', $rpjmd))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('nodeOptions.indikator_sasaran.0.label', 'Indikator Sasaran Pertama')
                ->where('nodeOptions.indikator_sasaran.0.group', 'Sasaran: Sasaran Pertama')
                ->where('nodeOptions.indikator_sasaran.1.label', 'Indikator Sasaran Kedua')
                ->where('nodeOptions.indikator_sasaran.1.group', 'Sasaran: Sasaran Kedua')
            );
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

    public function test_program_rpjmd_can_save_opd_penanggung_jawab_from_program_form(): void
    {
        $this->seed();

        $opdA = Opd::create(['kode' => '2.81', 'nama' => 'Dinas Program A', 'status' => 'active']);
        $opdB = Opd::create(['kode' => '2.82', 'nama' => 'Dinas Program B', 'status' => 'active']);
        $rpjmd = $this->createRpjmdWithProgramForOpd($opdA, 'RPJMD Program Dengan OPD');
        $sasaran = SasaranDaerah::whereHas('tujuan', fn ($query) => $query->forRpjmd($rpjmd->id))->firstOrFail();

        $user = User::factory()->create();
        $user->roles()->sync([Role::where('name', 'admin_kabupaten_bapperida')->value('id')]);

        $this->actingAs($user)
            ->post(route('rpjmd.nodes.store', $rpjmd), [
                'type' => 'program',
                'parent_id' => $sasaran->id,
                'uraian' => 'Program langsung memilih PD penanggung jawab',
                'is_penanggung_jawab_manual' => true,
                'opd_ids' => [$opdA->id, $opdB->id],
                'urutan' => 8,
            ])
            ->assertRedirect();

        $program = ProgramRpjmd::where('nama', 'Program langsung memilih PD penanggung jawab')->firstOrFail();

        $this->assertDatabaseHas('program_rpjmd', [
            'id' => $program->id,
            'is_penanggung_jawab_manual' => true,
        ]);

        $this->assertDatabaseHas('program_rpjmd_opd_penanggung_jawab', [
            'program_rpjmd_id' => $program->id,
            'opd_id' => $opdA->id,
            'peran' => 'penanggung_jawab',
            'is_utama' => true,
        ]);

        $this->assertDatabaseHas('program_rpjmd_opd_penanggung_jawab', [
            'program_rpjmd_id' => $program->id,
            'opd_id' => $opdB->id,
            'peran' => 'penanggung_jawab',
            'is_utama' => true,
        ]);
    }

    public function test_indikator_program_pengampu_is_resolved_from_program_bidang_urusan(): void
    {
        $this->seed();

        $bidang = BidangUrusan::where('kode', '5.04')->firstOrFail();
        $opdPengampu = $bidang->opdPengampu()->firstOrFail();
        $rpjmd = $this->createRpjmdWithProgramForOpd($opdPengampu, 'RPJMD Pengampu Indikator Program');
        $sasaran = SasaranDaerah::whereHas('tujuan', fn ($query) => $query->forRpjmd($rpjmd->id))->firstOrFail();
        $programPemerintahan = ProgramPemerintahan::query()->updateOrCreate(
            [
                'tahun_awal' => $rpjmd->tahun_awal,
                'tahun_akhir' => $rpjmd->tahun_akhir,
                'bidang_urusan_id' => $bidang->id,
                'kode' => '5.04.02',
            ],
            [
                'nama' => 'PROGRAM PENGEMBANGAN SUMBER DAYA MANUSIA',
                'status' => 'active',
            ],
        );

        $user = User::factory()->create();
        $user->roles()->sync([Role::where('name', 'admin_kabupaten_bapperida')->value('id')]);

        $this->actingAs($user)
            ->post(route('rpjmd.nodes.store', $rpjmd), [
                'type' => 'program',
                'parent_id' => $sasaran->id,
                'program_pemerintahan_id' => $programPemerintahan->id,
                'urutan' => 1,
            ])
            ->assertRedirect();

        $program = ProgramRpjmd::where('nama', 'PROGRAM PENGEMBANGAN SUMBER DAYA MANUSIA')->firstOrFail();

        $this->assertFalse($program->is_penanggung_jawab_manual);
        $this->assertDatabaseHas('program_rpjmd_opd_penanggung_jawab', [
            'program_rpjmd_id' => $program->id,
            'opd_id' => $opdPengampu->id,
            'peran' => 'penanggung_jawab',
        ]);

        $this->actingAs($user)
            ->post(route('rpjmd.nodes.store', $rpjmd), [
                'type' => 'indikator_program',
                'parent_id' => $program->id,
                'indikator' => 'Indikator program mengikuti pengampu bidang',
                'cakupan_pengampu' => 'semua_opd',
                'opd_id' => Opd::whereKeyNot($opdPengampu->id)->value('id'),
                'urutan' => 1,
            ])
            ->assertRedirect();

        $indikator = IndikatorProgramRpjmd::where('indikator', 'Indikator program mengikuti pengampu bidang')->firstOrFail();

        $this->assertSame('opd_tertentu', $indikator->cakupan_pengampu);
        $this->assertSame($opdPengampu->id, $indikator->opd_id);
        $this->assertDatabaseHas('indikator_program_rpjmd_opd_pengampu', [
            'indikator_program_rpjmd_id' => $indikator->id,
            'opd_id' => $opdPengampu->id,
            'peran' => 'pengampu_data',
        ]);
    }

    public function test_indikator_program_penunjang_is_resolved_as_semua_opd(): void
    {
        $this->seed();

        $opd = Opd::firstOrFail();
        $rpjmd = $this->createRpjmdWithProgramForOpd($opd, 'RPJMD Pengampu Penunjang');
        $sasaran = SasaranDaerah::whereHas('tujuan', fn ($query) => $query->forRpjmd($rpjmd->id))->firstOrFail();
        $bidangs = BidangUrusan::query()->orderBy('kode')->take(2)->get();
        $programName = 'Program Penunjang Urusan Pemerintahan Daerah Kabupaten/Kota';
        $firstProgram = ProgramPemerintahan::query()->updateOrCreate(
            [
                'tahun_awal' => $rpjmd->tahun_awal,
                'tahun_akhir' => $rpjmd->tahun_akhir,
                'bidang_urusan_id' => $bidangs[0]->id,
                'kode' => '9.99.01',
            ],
            ['nama' => $programName, 'status' => 'active'],
        );
        ProgramPemerintahan::query()->updateOrCreate(
            [
                'tahun_awal' => $rpjmd->tahun_awal,
                'tahun_akhir' => $rpjmd->tahun_akhir,
                'bidang_urusan_id' => $bidangs[1]->id,
                'kode' => '9.99.02',
            ],
            ['nama' => $programName, 'status' => 'active'],
        );

        $user = User::factory()->create();
        $user->roles()->sync([Role::where('name', 'admin_kabupaten_bapperida')->value('id')]);

        $this->actingAs($user)
            ->post(route('rpjmd.nodes.store', $rpjmd), [
                'type' => 'program',
                'parent_id' => $sasaran->id,
                'program_pemerintahan_id' => $firstProgram->id,
                'urutan' => 1,
            ])
            ->assertRedirect();

        $program = ProgramRpjmd::where('nama', $programName)->firstOrFail();

        $this->actingAs($user)
            ->post(route('rpjmd.nodes.store', $rpjmd), [
                'type' => 'indikator_program',
                'parent_id' => $program->id,
                'indikator' => 'Persentase tingkat ketercapaian kinerja perangkat daerah',
                'opd_id' => $opd->id,
                'urutan' => 1,
            ])
            ->assertRedirect();

        $semuaOpd = IndikatorProgramRpjmd::where('indikator', 'Persentase tingkat ketercapaian kinerja perangkat daerah')->firstOrFail();

        $this->assertSame('semua_opd', $semuaOpd->cakupan_pengampu);
        $this->assertNull($semuaOpd->opd_id);
        $this->assertDatabaseMissing('indikator_program_rpjmd_opd_pengampu', [
            'indikator_program_rpjmd_id' => $semuaOpd->id,
        ]);
    }

    public function test_program_rpjmd_is_created_from_sasaran_with_optional_strategi_reference(): void
    {
        $this->seed();

        $opd = Opd::create(['kode' => '2.03', 'nama' => 'Dinas Program', 'status' => 'active']);
        $rpjmd = $this->createRpjmdWithProgramForOpd($opd, 'RPJMD Program Baru');
        $sasaran = SasaranDaerah::whereHas('tujuan', fn ($query) => $query->forRpjmd($rpjmd->id))->firstOrFail();
        $strategi = StrategiDaerah::firstOrFail();

        $user = User::factory()->create();
        $user->roles()->sync([Role::where('name', 'admin_kabupaten_bapperida')->value('id')]);

        $this->actingAs($user)
            ->post(route('rpjmd.nodes.store', $rpjmd), [
                'type' => 'program',
                'parent_id' => $sasaran->id,
                'strategi_daerah_id' => $strategi->id,
                'uraian' => 'Program Peningkatan Akuntabilitas Kinerja',
                'status' => 'draft',
                'urutan' => 2,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('program_rpjmd', [
            'indikator_sasaran_daerah_id' => null,
            'sasaran_daerah_id' => $sasaran->id,
            'strategi_daerah_id' => $strategi->id,
            'nama' => 'Program Peningkatan Akuntabilitas Kinerja',
            'urutan' => 2,
        ]);
    }

    public function test_program_rpjmd_can_use_program_pemerintahan_reference(): void
    {
        $this->seed();

        $opd = Opd::create(['kode' => '2.31', 'nama' => 'Dinas Program Referensi', 'status' => 'active']);
        $rpjmd = $this->createRpjmdWithProgramForOpd($opd, 'RPJMD Program Referensi');
        $sasaran = SasaranDaerah::whereHas('tujuan', fn ($query) => $query->forRpjmd($rpjmd->id))->firstOrFail();
        $bidang = BidangUrusan::query()->firstOrFail();
        $programPemerintahan = ProgramPemerintahan::query()->updateOrCreate(
            ['tahun_awal' => $rpjmd->tahun_awal, 'tahun_akhir' => $rpjmd->tahun_akhir, 'bidang_urusan_id' => $bidang->id, 'kode' => '9.98.01'],
            ['nama' => 'Program Referensi RPJMD', 'status' => 'active'],
        );
        $programPemerintahan->load('bidangUrusan.urusanPemerintahan');

        $user = User::factory()->create();
        $user->roles()->sync([Role::where('name', 'admin_kabupaten_bapperida')->value('id')]);

        $this->actingAs($user)
            ->post(route('rpjmd.nodes.store', $rpjmd), [
                'type' => 'program',
                'parent_id' => $sasaran->id,
                'program_pemerintahan_id' => $programPemerintahan->id,
                'status' => 'draft',
                'urutan' => 3,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('program_rpjmd', [
            'indikator_sasaran_daerah_id' => null,
            'sasaran_daerah_id' => $sasaran->id,
            'program_pemerintahan_id' => $programPemerintahan->id,
            'kode' => $programPemerintahan->kode,
            'nama' => $programPemerintahan->nama,
            'urusan_pemerintahan_id' => $programPemerintahan->bidangUrusan->urusan_pemerintahan_id,
            'urutan' => 3,
        ]);
    }

    public function test_program_rpjmd_links_all_master_programs_with_same_name(): void
    {
        $this->seed();

        $opd = Opd::create(['kode' => '2.32', 'nama' => 'Dinas Program Lintas Kode', 'status' => 'active']);
        $rpjmd = $this->createRpjmdWithProgramForOpd($opd, 'RPJMD Program Lintas Kode');
        $sasaran = SasaranDaerah::whereHas('tujuan', fn ($query) => $query->forRpjmd($rpjmd->id))->firstOrFail();
        $bidangs = BidangUrusan::query()->orderBy('kode')->take(2)->get();
        $programName = 'Program Penunjang Urusan Pemerintahan Daerah Kab/Kota';
        $firstProgram = ProgramPemerintahan::query()->updateOrCreate(
            ['tahun_awal' => $rpjmd->tahun_awal, 'tahun_akhir' => $rpjmd->tahun_akhir, 'bidang_urusan_id' => $bidangs[0]->id, 'kode' => '9.99.01'],
            ['nama' => $programName, 'status' => 'active'],
        );
        $secondProgram = ProgramPemerintahan::query()->updateOrCreate(
            ['tahun_awal' => $rpjmd->tahun_awal, 'tahun_akhir' => $rpjmd->tahun_akhir, 'bidang_urusan_id' => $bidangs[1]->id, 'kode' => '9.99.02'],
            ['nama' => $programName, 'status' => 'active'],
        );

        $user = User::factory()->create();
        $user->roles()->sync([Role::where('name', 'admin_kabupaten_bapperida')->value('id')]);

        $this->actingAs($user)
            ->post(route('rpjmd.nodes.store', $rpjmd), [
                'type' => 'program',
                'parent_id' => $sasaran->id,
                'program_pemerintahan_id' => $firstProgram->id,
                'status' => 'draft',
                'urutan' => 4,
            ])
            ->assertRedirect();

        $programRpjmd = ProgramRpjmd::query()
            ->where('sasaran_daerah_id', $sasaran->id)
            ->where('nama', $programName)
            ->firstOrFail();

        $this->assertDatabaseHas('program_rpjmd_program_pemerintahan', [
            'program_rpjmd_id' => $programRpjmd->id,
            'program_pemerintahan_id' => $firstProgram->id,
        ]);
        $this->assertDatabaseHas('program_rpjmd_program_pemerintahan', [
            'program_rpjmd_id' => $programRpjmd->id,
            'program_pemerintahan_id' => $secondProgram->id,
        ]);
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

    public function test_bapperida_can_autosave_rpjmd_node_as_json(): void
    {
        $this->seed();

        $opd = Opd::create(['kode' => '2.05', 'nama' => 'Dinas Autosave', 'status' => 'active']);
        $rpjmd = $this->createRpjmdWithProgramForOpd($opd, 'RPJMD Autosave Node');
        $visi = RpjmdVisi::where('rpjmd_id', $rpjmd->id)->firstOrFail();
        $misi = RpjmdMisi::where('rpjmd_id', $rpjmd->id)->firstOrFail();

        $user = User::factory()->create();
        $user->roles()->sync([Role::where('name', 'admin_kabupaten_bapperida')->value('id')]);

        $this->actingAs($user)
            ->putJson(route('rpjmd.nodes.update', [$rpjmd, 'misi', $misi->id]), [
                'type' => 'misi',
                'parent_id' => $visi->id,
                'kode' => 'M.AUTO',
                'uraian' => 'Misi tersimpan lewat autosave',
                'urutan' => 3,
            ])
            ->assertOk()
            ->assertJson([
                'id' => $misi->id,
                'type' => 'misi',
                'message' => 'Data cascading RPJMD berhasil diperbarui.',
            ]);

        $this->assertDatabaseHas('rpjmd_misi', [
            'id' => $misi->id,
            'kode' => 'M.AUTO',
            'misi' => 'Misi tersimpan lewat autosave',
            'urutan' => 3,
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

        $this->actingAs($user)
            ->post(route('target-triwulan-indikator.bulk-store'), [
                'related_table' => 'indikator_program_rpjmd',
                'related_id' => $indikator->id,
                'periode_tahun_id' => $periode->id,
                'targets' => [
                    ['triwulan' => 'tw1', 'target_text' => '25 persen', 'target_angka' => 25, 'target_anggaran' => 1000000],
                    ['triwulan' => 'tw2', 'target_text' => '50 persen', 'target_angka' => 50, 'target_anggaran' => 2000000],
                    ['triwulan' => 'tw3', 'target_text' => '75 persen', 'target_angka' => 75, 'target_anggaran' => 3000000],
                    ['triwulan' => 'tw4', 'target_text' => '100 persen', 'target_angka' => 100, 'target_anggaran' => 4000000],
                ],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('target_triwulan_indikator', [
            'related_table' => 'indikator_program_rpjmd',
            'related_id' => $indikator->id,
            'periode_tahun_id' => $periode->id,
            'triwulan' => 'tw4',
            'target_text' => '100 persen',
            'target_anggaran' => 4000000,
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

        $this->actingAs($adminOpd)
            ->post(route('target-triwulan-indikator.bulk-store'), [
                'related_table' => 'indikator_program_rpjmd',
                'related_id' => $indikator->id,
                'periode_tahun_id' => $periode->id,
                'targets' => [
                    ['triwulan' => 'tw1', 'target_text' => 'Tidak boleh'],
                ],
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

    public function test_rpjmd_status_cannot_be_changed_through_metadata_form(): void
    {
        $this->seed();

        $user = User::factory()->create();
        $user->roles()->sync([Role::where('name', 'admin_kabupaten_bapperida')->value('id')]);

        $this->actingAs($user)
            ->post(route('rpjmd.store'), [
                'judul' => 'RPJMD Status Workflow',
                'tahun_awal' => 2025,
                'tahun_akhir' => 2029,
                'status' => 'approved',
            ])
            ->assertRedirect();

        $rpjmd = Rpjmd::query()->where('judul', 'RPJMD Status Workflow')->firstOrFail();
        $this->assertSame('draft', $rpjmd->status);

        $this->actingAs($user)
            ->put(route('rpjmd.update', $rpjmd), [
                'judul' => $rpjmd->judul,
                'tahun_awal' => $rpjmd->tahun_awal,
                'tahun_akhir' => $rpjmd->tahun_akhir,
                'status' => 'approved',
            ])
            ->assertRedirect(route('rpjmd.show', $rpjmd));

        $this->assertSame('draft', $rpjmd->refresh()->status);
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
        $strategi = StrategiDaerah::create([
            'kode' => 'ST1',
            'strategi' => 'Strategi Import',
            'status' => 'active',
        ]);
        $satuan = SatuanIndikator::query()->where('nama', 'Persen')->firstOrFail();

        $csv = collect([
            ['level', 'rpjmd_judul', 'tahun_awal', 'tahun_akhir', 'kode', 'uraian', 'tahun_target', 'target', 'target_text', 'opd_kode', 'kode_strategi', 'satuan', 'misi_kode_terkait', 'indikator_tujuan_kode_terkait'],
            ['rpjmd', 'RPJMD Import Apply', 2026, 2030, null, null, null, null, null, null, null, null, null, null],
            ['visi', null, null, null, null, 'Visi Import', null, null, null, null, null, null, null, null],
            ['misi', null, null, null, 'M1', 'Misi Import', null, null, null, null, null, null, null, null],
            ['tujuan', null, null, null, 'T1', 'Tujuan Import', null, null, null, null, null, null, 'M1', null],
            ['indikator_tujuan', null, null, null, 'IT1', 'Indeks Tujuan Import', 2026, 80, '80 poin', null, null, 'Persen', null, null],
            ['sasaran', null, null, null, 'S1', 'Sasaran Import', null, null, null, null, null, null, null, 'IT1'],
            ['indikator_sasaran', null, null, null, 'IS1', 'Indeks Sasaran Import', 2026, 75, '75 poin', null, null, 'Persen', null, null],
            ['program', null, null, null, 'P1', 'Program Import', null, null, null, null, 'ST1', null, null, null],
            ['indikator_program', null, null, null, 'IP1', 'Indikator Program Import', 2026, 70, '70 persen', null, null, 'Persen', null, null],
            ['opd_penanggung_jawab', null, null, null, null, null, null, null, null, $opd->kode, null, null, null, null],
        ])->map(fn (array $row) => implode(',', $row))->implode("\n");

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
        $this->assertDatabaseHas('indikator_tujuan_daerah', [
            'kode' => 'IT1',
            'indikator' => 'Indeks Tujuan Import',
            'satuan_indikator_id' => $satuan->id,
        ]);
        $this->assertDatabaseHas('target_indikator_tujuan_daerah', ['target_text' => '80 poin']);
        $this->assertDatabaseHas('sasaran_daerah', ['kode' => 'S1', 'sasaran' => 'Sasaran Import']);
        $this->assertDatabaseHas('indikator_sasaran_daerah', ['kode' => 'IS1', 'indikator' => 'Indeks Sasaran Import']);
        $this->assertDatabaseHas('target_indikator_sasaran_daerah', ['target_text' => '75 poin']);
        $this->assertDatabaseHas('program_rpjmd', [
            'kode' => 'P1',
            'nama' => 'Program Import',
            'strategi_daerah_id' => $strategi->id,
        ]);
        $this->assertDatabaseHas('indikator_program_rpjmd', ['kode' => 'IP1', 'indikator' => 'Indikator Program Import']);
        $this->assertDatabaseHas('target_indikator_program_rpjmd', ['target_text' => '70 persen']);

        $tujuan = TujuanDaerah::query()->where('kode', 'T1')->firstOrFail();
        $misi = RpjmdMisi::query()->where('kode', 'M1')->firstOrFail();
        $sasaran = SasaranDaerah::query()->where('kode', 'S1')->firstOrFail();
        $indikatorTujuan = IndikatorTujuanDaerah::query()->where('kode', 'IT1')->firstOrFail();
        $this->assertDatabaseHas('tujuan_daerah_misi', [
            'tujuan_daerah_id' => $tujuan->id,
            'rpjmd_misi_id' => $misi->id,
        ]);
        $this->assertDatabaseHas('sasaran_daerah_indikator_tujuan', [
            'sasaran_daerah_id' => $sasaran->id,
            'indikator_tujuan_daerah_id' => $indikatorTujuan->id,
        ]);

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

    public function test_import_cannot_modify_approved_rpjmd(): void
    {
        $this->seed();
        Storage::fake('local');

        $user = User::factory()->create();
        $user->roles()->sync([Role::where('name', 'admin_kabupaten_bapperida')->value('id')]);
        $rpjmd = Rpjmd::create([
            'judul' => 'RPJMD Import Terkunci',
            'tahun_awal' => 2025,
            'tahun_akhir' => 2029,
            'status' => 'approved',
        ]);

        $csv = implode("\n", [
            'level,rpjmd_id,rpjmd_judul,tahun_awal,tahun_akhir,uraian',
            "rpjmd,{$rpjmd->id},{$rpjmd->judul},2025,2029,{$rpjmd->judul}",
            'visi,,,,,Visi yang tidak boleh masuk',
        ]);

        $this->actingAs($user)
            ->post(route('rpjmd.import.store'), [
                'file' => UploadedFile::fake()->createWithContent('rpjmd-locked.csv', $csv),
            ])
            ->assertRedirect();

        $batch = ImportBatch::query()->latest('id')->firstOrFail();

        $this->actingAs($user)
            ->post(route('rpjmd.import.apply', $batch))
            ->assertRedirect(route('rpjmd.import.show', $batch));

        $this->assertSame('failed', $batch->refresh()->status);
        $this->assertDatabaseMissing('rpjmd_visi', [
            'rpjmd_id' => $rpjmd->id,
            'visi' => 'Visi yang tidak boleh masuk',
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
            'rpjmd_visi_id' => $visi->id,
            'rpjmd_misi_id' => null,
            'tujuan' => "Tujuan {$judul}",
            'urutan' => 1,
        ]);
        $tujuan->misiTerkait()->sync([$misi->id => ['urutan' => 1]]);

        $sasaran = SasaranDaerah::create([
            'tujuan_daerah_id' => $tujuan->id,
            'sasaran' => "Sasaran {$judul}",
            'urutan' => 1,
        ]);

        $strategi = StrategiDaerah::create([
            'strategi' => "Strategi {$judul}",
            'status' => 'active',
        ]);
        $indikatorSasaran = IndikatorSasaranDaerah::create([
            'sasaran_daerah_id' => $sasaran->id,
            'indikator' => "Indikator Sasaran {$judul}",
            'urutan' => 1,
        ]);

        $program = ProgramRpjmd::create([
            'strategi_daerah_id' => $strategi->id,
            'sasaran_daerah_id' => $sasaran->id,
            'indikator_sasaran_daerah_id' => null,
            'nama' => "Program {$judul}",
            'status' => 'approved',
            'is_penanggung_jawab_manual' => true,
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
