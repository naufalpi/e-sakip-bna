<?php

namespace Tests\Feature;

use App\Models\ImportBatch;
use App\Models\Opd;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ImportTemplateValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_import_templates_can_be_downloaded(): void
    {
        $this->seed();

        $bapperida = User::factory()->create();
        $bapperida->roles()->sync([Role::where('name', 'admin_kabupaten_bapperida')->value('id')]);

        $this->actingAs($bapperida)
            ->get(route('rpjmd.import.template'))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        $opd = Opd::create(['kode' => '8.01', 'nama' => 'Dinas Template', 'status' => 'active']);
        $adminOpd = User::factory()->create(['opd_id' => $opd->id]);
        $adminOpd->roles()->sync([Role::where('name', 'admin_opd')->value('id')]);

        $response = $this->actingAs($adminOpd)
            ->get(route('renstra-opd.import.template'))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        $this->assertStringStartsWith('PK', $response->getContent());
    }

    public function test_rpjmd_import_preview_fails_when_required_columns_are_missing(): void
    {
        $this->seed();
        Storage::fake('local');
        config(['filesystems.default' => 'local']);

        $bapperida = User::factory()->create();
        $bapperida->roles()->sync([Role::where('name', 'admin_kabupaten_bapperida')->value('id')]);

        $file = UploadedFile::fake()->createWithContent('rpjmd-invalid.csv', "kode,nama\nA,B\n");

        $this->actingAs($bapperida)
            ->post(route('rpjmd.import.store'), ['file' => $file])
            ->assertRedirect();

        $batch = ImportBatch::query()->where('module', 'rpjmd')->latest('id')->firstOrFail();

        $this->assertSame('failed', $batch->status);
        $this->assertStringContainsString('Kolom wajib belum ada', $batch->error_message);
        $this->assertDatabaseCount('import_batch_rows', 0);
    }

    public function test_renstra_import_preview_fails_when_required_columns_are_missing(): void
    {
        $this->seed();
        Storage::fake('local');
        config(['filesystems.default' => 'local']);

        $opd = Opd::create(['kode' => '8.02', 'nama' => 'Dinas Invalid Import', 'status' => 'active']);
        $adminOpd = User::factory()->create(['opd_id' => $opd->id]);
        $adminOpd->roles()->sync([Role::where('name', 'admin_opd')->value('id')]);

        $file = UploadedFile::fake()->createWithContent('renstra-invalid.csv', "level,kode,uraian\nrenstra,R1,Renstra tanpa OPD dan RPJMD\n");

        $this->actingAs($adminOpd)
            ->post(route('renstra-opd.import.store'), ['file' => $file])
            ->assertRedirect();

        $batch = ImportBatch::query()->where('module', 'renstra_opd')->latest('id')->firstOrFail();

        $this->assertSame('failed', $batch->status);
        $this->assertStringContainsString('identitas OPD', $batch->error_message);
        $this->assertStringContainsString('referensi RPJMD', $batch->error_message);
    }
}
