<?php

namespace Tests\Feature;

use App\Models\JabatanOrganisasi;
use App\Models\Opd;
use App\Models\Pegawai;
use App\Services\Master\OpdLeaderResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OpdLeaderResolverTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_uses_active_verified_organization_placement_before_legacy_opd_fields(): void
    {
        $opd = Opd::create([
            'kode' => '2.16',
            'nama' => 'Dinas Komunikasi dan Informatika',
            'nama_kepala' => 'Nama Lama',
            'nip_kepala' => '190000000000000001',
            'status' => 'active',
        ]);
        $job = JabatanOrganisasi::create([
            'opd_id' => $opd->id,
            'nama' => 'Kepala Dinas Komunikasi dan Informatika',
            'level_jabatan' => 'jpt_pratama',
            'status' => 'active',
            'verification_status' => 'verified',
        ]);
        $employee = Pegawai::create([
            'opd_id' => $opd->id,
            'nama' => 'Pejabat Aktif',
            'nip' => '199001012010011001',
            'pangkat_golongan' => 'Pembina Tingkat I / IV.b',
            'jenis_pegawai' => 'pns',
            'status' => 'active',
        ]);
        $placement = $employee->penempatan()->create([
            'jabatan_organisasi_id' => $job->id,
            'nama_pejabat' => $employee->nama,
            'nip' => $employee->nip,
            'pangkat_golongan' => $employee->pangkat_golongan,
            'jenis_penugasan' => 'definitif',
            'tanggal_mulai' => '2026-01-01',
        ]);

        $leader = app(OpdLeaderResolver::class)->resolve($opd, '2026-09-02');

        $this->assertSame('struktur_organisasi', $leader['source']);
        $this->assertSame('Pejabat Aktif', $leader['name']);
        $this->assertSame('199001012010011001', $leader['nip']);
        $this->assertSame('Kepala Dinas Komunikasi dan Informatika', $leader['position']);
        $this->assertSame($placement->id, $leader['placement_id']);
    }

    public function test_it_keeps_legacy_opd_leader_as_fallback_when_no_active_placement_exists(): void
    {
        $opd = Opd::create([
            'kode' => '1.01',
            'nama' => 'Dinas Kesehatan',
            'nama_kepala' => 'Pejabat Data Lama',
            'nip_kepala' => '196001011990031001',
            'status' => 'active',
        ]);

        $leader = app(OpdLeaderResolver::class)->resolve($opd, '2026-09-02');

        $this->assertSame('opd_legacy', $leader['source']);
        $this->assertSame('Pejabat Data Lama', $leader['name']);
        $this->assertSame('196001011990031001', $leader['nip']);
        $this->assertNull($leader['placement_id']);
    }
}
