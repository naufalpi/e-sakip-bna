<?php

namespace Tests\Feature;

use App\Models\Opd;
use App\Models\Pegawai;
use App\Models\PeriodeTahun;
use App\Models\PerjanjianKinerja;
use App\Models\Role;
use App\Models\User;
use App\Services\Kinerja\KopDokumenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class KopDokumenTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_opd_only_manages_own_letterhead(): void
    {
        $this->seed();
        $opd = Opd::query()->where('status', 'active')->firstOrFail();
        $otherOpd = Opd::query()->where('status', 'active')->whereKeyNot($opd->id)->firstOrFail();
        $admin = User::factory()->create(['opd_id' => $opd->id]);
        $admin->roles()->sync([Role::where('name', 'admin_opd')->value('id')]);

        $this->actingAs($admin)
            ->get(route('master.kop-dokumen.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Master/KopDokumen/Index')
                ->has('items', 1)
                ->where('items.0.scope_key', 'opd:'.$opd->id)
            );

        $payload = $this->letterheadPayload('DINAS PENGUJIAN KOP');
        $this->actingAs($admin)
            ->post(route('master.kop-dokumen.update', ['scopeKey' => 'opd:'.$opd->id]), $payload)
            ->assertRedirect();

        $this->assertDatabaseHas('kop_dokumen', [
            'scope_key' => 'opd:'.$opd->id,
            'opd_id' => $opd->id,
            'nama_instansi' => 'DINAS PENGUJIAN KOP',
        ]);

        $this->actingAs($admin)
            ->post(route('master.kop-dokumen.update', ['scopeKey' => 'opd:'.$otherOpd->id]), $payload)
            ->assertForbidden();
    }

    public function test_pk_uses_snapshot_and_draft_snapshot_can_be_adjusted(): void
    {
        $this->seed();
        $opd = Opd::query()->where('status', 'active')->firstOrFail();
        $periode = PeriodeTahun::query()->firstOrFail();
        $admin = User::factory()->create(['opd_id' => $opd->id]);
        $admin->roles()->sync([Role::where('name', 'admin_opd')->value('id')]);
        $pegawai = Pegawai::create([
            'opd_id' => $opd->id,
            'nama' => 'Pegawai Uji Kop',
            'jenis_pegawai' => 'pns',
            'status' => 'active',
        ]);

        $this->actingAs($admin)->post(
            route('master.kop-dokumen.update', ['scopeKey' => 'opd:'.$opd->id]),
            $this->letterheadPayload('DINAS SUMBER KOP'),
        )->assertRedirect();

        $pk = PerjanjianKinerja::create([
            'opd_id' => $opd->id,
            'pegawai_id' => $pegawai->id,
            'periode_tahun_id' => $periode->id,
            'tahun' => $periode->tahun,
            'judul' => 'PK Uji Kop',
            'tipe_pk' => 'individual',
            'level_pk' => 'individu',
            'status' => 'draft',
        ]);
        app(KopDokumenService::class)->applySnapshot($pk);

        $this->assertSame('DINAS SUMBER KOP', $pk->fresh()->kop_dokumen_snapshot['nama_instansi']);

        $this->actingAs($admin)
            ->patch(route('perjanjian-kinerja.kop.update', $pk), $this->letterheadPayload('DINAS KHUSUS PK'))
            ->assertRedirect();

        $this->assertSame('DINAS KHUSUS PK', $pk->fresh()->kop_dokumen_snapshot['nama_instansi']);

        $pk->forceFill(['status' => 'approved'])->save();
        $this->actingAs($admin)
            ->patch(route('perjanjian-kinerja.kop.update', $pk), $this->letterheadPayload('TIDAK BOLEH BERUBAH'))
            ->assertForbidden();
    }

    private function letterheadPayload(string $officeName): array
    {
        return [
            'nama_pemerintah' => 'PEMERINTAH KABUPATEN BANJARNEGARA',
            'nama_instansi' => $officeName,
            'alamat' => 'Jalan Pengujian Nomor 1',
            'telepon' => '(0286) 000000',
            'faksimile' => '(0286) 000001',
            'website' => 'uji.banjarnegarakab.go.id',
            'email' => 'uji@banjarnegarakab.go.id',
            'kota' => 'BANJARNEGARA',
            'kode_pos' => '53414',
        ];
    }
}
