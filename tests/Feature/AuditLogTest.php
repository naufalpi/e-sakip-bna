<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Opd;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\ActivityLogService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_audit_log_is_presented_as_readable_activity(): void
    {
        $user = $this->userWhoCanViewAuditLogs();
        ActivityLog::create([
            'user_id' => $user->id,
            'action' => 'updated',
            'model_type' => Opd::class,
            'model_id' => 17,
            'old_values' => ['nama' => 'Dinas Lama', 'status' => 'inactive'],
            'new_values' => ['nama' => 'Dinas Baru', 'status' => 'active'],
            'ip_address' => '203.0.113.42',
            'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/125.0 Safari/537.36',
        ]);

        $this->actingAs($user)
            ->get(route('audit-log.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('AuditLog/Index')
                ->where('logs.data.0.action_label', 'Diperbarui')
                ->where('logs.data.0.model_label', 'Perangkat daerah')
                ->where('logs.data.0.summary', 'Perangkat daerah “Dinas Baru” diperbarui.')
                ->where('logs.data.0.changes.0.field_label', 'Nama')
                ->where('logs.data.0.changes.0.from', 'Dinas Lama')
                ->where('logs.data.0.changes.0.to', 'Dinas Baru')
                ->where('logs.data.0.device_label', 'Google Chrome · Windows')
                ->where('canClear', false));
    }

    public function test_only_super_admin_can_clear_all_audit_logs(): void
    {
        foreach (range(1, 3) as $index) {
            ActivityLog::create(['action' => 'created', 'description' => "Log pengujian {$index}"]);
        }
        $viewer = $this->userWhoCanViewAuditLogs();
        $countBeforeForbiddenAttempt = ActivityLog::query()->count();

        $this->actingAs($viewer)
            ->delete(route('audit-log.destroy-all'))
            ->assertForbidden();
        $this->assertDatabaseCount('activity_logs', $countBeforeForbiddenAttempt);

        $superAdmin = User::factory()->create(['status' => 'active', 'email_verified_at' => now()]);
        $superAdmin->roles()->attach(Role::create(['name' => 'super_admin', 'label' => 'Super Admin', 'is_system' => true]));
        $countBeforeDeletion = ActivityLog::query()->count();

        $this->actingAs($superAdmin)
            ->delete(route('audit-log.destroy-all'))
            ->assertRedirect(route('audit-log.index'))
            ->assertSessionHas('success', number_format($countBeforeDeletion, 0, ',', '.').' audit log berhasil dihapus.');

        $this->assertDatabaseCount('activity_logs', 0);
    }

    public function test_client_ip_is_read_from_forwarded_header_only_for_a_trusted_proxy(): void
    {
        config(['trustedproxy.proxies' => ['10.3.1.238']]);
        Route::post('/testing/audit-client-ip', function (ActivityLogService $activityLogService) {
            $activityLogService->log('created', description: 'Pengujian alamat IP klien.');

            return response()->noContent();
        })->middleware('web');

        $this->withServerVariables(['REMOTE_ADDR' => '10.3.1.238'])
            ->withHeader('X-Forwarded-For', '203.0.113.42')
            ->post('/testing/audit-client-ip')
            ->assertNoContent();

        $this->assertDatabaseHas('activity_logs', [
            'description' => 'Pengujian alamat IP klien.',
            'ip_address' => '203.0.113.42',
        ]);
    }

    private function userWhoCanViewAuditLogs(): User
    {
        $permission = Permission::create([
            'name' => 'activity_logs.view',
            'label' => 'Lihat Audit Log',
            'module' => 'audit_log',
        ]);
        $role = Role::create(['name' => 'auditor', 'label' => 'Auditor', 'is_system' => false]);
        $role->permissions()->attach($permission);
        $user = User::factory()->create(['status' => 'active', 'email_verified_at' => now()]);
        $user->roles()->attach($role);

        return $user;
    }
}
