<?php

namespace Tests\Feature;

use App\Http\Controllers\Kinerja\PerjanjianKinerjaController;
use App\Models\DpaOpd;
use App\Models\DpaOpdItem;
use App\Models\IndikatorOpdKegiatan;
use App\Models\IndikatorOpdProgram;
use App\Models\IndikatorSasaranOpd;
use App\Models\IndikatorSubKegiatan;
use App\Models\JabatanOrganisasi;
use App\Models\Opd;
use App\Models\OpdKegiatan;
use App\Models\OpdProgram;
use App\Models\OpdSubKegiatan;
use App\Models\Pegawai;
use App\Models\PeriodeTahun;
use App\Models\PerjanjianKinerja;
use App\Models\PerjanjianKinerjaItem;
use App\Models\RealisasiKinerja;
use App\Models\RealisasiProgram;
use App\Models\RencanaAksi;
use App\Models\RencanaAksiItem;
use App\Models\RenjaOpd;
use App\Models\RenstraOpd;
use App\Models\Role;
use App\Models\Rpjmd;
use App\Models\SasaranOpd;
use App\Models\TargetIndikatorOpdKegiatan;
use App\Models\TargetIndikatorOpdProgram;
use App\Models\TargetIndikatorSasaranOpd;
use App\Models\TargetIndikatorSubKegiatan;
use App\Models\TujuanOpd;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class KinerjaWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_kepala_opd_uses_current_active_bupati_when_document_date_is_empty(): void
    {
        $this->seed();
        $opd = Opd::query()->where('status', 'active')->firstOrFail();
        $periode = PeriodeTahun::query()->firstOrFail();
        $bupatiJob = JabatanOrganisasi::create([
            'nama' => 'Bupati Banjarnegara',
            'level_jabatan' => 'kepala_daerah',
            'status' => 'active',
        ]);
        $headJob = JabatanOrganisasi::create([
            'opd_id' => $opd->id,
            'parent_id' => $bupatiJob->id,
            'nama' => 'Kepala Dinas Pengujian',
            'level_jabatan' => 'jpt_pratama',
            'status' => 'active',
        ]);
        $head = Pegawai::create([
            'opd_id' => $opd->id,
            'nama' => 'Kepala Dinas Pengujian',
            'jenis_pegawai' => 'pns',
            'status' => 'active',
        ]);
        $bupati = Pegawai::create([
            'nama' => 'Bupati Aktif',
            'jenis_pegawai' => 'pejabat_negara',
            'status' => 'active',
        ]);
        $headPlacement = $head->penempatan()->create([
            'jabatan_organisasi_id' => $headJob->id,
            'nama_pejabat' => $head->nama,
            'jenis_penugasan' => 'definitif',
            'tanggal_mulai' => now()->subYear()->toDateString(),
        ]);
        $bupati->penempatan()->create([
            'jabatan_organisasi_id' => $bupatiJob->id,
            'nama_pejabat' => $bupati->nama,
            'jenis_penugasan' => 'definitif',
            'tanggal_mulai' => now()->subDay()->toDateString(),
        ]);

        $method = new \ReflectionMethod(PerjanjianKinerjaController::class, 'prepareSubjectData');
        $data = $method->invoke(app(PerjanjianKinerjaController::class), [
            'opd_id' => $opd->id,
            'pegawai_id' => $head->id,
            'penempatan_pegawai_id' => $headPlacement->id,
            'atasan_pegawai_id' => $bupati->id,
            'tipe_pk' => 'cascading',
            'level_pk' => 'kepala_opd',
            'periode_tahun_id' => $periode->id,
            'tahun' => (int) now()->format('Y'),
            'tanggal_dokumen' => null,
            'dpa_opd_id' => null,
        ]);

        $this->assertSame($bupati->nama, $data['nama_atasan_snapshot']);
        $this->assertSame('Bupati Banjarnegara', $data['jabatan_atasan_snapshot']);
        $this->assertSame('PK Kepala Dinas Pengujian Tahun '.now()->format('Y'), $data['judul']);
    }

    public function test_pk_uses_active_job_opd_as_canonical_employee_and_supervisor_scope(): void
    {
        $this->seed();
        $opd = Opd::query()->where('status', 'active')->firstOrFail();
        $otherOpd = Opd::query()->where('status', 'active')->whereKeyNot($opd->id)->firstOrFail();
        $periode = PeriodeTahun::query()->firstOrFail();
        $adminOpd = User::factory()->create(['opd_id' => $opd->id]);
        $adminOpd->roles()->sync([Role::query()->where('name', 'admin_opd')->value('id')]);
        $headJob = JabatanOrganisasi::create([
            'opd_id' => $opd->id,
            'nama' => 'Kepala Dinas Sinkronisasi',
            'level_jabatan' => 'jpt_pratama',
            'status' => 'active',
        ]);
        $childJob = JabatanOrganisasi::create([
            'opd_id' => $opd->id,
            'parent_id' => $headJob->id,
            'nama' => 'Kepala Bidang Sinkronisasi',
            'level_jabatan' => 'administrator',
            'status' => 'active',
        ]);
        $head = Pegawai::create([
            'opd_id' => $otherOpd->id,
            'nama' => 'Atasan Dengan OPD Lama',
            'jenis_pegawai' => 'pns',
            'status' => 'active',
        ]);
        $owner = Pegawai::create([
            'opd_id' => $otherOpd->id,
            'nama' => 'Pemilik Dengan OPD Lama',
            'jenis_pegawai' => 'pns',
            'status' => 'active',
        ]);
        $head->penempatan()->create([
            'jabatan_organisasi_id' => $headJob->id,
            'nama_pejabat' => $head->nama,
            'jenis_penugasan' => 'definitif',
            'tanggal_mulai' => now()->subYear()->toDateString(),
        ]);
        $ownerPlacement = $owner->penempatan()->create([
            'jabatan_organisasi_id' => $childJob->id,
            'nama_pejabat' => $owner->nama,
            'jenis_penugasan' => 'definitif',
            'tanggal_mulai' => now()->subYear()->toDateString(),
        ]);

        $controller = app(PerjanjianKinerjaController::class);
        $subjectOptions = new \ReflectionMethod($controller, 'subjectOptions');
        $options = $subjectOptions->invoke($controller, $adminOpd);
        $this->assertContains($owner->id, collect($options['pegawaiOptions'])->pluck('id')->all());

        $prepare = new \ReflectionMethod($controller, 'prepareSubjectData');
        $data = $prepare->invoke($controller, [
            'opd_id' => $opd->id,
            'pegawai_id' => $owner->id,
            'penempatan_pegawai_id' => $ownerPlacement->id,
            'atasan_pegawai_id' => $head->id,
            'tipe_pk' => 'cascading',
            'level_pk' => 'struktural',
            'periode_tahun_id' => $periode->id,
            'tahun' => $periode->tahun,
            'tanggal_dokumen' => now()->toDateString(),
        ]);

        $this->assertSame($owner->nama, $data['nama_pegawai_snapshot']);
        $this->assertSame($head->nama, $data['nama_atasan_snapshot']);
        $this->assertSame($childJob->nama, $data['jabatan_snapshot']);
        $this->assertSame($headJob->nama, $data['jabatan_atasan_snapshot']);
    }

    public function test_pk_edit_keeps_saved_inactive_subjects_available(): void
    {
        $this->seed();
        $opd = Opd::query()->where('status', 'active')->firstOrFail();
        $periode = PeriodeTahun::query()->firstOrFail();
        $adminOpd = User::factory()->create(['opd_id' => $opd->id]);
        $adminOpd->roles()->sync([Role::query()->where('name', 'admin_opd')->value('id')]);
        $owner = Pegawai::create([
            'opd_id' => $opd->id,
            'nama' => 'A Pemilik PK Lama',
            'jenis_pegawai' => 'pns',
            'status' => 'inactive',
        ]);
        $supervisor = Pegawai::create([
            'opd_id' => $opd->id,
            'nama' => 'B Atasan PK Lama',
            'jenis_pegawai' => 'pns',
            'status' => 'inactive',
        ]);
        $pk = PerjanjianKinerja::create([
            'opd_id' => $opd->id,
            'pegawai_id' => $owner->id,
            'atasan_pegawai_id' => $supervisor->id,
            'periode_tahun_id' => $periode->id,
            'tahun' => $periode->tahun,
            'tipe_pk' => 'individual',
            'level_pk' => 'individu',
            'judul' => 'PK Pegawai Lama',
            'status' => 'draft',
        ]);

        $this->actingAs($adminOpd)
            ->get(route('perjanjian-kinerja.edit', $pk))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Kinerja/PerjanjianKinerja/Form')
                ->where('item.pegawai_id', $owner->id)
                ->where('item.atasan_pegawai_id', $supervisor->id)
                ->where('pegawaiOptions.0.id', $owner->id)
                ->where('pegawaiOptions.1.id', $supervisor->id));
    }

    public function test_admin_opd_can_manage_only_own_perjanjian_kinerja(): void
    {
        $this->seed();

        [$opd, $otherOpd, $periode, $adminOpd] = $this->basicActors();
        $pegawai = $this->pegawai($opd, 'Pegawai Dinas Kesehatan');

        $this->actingAs($adminOpd)
            ->post(route('perjanjian-kinerja.store'), [
                'opd_id' => $opd->id,
                'pegawai_id' => $pegawai->id,
                'tipe_pk' => 'individual',
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
                'pegawai_id' => $pegawai->id,
                'tipe_pk' => 'individual',
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

    public function test_perjanjian_kinerja_requires_approved_renstra_when_linked(): void
    {
        $this->seed();

        [$opd, , $periode, $adminOpd] = $this->basicActors();
        $pegawai = $this->pegawai($opd, 'Pegawai Pemilik PK');

        $rpjmd = Rpjmd::create([
            'periode_tahun_id' => $periode->id,
            'judul' => 'RPJMD Draft Renstra',
            'tahun_awal' => $periode->tahun,
            'tahun_akhir' => $periode->tahun + 4,
            'status' => 'approved',
        ]);

        $renstra = RenstraOpd::create([
            'opd_id' => $opd->id,
            'rpjmd_id' => $rpjmd->id,
            'periode_tahun_id' => $periode->id,
            'judul' => 'Renstra Belum Disetujui',
            'tahun_awal' => $periode->tahun,
            'tahun_akhir' => $periode->tahun + 4,
            'status' => 'draft',
        ]);

        $tujuan = TujuanOpd::create([
            'renstra_opd_id' => $renstra->id,
            'tujuan' => 'Meningkatnya kualitas tata kelola',
            'urutan' => 1,
        ]);
        $sasaran = SasaranOpd::create([
            'tujuan_opd_id' => $tujuan->id,
            'sasaran' => 'Meningkatnya kualitas layanan',
            'urutan' => 1,
        ]);
        $indikator = IndikatorSasaranOpd::create([
            'sasaran_opd_id' => $sasaran->id,
            'indikator' => 'Indeks kualitas layanan',
            'urutan' => 1,
        ]);
        TargetIndikatorSasaranOpd::create([
            'indikator_sasaran_opd_id' => $indikator->id,
            'periode_tahun_id' => $periode->id,
            'target' => 90,
            'target_text' => '90 persen',
        ]);
        $program = OpdProgram::create([
            'renstra_opd_id' => $renstra->id,
            'sasaran_opd_id' => $sasaran->id,
            'kode' => 'P1',
            'nama' => 'Program Layanan',
            'sasaran_program' => 'Meningkatnya mutu program',
            'urutan' => 1,
        ]);
        $indikatorProgram = IndikatorOpdProgram::create([
            'opd_program_id' => $program->id,
            'indikator' => 'Indeks mutu program',
            'urutan' => 1,
        ]);
        TargetIndikatorOpdProgram::create([
            'indikator_opd_program_id' => $indikatorProgram->id,
            'periode_tahun_id' => $periode->id,
            'target' => 91,
            'target_text' => '91 persen',
        ]);
        $kegiatan = OpdKegiatan::create([
            'opd_program_id' => $program->id,
            'kode' => 'K1',
            'nama' => 'Kegiatan Layanan',
            'sasaran_kegiatan' => 'Meningkatnya mutu kegiatan',
            'urutan' => 1,
        ]);
        $indikatorKegiatan = IndikatorOpdKegiatan::create([
            'opd_kegiatan_id' => $kegiatan->id,
            'indikator' => 'Indeks mutu kegiatan',
            'urutan' => 1,
        ]);
        TargetIndikatorOpdKegiatan::create([
            'indikator_opd_kegiatan_id' => $indikatorKegiatan->id,
            'periode_tahun_id' => $periode->id,
            'target' => 92,
            'target_text' => '92 persen',
        ]);
        $subKegiatan = OpdSubKegiatan::create([
            'opd_kegiatan_id' => $kegiatan->id,
            'kode' => 'SK1',
            'nama' => 'Sub Kegiatan Layanan',
            'sasaran_sub_kegiatan' => 'Tersedianya keluaran layanan',
            'urutan' => 1,
        ]);
        $indikatorSubKegiatan = IndikatorSubKegiatan::create([
            'opd_sub_kegiatan_id' => $subKegiatan->id,
            'indikator' => 'Jumlah keluaran layanan',
            'urutan' => 1,
        ]);
        TargetIndikatorSubKegiatan::create([
            'indikator_sub_kegiatan_id' => $indikatorSubKegiatan->id,
            'periode_tahun_id' => $periode->id,
            'target' => 12,
            'target_text' => '12 dokumen',
        ]);

        $payload = [
            'opd_id' => $opd->id,
            'pegawai_id' => $pegawai->id,
            'tipe_pk' => 'cascading',
            'level_pk' => 'struktural',
            'renstra_opd_id' => $renstra->id,
            'lingkup_kinerja_snapshot' => [
                'sasaran_opd:'.$sasaran->id,
                'opd_program:'.$program->id,
                'opd_kegiatan:'.$kegiatan->id,
                'opd_sub_kegiatan:'.$subKegiatan->id,
            ],
            'periode_tahun_id' => $periode->id,
            'tahun' => $periode->tahun,
            'judul' => 'PK Dari Renstra',
            'status' => 'draft',
        ];

        $this->actingAs($adminOpd)
            ->post(route('perjanjian-kinerja.store'), $payload)
            ->assertSessionHasErrors('renstra_opd_id');

        $renstra->forceFill(['status' => 'approved', 'is_active_version' => true])->save();

        $renja = RenjaOpd::create([
            'renstra_opd_id' => $renstra->id,
            'opd_id' => $opd->id,
            'periode_tahun_id' => $periode->id,
            'tahun' => $periode->tahun,
            'judul' => 'Renja Sumber DPA PK',
            'status' => 'approved',
        ]);
        $dpa = DpaOpd::create([
            'renja_opd_id' => $renja->id,
            'opd_id' => $opd->id,
            'periode_tahun_id' => $periode->id,
            'tahun' => $periode->tahun,
            'judul' => 'DPA Sesuai Renstra PK',
            'jenis_anggaran' => 'murni',
            'status' => 'approved',
        ]);
        DpaOpdItem::create([
            'dpa_opd_id' => $dpa->id,
            'kode_program' => 'P1',
            'nama_program' => 'Program Layanan',
            'kode_kegiatan' => 'K1',
            'nama_kegiatan' => 'Kegiatan Layanan',
            'kode_sub_kegiatan' => 'SK1',
            'nama_sub_kegiatan' => 'Sub Kegiatan Layanan',
            'pagu_dpa' => 100000,
            'sumber_pendanaan' => 'APBD',
            'urutan' => 1,
        ]);

        $otherRenstra = RenstraOpd::create([
            'opd_id' => $opd->id,
            'rpjmd_id' => $rpjmd->id,
            'periode_tahun_id' => $periode->id,
            'judul' => 'Renstra Lain yang Tidak Dipilih PK',
            'tahun_awal' => $periode->tahun,
            'tahun_akhir' => $periode->tahun + 4,
            'status' => 'approved',
            'is_active_version' => false,
        ]);
        $otherRenja = RenjaOpd::create([
            'renstra_opd_id' => $otherRenstra->id,
            'opd_id' => $opd->id,
            'periode_tahun_id' => $periode->id,
            'tahun' => $periode->tahun,
            'judul' => 'Renja Lain',
            'status' => 'approved',
        ]);
        $otherDpa = DpaOpd::create([
            'renja_opd_id' => $otherRenja->id,
            'opd_id' => $opd->id,
            'periode_tahun_id' => $periode->id,
            'tahun' => $periode->tahun,
            'judul' => 'DPPA Lain yang Lebih Baru',
            'jenis_anggaran' => 'perubahan',
            'status' => 'approved',
        ]);
        DpaOpdItem::create([
            'dpa_opd_id' => $otherDpa->id,
            'kode_program' => 'P1',
            'kode_kegiatan' => 'K1',
            'kode_sub_kegiatan' => 'SK1',
            'pagu_dpa' => 999999,
            'urutan' => 1,
        ]);

        $this->actingAs($adminOpd)
            ->getJson(route('perjanjian-kinerja.cascading-scope-options', ['renstra_opd_id' => $renstra->id]))
            ->assertOk()
            ->assertJsonPath('groups.0.items.0.key', 'sasaran_opd:'.$sasaran->id)
            ->assertJsonPath('groups.0.items.0.indicator_count', 1)
            ->assertJsonPath('groups.1.items.0.key', 'opd_program:'.$program->id)
            ->assertJsonPath('groups.2.items.0.key', 'opd_kegiatan:'.$kegiatan->id)
            ->assertJsonPath('groups.3.items.0.key', 'opd_sub_kegiatan:'.$subKegiatan->id);

        $this->actingAs($adminOpd)
            ->post(route('perjanjian-kinerja.store'), $payload)
            ->assertRedirect();

        $pk = PerjanjianKinerja::query()->where('pegawai_id', $pegawai->id)->latest('id')->firstOrFail();
        $this->assertSame('renstra_cascading', $pk->sumber_data);
        $this->assertSame(4, $pk->items()->count());
        $this->assertDatabaseHas('perjanjian_kinerja_items', [
            'perjanjian_kinerja_id' => $pk->id,
            'indikator' => 'Indeks kualitas layanan',
            'target_text' => '90 persen',
            'is_readonly' => true,
        ]);
        $this->actingAs($adminOpd)
            ->get(route('perjanjian-kinerja.show', $pk))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('documentPreview.source_label', 'Lingkup kinerja Renstra OPD Tahun '.$periode->tahun)
                ->where('documentPreview.performance_groups.0.type', 'sasaran_opd')
                ->where('documentPreview.performance_groups.1.type', 'program_opd')
                ->where('documentPreview.performance_groups.2.type', 'kegiatan_opd')
                ->where('documentPreview.performance_groups.3.type', 'sub_kegiatan_opd')
                ->where('documentPreview.programs.0.name', 'Program Layanan')
                ->where('documentPreview.programs.0.budget', 100000)
                ->where('documentPreview.programs.0.activities.0.name', 'Kegiatan Layanan')
            );

        TargetIndikatorSasaranOpd::query()
            ->where('indikator_sasaran_opd_id', $indikator->id)
            ->update(['target' => 95, 'target_text' => '95 persen']);
        $this->actingAs($adminOpd)
            ->put(route('perjanjian-kinerja.update', $pk), $payload)
            ->assertRedirect()
            ->assertSessionDoesntHaveErrors();
        $this->assertDatabaseHas('perjanjian_kinerja_items', [
            'perjanjian_kinerja_id' => $pk->id,
            'indikator' => 'Indeks kualitas layanan',
            'target_text' => '95 persen',
        ]);

        $lowerPayload = [
            ...$payload,
            'level_pk' => 'individu',
            'judul' => 'PK Kasi Cascading',
        ];
        $this->actingAs($adminOpd)
            ->post(route('perjanjian-kinerja.store'), [
                ...$lowerPayload,
                'lingkup_kinerja_snapshot' => ['sasaran_opd:'.$sasaran->id],
            ])
            ->assertSessionHasErrors('lingkup_kinerja_snapshot');

        $this->actingAs($adminOpd)
            ->post(route('perjanjian-kinerja.store'), [
                ...$lowerPayload,
                'lingkup_kinerja_snapshot' => [
                    'opd_kegiatan:'.$kegiatan->id,
                    'opd_sub_kegiatan:'.$subKegiatan->id,
                ],
            ])
            ->assertRedirect()
            ->assertSessionDoesntHaveErrors();

        $lowerPk = PerjanjianKinerja::query()
            ->where('pegawai_id', $pegawai->id)
            ->where('level_pk', 'individu')
            ->firstOrFail();
        $this->actingAs($adminOpd)
            ->get(route('perjanjian-kinerja.show', $lowerPk))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('documentPreview.is_lower_cascading', true)
                ->where('documentPreview.performance_groups.0.type', 'kegiatan_opd')
                ->where('documentPreview.performance_groups.1.type', 'sub_kegiatan_opd')
                ->where('documentPreview.activity_budget_groups.0.name', 'Kegiatan Layanan')
                ->where('documentPreview.activity_budget_groups.0.budget', 100000)
                ->where('documentPreview.activity_budget_groups.0.sub_activities.0.name', 'Sub Kegiatan Layanan')
                ->where('documentPreview.activity_budget_groups.0.sub_activities.0.budget', 100000)
            );
    }

    public function test_rencana_aksi_and_realisasi_items_can_be_saved(): void
    {
        $this->seed();

        [$opd, , $periode, $adminOpd] = $this->basicActors();
        [$sasaranOpd, $indikatorSasaranOpd, $renstra] = $this->approvedRenstraTarget($opd, $periode);

        $pk = PerjanjianKinerja::create([
            'opd_id' => $opd->id,
            'renstra_opd_id' => $renstra->id,
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
            ->assertSessionHasErrors('indikator_sasaran_opd_id');

        $this->actingAs($adminOpd)
            ->post(route('perjanjian-kinerja.items.store', $pk), [
                'sasaran_opd_id' => $sasaranOpd->id,
                'indikator_sasaran_opd_id' => $indikatorSasaranOpd->id,
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
                'sasaran_opd_id' => $sasaranOpd->id,
                'indikator_sasaran_opd_id' => $indikatorSasaranOpd->id,
                'sasaran' => 'Meningkatnya kualitas layanan publik',
                'indikator' => 'Indeks layanan publik',
                'target' => 90,
                'target_text' => '90 persen',
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
            ->post(route('perjanjian-kinerja.items.store', $pk), [
                'sasaran_opd_id' => $sasaranOpd->id,
                'indikator_sasaran_opd_id' => $indikatorSasaranOpd->id,
                'sasaran' => 'Item baru pada PK approved',
                'indikator' => 'Indikator baru',
                'target' => 95,
                'target_text' => '95 persen',
                'urutan' => 4,
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
            ->post(route('rencana-aksi.items.store', $rencanaAksi), [
                'perjanjian_kinerja_item_id' => $pkItem->id,
                'periode_realisasi' => 'triwulan',
                'triwulan' => 'tw4',
                'aksi' => 'Item baru pada rencana aksi approved',
                'indikator' => 'Layanan selesai tepat waktu',
                'target' => 100,
                'target_text' => '100 persen',
                'anggaran' => 2500000,
                'status' => 'draft',
                'urutan' => 4,
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

        $realisasi->forceFill(['status' => 'approved'])->save();

        $this->actingAs($adminOpd)
            ->post(route('realisasi-kinerja.programs.store', $realisasi), [
                'perjanjian_kinerja_item_id' => $pkItem->id,
                'indikator' => 'Item baru pada realisasi approved',
                'target' => 90,
                'target_text' => '90 persen',
                'realisasi' => 90,
                'realisasi_text' => '90 persen',
                'anggaran' => 1000000,
                'realisasi_anggaran' => 900000,
                'urutan' => 3,
            ])
            ->assertForbidden();

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

    private function approvedRenstraTarget(Opd $opd, PeriodeTahun $periode): array
    {
        $rpjmd = Rpjmd::create([
            'periode_tahun_id' => $periode->id,
            'judul' => 'RPJMD Referensi PK',
            'tahun_awal' => $periode->tahun,
            'tahun_akhir' => $periode->tahun + 4,
            'status' => 'approved',
        ]);

        $renstra = RenstraOpd::create([
            'opd_id' => $opd->id,
            'rpjmd_id' => $rpjmd->id,
            'periode_tahun_id' => $periode->id,
            'judul' => 'Renstra Referensi PK',
            'tahun_awal' => $periode->tahun,
            'tahun_akhir' => $periode->tahun + 4,
            'status' => 'approved',
        ]);

        $tujuan = TujuanOpd::create([
            'renstra_opd_id' => $renstra->id,
            'tujuan' => 'Meningkatnya kualitas layanan',
            'urutan' => 1,
        ]);

        $sasaran = SasaranOpd::create([
            'tujuan_opd_id' => $tujuan->id,
            'kode' => 'S1',
            'sasaran' => 'Meningkatnya kualitas layanan publik',
            'urutan' => 1,
        ]);

        $indikator = IndikatorSasaranOpd::create([
            'sasaran_opd_id' => $sasaran->id,
            'kode' => 'ISK1',
            'indikator' => 'Indeks layanan publik',
            'tipe_indikator' => 'positif',
            'urutan' => 1,
        ]);

        TargetIndikatorSasaranOpd::create([
            'indikator_sasaran_opd_id' => $indikator->id,
            'periode_tahun_id' => $periode->id,
            'target' => 90,
            'target_text' => '90 persen',
        ]);

        return [$sasaran, $indikator, $renstra];
    }

    private function pegawai(Opd $opd, string $nama): Pegawai
    {
        return Pegawai::create([
            'opd_id' => $opd->id,
            'nama' => $nama,
            'jenis_pegawai' => 'pns',
            'status' => 'active',
        ]);
    }
}
