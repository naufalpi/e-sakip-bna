<?php

namespace Tests\Feature;

use App\Models\ImportBatch;
use App\Models\JabatanOrganisasi;
use App\Models\Opd;
use App\Models\Pegawai;
use App\Models\RiwayatPejabatJabatan;
use App\Models\Role;
use App\Models\User;
use App\Services\Imports\ImportTemplateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;
use ZipArchive;

class PegawaiImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_opd_downloads_scoped_template_and_can_import_employee_with_placement(): void
    {
        $this->seed();
        $opd = Opd::query()->where('status', 'active')->firstOrFail();
        $otherOpd = Opd::query()->where('status', 'active')->whereKeyNot($opd->id)->firstOrFail();
        $admin = $this->adminOpd($opd);
        $job = JabatanOrganisasi::create([
            'opd_id' => $opd->id,
            'nama' => 'Pranata Komputer Ahli Pertama',
            'level_jabatan' => 'fungsional',
            'status' => 'active',
            'verification_status' => 'verified',
        ]);

        $response = $this->actingAs($admin)
            ->get(route('master.pegawai.import.template'))
            ->assertOk()
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        $path = tempnam(sys_get_temp_dir(), 'pegawai_template_test_');
        file_put_contents($path, $response->getContent());
        $zip = new ZipArchive;
        $zip->open($path);
        $employeeSheet = $zip->getFromName('xl/worksheets/sheet1.xml');
        $jobReferences = $zip->getFromName('xl/worksheets/sheet4.xml');
        $opdReferences = $zip->getFromName('xl/worksheets/sheet5.xml');
        $zip->close();
        @unlink($path);

        $this->assertStringContainsString('Nama Pegawai *', $employeeSheet);
        $this->assertStringContainsString('dataValidations', $employeeSheet);
        $this->assertStringContainsString($job->nama, $jobReferences);
        $this->assertStringContainsString($opd->nama, $opdReferences);
        $this->assertStringNotContainsString($otherOpd->nama, $opdReferences);

        $file = $this->employeeWorkbook($opd, [
            $this->headers(),
            ['Naufal Akbar Pinastika, S.Kom', '200010042025041005', 'PNS', 'Aktif', 'Penata Muda, III/a', $job->nama, $opd->kode, null, 'Definitif', '2026-01-03', null, null, null, null],
        ]);

        $this->actingAs($admin)
            ->post(route('master.pegawai.import.store'), ['file' => $file])
            ->assertRedirect();

        $batch = ImportBatch::query()->where('module', 'pegawai')->latest()->firstOrFail();
        $this->assertSame('previewed', $batch->status, $batch->error_message ?? '');
        $this->assertSame($opd->id, (int) data_get($batch->metadata, 'scope_opd_id'));
        $this->assertSame(1, $batch->rows()->where('status', 'valid')->count(), $batch->rows()->pluck('error_message')->filter()->implode(' | '));

        $this->actingAs($admin)
            ->post(route('master.pegawai.import.apply', $batch))
            ->assertRedirect(route('master.pegawai.import.show', $batch));

        $this->assertDatabaseHas('pegawai', [
            'opd_id' => $opd->id,
            'nama' => 'Naufal Akbar Pinastika, S.Kom',
            'nip' => '200010042025041005',
            'jenis_pegawai' => 'pns',
        ]);
        $this->assertDatabaseHas('riwayat_pejabat_jabatan', [
            'jabatan_organisasi_id' => $job->id,
            'nip' => '200010042025041005',
        ]);
    }

    public function test_admin_opd_cannot_import_employee_from_another_opd(): void
    {
        $this->seed();
        $opd = Opd::query()->where('status', 'active')->firstOrFail();
        $otherOpd = Opd::query()->where('status', 'active')->whereKeyNot($opd->id)->firstOrFail();
        $admin = $this->adminOpd($opd);
        $job = JabatanOrganisasi::create([
            'opd_id' => $otherOpd->id,
            'nama' => 'Jabatan OPD Lain',
            'level_jabatan' => 'pelaksana',
            'status' => 'active',
            'verification_status' => 'verified',
        ]);
        $file = $this->employeeWorkbook($opd, [
            $this->headers(),
            ['Pegawai OPD Lain', '199001012020011001', 'PNS', 'Aktif', null, $job->nama, $otherOpd->kode, null, 'Definitif', '2026-01-03', null, null, null, null],
        ]);

        $this->actingAs($admin)->post(route('master.pegawai.import.store'), ['file' => $file])->assertRedirect();

        $batch = ImportBatch::query()->where('module', 'pegawai')->latest()->firstOrFail();
        $this->assertSame('previewed', $batch->status);
        $this->assertSame(1, $batch->rows()->where('status', 'invalid')->count());
        $this->assertStringContainsString('di luar perangkat daerah', $batch->rows()->firstOrFail()->error_message);
        $this->actingAs($admin)
            ->post(route('master.pegawai.import.apply', $batch))
            ->assertSessionHasErrors('import_batch_id');
    }

    public function test_employee_preview_rejects_corrupted_scientific_notation_nip(): void
    {
        $this->seed();
        $opd = Opd::query()->where('status', 'active')->firstOrFail();
        $admin = $this->adminOpd($opd);
        $job = JabatanOrganisasi::create([
            'opd_id' => $opd->id,
            'nama' => 'Pengadministrasi Umum Import',
            'level_jabatan' => 'pelaksana',
            'status' => 'active',
            'verification_status' => 'verified',
        ]);
        $file = $this->employeeWorkbook($opd, [
            $this->headers(),
            ['Pegawai Salah NIP', '1.99001E+17', 'PNS', 'Aktif', null, $job->nama, $opd->kode, null, 'Definitif', '2026-01-03', null, null, null, null],
        ]);

        $this->actingAs($admin)->post(route('master.pegawai.import.store'), ['file' => $file])->assertRedirect();

        $batch = ImportBatch::query()->where('module', 'pegawai')->latest()->firstOrFail();
        $this->assertSame(1, $batch->rows()->where('status', 'invalid')->count());
        $this->assertStringContainsString('notasi ilmiah', $batch->rows()->firstOrFail()->error_message);
    }

    public function test_employee_update_preserves_optional_identity_fields_when_cells_are_blank(): void
    {
        $this->seed();
        $opd = Opd::query()->where('status', 'active')->firstOrFail();
        $admin = $this->adminOpd($opd);
        $linkedAccount = User::factory()->create(['opd_id' => $opd->id]);
        $job = JabatanOrganisasi::create([
            'opd_id' => $opd->id,
            'nama' => 'Analis Kebijakan Import',
            'level_jabatan' => 'fungsional',
            'status' => 'active',
            'verification_status' => 'verified',
        ]);
        $employee = Pegawai::create([
            'opd_id' => $opd->id,
            'user_id' => $linkedAccount->id,
            'nama' => 'Nama Pegawai Lama',
            'nip' => '199001012020011001',
            'pangkat_golongan' => 'Penata, III/c',
            'jenis_pegawai' => 'pns',
            'status' => 'active',
        ]);
        RiwayatPejabatJabatan::create([
            'jabatan_organisasi_id' => $job->id,
            'pegawai_id' => $employee->id,
            'user_id' => $linkedAccount->id,
            'nama_pejabat' => $employee->nama,
            'nip' => $employee->nip,
            'pangkat_golongan' => $employee->pangkat_golongan,
            'jenis_penugasan' => 'definitif',
            'tanggal_mulai' => '2026-01-03',
        ]);
        $file = $this->employeeWorkbook($opd, [
            $this->headers(),
            ['Nama Pegawai Diperbarui', '199001012020011001', 'PNS', null, null, $job->nama, $opd->kode, null, 'Definitif', '2026-01-03', null, null, null, null],
        ]);

        $this->actingAs($admin)->post(route('master.pegawai.import.store'), ['file' => $file])->assertRedirect();
        $batch = ImportBatch::query()->where('module', 'pegawai')->latest()->firstOrFail();
        $this->assertSame(1, $batch->rows()->where('status', 'valid')->count(), $batch->rows()->pluck('error_message')->filter()->implode(' | '));

        $this->actingAs($admin)->post(route('master.pegawai.import.apply', $batch))->assertRedirect();

        $employee->refresh();
        $this->assertSame('Nama Pegawai Diperbarui', $employee->nama);
        $this->assertSame($linkedAccount->id, $employee->user_id);
        $this->assertSame('Penata, III/c', $employee->pangkat_golongan);
        $this->assertDatabaseHas('riwayat_pejabat_jabatan', [
            'pegawai_id' => $employee->id,
            'nama_pejabat' => 'Nama Pegawai Diperbarui',
            'user_id' => $linkedAccount->id,
            'pangkat_golongan' => 'Penata, III/c',
        ]);
    }

    private function adminOpd(Opd $opd): User
    {
        $user = User::factory()->create(['opd_id' => $opd->id]);
        $user->roles()->sync([Role::where('name', 'admin_opd')->value('id')]);

        return $user;
    }

    /** @return array<int, string> */
    private function headers(): array
    {
        return ['Nama Pegawai *', 'NIP', 'Jenis Pegawai', 'Status Pegawai', 'Pangkat / Golongan', 'Nama Jabatan *', 'Kode OPD **', 'Kode Unit', 'Jenis Penugasan', 'TMT Jabatan *', 'Tanggal Selesai', 'Nomor SK', 'Tanggal SK', 'Akun Pengguna'];
    }

    /** @param array<int, array<int, string|null>> $rows */
    private function employeeWorkbook(Opd $opd, array $rows): UploadedFile
    {
        $template = app(ImportTemplateService::class)->make('pegawai_opd', ['opd_id' => $opd->id]);
        $path = tempnam(sys_get_temp_dir(), 'pegawai_import_test_');
        file_put_contents($path, $template['content']);

        $zip = new ZipArchive;
        $zip->open($path);
        $zip->addFromString('xl/worksheets/sheet1.xml', $this->worksheetXml($rows));
        $zip->close();

        $content = file_get_contents($path);
        @unlink($path);

        return UploadedFile::fake()->createWithContent('pegawai-opd.xlsx', $content);
    }

    /** @param array<int, array<int, string|null>> $rows */
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
}
