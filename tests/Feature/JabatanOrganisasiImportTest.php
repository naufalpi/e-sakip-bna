<?php

namespace Tests\Feature;

use App\Models\ImportBatch;
use App\Models\JabatanOrganisasi;
use App\Models\Opd;
use App\Models\Role;
use App\Models\User;
use App\Services\Imports\ImportTemplateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;
use ZipArchive;

class JabatanOrganisasiImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_structure_template_is_styled_and_separates_data_examples_and_references(): void
    {
        $template = app(ImportTemplateService::class)->make('jabatan_organisasi');
        $path = tempnam(sys_get_temp_dir(), 'jabatan_template_test_');
        file_put_contents($path, $template['content']);

        $zip = new ZipArchive;
        $zip->open($path);
        $jobs = $zip->getFromName('xl/worksheets/sheet1.xml');
        $examples = $zip->getFromName('xl/worksheets/sheet2.xml');
        $guide = $zip->getFromName('xl/worksheets/sheet3.xml');
        $references = $zip->getFromName('xl/worksheets/sheet4.xml');
        $styles = $zip->getFromName('xl/styles.xml');
        $zip->close();
        @unlink($path);

        $this->assertStringContainsString('Nama Jabatan *', $jobs);
        $this->assertStringNotContainsString('KODE_OPD', $jobs);
        $this->assertStringContainsString('dataValidations', $jobs);
        $this->assertStringContainsString('Kepala Bidang Contoh', $examples);
        $this->assertStringContainsString('Unggah hanya membuat preview', $guide);
        $this->assertStringContainsString('Kode OPD', $references);
        $this->assertStringContainsString('Aptos', $styles);
    }

    public function test_manager_can_preview_and_apply_structure_workbook_without_importing_employee_sheet(): void
    {
        $this->seed();
        $admin = $this->userWithRole('admin_kabupaten_bagian_organisasi');
        $opd = Opd::query()->where('status', 'active')->firstOrFail();
        $jobs = [
            ['nama_jabatan', 'level_jabatan', 'opd_kode', 'unit_kode', 'atasan_nama_jabatan', 'atasan_opd_kode', 'atasan_unit_kode', 'eselon', 'urutan', 'status'],
            ['Bupati Banjarnegara', 'kepala_daerah', null, null, null, null, null, null, 1, 'active'],
            ['Kepala OPD Import', 'jpt_pratama', $opd->kode, null, 'Bupati Banjarnegara', null, null, 'ii_b', 1, 'active'],
        ];
        $officials = [
            ['nama_jabatan', 'opd_kode', 'unit_kode', 'nama_pejabat', 'nip', 'pangkat_golongan', 'jenis_penugasan', 'nomor_sk', 'tanggal_sk', 'tmt_jabatan', 'tanggal_selesai', 'akun_pengguna'],
            ['Kepala OPD Import', $opd->kode, null, 'Pejabat Hasil Import', '198001012010011001', 'Pembina, IV/a', 'definitif', 'SK/001/2026', '2026-01-02', '2026-01-03', null, null],
        ];
        $file = $this->workbook($jobs, $officials);

        $this->actingAs($admin)
            ->get(route('master.jabatan-organisasi.import.template'))
            ->assertOk()
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        $this->actingAs($admin)
            ->post(route('master.jabatan-organisasi.import.store'), ['file' => $file])
            ->assertRedirect();

        $batch = ImportBatch::query()->where('module', 'jabatan_organisasi')->latest()->firstOrFail();
        $this->assertSame('previewed', $batch->status, $batch->error_message ?? '');
        $this->assertSame(2, $batch->rows()->where('status', 'valid')->count(), $batch->rows()->pluck('error_message')->filter()->implode(' | '));
        $this->assertSame(0, $batch->rows()->where('status', 'invalid')->count());

        $this->actingAs($admin)
            ->post(route('master.jabatan-organisasi.import.apply', $batch))
            ->assertRedirect(route('master.jabatan-organisasi.import.show', $batch));

        $bupati = JabatanOrganisasi::query()->where('nama', 'Bupati Banjarnegara')->firstOrFail();
        $kepalaOpd = JabatanOrganisasi::query()->where('nama', 'Kepala OPD Import')->firstOrFail();

        $this->assertSame($bupati->id, $kepalaOpd->parent_id);
        $this->assertDatabaseMissing('pegawai', ['nip' => '198001012010011001']);
        $this->assertSame('imported', $batch->fresh()->status);

        $this->actingAs($admin)
            ->post(route('master.jabatan-organisasi.import.store'), ['file' => $this->workbook($jobs, $officials)])
            ->assertRedirect();
        $secondBatch = ImportBatch::query()->where('module', 'jabatan_organisasi')->latest()->firstOrFail();
        $this->actingAs($admin)
            ->post(route('master.jabatan-organisasi.import.apply', $secondBatch))
            ->assertRedirect();

        $this->assertSame(2, JabatanOrganisasi::query()->whereIn('nama', ['Bupati Banjarnegara', 'Kepala OPD Import'])->count());
        $this->assertDatabaseCount('riwayat_pejabat_jabatan', 0);
    }

    public function test_duplicate_jabatan_is_invalid_and_cannot_be_applied(): void
    {
        $this->seed();
        $admin = $this->userWithRole('admin_kabupaten_dinkominfo');
        $file = $this->workbook([
            ['nama_jabatan', 'level_jabatan', 'opd_kode', 'unit_kode', 'atasan_nama_jabatan', 'atasan_opd_kode', 'atasan_unit_kode', 'eselon', 'urutan', 'status'],
            ['Bupati Banjarnegara', 'kepala_daerah', null, null, null, null, null, null, 1, 'active'],
            ['Bupati Banjarnegara', 'kepala_daerah', null, null, null, null, null, null, 2, 'active'],
        ], [
            ['nama_jabatan', 'opd_kode', 'unit_kode', 'nama_pejabat', 'nip', 'pangkat_golongan', 'jenis_penugasan', 'nomor_sk', 'tanggal_sk', 'tanggal_mulai', 'tanggal_selesai', 'akun_pengguna'],
        ]);

        $this->actingAs($admin)
            ->post(route('master.jabatan-organisasi.import.store'), ['file' => $file])
            ->assertRedirect();

        $batch = ImportBatch::query()->where('module', 'jabatan_organisasi')->latest()->firstOrFail();
        $this->assertSame('previewed', $batch->status, $batch->error_message ?? '');
        $this->assertSame(2, $batch->rows()->where('status', 'invalid')->count());

        $this->actingAs($admin)
            ->post(route('master.jabatan-organisasi.import.apply', $batch))
            ->assertSessionHasErrors('import_batch_id');

        $this->assertDatabaseMissing('jabatan_organisasi', ['nama' => 'Bupati Banjarnegara']);
    }

    public function test_admin_opd_cannot_access_jabatan_import(): void
    {
        $this->seed();
        $admin = $this->userWithRole('admin_opd');

        $this->actingAs($admin)
            ->get(route('master.jabatan-organisasi.import.create'))
            ->assertForbidden();
    }

    /**
     * @param  array<int, array<int, string|int|null>>  $jobs
     * @param  array<int, array<int, string|int|null>>  $officials
     */
    private function workbook(array $jobs, array $officials): UploadedFile
    {
        $template = app(ImportTemplateService::class)->make('jabatan_organisasi');
        $path = tempnam(sys_get_temp_dir(), 'jabatan_import_test_');
        file_put_contents($path, $template['content']);

        $zip = new ZipArchive;
        $zip->open($path);
        $zip->addFromString('xl/worksheets/sheet1.xml', $this->worksheetXml($jobs));
        $zip->addFromString('xl/worksheets/sheet2.xml', $this->worksheetXml($officials));
        $zip->close();

        $content = file_get_contents($path);
        @unlink($path);

        return UploadedFile::fake()->createWithContent('jabatan-organisasi.xlsx', $content);
    }

    /** @param array<int, array<int, string|int|null>> $rows */
    private function worksheetXml(array $rows): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>';

        foreach ($rows as $rowIndex => $row) {
            $excelRow = $rowIndex + 1;
            $xml .= '<row r="'.$excelRow.'">';
            foreach ($row as $columnIndex => $value) {
                $cell = $this->columnName($columnIndex + 1).$excelRow;
                $escaped = htmlspecialchars((string) $value, ENT_XML1 | ENT_COMPAT, 'UTF-8');
                $xml .= '<c r="'.$cell.'" t="inlineStr"><is><t xml:space="preserve">'.$escaped.'</t></is></c>';
            }
            $xml .= '</row>';
        }

        return $xml.'</sheetData></worksheet>';
    }

    private function columnName(int $index): string
    {
        $name = '';
        while ($index > 0) {
            $index--;
            $name = chr(65 + ($index % 26)).$name;
            $index = intdiv($index, 26);
        }

        return $name;
    }

    private function userWithRole(string $roleName): User
    {
        $user = User::factory()->create();
        $user->roles()->sync([Role::where('name', $roleName)->value('id')]);

        return $user;
    }
}
