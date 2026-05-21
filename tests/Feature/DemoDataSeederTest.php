<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Dokumen;
use App\Models\EvaluasiSakip;
use App\Models\ImportBatch;
use App\Models\Lkjip;
use App\Models\Notification;
use App\Models\Opd;
use App\Models\PerjanjianKinerja;
use App\Models\RealisasiKinerja;
use App\Models\RenstraOpd;
use App\Models\Rpjmd;
use App\Models\TargetTriwulanIndikator;
use App\Models\User;
use App\Models\WorkflowSubmission;
use Database\Seeders\DemoDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DemoDataSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_data_seeder_populates_end_to_end_sakip_dataset(): void
    {
        Storage::fake((string) config('filesystems.documents_disk', 'local'));

        $this->seed(DemoDataSeeder::class);

        $this->assertDatabaseHas('users', [
            'email' => 'admin@example.test',
            'username' => 'superadmin',
        ]);
        $this->assertDatabaseHas('users', ['email' => 'admin.dinkes@example.test']);
        $this->assertDatabaseHas('opds', ['singkatan' => 'Dinkes']);
        $this->assertDatabaseHas('opds', ['singkatan' => 'Disdikpora']);
        $this->assertDatabaseHas('opds', ['singkatan' => 'Dinkominfo']);

        $this->assertSame(3, Opd::query()->count());
        $this->assertSame(1, Rpjmd::query()->count());
        $this->assertSame(3, RenstraOpd::query()->count());
        $this->assertSame(3, PerjanjianKinerja::query()->count());
        $this->assertSame(3, RealisasiKinerja::query()->count());
        $this->assertSame(3, Lkjip::query()->count());
        $this->assertSame(3, EvaluasiSakip::query()->count());

        $this->assertGreaterThanOrEqual(1, TargetTriwulanIndikator::query()->count());
        $this->assertGreaterThanOrEqual(20, Dokumen::query()->count());
        $this->assertGreaterThanOrEqual(1, ImportBatch::query()->count());
        $this->assertGreaterThanOrEqual(15, WorkflowSubmission::query()->count());
        $this->assertGreaterThanOrEqual(3, Notification::query()->where('type', 'demo')->count());

        $this->assertDatabaseHas('realisasi_kinerja', [
            'triwulan' => 'tw4',
            'status_capaian' => 'hijau',
            'status_efisiensi' => 'efisien',
        ]);
        $this->assertDatabaseHas('realisasi_kinerja', [
            'triwulan' => 'tw4',
            'status_capaian' => 'kuning',
        ]);
        $this->assertDatabaseHas('dokumen', ['jenis' => 'lhe']);
        $this->assertDatabaseHas('workflow_submissions', ['module' => 'lkjip']);
        $this->assertDatabaseHas('activity_logs', ['action' => 'seed_demo_data']);

        $superAdmin = User::query()->where('username', 'superadmin')->firstOrFail();

        $this->assertTrue($superAdmin->hasRole('super_admin'));
        $this->assertTrue($superAdmin->hasPermission('manage_users'));
        $this->assertTrue(ActivityLog::query()->where('action', 'seed_demo_data')->exists());
    }
}
