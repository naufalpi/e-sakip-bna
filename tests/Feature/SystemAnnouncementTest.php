<?php

namespace Tests\Feature;

use App\Models\Opd;
use App\Models\Role;
use App\Models\SystemAnnouncement;
use App\Models\User;
use App\Services\SystemAnnouncementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SystemAnnouncementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_only_super_admin_can_manage_system_announcements(): void
    {
        $superAdmin = $this->userWithRole('super_admin', 'Super Admin');
        $regularUser = $this->userWithRole('admin_opd', 'Admin OPD');

        $this->actingAs($regularUser)
            ->get(route('master.system-announcements.index'))
            ->assertForbidden();

        $this->actingAs($superAdmin)
            ->get(route('master.system-announcements.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Master/SystemAnnouncement/Index')
                ->has('items.data', 0));

        $this->actingAs($superAdmin)
            ->post(route('master.system-announcements.store'), [
                'title' => 'Pemeliharaan sistem',
                'message' => 'Aplikasi tidak dapat digunakan sementara pada malam hari.',
                'type' => 'warning',
                'audience' => 'all',
                'target_roles' => [],
                'target_opd_ids' => [],
                'starts_at' => now()->addHour()->format('Y-m-d H:i:s'),
                'ends_at' => now()->addHours(2)->format('Y-m-d H:i:s'),
                'is_active' => true,
                'is_dismissible' => true,
            ])
            ->assertRedirect();

        $announcement = SystemAnnouncement::query()->firstOrFail();
        $this->assertSame('scheduled', $announcement->displayStatus());
        $this->assertDatabaseHas('activity_logs', [
            'model_type' => SystemAnnouncement::class,
            'model_id' => $announcement->id,
            'action' => 'created',
        ]);

        $this->actingAs($superAdmin)
            ->put(route('master.system-announcements.update', $announcement), [
                'title' => 'Pemeliharaan sistem terjadwal',
                'message' => 'Jadwal pemeliharaan telah diperbarui.',
                'type' => 'important',
                'audience' => 'all',
                'target_roles' => [],
                'target_opd_ids' => [],
                'is_active' => true,
                'is_dismissible' => false,
            ])
            ->assertRedirect();
        $this->assertDatabaseHas('system_announcements', [
            'id' => $announcement->id,
            'title' => 'Pemeliharaan sistem terjadwal',
            'type' => 'important',
            'is_dismissible' => false,
        ]);

        $this->actingAs($superAdmin)
            ->patch(route('master.system-announcements.toggle', $announcement))
            ->assertRedirect();
        $this->assertFalse($announcement->fresh()->is_active);

        $this->actingAs($superAdmin)
            ->delete(route('master.system-announcements.destroy', $announcement))
            ->assertRedirect();
        $this->assertSoftDeleted($announcement);
    }

    public function test_announcement_validation_requires_valid_targets_schedule_and_link(): void
    {
        $superAdmin = $this->userWithRole('super_admin', 'Super Admin');

        $this->actingAs($superAdmin)
            ->post(route('master.system-announcements.store'), [
                'title' => 'Informasi',
                'message' => 'Isi pengumuman.',
                'type' => 'info',
                'audience' => 'roles',
                'target_roles' => [],
                'starts_at' => '2026-09-09 12:00:00',
                'ends_at' => '2026-09-09 11:00:00',
                'link_url' => 'javascript:alert(1)',
                'is_active' => true,
                'is_dismissible' => true,
            ])
            ->assertSessionHasErrors(['target_roles', 'ends_at', 'link_url']);

        $this->assertDatabaseCount('system_announcements', 0);
    }

    public function test_visible_announcements_are_filtered_by_schedule_role_and_opd(): void
    {
        $adminRole = Role::create(['name' => 'admin_opd', 'label' => 'Admin OPD']);
        Role::create(['name' => 'pimpinan', 'label' => 'Pimpinan']);
        $opd = Opd::create(['kode' => '1.01', 'nama' => 'Dinas Contoh', 'status' => 'active']);
        $otherOpd = Opd::create(['kode' => '1.02', 'nama' => 'Dinas Lain', 'status' => 'active']);
        $user = User::factory()->create(['opd_id' => $opd->id]);
        $user->roles()->attach($adminRole);

        $all = $this->announcement(['title' => 'Untuk semua', 'audience' => 'all']);
        $role = $this->announcement([
            'title' => 'Untuk admin OPD',
            'audience' => 'roles',
            'target_roles' => ['admin_opd'],
            'type' => 'important',
        ]);
        $opdTarget = $this->announcement([
            'title' => 'Untuk OPD pengguna',
            'audience' => 'opds',
            'target_opd_ids' => [$opd->id],
        ]);
        $this->announcement(['title' => 'Role lain', 'audience' => 'roles', 'target_roles' => ['pimpinan']]);
        $this->announcement(['title' => 'OPD lain', 'audience' => 'opds', 'target_opd_ids' => [$otherOpd->id]]);
        $this->announcement(['title' => 'Belum mulai', 'starts_at' => now()->addMinute()]);
        $this->announcement(['title' => 'Sudah selesai', 'ends_at' => now()->subMinutes(2)]);
        $this->announcement(['title' => 'Dimatikan', 'is_active' => false]);

        $visibleIds = collect(app(SystemAnnouncementService::class)->visibleFor($user->load('roles')))->pluck('id')->all();

        $this->assertEqualsCanonicalizing([$all->id, $role->id, $opdTarget->id], $visibleIds);
    }

    public function test_cache_is_invalidated_immediately_when_announcement_changes(): void
    {
        $user = $this->userWithRole('admin_opd', 'Admin OPD');
        $service = app(SystemAnnouncementService::class);
        $first = $this->announcement(['title' => 'Pengumuman pertama']);

        $this->assertEquals([$first->id], collect($service->visibleFor($user->load('roles')))->pluck('id')->all());

        $second = $this->announcement(['title' => 'Pengumuman kedua', 'type' => 'important']);

        $this->assertEqualsCanonicalizing(
            [$first->id, $second->id],
            collect($service->visibleFor($user))->pluck('id')->all(),
        );
    }

    public function test_visible_announcements_are_shared_with_authenticated_inertia_pages(): void
    {
        $superAdmin = $this->userWithRole('super_admin', 'Super Admin');
        $visible = $this->announcement(['title' => 'Tampil pada header']);
        $this->announcement(['title' => 'Belum waktunya', 'starts_at' => now()->addHours(2)]);

        $this->actingAs($superAdmin)
            ->get(route('master.system-announcements.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('system_announcements', 1)
                ->where('system_announcements.0.id', $visible->id)
                ->where('system_announcements.0.title', 'Tampil pada header'));
    }

    private function userWithRole(string $name, string $label): User
    {
        $role = Role::firstOrCreate(['name' => $name], ['label' => $label]);
        $user = User::factory()->create();
        $user->roles()->attach($role);

        return $user;
    }

    /** @param array<string, mixed> $overrides */
    private function announcement(array $overrides = []): SystemAnnouncement
    {
        return SystemAnnouncement::create([
            'title' => 'Pengumuman',
            'message' => 'Informasi untuk pengguna aplikasi.',
            'type' => 'info',
            'audience' => 'all',
            'target_roles' => null,
            'target_opd_ids' => null,
            'starts_at' => null,
            'ends_at' => null,
            'is_active' => true,
            'is_dismissible' => true,
            ...$overrides,
        ]);
    }
}
