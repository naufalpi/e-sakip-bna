<?php

namespace Tests\Feature;

use App\Models\DpaOpd;
use App\Models\DpaOpdItem;
use App\Models\IndikatorSasaranOpd;
use App\Models\IndikatorTujuanDaerah;
use App\Models\IndikatorTujuanOpd;
use App\Models\Opd;
use App\Models\OpdProgram;
use App\Models\PeriodeTahun;
use App\Models\PerjanjianKinerja;
use App\Models\RenjaOpd;
use App\Models\RenstraOpd;
use App\Models\Rkpd;
use App\Models\RkpdIkuTarget;
use App\Models\RkpdItem;
use App\Models\Rpjmd;
use App\Models\RpjmdVisi;
use App\Models\SasaranOpd;
use App\Models\TargetIndikatorSasaranOpd;
use App\Models\TargetIndikatorTujuanOpd;
use App\Models\TujuanDaerah;
use App\Models\TujuanOpd;
use App\Services\Kinerja\PerjanjianKinerjaSnapshotService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PerjanjianKinerjaSnapshotTest extends TestCase
{
    use RefreshDatabase;

    public function test_pk_bupati_snapshots_rkpd_target_and_program_budget(): void
    {
        $this->seed();
        $periode = PeriodeTahun::query()->updateOrCreate(
            ['tahun' => 2091],
            ['nama' => 'Tahun 2091', 'status' => 'active'],
        );
        $opd = Opd::create(['kode' => '9.91', 'nama' => 'Dinas Uji RKPD', 'singkatan' => 'DURKPD', 'status' => 'active']);
        $rpjmd = Rpjmd::create([
            'periode_tahun_id' => $periode->id,
            'judul' => 'RPJMD Uji PK Bupati',
            'tahun_awal' => 2090,
            'tahun_akhir' => 2094,
            'status' => 'approved',
        ]);
        $visi = RpjmdVisi::create(['rpjmd_id' => $rpjmd->id, 'visi' => 'Banjarnegara Maju', 'urutan' => 1]);
        $tujuan = TujuanDaerah::create(['rpjmd_visi_id' => $visi->id, 'kode' => 'T1', 'tujuan' => 'Meningkatnya kesejahteraan', 'urutan' => 1]);
        $indikator = IndikatorTujuanDaerah::create(['tujuan_daerah_id' => $tujuan->id, 'indikator' => 'Indeks kesejahteraan', 'urutan' => 1]);
        $rkpd = Rkpd::create([
            'rpjmd_id' => $rpjmd->id,
            'periode_tahun_id' => $periode->id,
            'tahun' => 2091,
            'judul' => 'RKPD Ditetapkan 2091',
            'status' => 'approved',
            'jenis_versi' => 'ditetapkan',
            'is_active_version' => true,
        ]);
        RkpdIkuTarget::create([
            'rkpd_id' => $rkpd->id,
            'periode_tahun_id' => $periode->id,
            'indikator_type' => 'indikator_tujuan_daerah',
            'indikator_id' => $indikator->id,
            'target_rkpd' => '82,50',
        ]);
        RkpdItem::create([
            'rkpd_id' => $rkpd->id,
            'opd_id' => $opd->id,
            'kode' => '1.01.01',
            'nama_urusan_bidang_program_kegiatan_sub' => 'PROGRAM PELAYANAN PUBLIK',
            'pagu_indikatif' => 125000000,
            'sumber_dana' => 'APBD',
            'status' => 'draft',
            'urutan' => 1,
        ]);
        $pk = PerjanjianKinerja::create([
            'opd_id' => null,
            'rkpd_id' => $rkpd->id,
            'periode_tahun_id' => $periode->id,
            'tahun' => 2091,
            'judul' => 'PK Bupati 2091',
            'tipe_pk' => 'cascading',
            'level_pk' => 'bupati',
            'sumber_data' => 'rkpd',
            'status' => 'draft',
        ]);

        app(PerjanjianKinerjaSnapshotService::class)->populate($pk);

        $this->assertDatabaseHas('perjanjian_kinerja_items', [
            'perjanjian_kinerja_id' => $pk->id,
            'jenis_item' => 'tujuan',
            'target_text' => '82,50',
            'is_readonly' => true,
        ]);
        $this->assertDatabaseHas('perjanjian_kinerja_programs', [
            'perjanjian_kinerja_id' => $pk->id,
            'nama_program' => 'PROGRAM PELAYANAN PUBLIK',
            'anggaran' => 125000000,
        ]);
    }

    public function test_pk_kepala_opd_snapshots_renstra_matrix_and_dpa_budget(): void
    {
        $this->seed();
        $periode = PeriodeTahun::query()->updateOrCreate(
            ['tahun' => 2092],
            ['nama' => 'Tahun 2092', 'status' => 'active'],
        );
        $opd = Opd::create(['kode' => '9.92', 'nama' => 'Dinas Uji PK', 'singkatan' => 'DUPK', 'status' => 'active']);
        $rpjmd = Rpjmd::create([
            'periode_tahun_id' => $periode->id,
            'judul' => 'RPJMD Uji PK OPD',
            'tahun_awal' => 2090,
            'tahun_akhir' => 2094,
            'status' => 'approved',
        ]);
        $renstra = RenstraOpd::create([
            'opd_id' => $opd->id,
            'rpjmd_id' => $rpjmd->id,
            'periode_tahun_id' => $periode->id,
            'judul' => 'Renstra Dinas Uji PK',
            'tahun_awal' => 2090,
            'tahun_akhir' => 2094,
            'status' => 'approved',
            'is_active_version' => true,
        ]);
        $tujuan = TujuanOpd::create(['renstra_opd_id' => $renstra->id, 'tujuan' => 'Layanan semakin baik', 'urutan' => 1]);
        $indikatorTujuan = IndikatorTujuanOpd::create(['tujuan_opd_id' => $tujuan->id, 'indikator' => 'Indeks layanan', 'urutan' => 1]);
        TargetIndikatorTujuanOpd::create(['indikator_tujuan_opd_id' => $indikatorTujuan->id, 'periode_tahun_id' => $periode->id, 'target_text' => 'A']);
        $sasaran = SasaranOpd::create(['tujuan_opd_id' => $tujuan->id, 'kode' => 'S1', 'sasaran' => 'Kualitas layanan meningkat', 'urutan' => 1]);
        $indikatorSasaran = IndikatorSasaranOpd::create(['sasaran_opd_id' => $sasaran->id, 'indikator' => 'Nilai pelayanan', 'urutan' => 1]);
        TargetIndikatorSasaranOpd::create(['indikator_sasaran_opd_id' => $indikatorSasaran->id, 'periode_tahun_id' => $periode->id, 'target' => 90]);
        OpdProgram::create([
            'renstra_opd_id' => $renstra->id,
            'sasaran_opd_id' => $sasaran->id,
            'kode' => '1.01.01',
            'nama' => 'PROGRAM ADMINISTRASI UMUM',
            'sasaran_program' => 'Administrasi tersedia',
            'status' => 'draft',
            'urutan' => 1,
        ]);
        $program = OpdProgram::create([
            'renstra_opd_id' => $renstra->id,
            'sasaran_opd_id' => $sasaran->id,
            'kode' => '2.16.03',
            'nama' => 'PROGRAM PENGELOLAAN INFORMASI',
            'sasaran_program' => 'Informasi tersedia',
            'status' => 'draft',
            'urutan' => 2,
        ]);
        $renja = RenjaOpd::create([
            'renstra_opd_id' => $renstra->id,
            'opd_id' => $opd->id,
            'periode_tahun_id' => $periode->id,
            'tahun' => 2092,
            'judul' => 'Renja Dinas Uji PK',
            'status' => 'approved',
        ]);
        $dpa = DpaOpd::create([
            'renja_opd_id' => $renja->id,
            'opd_id' => $opd->id,
            'periode_tahun_id' => $periode->id,
            'tahun' => 2092,
            'judul' => 'DPA Dinas Uji PK',
            'status' => 'approved',
        ]);
        DpaOpdItem::create([
            'dpa_opd_id' => $dpa->id,
            'kode_program' => '2.16.03',
            'nama_program' => 'PROGRAM PENGELOLAAN INFORMASI',
            'pagu_dpa' => 74000000,
            'sumber_pendanaan' => 'APBD',
            'urutan' => 1,
        ]);
        DpaOpdItem::create([
            'dpa_opd_id' => $dpa->id,
            'kode_program' => '2.16.03',
            'nama_program' => 'PROGRAM PENGELOLAAN INFORMASI',
            'pagu_dpa' => 26000000,
            'sumber_pendanaan' => 'DAK',
            'urutan' => 2,
        ]);
        $pk = PerjanjianKinerja::create([
            'opd_id' => $opd->id,
            'renstra_opd_id' => $renstra->id,
            'dpa_opd_id' => $dpa->id,
            'periode_tahun_id' => $periode->id,
            'tahun' => 2092,
            'judul' => 'PK Kepala Dinas Uji 2092',
            'tipe_pk' => 'cascading',
            'level_pk' => 'kepala_opd',
            'sumber_data' => 'dpa',
            'status' => 'draft',
        ]);

        app(PerjanjianKinerjaSnapshotService::class)->populate($pk);

        $this->assertSame(['tujuan_opd', 'sasaran_opd'], $pk->items()->pluck('jenis_item')->all());
        $this->assertDatabaseMissing('perjanjian_kinerja_items', [
            'perjanjian_kinerja_id' => $pk->id,
            'jenis_item' => 'program_opd',
        ]);
        $this->assertTrue($pk->items()->get()->every(fn ($item) => $item->is_readonly));
        $this->assertDatabaseHas('perjanjian_kinerja_programs', [
            'perjanjian_kinerja_id' => $pk->id,
            'opd_program_id' => $program->id,
            'nama_program' => 'PROGRAM PENGELOLAAN INFORMASI',
            'anggaran' => 100000000,
            'keterangan' => 'APBD, DAK',
        ]);

        TargetIndikatorSasaranOpd::query()
            ->where('indikator_sasaran_opd_id', $indikatorSasaran->id)
            ->delete();

        try {
            app(PerjanjianKinerjaSnapshotService::class)->populate($pk);
            $this->fail('Sinkronisasi PK seharusnya ditolak ketika target tahunan RENSTRA belum tersedia.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('renstra_opd_id', $exception->errors());
        }

        // Penghapusan snapshot dilakukan di transaksi yang sama, sehingga kegagalan sinkronisasi
        // tidak boleh merusak data PK terakhir yang masih sah.
        $this->assertDatabaseHas('perjanjian_kinerja_items', [
            'perjanjian_kinerja_id' => $pk->id,
            'indikator' => 'Nilai pelayanan',
            'target' => 90,
        ]);
        $this->assertDatabaseHas('perjanjian_kinerja_programs', [
            'perjanjian_kinerja_id' => $pk->id,
            'anggaran' => 100000000,
        ]);
    }
}
