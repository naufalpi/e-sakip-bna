<?php

namespace Tests\Feature;

use App\Models\BidangUrusan;
use App\Models\KegiatanPemerintahan;
use App\Models\PeriodeTahun;
use App\Models\ProgramPemerintahan;
use App\Models\SatuanIndikator;
use App\Models\SubKegiatanPemerintahan;
use App\Models\UrusanPemerintahan;
use App\Services\Master\CopyProgramKegiatanReferenceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProgramPemerintahanCopyTest extends TestCase
{
    use RefreshDatabase;

    public function test_copy_program_period_copies_kegiatan_sub_kegiatan_and_metadata(): void
    {
        $periodeIds = collect(range(2025, 2034))
            ->mapWithKeys(fn (int $tahun) => [
                $tahun => PeriodeTahun::create([
                    'tahun' => $tahun,
                    'nama' => "Tahun {$tahun}",
                    'status' => $tahun === 2025 ? 'active' : 'draft',
                ])->id,
            ]);
        $urusan = UrusanPemerintahan::create([
            'kode' => '2',
            'nama' => 'Urusan Wajib Non Pelayanan Dasar',
            'status' => 'active',
        ]);
        $bidang = BidangUrusan::create([
            'urusan_pemerintahan_id' => $urusan->id,
            'kode' => '2.16',
            'nama' => 'Urusan Pemerintahan Bidang Komunikasi dan Informatika',
            'status' => 'active',
        ]);
        $satuan = SatuanIndikator::create([
            'nama' => 'Persen',
            'simbol' => '%',
            'status' => 'active',
        ]);
        $program = ProgramPemerintahan::create([
            'bidang_urusan_id' => $bidang->id,
            'tahun_awal' => 2025,
            'tahun_akhir' => 2029,
            'kode' => '2.16.03',
            'nama' => 'Program Pengelolaan Aplikasi Informatika',
            'status' => 'active',
        ]);
        $kegiatan = KegiatanPemerintahan::create([
            'periode_tahun_id' => $periodeIds[2025],
            'program_pemerintahan_id' => $program->id,
            'kode' => '2.16.03.2.01',
            'nama' => 'Pengelolaan Nama Domain',
            'status' => 'active',
        ]);
        SubKegiatanPemerintahan::create([
            'periode_tahun_id' => $periodeIds[2025],
            'kegiatan_pemerintahan_id' => $kegiatan->id,
            'kode' => '2.16.03.2.01.0001',
            'nama' => 'Pendaftaran Nama Domain Pemerintah Daerah',
            'sasaran_sub_kegiatan' => 'Tersedianya layanan domain pemerintah daerah',
            'indikator_sub_kegiatan' => 'Jumlah dokumen pengelolaan nama domain',
            'satuan_indikator_id' => $satuan->id,
            'definisi_operasional' => 'Dokumen pengelolaan nama domain yang disusun.',
            'status' => 'active',
        ]);

        $result = app(CopyProgramKegiatanReferenceService::class)
            ->copyProgramPeriod(2025, 2029, 2030, 2034);

        $targetProgram = ProgramPemerintahan::query()
            ->where('tahun_awal', 2030)
            ->where('tahun_akhir', 2034)
            ->where('bidang_urusan_id', $bidang->id)
            ->where('kode', '2.16.03')
            ->firstOrFail();
        $targetKegiatan = KegiatanPemerintahan::query()
            ->where('periode_tahun_id', $periodeIds[2030])
            ->where('program_pemerintahan_id', $targetProgram->id)
            ->where('kode', '2.16.03.2.01')
            ->firstOrFail();

        $this->assertSame(1, $result['program_created']);
        $this->assertSame(1, $result['kegiatan_created']);
        $this->assertSame(1, $result['sub_kegiatan_created']);
        $this->assertDatabaseHas('sub_kegiatan_pemerintahan', [
            'periode_tahun_id' => $periodeIds[2030],
            'kegiatan_pemerintahan_id' => $targetKegiatan->id,
            'kode' => '2.16.03.2.01.0001',
            'sasaran_sub_kegiatan' => 'Tersedianya layanan domain pemerintah daerah',
            'indikator_sub_kegiatan' => 'Jumlah dokumen pengelolaan nama domain',
            'satuan_indikator_id' => $satuan->id,
            'definisi_operasional' => 'Dokumen pengelolaan nama domain yang disusun.',
        ]);
    }

    public function test_copy_program_period_uses_first_populated_source_year_when_source_start_is_empty(): void
    {
        $periodeIds = collect(range(2025, 2034))
            ->mapWithKeys(fn (int $tahun) => [
                $tahun => PeriodeTahun::create([
                    'tahun' => $tahun,
                    'nama' => "Tahun {$tahun}",
                    'status' => $tahun === 2026 ? 'active' : 'draft',
                ])->id,
            ]);
        $urusan = UrusanPemerintahan::create([
            'kode' => '2',
            'nama' => 'Urusan Wajib Non Pelayanan Dasar',
            'status' => 'active',
        ]);
        $bidang = BidangUrusan::create([
            'urusan_pemerintahan_id' => $urusan->id,
            'kode' => '2.16',
            'nama' => 'Urusan Pemerintahan Bidang Komunikasi dan Informatika',
            'status' => 'active',
        ]);
        $program = ProgramPemerintahan::create([
            'bidang_urusan_id' => $bidang->id,
            'tahun_awal' => 2025,
            'tahun_akhir' => 2029,
            'kode' => '2.16.03',
            'nama' => 'Program Pengelolaan Aplikasi Informatika',
            'status' => 'active',
        ]);
        $kegiatan = KegiatanPemerintahan::create([
            'periode_tahun_id' => $periodeIds[2026],
            'program_pemerintahan_id' => $program->id,
            'kode' => '2.16.03.2.01',
            'nama' => 'Pengelolaan Nama Domain',
            'status' => 'active',
        ]);
        SubKegiatanPemerintahan::create([
            'periode_tahun_id' => $periodeIds[2026],
            'kegiatan_pemerintahan_id' => $kegiatan->id,
            'kode' => '2.16.03.2.01.0001',
            'nama' => 'Pendaftaran Nama Domain Pemerintah Daerah',
            'status' => 'active',
        ]);

        $result = app(CopyProgramKegiatanReferenceService::class)
            ->copyProgramPeriod(2025, 2029, 2030, 2034);

        $targetProgram = ProgramPemerintahan::query()
            ->where('tahun_awal', 2030)
            ->where('tahun_akhir', 2034)
            ->where('kode', '2.16.03')
            ->firstOrFail();
        $targetKegiatan = KegiatanPemerintahan::query()
            ->where('periode_tahun_id', $periodeIds[2030])
            ->where('program_pemerintahan_id', $targetProgram->id)
            ->where('kode', '2.16.03.2.01')
            ->firstOrFail();

        $this->assertSame(1, $result['kegiatan_created']);
        $this->assertSame(1, $result['sub_kegiatan_created']);
        $this->assertDatabaseHas('sub_kegiatan_pemerintahan', [
            'periode_tahun_id' => $periodeIds[2030],
            'kegiatan_pemerintahan_id' => $targetKegiatan->id,
            'kode' => '2.16.03.2.01.0001',
        ]);
    }
}
