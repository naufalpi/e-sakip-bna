<?php

namespace Tests\Feature;

use App\Models\BidangUrusan;
use App\Models\IndikatorSubKegiatan;
use App\Models\KegiatanPemerintahan;
use App\Models\Opd;
use App\Models\OpdKegiatan;
use App\Models\OpdProgram;
use App\Models\OpdSubKegiatan;
use App\Models\PeriodeTahun;
use App\Models\Permission;
use App\Models\ProgramPemerintahan;
use App\Models\RenjaOpd;
use App\Models\RenjaOpdItem;
use App\Models\RenstraOpd;
use App\Models\Rkpd;
use App\Models\RkpdItem;
use App\Models\Role;
use App\Models\Rpjmd;
use App\Models\SasaranOpd;
use App\Models\SubKegiatanPemerintahan;
use App\Models\TargetIndikatorSubKegiatan;
use App\Models\TujuanOpd;
use App\Models\UrusanPemerintahan;
use App\Models\User;
use App\Services\Perencanaan\RenjaInitialItemService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class RenjaInitialItemsTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_renja_copies_unique_renstra_sub_kegiatan_and_locks_its_renstra_reference(): void
    {
        $data = $this->planningData();

        $this->actingAs($data['user'])
            ->post(route('renja-opd.store'), [
                'opd_id' => $data['opd']->id,
                'periode_tahun_id' => $data['target_period']->id,
                'tahun' => 2089,
                'judul' => 'RENJA DINAS UJI TAHUN 2089',
            ])
            ->assertRedirect();

        $renja = RenjaOpd::query()->where('judul', 'RENJA DINAS UJI TAHUN 2089')->sole();
        $copied = $renja->items()->sole();

        $this->assertSame($data['renstra']->id, $renja->renstra_opd_id);
        $this->assertSame('renstra', $copied->sumber_item);
        $this->assertSame($data['source_sub_opd']->id, $copied->opd_sub_kegiatan_id);
        $this->assertSame($data['target_sub']->id, $copied->sub_kegiatan_pemerintahan_id);
        $this->assertSame($data['target_kegiatan']->id, $copied->kegiatan_pemerintahan_id);
        $this->assertSame($data['program']->id, $copied->program_pemerintahan_id);
        $this->assertSame("Jumlah layanan aktif\nPersentase layanan tepat waktu", $copied->indikator);
        $this->assertSame("25 layanan\n90%", $copied->target_akhir_renstra);
        $this->assertNull($copied->target);
        $this->assertNull($copied->pagu_indikatif);

        $this->actingAs($data['user'])
            ->put(route('renja-opd.items.update', [$renja, $copied]), [
                'sub_kegiatan_pemerintahan_id' => $data['target_sub']->id,
                'indikator' => 'Tidak boleh mengganti indikator RENSTRA',
                'target_akhir_renstra' => '999',
                'target' => '12 layanan',
                'pagu_indikatif' => '1.250.000',
                'lokasi' => 'Kabupaten Banjarnegara',
                'status' => 'draft',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('renja_opd_items', [
            'id' => $copied->id,
            'sub_kegiatan_pemerintahan_id' => $data['target_sub']->id,
            'indikator' => "Jumlah layanan aktif\nPersentase layanan tepat waktu",
            'target_akhir_renstra' => "25 layanan\n90%",
            'target' => '12 layanan',
            'pagu_indikatif' => '1250000.00',
        ]);

        $this->actingAs($data['user'])
            ->put(route('renja-opd.items.update', [$renja, $copied]), [
                'sub_kegiatan_pemerintahan_id' => $data['manual_sub']->id,
                'target' => '15 layanan',
                'status' => 'draft',
            ])
            ->assertSessionHasErrors('sub_kegiatan_pemerintahan_id');

        $this->assertSame($data['target_sub']->id, $copied->fresh()->sub_kegiatan_pemerintahan_id);

        $this->actingAs($data['user'])
            ->post(route('renja-opd.items.store', $renja), [
                'sub_kegiatan_pemerintahan_id' => $data['manual_sub']->id,
                'indikator' => 'Jumlah dokumen manual',
                'target' => '1 dokumen',
                'status' => 'draft',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('renja_opd_items', [
            'renja_opd_id' => $renja->id,
            'sub_kegiatan_pemerintahan_id' => $data['manual_sub']->id,
            'opd_sub_kegiatan_id' => null,
            'sumber_item' => 'manual',
        ]);
    }

    public function test_bootstrap_never_overwrites_or_restores_existing_renja_items(): void
    {
        $data = $this->planningData();
        $renja = RenjaOpd::create([
            'renstra_opd_id' => $data['renstra']->id,
            'opd_id' => $data['opd']->id,
            'periode_tahun_id' => $data['target_period']->id,
            'tahun' => 2089,
            'judul' => 'RENJA EKSISTING',
            'status' => 'draft',
        ]);
        $existing = RenjaOpdItem::create([
            'renja_opd_id' => $renja->id,
            'sub_kegiatan_pemerintahan_id' => $data['manual_sub']->id,
            'kode' => $data['manual_sub']->kode,
            'nama_sub_kegiatan' => $data['manual_sub']->nama,
            'sumber_item' => 'manual',
            'status' => 'draft',
            'urutan' => 1,
        ]);
        $existing->delete();

        $result = app(RenjaInitialItemService::class)->bootstrapFromRenstra($renja);

        $this->assertFalse($result['bootstrapped']);
        $this->assertSame(0, $result['copied']);
        $this->assertSame(1, RenjaOpdItem::withTrashed()->where('renja_opd_id', $renja->id)->count());
    }

    public function test_new_renja_can_use_a_working_rkpd_before_rkpd_is_established(): void
    {
        $data = $this->planningData();
        $workingRkpd = Rkpd::create([
            'rpjmd_id' => $data['rkpd_official']->rpjmd_id,
            'periode_tahun_id' => $data['target_period']->id,
            'tahun' => 2089,
            'judul' => 'RKPD AWAL TAHUN 2089',
            'status' => 'draft',
            'jenis_versi' => 'awal',
            'nomor_versi' => 1,
            'is_active_version' => false,
        ]);

        $this->actingAs($data['user'])
            ->post(route('renja-opd.store'), [
                'rkpd_id' => $workingRkpd->id,
                'renstra_opd_id' => $data['renstra']->id,
                'opd_id' => $data['opd']->id,
                'periode_tahun_id' => $data['target_period']->id,
                'tahun' => 2089,
                'judul' => 'RENJA DENGAN RKPD TAHAP KERJA',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('renja_opd', [
            'judul' => 'RENJA DENGAN RKPD TAHAP KERJA',
            'rkpd_id' => $workingRkpd->id,
            'jenis_versi' => 'awal',
            'status' => 'draft',
        ]);
    }

    public function test_new_renja_rejects_a_non_official_established_rkpd_reference(): void
    {
        $data = $this->planningData();
        $data['rkpd_official']->update(['status' => 'draft']);

        $this->actingAs($data['user'])
            ->post(route('renja-opd.store'), [
                'rkpd_id' => $data['rkpd_official']->id,
                'renstra_opd_id' => $data['renstra']->id,
                'opd_id' => $data['opd']->id,
                'periode_tahun_id' => $data['target_period']->id,
                'tahun' => 2089,
                'judul' => 'RENJA DENGAN RKPD TIDAK SAH',
            ])
            ->assertSessionHasErrors('rkpd_id');

        $this->assertDatabaseMissing('renja_opd', ['judul' => 'RENJA DENGAN RKPD TIDAK SAH']);
    }

    public function test_new_renja_rejects_a_renstra_that_is_not_official(): void
    {
        $data = $this->planningData();
        $data['renstra']->update(['status' => 'revision']);

        $this->actingAs($data['user'])
            ->post(route('renja-opd.store'), [
                'rkpd_id' => $data['rkpd_official']->id,
                'renstra_opd_id' => $data['renstra']->id,
                'opd_id' => $data['opd']->id,
                'periode_tahun_id' => $data['target_period']->id,
                'tahun' => 2089,
                'judul' => 'RENJA DARI RENSTRA REVISI',
            ])
            ->assertSessionHasErrors('renstra_opd_id');

        $this->assertDatabaseMissing('renja_opd', ['judul' => 'RENJA DARI RENSTRA REVISI']);
    }

    public function test_bootstrap_service_rechecks_the_renstra_official_status(): void
    {
        $data = $this->planningData();
        $data['renstra']->update(['status' => 'draft']);
        $renja = RenjaOpd::create([
            'rkpd_id' => $data['rkpd_official']->id,
            'renstra_opd_id' => $data['renstra']->id,
            'opd_id' => $data['opd']->id,
            'periode_tahun_id' => $data['target_period']->id,
            'tahun' => 2089,
            'judul' => 'RENJA UJI SERVICE',
            'status' => 'draft',
        ]);

        try {
            app(RenjaInitialItemService::class)->bootstrapFromRenstra($renja);
            $this->fail('Bootstrap seharusnya menolak RENSTRA yang belum resmi.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('renstra_opd_id', $exception->errors());
        }

        $this->assertSame(0, $renja->items()->count());
    }

    public function test_long_renstra_target_is_preserved_in_renja_and_rkpd_text_columns(): void
    {
        $data = $this->planningData();
        $longTargets = [
            str_repeat('Target layanan sangat panjang ', 12),
            str_repeat('Target persentase sangat panjang ', 12),
        ];
        $targets = TargetIndikatorSubKegiatan::query()
            ->whereHas('indikator', fn ($query) => $query->where('opd_sub_kegiatan_id', $data['source_sub_opd']->id))
            ->orderBy('id')
            ->get();

        foreach ($targets as $index => $target) {
            $target->update(['target_text' => $longTargets[$index]]);
        }

        $this->actingAs($data['user'])
            ->post(route('renja-opd.store'), [
                'opd_id' => $data['opd']->id,
                'periode_tahun_id' => $data['target_period']->id,
                'tahun' => 2089,
                'judul' => 'RENJA TARGET PANJANG',
            ])
            ->assertRedirect();

        $renja = RenjaOpd::query()->where('judul', 'RENJA TARGET PANJANG')->sole();
        $expected = collect($longTargets)->map(fn (string $target) => trim($target))->implode("\n");

        $this->assertGreaterThan(255, strlen($expected));
        $this->assertSame($expected, $renja->items()->sole()->target_akhir_renstra);

        $rkpdItem = RkpdItem::create([
            'rkpd_id' => $data['rkpd_official']->id,
            'opd_id' => $data['opd']->id,
            'sub_kegiatan_pemerintahan_id' => $data['target_sub']->id,
            'target_akhir_renstra' => $expected,
            'status' => 'draft',
            'urutan' => 1,
        ]);

        $this->assertSame($expected, $rkpdItem->fresh()->target_akhir_renstra);
    }

    public function test_database_unique_index_blocks_duplicate_active_renja_sub_kegiatan_but_allows_replacement_after_soft_delete(): void
    {
        $data = $this->planningData();
        $renja = RenjaOpd::create([
            'rkpd_id' => $data['rkpd_official']->id,
            'renstra_opd_id' => $data['renstra']->id,
            'opd_id' => $data['opd']->id,
            'periode_tahun_id' => $data['target_period']->id,
            'tahun' => 2089,
            'judul' => 'RENJA UJI UNIQUE',
            'status' => 'draft',
        ]);
        $attributes = [
            'renja_opd_id' => $renja->id,
            'sub_kegiatan_pemerintahan_id' => $data['target_sub']->id,
            'kode' => $data['target_sub']->kode,
            'nama_sub_kegiatan' => $data['target_sub']->nama,
            'sumber_item' => 'manual',
            'status' => 'draft',
            'urutan' => 1,
        ];
        $first = RenjaOpdItem::create($attributes);
        $first->delete();
        RenjaOpdItem::create($attributes);

        $this->expectException(QueryException::class);
        RenjaOpdItem::create($attributes);
    }

    /** @return array<string, mixed> */
    private function planningData(): array
    {
        $sourcePeriod = PeriodeTahun::create(['tahun' => 2088, 'nama' => 'Tahun 2088', 'status' => 'active']);
        $targetPeriod = PeriodeTahun::create(['tahun' => 2089, 'nama' => 'Tahun 2089', 'status' => 'active']);
        $finalPeriod = PeriodeTahun::create(['tahun' => 2092, 'nama' => 'Tahun 2092', 'status' => 'active']);
        $urusan = UrusanPemerintahan::create(['kode' => '9', 'nama' => 'Urusan Pengujian', 'status' => 'active']);
        $bidang = BidangUrusan::create([
            'urusan_pemerintahan_id' => $urusan->id,
            'kode' => '9.01',
            'nama' => 'Bidang Pengujian',
            'status' => 'active',
        ]);
        $opd = Opd::create(['kode' => '9.01.0.00.0.00.01.0000', 'nama' => 'Dinas Uji', 'status' => 'active']);
        $program = ProgramPemerintahan::create([
            'bidang_urusan_id' => $bidang->id,
            'tahun_awal' => 2088,
            'tahun_akhir' => 2092,
            'kode' => '9.01.01',
            'nama' => 'Program Pengujian',
            'status' => 'active',
        ]);
        $sourceKegiatan = KegiatanPemerintahan::create([
            'periode_tahun_id' => $sourcePeriod->id,
            'program_pemerintahan_id' => $program->id,
            'kode' => '9.01.01.2.01',
            'nama' => 'Kegiatan Pengujian',
            'status' => 'active',
        ]);
        $targetKegiatan = KegiatanPemerintahan::create([
            'periode_tahun_id' => $targetPeriod->id,
            'program_pemerintahan_id' => $program->id,
            'kode' => $sourceKegiatan->kode,
            'nama' => $sourceKegiatan->nama,
            'status' => 'active',
        ]);
        $sourceSub = SubKegiatanPemerintahan::create([
            'periode_tahun_id' => $sourcePeriod->id,
            'kegiatan_pemerintahan_id' => $sourceKegiatan->id,
            'kode' => '9.01.01.2.01.0001',
            'nama' => 'Pelaksanaan Pengujian',
            'indikator_sub_kegiatan' => 'Jumlah layanan aktif',
            'status' => 'active',
        ]);
        $targetSub = SubKegiatanPemerintahan::create([
            'periode_tahun_id' => $targetPeriod->id,
            'kegiatan_pemerintahan_id' => $targetKegiatan->id,
            'kode' => $sourceSub->kode,
            'nama' => $sourceSub->nama,
            'indikator_sub_kegiatan' => $sourceSub->indikator_sub_kegiatan,
            'status' => 'active',
        ]);
        $manualSub = SubKegiatanPemerintahan::create([
            'periode_tahun_id' => $targetPeriod->id,
            'kegiatan_pemerintahan_id' => $targetKegiatan->id,
            'kode' => '9.01.01.2.01.0002',
            'nama' => 'Penyusunan Dokumen Pengujian',
            'status' => 'active',
        ]);
        $rpjmd = Rpjmd::create([
            'periode_tahun_id' => $sourcePeriod->id,
            'judul' => 'RPJMD UJI',
            'tahun_awal' => 2088,
            'tahun_akhir' => 2092,
            'status' => 'approved',
            'is_active_version' => true,
        ]);
        $rkpdOfficial = Rkpd::create([
            'rpjmd_id' => $rpjmd->id,
            'periode_tahun_id' => $targetPeriod->id,
            'tahun' => 2089,
            'judul' => 'RKPD DITETAPKAN TAHUN 2089',
            'status' => 'approved',
            'jenis_versi' => 'ditetapkan',
            'nomor_versi' => 2,
            'is_active_version' => true,
        ]);
        $renstra = RenstraOpd::create([
            'opd_id' => $opd->id,
            'rpjmd_id' => $rpjmd->id,
            'periode_tahun_id' => $sourcePeriod->id,
            'judul' => 'RENSTRA DINAS UJI',
            'tahun_awal' => 2088,
            'tahun_akhir' => 2092,
            'status' => 'approved',
            'is_active_version' => true,
        ]);
        $tujuan = TujuanOpd::create(['renstra_opd_id' => $renstra->id, 'tujuan' => 'Meningkatkan layanan uji', 'urutan' => 1]);
        $sasaranA = SasaranOpd::create(['tujuan_opd_id' => $tujuan->id, 'sasaran' => 'Sasaran A', 'urutan' => 1]);
        $sasaranB = SasaranOpd::create(['tujuan_opd_id' => $tujuan->id, 'sasaran' => 'Sasaran B', 'urutan' => 2]);

        $sourceSubOpd = null;
        foreach ([$sasaranA, $sasaranB] as $index => $sasaran) {
            $opdProgram = OpdProgram::create([
                'renstra_opd_id' => $renstra->id,
                'sasaran_opd_id' => $sasaran->id,
                'program_pemerintahan_id' => $program->id,
                'kode' => $program->kode,
                'nama' => $program->nama,
                'sasaran_program' => 'Sasaran Program '.($index + 1),
                'status' => 'approved',
                'urutan' => $index + 1,
            ]);
            $opdKegiatan = OpdKegiatan::create([
                'opd_program_id' => $opdProgram->id,
                'kegiatan_pemerintahan_id' => $sourceKegiatan->id,
                'kode' => $sourceKegiatan->kode,
                'nama' => $sourceKegiatan->nama,
                'sasaran_kegiatan' => 'Sasaran Kegiatan '.($index + 1),
                'urutan' => 1,
            ]);
            $opdSub = OpdSubKegiatan::create([
                'opd_kegiatan_id' => $opdKegiatan->id,
                'sub_kegiatan_pemerintahan_id' => $sourceSub->id,
                'kode' => $sourceSub->kode,
                'nama' => $sourceSub->nama,
                'urutan' => 1,
            ]);

            if ($index === 0) {
                $sourceSubOpd = $opdSub;
                $indicatorOne = IndikatorSubKegiatan::create(['opd_sub_kegiatan_id' => $opdSub->id, 'indikator' => 'Jumlah layanan aktif', 'urutan' => 1]);
                $indicatorTwo = IndikatorSubKegiatan::create(['opd_sub_kegiatan_id' => $opdSub->id, 'indikator' => 'Persentase layanan tepat waktu', 'urutan' => 2]);
                TargetIndikatorSubKegiatan::create([
                    'indikator_sub_kegiatan_id' => $indicatorOne->id,
                    'periode_tahun_id' => $finalPeriod->id,
                    'target_text' => '25 layanan',
                ]);
                TargetIndikatorSubKegiatan::create([
                    'indikator_sub_kegiatan_id' => $indicatorTwo->id,
                    'periode_tahun_id' => $finalPeriod->id,
                    'target_text' => '90%',
                ]);
            }
        }

        $permission = Permission::create(['name' => 'renja.manage', 'label' => 'Kelola RENJA', 'module' => 'renja']);
        $role = Role::create(['name' => 'admin_opd', 'label' => 'Admin OPD']);
        $role->permissions()->attach($permission);
        $user = User::factory()->create(['opd_id' => $opd->id]);
        $user->roles()->attach($role);

        return [
            'source_period' => $sourcePeriod,
            'target_period' => $targetPeriod,
            'opd' => $opd,
            'program' => $program,
            'target_kegiatan' => $targetKegiatan,
            'target_sub' => $targetSub,
            'manual_sub' => $manualSub,
            'renstra' => $renstra,
            'rkpd_official' => $rkpdOfficial,
            'source_sub_opd' => $sourceSubOpd,
            'user' => $user,
        ];
    }
}
