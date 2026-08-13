<?php

namespace Tests\Feature;

use App\Models\PeriodeTahun;
use App\Models\Rkpd;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RkpdUniqueConstraintTest extends TestCase
{
    use RefreshDatabase;

    public function test_soft_deleted_rkpd_does_not_block_recreating_same_year(): void
    {
        $periode = PeriodeTahun::create([
            'tahun' => 2026,
            'nama' => 'Tahun 2026',
            'status' => 'active',
        ]);

        $firstRkpd = Rkpd::create([
            'periode_tahun_id' => $periode->id,
            'tahun' => 2026,
            'judul' => 'RKPD KABUPATEN BANJARNEGARA TAHUN 2026',
            'status' => 'draft',
        ]);

        $firstRkpd->delete();

        $secondRkpd = Rkpd::create([
            'periode_tahun_id' => $periode->id,
            'tahun' => 2026,
            'judul' => 'RKPD KABUPATEN BANJARNEGARA TAHUN 2026',
            'status' => 'draft',
        ]);

        $this->assertNotSame($firstRkpd->id, $secondRkpd->id);
        $this->assertDatabaseHas('rkpd', [
            'id' => $secondRkpd->id,
            'periode_tahun_id' => $periode->id,
            'tahun' => 2026,
            'deleted_at' => null,
        ]);
    }
}
