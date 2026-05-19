<?php

namespace Tests\Feature;

use App\Models\Opd;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MasterAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_access_master_pages(): void
    {
        $this->seed();

        $superAdmin = User::where('email', 'admin@example.test')->firstOrFail();

        $this->actingAs($superAdmin)
            ->get(route('master.opd.index'))
            ->assertOk();

        $this->actingAs($superAdmin)
            ->get(route('master.users.index'))
            ->assertOk();

        $this->actingAs($superAdmin)
            ->get(route('master.role-permission.index'))
            ->assertOk();
    }

    public function test_pimpinan_cannot_access_user_management(): void
    {
        $this->seed();

        $user = User::factory()->create();
        $user->roles()->sync([Role::where('name', 'pimpinan')->value('id')]);

        $this->actingAs($user)
            ->get(route('master.users.index'))
            ->assertForbidden();
    }

    public function test_seeded_super_admin_can_login_and_has_permissions(): void
    {
        $this->seed();

        $response = $this->post(route('login'), [
            'email' => 'admin@example.test',
            'password' => 'password',
        ]);

        $response->assertRedirect(route('dashboard', absolute: false));

        /** @var User $user */
        $user = User::where('username', 'superadmin')->firstOrFail();

        $this->assertTrue($user->isSuperAdmin());
        $this->assertTrue($user->hasRole('super_admin'));
        $this->assertTrue($user->hasPermission('manage_users'));
        $this->assertTrue($user->hasPermission('manage_rpjmd'));
        $this->assertNotNull($user->fresh()->last_login_at);
    }

    public function test_admin_opd_can_view_but_cannot_manage_opd_master(): void
    {
        $this->seed();

        $opd = Opd::create([
            'kode' => '1.01',
            'nama' => 'Dinas Contoh',
            'status' => 'active',
        ]);

        $user = User::factory()->create(['opd_id' => $opd->id]);
        $user->roles()->sync([Role::where('name', 'admin_opd')->value('id')]);

        $this->actingAs($user)
            ->get(route('master.opd.index'))
            ->assertOk();

        $this->actingAs($user)
            ->get(route('master.opd.create'))
            ->assertForbidden();
    }
}
