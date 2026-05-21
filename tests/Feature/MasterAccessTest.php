<?php

namespace Tests\Feature;

use App\Models\Opd;
use App\Models\Permission;
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

    public function test_role_permissions_can_be_updated_by_authorized_admin_and_logged(): void
    {
        $this->seed();

        $admin = User::factory()->create();
        $admin->roles()->sync([Role::where('name', 'admin_kabupaten_dinkominfo')->value('id')]);

        $role = Role::where('name', 'admin_opd')->firstOrFail();
        $permissionIds = Permission::query()
            ->whereIn('name', ['dashboard.view', 'view_dashboard_opd'])
            ->pluck('id')
            ->all();

        $this->actingAs($admin)
            ->patch(route('master.role-permission.update', $role), [
                'permission_ids' => $permissionIds,
            ])
            ->assertRedirect();

        $this->assertEqualsCanonicalizing($permissionIds, $role->fresh()->permissions()->pluck('permissions.id')->all());
        $this->assertDatabaseHas('activity_logs', [
            'action' => 'permissions_synced',
            'model_type' => Role::class,
            'model_id' => $role->id,
        ]);
    }

    public function test_role_permissions_cannot_be_updated_by_read_only_role(): void
    {
        $this->seed();

        $user = User::factory()->create();
        $user->roles()->sync([Role::where('name', 'pimpinan')->value('id')]);
        $role = Role::where('name', 'admin_opd')->firstOrFail();

        $this->actingAs($user)
            ->patch(route('master.role-permission.update', $role), [
                'permission_ids' => [],
            ])
            ->assertForbidden();
    }
}
