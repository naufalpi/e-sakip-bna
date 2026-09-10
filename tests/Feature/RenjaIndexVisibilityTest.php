<?php

namespace Tests\Feature;

use App\Models\Opd;
use App\Models\PeriodeTahun;
use App\Models\RenjaOpd;
use App\Models\Rkpd;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class RenjaIndexVisibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_hides_process_archive_after_renja_is_established(): void
    {
        [$user, $initial, $established] = $this->establishedRenjaLineage();

        $this->actingAs($user)
            ->get(route('renja-opd.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('RenjaOpd/Index')
                ->has('items.data', 1)
                ->where('items.data.0.id', $established->id)
                ->where('items.data.0.is_active_version', true)
                ->where('items.data.0.is_archived_version', false)
                ->where('filters.jenis_versi', 'aktif')
                ->where('summary.awal', 0)
                ->where('summary.ditetapkan', 1)
                ->where('summary.perubahan', 0)
            );

        $this->assertFalse($initial->fresh()->is_active_version);
    }

    public function test_index_shows_working_change_instead_of_active_official_version(): void
    {
        [$user, , $established, $rkpd, $period, $opd] = $this->establishedRenjaLineage();

        $change = RenjaOpd::create([
            'rkpd_id' => $rkpd->id,
            'opd_id' => $opd->id,
            'periode_tahun_id' => $period->id,
            'tahun' => 2027,
            'judul' => 'RENJA PERUBAHAN DINAS PENGUJIAN TAHUN 2027',
            'status' => 'draft',
            'jenis_versi' => 'perubahan',
            'nomor_versi' => 3,
            'parent_version_id' => $established->id,
            'root_version_id' => $established->root_version_id,
            'is_active_version' => false,
        ]);

        $this->actingAs($user)
            ->get(route('renja-opd.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('RenjaOpd/Index')
                ->has('items.data', 1)
                ->where('items.data.0.id', $change->id)
                ->where('items.data.0.is_working_version', true)
                ->where('items.data.0.active_version.id', $established->id)
                ->where('items.data.0.active_version.version_label', 'RENJA Ditetapkan')
                ->where('summary.awal', 0)
                ->where('summary.ditetapkan', 0)
                ->where('summary.perubahan', 1)
            );
    }

    public function test_all_versions_filter_includes_process_archive_and_active_version(): void
    {
        [$user, $initial, $established] = $this->establishedRenjaLineage();

        $this->actingAs($user)
            ->get(route('renja-opd.index', ['jenis_versi' => 'semua']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('RenjaOpd/Index')
                ->has('items.data', 2)
                ->where('items.data.0.id', $established->id)
                ->where('items.data.0.is_archived_version', false)
                ->where('items.data.1.id', $initial->id)
                ->where('items.data.1.is_archived_version', true)
                ->where('filters.jenis_versi', 'semua')
                ->where('summary.awal', 1)
                ->where('summary.ditetapkan', 1)
                ->where('summary.perubahan', 0)
            );
    }

    /** @return array{User, RenjaOpd, RenjaOpd, Rkpd, PeriodeTahun, Opd} */
    private function establishedRenjaLineage(): array
    {
        $this->seed();

        $user = User::factory()->create();
        $user->roles()->sync([Role::where('name', 'super_admin')->value('id')]);

        $period = PeriodeTahun::query()->where('tahun', 2027)->firstOrFail();
        $opd = Opd::create([
            'kode' => '1.01',
            'nama' => 'Dinas Pengujian',
            'status' => 'active',
        ]);
        $rkpd = Rkpd::create([
            'periode_tahun_id' => $period->id,
            'tahun' => 2027,
            'judul' => 'RKPD KABUPATEN BANJARNEGARA TAHUN 2027',
            'status' => 'approved',
            'jenis_versi' => 'ditetapkan',
            'nomor_versi' => 2,
            'is_active_version' => true,
        ]);
        $initial = RenjaOpd::create([
            'rkpd_id' => $rkpd->id,
            'opd_id' => $opd->id,
            'periode_tahun_id' => $period->id,
            'tahun' => 2027,
            'judul' => 'RENJA DINAS PENGUJIAN TAHUN 2027',
            'status' => 'approved',
            'jenis_versi' => 'awal',
            'nomor_versi' => 1,
            'is_active_version' => false,
        ]);
        $established = RenjaOpd::create([
            'rkpd_id' => $rkpd->id,
            'opd_id' => $opd->id,
            'periode_tahun_id' => $period->id,
            'tahun' => 2027,
            'judul' => 'RENJA DINAS PENGUJIAN TAHUN 2027',
            'status' => 'approved',
            'jenis_versi' => 'ditetapkan',
            'nomor_versi' => 2,
            'parent_version_id' => $initial->id,
            'root_version_id' => $initial->root_version_id,
            'is_active_version' => true,
        ]);

        return [$user, $initial, $established, $rkpd, $period, $opd];
    }
}
