<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\Rpjmd;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
