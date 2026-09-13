<?php

namespace App\Services\Master;

use App\Models\ImportBatch;
use App\Models\JabatanOrganisasi;
use App\Models\Opd;
use App\Models\OpdUnit;
use App\Models\Pegawai;
use App\Models\ReferensiJabatan;
use App\Models\RiwayatPejabatJabatan;
use App\Models\User;
use App\Services\Imports\SpreadsheetImportReader;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

class JabatanOrganisasiImportPreviewService
{
    private const MAX_ROWS_PER_SHEET = 2000;

    /** @var array<string, Opd> */
    private array $activeOpdsByCode = [];

    /** @var array<string, OpdUnit> */
    private array $activeUnitsByLocation = [];

    /** @var array<string, ReferensiJabatan> */
    private array $jobReferencesByCode = [];

    /** @var array<string, Collection<int, ReferensiJabatan>> */
    private array $jobReferencesByTypeAndName = [];

    /** @var array<string, Collection<int, JabatanOrganisasi>> */
    private array $jobsByIdentity = [];

    /** @var array<int, int|null> */
    private array $jobParentIds = [];

    /** @var array<string, Collection<int, User>> */
    private array $activeUsersByLogin = [];

    /** @var array<string, Pegawai> */
    private array $employeesByNip = [];

    /** @var array<int, Pegawai> */
    private array $employeesByUserId = [];

    /** @var array<string, Collection<int, Pegawai>> */
    private array $employeesByOpdAndName = [];

    /** @var array<int, Collection<int, RiwayatPejabatJabatan>> */
    private array $historiesByJobId = [];

    public const MODE_COMBINED = 'combined';

    public const MODE_STRUCTURE = 'structure';

    public const MODE_EMPLOYEE = 'employee';

    public function __construct(private readonly SpreadsheetImportReader $reader) {}

    public function storePreview(UploadedFile $file, User $user, string $mode = self::MODE_COMBINED, ?int $scopeOpdId = null): ImportBatch
    {
        if (! in_array($mode, [self::MODE_COMBINED, self::MODE_STRUCTURE, self::MODE_EMPLOYEE], true)) {
            throw new RuntimeException('Mode import tidak dikenali.');
        }

        $disk = config('filesystems.default', 'local');
        $path = $file->store('imports/'.($mode === self::MODE_EMPLOYEE ? 'pegawai-opd' : 'jabatan-organisasi').'/'.now()->format('Y/m'), $disk);

        if (! is_string($path)) {
            throw new RuntimeException('File import gagal disimpan.');
        }

        $batch = ImportBatch::create([
            'module' => $mode === self::MODE_EMPLOYEE ? 'pegawai' : 'jabatan_organisasi',
            'import_type' => match ($mode) {
                self::MODE_STRUCTURE => 'struktur_organisasi',
                self::MODE_EMPLOYEE => 'pegawai_dan_penempatan',
                default => 'jabatan_dan_pejabat',
            },
            'status' => 'processing',
            'original_filename' => $file->getClientOriginalName(),
            'mime_type' => $file->getClientMimeType(),
            'file_size' => $file->getSize() ?: 0,
            'storage_disk' => $disk,
            'storage_path' => $path,
            'uploaded_by' => $user->id,
            'metadata' => [
                'parser' => 'spreadsheet_multi_sheet_preview',
                'mode' => $mode,
                'scope_opd_id' => $scopeOpdId,
                'note' => 'Data hanya disimpan setelah tombol Terapkan Import ditekan.',
            ],
        ]);

        try {
            [$jobSheet, $officialSheet] = $this->resolveSheets($file, $mode);
            $rows = $this->prepareRows($jobSheet, $officialSheet, $scopeOpdId);

            DB::transaction(function () use ($batch, $rows, $jobSheet, $officialSheet): void {
                foreach ($rows as $index => $row) {
                    $batch->rows()->create([
                        'row_number' => $index + 1,
                        'status' => $row['status'],
                        'raw_data' => [
                            'sheet' => $row['entity_type'] === 'jabatan' ? 'Struktur Organisasi' : 'Pegawai OPD',
                            'sheet_row' => $row['sheet_row'],
                            'cells' => array_values($row['cells']),
                        ],
                        'normalized_data' => [
                            'entity_type' => $row['entity_type'],
                            'mapped' => $row['mapped'],
                            'prepared' => $row['prepared'],
                        ],
                        'error_message' => $row['error_message'],
                    ]);
                }

                $summary = $this->summary($rows);
                $batch->update([
                    'status' => 'previewed',
                    'total_rows' => count($rows),
                    'preview_rows' => min(count($rows), 200),
                    'metadata' => [
                        ...($batch->metadata ?? []),
                        'columns' => [
                            'jabatan' => $jobSheet['columns'],
                            'pejabat' => $officialSheet['columns'],
                        ],
                        'preview' => $summary,
                    ],
                ]);
            });
        } catch (Throwable $exception) {
            $batch->update([
                'status' => 'failed',
                'error_message' => $exception->getMessage(),
            ]);
        }

        return $batch->fresh(['uploadedBy:id,name', 'rows']);
    }

    /**
     * @return array{0: array{columns: array<int, string>, rows: array<int, array<int, string|null>>}, 1: array{columns: array<int, string>, rows: array<int, array<int, string|null>>}}
     */
    private function resolveSheets(UploadedFile $file, string $mode): array
    {
        $resolved = [];

        foreach ($this->reader->readWorksheets($file, self::MAX_ROWS_PER_SHEET + 2) as $rows) {
            $columns = array_map(fn (string $column) => $this->canonicalColumn($column), $this->reader->detectColumns($rows));

            if (in_array('nama_jabatan', $columns, true) && in_array('level_jabatan', $columns, true)) {
                if (isset($resolved['jabatan'])) {
                    throw new RuntimeException('Ditemukan lebih dari satu sheet Struktur Organisasi. Sisakan satu sheet data saja.');
                }
                $resolved['jabatan'] = ['columns' => $columns, 'rows' => array_slice($rows, 1)];
            } elseif (in_array('nama_jabatan', $columns, true) && in_array('nama_pejabat', $columns, true)) {
                if (isset($resolved['pejabat'])) {
                    throw new RuntimeException('Ditemukan lebih dari satu sheet Pegawai OPD. Sisakan satu sheet data saja.');
                }
                $resolved['pejabat'] = ['columns' => $columns, 'rows' => array_slice($rows, 1)];
            }
        }

        if ($mode === self::MODE_COMBINED && ! isset($resolved['jabatan'], $resolved['pejabat'])) {
            throw new RuntimeException('Sheet Jabatan dan Pegawai tidak ditemukan. Template lama dengan sheet Pejabat tetap didukung bila kolomnya lengkap.');
        }

        if ($mode === self::MODE_STRUCTURE && ! isset($resolved['jabatan'])) {
            throw new RuntimeException('Sheet Struktur Organisasi tidak ditemukan. Unduh dan gunakan template terbaru dari sistem.');
        }

        if ($mode === self::MODE_EMPLOYEE && ! isset($resolved['pejabat'])) {
            throw new RuntimeException('Sheet Pegawai OPD tidak ditemukan. Unduh dan gunakan template terbaru dari sistem.');
        }

        $emptySheet = ['columns' => [], 'rows' => []];
        $jobSheet = $mode === self::MODE_EMPLOYEE ? $emptySheet : ($resolved['jabatan'] ?? $emptySheet);
        $officialSheet = $mode === self::MODE_STRUCTURE ? $emptySheet : ($resolved['pejabat'] ?? $emptySheet);

        if (count($jobSheet['rows']) > self::MAX_ROWS_PER_SHEET) {
            throw new RuntimeException('Sheet Struktur Organisasi melebihi batas 2.000 baris data. Pisahkan menjadi beberapa file.');
        }

        if (count($officialSheet['rows']) > self::MAX_ROWS_PER_SHEET) {
            throw new RuntimeException('Sheet Pegawai OPD melebihi batas 2.000 baris data. Pisahkan menjadi beberapa file.');
        }

        if ($jobSheet['columns'] !== []) {
            $this->assertColumns($jobSheet['columns'], ['nama_jabatan', 'level_jabatan', 'opd_kode', 'unit_kode', 'atasan_nama_jabatan', 'atasan_opd_kode', 'atasan_unit_kode', 'eselon', 'urutan', 'status'], 'Struktur Organisasi');
        }

        if ($officialSheet['columns'] !== []) {
            $this->assertColumns($officialSheet['columns'], ['nama_jabatan', 'opd_kode', 'unit_kode', 'nama_pejabat', 'nip', 'pangkat_golongan', 'jenis_penugasan', 'nomor_sk', 'tanggal_sk', 'tanggal_selesai', 'akun_pengguna'], 'Pegawai OPD');

            if (! in_array('tmt_jabatan', $officialSheet['columns'], true) && ! in_array('tanggal_mulai', $officialSheet['columns'], true)) {
                throw new RuntimeException('Sheet Pegawai OPD tidak lengkap. Kolom TMT Jabatan tidak ditemukan. Gunakan template terbaru dari sistem.');
            }
        }

        return [$jobSheet, $officialSheet];
    }

    private function canonicalColumn(string $column): string
    {
        return [
            'nama_pegawai' => 'nama_pejabat',
            'kode_opd' => 'opd_kode',
            'kode_unit' => 'unit_kode',
            'nama_jabatan_atasan' => 'atasan_nama_jabatan',
            'kode_opd_atasan' => 'atasan_opd_kode',
            'kode_unit_atasan' => 'atasan_unit_kode',
            'tmt' => 'tmt_jabatan',
        ][$column] ?? $column;
    }

    /**
     * @param  array<int, string>  $columns
     * @param  array<int, string>  $required
     */
    private function assertColumns(array $columns, array $required, string $sheet): void
    {
        $missing = array_values(array_diff($required, $columns));

        if ($missing !== []) {
            throw new RuntimeException("Sheet {$sheet} tidak lengkap. Kolom yang tidak ditemukan: ".implode(', ', $missing).'.');
        }
    }

    /**
     * @param  array{columns: array<int, string>, rows: array<int, array<int, string|null>>}  $jobSheet
     * @param  array{columns: array<int, string>, rows: array<int, array<int, string|null>>}  $officialSheet
     * @return array<int, array<string, mixed>>
     */
    private function prepareRows(array $jobSheet, array $officialSheet, ?int $scopeOpdId = null): array
    {
        $this->primeReferenceCaches();
        $jobRows = [];

        foreach ($jobSheet['rows'] as $index => $cells) {
            $mapped = $this->reader->mapRow($cells, $jobSheet['columns']);
            $row = $this->rowShell('jabatan', $index + 2, $cells, $mapped);

            try {
                $row['prepared'] = $this->prepareJobBase($mapped);
                $this->assertOpdScope($row['prepared']['opd_id'] ?? null, $scopeOpdId);
                $row['status'] = 'pending';
            } catch (Throwable $exception) {
                $row['status'] = 'invalid';
                $row['error_message'] = $exception->getMessage();
            }

            $jobRows[] = $row;
        }

        $jobKeys = collect($jobRows)
            ->filter(fn (array $row) => isset($row['prepared']['identity_key']))
            ->groupBy(fn (array $row) => $row['prepared']['identity_key']);

        foreach ($jobRows as &$row) {
            if ($row['status'] === 'invalid') {
                continue;
            }

            $key = $row['prepared']['identity_key'];
            if (($jobKeys[$key] ?? collect())->count() > 1) {
                $row['status'] = 'invalid';
                $row['error_message'] = 'Jabatan yang sama muncul lebih dari sekali pada sheet Struktur Organisasi.';

                continue;
            }

            try {
                $row['prepared'] = $this->resolveJobParent($row['prepared'], $row['mapped'], $jobKeys);
                $row['status'] = 'valid';
            } catch (Throwable $exception) {
                $row['status'] = 'invalid';
                $row['error_message'] = $exception->getMessage();
            }
        }
        unset($row);

        $resolvedJobKeys = collect($jobRows)
            ->filter(fn (array $row) => isset($row['prepared']['identity_key']))
            ->groupBy(fn (array $row) => $row['prepared']['identity_key']);

        $this->markHierarchyCycles($jobRows, $resolvedJobKeys);
        $this->primeHistoryCache($officialSheet, $resolvedJobKeys);

        $officialRows = [];
        $workbookRanges = [];
        $workbookEmployees = [];

        foreach ($officialSheet['rows'] as $index => $cells) {
            $mapped = $this->reader->mapRow($cells, $officialSheet['columns']);
            $row = $this->rowShell('pejabat', $index + 2, $cells, $mapped);

            try {
                $prepared = $this->prepareOfficial($mapped, $resolvedJobKeys, $scopeOpdId);
                $this->assertOpdScope($prepared['opd_id'] ?? null, $scopeOpdId);
                $this->assertWorkbookPeriodAvailable($prepared, $workbookRanges, $index + 2);
                $this->assertWorkbookEmployeeConsistent($prepared, $workbookEmployees, $index + 2);
                $row['prepared'] = $prepared;
                $row['status'] = 'valid';
                $workbookRanges[$prepared['range_key']][] = [
                    'start' => $prepared['tanggal_mulai'],
                    'end' => $prepared['tanggal_selesai'],
                    'row' => $index + 2,
                ];
                $workbookEmployees[$prepared['employee_identity_key']] = [
                    'name' => $prepared['nama_pejabat'],
                    'nip' => $prepared['nip'],
                    'user_id' => $prepared['user_id'],
                    'row' => $index + 2,
                ];
            } catch (Throwable $exception) {
                $row['status'] = 'invalid';
                $row['error_message'] = $exception->getMessage();
            }

            $officialRows[] = $row;
        }

        return [...$jobRows, ...$officialRows];
    }

    /** @param array<string, string|null> $mapped */
    private function prepareJobBase(array $mapped): array
    {
        $name = $this->limited($this->required($mapped, 'nama_jabatan', 'Nama jabatan'), 255, 'Nama jabatan');
        $level = $this->choice($this->required($mapped, 'level_jabatan', 'Level jabatan'), $this->levelChoices(), 'level jabatan');

        $reference = null;
        if (in_array($level, ['fungsional', 'pelaksana'], true)) {
            $referenceCode = $this->nullable($mapped['kode_referensi_jabatan'] ?? null);
            if ($referenceCode !== null) {
                $reference = $this->jobReferencesByCode[$this->lookupKey($referenceCode)] ?? null;
                if (! $reference || $reference->jenis_jabatan !== $level) {
                    throw new RuntimeException("Kode referensi jabatan '{$referenceCode}' tidak ditemukan, tidak aktif, belum terverifikasi, atau jenisnya tidak sesuai.");
                }
            } else {
                $matches = $this->jobReferencesByTypeAndName[$level.'|'.$this->lookupKey($name)] ?? collect();
                if ($matches->count() > 1) {
                    throw new RuntimeException('Nama referensi jabatan tidak unik. Isi Kode Referensi Jabatan dari sheet Referensi Jabatan Global.');
                }
                $reference = $matches->first();
                if (! $reference) {
                    throw new RuntimeException("Jabatan {$name} belum tersedia sebagai referensi global aktif dan terverifikasi.");
                }
            }

            $name = $reference->nama;
        }

        if ($level === 'kepala_daerah' && ($this->nullable($mapped['opd_kode'] ?? null) !== null || $this->nullable($mapped['unit_kode'] ?? null) !== null)) {
            throw new RuntimeException('Kepala Daerah tidak ditempatkan pada OPD atau unit organisasi.');
        }

        [$opd, $unit] = $this->resolveLocation($mapped['opd_kode'] ?? null, $mapped['unit_kode'] ?? null, $level === 'kepala_daerah');

        $eselon = $this->nullable($mapped['eselon'] ?? null);
        if ($eselon !== null) {
            $eselon = $this->choice($eselon, $this->eselonChoices(), 'eselon');
        }
        if ($reference) {
            $eselon = null;
        }

        $order = $this->nullable($mapped['urutan'] ?? null);
        if ($order !== null && (filter_var($order, FILTER_VALIDATE_INT) === false || (int) $order < 0 || (int) $order > 65535)) {
            throw new RuntimeException('Urutan harus berupa angka 0 sampai 65535.');
        }

        $status = $this->nullable($mapped['status'] ?? null) ?? 'active';
        $status = $this->choice($status, ['active' => 'active', 'aktif' => 'active', 'inactive' => 'inactive', 'nonaktif' => 'inactive'], 'status');
        $key = $this->identityKey($name, $opd?->id, $unit?->id);
        $matches = $this->matchingJobs($name, $opd?->id, $unit?->id);

        if ($matches->count() > 1) {
            throw new RuntimeException('Terdapat lebih dari satu jabatan yang sama di sistem. Rapikan data ganda sebelum import.');
        }

        return [
            'identity_key' => $key,
            'existing_id' => $matches->first()?->id,
            'action' => $matches->isEmpty() ? 'create' : 'update',
            'nama' => $name,
            'referensi_jabatan_id' => $reference?->id,
            'level_jabatan' => $level,
            'opd_id' => $opd?->id,
            'opd_label' => $opd ? "{$opd->kode} - {$opd->nama}" : 'Pemerintah Kabupaten',
            'opd_unit_id' => $unit?->id,
            'unit_label' => $unit ? "{$unit->kode} - {$unit->nama}" : null,
            'eselon' => $eselon,
            'urutan' => $order === null ? 0 : (int) $order,
            'status' => $status,
            'parent_key' => null,
            'parent_existing_id' => null,
            'parent_label' => null,
        ];
    }

    /**
     * @param  array<string, mixed>  $prepared
     * @param  array<string, string|null>  $mapped
     * @param  Collection<string, Collection<int, array<string, mixed>>>  $jobKeys
     * @return array<string, mixed>
     */
    private function resolveJobParent(array $prepared, array $mapped, $jobKeys): array
    {
        if ($prepared['level_jabatan'] === 'kepala_daerah') {
            if ($this->nullable($mapped['atasan_nama_jabatan'] ?? null) !== null) {
                throw new RuntimeException('Kepala Daerah tidak memiliki atasan langsung pada hierarki ini.');
            }

            return $prepared;
        }

        $parentName = $this->required($mapped, 'atasan_nama_jabatan', 'Nama jabatan atasan');
        $parentOpdCode = $this->nullable($mapped['atasan_opd_kode'] ?? null);
        $parentUnitCode = $this->nullable($mapped['atasan_unit_kode'] ?? null);
        $parentIsHead = $parentOpdCode === null;
        [$parentOpd, $parentUnit] = $this->resolveLocation($parentOpdCode, $parentUnitCode, $parentIsHead);
        $parentKey = $this->identityKey($parentName, $parentOpd?->id, $parentUnit?->id);
        $workbookParent = ($jobKeys[$parentKey] ?? collect())->first();
        $parentPrepared = $workbookParent['prepared'] ?? null;
        $parentExisting = $this->matchingJobs($parentName, $parentOpd?->id, $parentUnit?->id);

        if ($parentExisting->count() > 1) {
            throw new RuntimeException('Jabatan atasan tidak unik di sistem. Rapikan data ganda sebelum import.');
        }

        if (! $parentPrepared && $parentExisting->isEmpty()) {
            throw new RuntimeException("Jabatan atasan '{$parentName}' tidak ditemukan pada sheet Struktur Organisasi maupun data sistem.");
        }

        $parentLevel = $parentPrepared['level_jabatan'] ?? $parentExisting->first()->level_jabatan;
        $allowed = [
            'jpt_pratama' => ['kepala_daerah'],
            'administrator' => ['jpt_pratama'],
            'pengawas' => ['jpt_pratama', 'administrator'],
            'fungsional' => ['jpt_pratama', 'administrator', 'pengawas', 'fungsional'],
            'pelaksana' => ['jpt_pratama', 'administrator', 'pengawas', 'fungsional'],
        ];

        if (! in_array($parentLevel, $allowed[$prepared['level_jabatan']] ?? [], true)) {
            throw new RuntimeException('Level jabatan atasan tidak sesuai dengan hierarki jabatan.');
        }

        if ($parentLevel !== 'kepala_daerah' && (int) ($parentPrepared['opd_id'] ?? $parentExisting->first()?->opd_id) !== (int) $prepared['opd_id']) {
            throw new RuntimeException('Atasan langsung harus berada pada perangkat daerah yang sama.');
        }

        if ($parentKey === $prepared['identity_key']) {
            throw new RuntimeException('Jabatan tidak dapat menjadi atasan untuk dirinya sendiri.');
        }

        return [
            ...$prepared,
            'parent_key' => $parentKey,
            'parent_existing_id' => $parentExisting->first()?->id,
            'parent_label' => $parentName,
        ];
    }

    /**
     * @param  array<string, string|null>  $mapped
     * @param  Collection<string, Collection<int, array<string, mixed>>>  $jobKeys
     * @return array<string, mixed>
     */
    private function prepareOfficial(array $mapped, $jobKeys, ?int $scopeOpdId): array
    {
        $jobName = $this->limited($this->required($mapped, 'nama_jabatan', 'Nama jabatan'), 255, 'Nama jabatan');
        $opdCode = $this->nullable($mapped['opd_kode'] ?? null);
        [$opd, $unit] = $this->resolveLocation($opdCode, $mapped['unit_kode'] ?? null, $opdCode === null);
        $jobKey = $this->identityKey($jobName, $opd?->id, $unit?->id);
        $workbookJob = ($jobKeys[$jobKey] ?? collect())->first();
        $existingJobs = $this->matchingJobs($jobName, $opd?->id, $unit?->id);
        $officialName = $this->limited($this->required($mapped, 'nama_pejabat', 'Nama pegawai'), 255, 'Nama pegawai');

        if ($existingJobs->count() > 1) {
            throw new RuntimeException('Jabatan pejabat tidak unik di sistem. Rapikan data ganda sebelum import.');
        }

        if (! $workbookJob && $existingJobs->isEmpty()) {
            throw new RuntimeException("Jabatan '{$jobName}' tidak ditemukan pada sheet Struktur Organisasi maupun data sistem.");
        }

        $resolvedJob = $existingJobs->first();
        if ($resolvedJob && ($resolvedJob->status !== 'active' || $resolvedJob->verification_status === 'rejected')) {
            throw new RuntimeException("Jabatan '{$jobName}' tidak aktif atau berstatus ditolak sehingga tidak dapat menerima penempatan pegawai.");
        }

        if ($workbookJob && ($workbookJob['prepared']['status'] ?? 'active') !== 'active') {
            throw new RuntimeException("Jabatan '{$jobName}' berstatus nonaktif pada sheet Struktur Organisasi.");
        }

        $jobId = $resolvedJob?->id;
        $jobLevel = $resolvedJob?->level_jabatan ?? ($workbookJob['prepared']['level_jabatan'] ?? null);
        $employeeType = $this->nullable($mapped['jenis_pegawai'] ?? null) ?? ($jobLevel === 'kepala_daerah' ? 'pejabat_negara' : 'pns');
        $employeeType = $this->choice($employeeType, $this->employeeTypeChoices(), 'jenis pegawai');
        $officialNip = $this->normalizeNip($mapped['nip'] ?? null);

        if (in_array($employeeType, ['pns', 'pppk'], true) && $officialNip === null) {
            throw new RuntimeException('NIP wajib diisi untuk pegawai berjenis PNS atau PPPK. Gunakan tepat 18 digit.');
        }

        $allowsMultiple = in_array($jobLevel, ['fungsional', 'pelaksana'], true);

        $start = $this->date($this->requiredAny($mapped, ['tmt_jabatan', 'tanggal_mulai'], 'TMT Jabatan'), 'TMT Jabatan');
        $endValue = $this->nullable($mapped['tanggal_selesai'] ?? null);
        $end = $endValue ? $this->date($endValue, 'Tanggal selesai') : null;

        if ($end !== null && $end < $start) {
            throw new RuntimeException('Tanggal selesai tidak boleh lebih awal dari tanggal mulai.');
        }

        $account = null;
        if ($accountValue = $this->nullable($mapped['akun_pengguna'] ?? null)) {
            $accountValue = $this->limited($accountValue, 255, 'Akun pengguna');
            $accounts = $this->activeUsersByLogin[$this->lookupKey($accountValue)] ?? collect();

            if ($accounts->count() !== 1) {
                throw new RuntimeException('Akun pengguna tidak ditemukan, tidak aktif, atau tidak unik.');
            }
            $account = $accounts->first();
        }

        $pegawaiByNip = $officialNip ? ($this->employeesByNip[$officialNip] ?? null) : null;
        $pegawaiByAccount = $account ? ($this->employeesByUserId[$account->id] ?? null) : null;

        if ($pegawaiByNip && $pegawaiByAccount && $pegawaiByNip->id !== $pegawaiByAccount->id) {
            throw new RuntimeException('NIP dan akun pengguna terhubung ke dua data pegawai yang berbeda. Periksa kembali identitas pegawai.');
        }

        $pegawai = $pegawaiByNip ?: $pegawaiByAccount;
        if (! $pegawai && ! $officialNip && ! $account) {
            $sameNames = $this->employeesByOpdAndName[$this->employeeNameKey($opd?->id, $officialName)] ?? collect();

            if ($sameNames->count() > 1) {
                throw new RuntimeException('Nama pegawai tidak unik. Isi NIP atau akun pengguna agar data dapat dicocokkan dengan tepat.');
            }
            $pegawai = $sameNames->first();
        }

        if ($scopeOpdId !== null && $pegawai?->opd_id !== null && (int) $pegawai->opd_id !== $scopeOpdId) {
            throw new RuntimeException('NIP atau akun tersebut sudah terdaftar pada perangkat daerah lain. Hubungi Admin Kabupaten untuk memproses perpindahan pegawai.');
        }

        $pegawaiId = $pegawai?->id;
        $existingHistory = null;

        if ($jobId) {
            $histories = $this->historiesByJobId[$jobId] ?? collect();
            if ($allowsMultiple) {
                $histories = $histories->filter(function (RiwayatPejabatJabatan $history) use ($pegawaiId, $officialName, $officialNip): bool {
                    if ($pegawaiId) {
                        return (int) $history->pegawai_id === (int) $pegawaiId;
                    }

                    if ($officialNip) {
                        return $history->nip === $officialNip;
                    }

                    return $this->lookupKey($history->nama_pejabat) === $this->lookupKey($officialName);
                });
            }

            $existingHistory = $histories->first(
                fn (RiwayatPejabatJabatan $history): bool => $history->tanggal_mulai?->format('Y-m-d') === $start
            );

            if ($existingHistory?->pegawai_id && $pegawaiId && $existingHistory->pegawai_id !== $pegawaiId) {
                throw new RuntimeException('Riwayat pada jabatan dan TMT yang sama sudah terhubung ke pegawai lain. Periksa NIP atau TMT Jabatan.');
            }
            $pegawaiId ??= $existingHistory?->pegawai_id;

            $overlap = $histories->contains(function (RiwayatPejabatJabatan $history) use ($end, $existingHistory, $start): bool {
                if ($existingHistory && $history->is($existingHistory)) {
                    return false;
                }

                $historyStart = $history->tanggal_mulai?->format('Y-m-d') ?? '0000-01-01';
                $historyEnd = $history->tanggal_selesai?->format('Y-m-d') ?? '9999-12-31';

                return $historyStart <= ($end ?? '9999-12-31') && $historyEnd >= $start;
            });

            if ($overlap) {
                throw new RuntimeException('Masa tugas bertumpang tindih dengan riwayat pejabat yang sudah ada.');
            }
        }

        $assignment = $this->nullable($mapped['jenis_penugasan'] ?? null) ?? 'definitif';
        $assignment = $this->choice($assignment, $this->assignmentChoices(), 'jenis penugasan');
        $employeeStatus = $this->nullable($mapped['status_pegawai'] ?? null);
        if ($employeeStatus !== null) {
            $employeeStatus = $this->choice($employeeStatus, ['active' => 'active', 'aktif' => 'active', 'inactive' => 'inactive', 'nonaktif' => 'inactive'], 'status pegawai');
        }
        $placementIsCurrent = $end === null || $end >= now()->toDateString();
        if ($placementIsCurrent && $employeeStatus === 'inactive') {
            throw new RuntimeException('Pegawai berstatus Nonaktif tidak dapat memiliki penempatan yang masih aktif. Isi Tanggal Selesai atau ubah Status Pegawai menjadi Aktif.');
        }
        if ($placementIsCurrent && $employeeStatus === null && $pegawai?->status === 'inactive') {
            throw new RuntimeException('Pegawai ini saat ini berstatus Nonaktif. Pilih Status Pegawai Aktif untuk mengaktifkan kembali dan menambahkan penempatan.');
        }
        $skDateValue = $this->nullable($mapped['tanggal_sk'] ?? null);
        $pangkatGolongan = $this->nullable($mapped['pangkat_golongan'] ?? null);
        $nomorSk = $this->nullable($mapped['nomor_sk'] ?? null);

        if ($pangkatGolongan !== null) {
            $pangkatGolongan = $this->limited($pangkatGolongan, 120, 'Pangkat/golongan');
        }
        if ($nomorSk !== null) {
            $nomorSk = $this->limited($nomorSk, 150, 'Nomor SK');
        }

        $identityKey = $officialNip
            ? 'nip:'.$officialNip
            : ($account ? 'user:'.$account->id : 'name:'.mb_strtolower($officialName).'|opd:'.($opd?->id ?? 0));

        return [
            'jabatan_key' => $jobKey,
            'range_key' => $allowsMultiple ? $jobKey.'|'.mb_strtolower($officialNip ?: $officialName) : $jobKey,
            'jabatan_existing_id' => $jobId,
            'existing_id' => $existingHistory?->id,
            'pegawai_existing_id' => $pegawaiId,
            'action' => $existingHistory ? 'update' : 'create',
            'jabatan_label' => $jobName,
            'opd_id' => $opd?->id,
            'opd_unit_id' => $unit?->id,
            'opd_label' => $opd ? "{$opd->kode} - {$opd->nama}" : 'Pemerintah Kabupaten',
            'unit_label' => $unit ? "{$unit->kode} - {$unit->nama}" : null,
            'employee_identity_key' => $identityKey,
            'user_id' => $account?->id,
            'account_label' => $account ? ($account->username ?: $account->email) : null,
            'nama_pejabat' => $officialName,
            'nip' => $officialNip,
            'pangkat_golongan' => $pangkatGolongan,
            'jenis_pegawai' => $employeeType,
            'status_pegawai' => $employeeStatus,
            'jenis_penugasan' => $assignment,
            'nomor_sk' => $nomorSk,
            'tanggal_sk' => $skDateValue ? $this->date($skDateValue, 'Tanggal SK') : null,
            'tanggal_mulai' => $start,
            'tanggal_selesai' => $end,
        ];
    }

    /**
     * @param  array<string, mixed>  $prepared
     * @param  array<string, array<int, array{start: string, end: string|null, row: int}>>  $ranges
     */
    private function assertWorkbookPeriodAvailable(array $prepared, array $ranges, int $sheetRow): void
    {
        foreach ($ranges[$prepared['range_key']] ?? [] as $range) {
            $overlaps = $prepared['tanggal_mulai'] <= ($range['end'] ?? '9999-12-31')
                && ($prepared['tanggal_selesai'] ?? '9999-12-31') >= $range['start'];

            if ($overlaps) {
                throw new RuntimeException("Masa tugas bertumpang tindih dengan baris {$range['row']} pada sheet Pegawai.");
            }
        }
    }

    /**
     * @param  array<string, mixed>  $prepared
     * @param  array<string, array{name: string, nip: string|null, user_id: int|null, row: int}>  $employees
     */
    private function assertWorkbookEmployeeConsistent(array $prepared, array $employees, int $sheetRow): void
    {
        $existing = $employees[$prepared['employee_identity_key']] ?? null;
        if (! $existing) {
            return;
        }

        $sameName = mb_strtolower(trim($existing['name'])) === mb_strtolower(trim($prepared['nama_pejabat']));
        $sameNip = ($existing['nip'] ?? null) === ($prepared['nip'] ?? null);
        $sameAccount = ($existing['user_id'] ?? null) === ($prepared['user_id'] ?? null);

        if (! $sameName || ! $sameNip || ! $sameAccount) {
            throw new RuntimeException("Identitas pegawai bertentangan dengan baris {$existing['row']} pada sheet Pegawai OPD. Samakan nama, NIP, dan akun untuk pegawai yang sama.");
        }
    }

    private function assertOpdScope(?int $rowOpdId, ?int $scopeOpdId): void
    {
        if ($scopeOpdId !== null && $rowOpdId !== $scopeOpdId) {
            throw new RuntimeException('Data berada di luar perangkat daerah akun Anda. Gunakan kode OPD yang tersedia pada sheet referensi.');
        }
    }

    private function primeReferenceCaches(): void
    {
        $this->activeOpdsByCode = [];
        foreach (Opd::query()->where('status', 'active')->get(['id', 'kode', 'nama']) as $opd) {
            $this->activeOpdsByCode[$this->lookupKey($opd->kode)] = $opd;
        }

        $this->activeUnitsByLocation = [];
        foreach (OpdUnit::query()->where('status', 'active')->get(['id', 'opd_id', 'kode', 'nama']) as $unit) {
            $this->activeUnitsByLocation[$unit->opd_id.'|'.$this->lookupKey($unit->kode)] = $unit;
        }

        $this->jobReferencesByCode = [];
        $this->jobReferencesByTypeAndName = [];
        foreach (ReferensiJabatan::query()
            ->where('status', 'active')
            ->where('verification_status', 'verified')
            ->where(fn ($query) => $query->whereNull('berlaku_mulai')->orWhereDate('berlaku_mulai', '<=', now()->toDateString()))
            ->where(fn ($query) => $query->whereNull('berlaku_sampai')->orWhereDate('berlaku_sampai', '>=', now()->toDateString()))
            ->get(['id', 'kode', 'nama', 'jenis_jabatan']) as $reference) {
            if (filled($reference->kode)) {
                $this->jobReferencesByCode[$this->lookupKey($reference->kode)] = $reference;
            }
            $key = $reference->jenis_jabatan.'|'.$this->lookupKey($reference->nama);
            $this->jobReferencesByTypeAndName[$key] ??= collect();
            $this->jobReferencesByTypeAndName[$key]->push($reference);
        }

        $this->jobsByIdentity = [];
        $this->jobParentIds = [];
        foreach (JabatanOrganisasi::query()->get(['id', 'opd_id', 'opd_unit_id', 'parent_id', 'nama', 'level_jabatan', 'status', 'verification_status']) as $job) {
            $key = $this->identityKey($job->nama, $job->opd_id, $job->opd_unit_id);
            $this->jobsByIdentity[$key] ??= collect();
            $this->jobsByIdentity[$key]->push($job);
            $this->jobParentIds[$job->id] = $job->parent_id;
        }

        $this->activeUsersByLogin = [];
        foreach (User::query()->where('status', 'active')->get(['id', 'username', 'email']) as $user) {
            foreach ([$user->username, $user->email] as $login) {
                if (! filled($login)) {
                    continue;
                }

                $key = $this->lookupKey($login);
                $this->activeUsersByLogin[$key] ??= collect();
                if (! $this->activeUsersByLogin[$key]->contains('id', $user->id)) {
                    $this->activeUsersByLogin[$key]->push($user);
                }
            }
        }

        $this->employeesByNip = [];
        $this->employeesByUserId = [];
        $this->employeesByOpdAndName = [];
        foreach (Pegawai::query()->get(['id', 'opd_id', 'opd_unit_id', 'user_id', 'nama', 'nip', 'pangkat_golongan', 'jenis_pegawai', 'status']) as $employee) {
            if (filled($employee->nip)) {
                $this->employeesByNip[$employee->nip] = $employee;
            }
            if ($employee->user_id) {
                $this->employeesByUserId[$employee->user_id] = $employee;
            }

            $nameKey = $this->employeeNameKey($employee->opd_id, $employee->nama);
            $this->employeesByOpdAndName[$nameKey] ??= collect();
            $this->employeesByOpdAndName[$nameKey]->push($employee);
        }

        $this->historiesByJobId = [];
    }

    /**
     * @param  array{columns: array<int, string>, rows: array<int, array<int, string|null>>}  $officialSheet
     * @param  Collection<string, Collection<int, array<string, mixed>>>  $jobKeys
     */
    private function primeHistoryCache(array $officialSheet, $jobKeys): void
    {
        $jobIds = [];

        foreach ($officialSheet['rows'] as $cells) {
            $mapped = $this->reader->mapRow($cells, $officialSheet['columns']);
            $jobName = $this->nullable($mapped['nama_jabatan'] ?? null);
            if ($jobName === null) {
                continue;
            }

            try {
                $opdCode = $this->nullable($mapped['opd_kode'] ?? null);
                [$opd, $unit] = $this->resolveLocation($opdCode, $mapped['unit_kode'] ?? null, $opdCode === null);
                $jobKey = $this->identityKey($jobName, $opd?->id, $unit?->id);

                foreach ($this->matchingJobs($jobName, $opd?->id, $unit?->id) as $job) {
                    $jobIds[$job->id] = $job->id;
                }

                $workbookJob = ($jobKeys[$jobKey] ?? collect())->first();
                if ($existingId = data_get($workbookJob, 'prepared.existing_id')) {
                    $jobIds[$existingId] = $existingId;
                }
            } catch (Throwable) {
                // Baris tetap akan menghasilkan pesan validasi lengkap saat diproses.
            }
        }

        if ($jobIds === []) {
            return;
        }

        foreach (RiwayatPejabatJabatan::query()->whereIn('jabatan_organisasi_id', array_values($jobIds))->get() as $history) {
            $this->historiesByJobId[$history->jabatan_organisasi_id] ??= collect();
            $this->historiesByJobId[$history->jabatan_organisasi_id]->push($history);
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @param  Collection<string, Collection<int, array<string, mixed>>>  $jobKeys
     */
    private function markHierarchyCycles(array &$rows, $jobKeys): void
    {
        foreach ($rows as &$row) {
            if ($row['status'] !== 'valid') {
                continue;
            }

            $visited = [];
            $cursor = $row['prepared']['identity_key'];
            $databaseCursorId = null;

            while ($cursor && isset($jobKeys[$cursor])) {
                if (isset($visited[$cursor])) {
                    $row['status'] = 'invalid';
                    $row['error_message'] = 'Hierarki atasan membentuk siklus pada sheet Struktur Organisasi.';
                    break;
                }

                $visited[$cursor] = true;
                $cursorRow = $jobKeys[$cursor]->first();
                $parentKey = $cursorRow['prepared']['parent_key'] ?? null;

                if ($parentKey && isset($jobKeys[$parentKey])) {
                    $cursor = $parentKey;
                } else {
                    $cursor = null;
                    $databaseCursorId = $cursorRow['prepared']['parent_existing_id'] ?? null;
                }
            }

            $visitedIds = [];
            while ($row['status'] === 'valid' && $databaseCursorId) {
                if ((int) $databaseCursorId === (int) ($row['prepared']['existing_id'] ?? 0)) {
                    $row['status'] = 'invalid';
                    $row['error_message'] = 'Atasan yang dipilih akan membentuk siklus dengan hierarki yang sudah ada.';
                    break;
                }

                if (isset($visitedIds[$databaseCursorId])) {
                    break;
                }

                $visitedIds[$databaseCursorId] = true;
                $databaseCursorId = $this->jobParentIds[(int) $databaseCursorId] ?? null;
            }
        }
        unset($row);
    }

    /** @return array{0: Opd|null, 1: OpdUnit|null} */
    private function resolveLocation(mixed $opdCode, mixed $unitCode, bool $allowEmptyOpd): array
    {
        $opdCode = $this->nullable($opdCode);
        $unitCode = $this->nullable($unitCode);

        if ($opdCode === null) {
            if (! $allowEmptyOpd) {
                throw new RuntimeException('Kode OPD wajib diisi.');
            }
            if ($unitCode !== null) {
                throw new RuntimeException('Kode unit tidak boleh diisi tanpa kode OPD.');
            }

            return [null, null];
        }

        $opd = $this->activeOpdsByCode[$this->lookupKey($opdCode)] ?? null;
        if (! $opd) {
            throw new RuntimeException("OPD dengan kode '{$opdCode}' tidak ditemukan atau tidak aktif.");
        }

        $unit = null;
        if ($unitCode !== null) {
            $unit = $this->activeUnitsByLocation[$opd->id.'|'.$this->lookupKey($unitCode)] ?? null;
            if (! $unit) {
                throw new RuntimeException("Unit '{$unitCode}' tidak ditemukan atau tidak aktif pada OPD {$opd->nama}.");
            }
        }

        return [$opd, $unit];
    }

    private function matchingJobs(string $name, ?int $opdId, ?int $unitId)
    {
        return $this->jobsByIdentity[$this->identityKey($name, $opdId, $unitId)] ?? collect();
    }

    private function lookupKey(mixed $value): string
    {
        return mb_strtolower(trim((string) $value));
    }

    private function employeeNameKey(?int $opdId, string $name): string
    {
        return ($opdId ?? 0).'|'.$this->lookupKey($name);
    }

    /** @param array<string, string|null> $mapped */
    private function required(array $mapped, string $key, string $label): string
    {
        return $this->nullable($mapped[$key] ?? null) ?? throw new RuntimeException("{$label} wajib diisi.");
    }

    /**
     * @param  array<string, string|null>  $mapped
     * @param  array<int, string>  $keys
     */
    private function requiredAny(array $mapped, array $keys, string $label): string
    {
        foreach ($keys as $key) {
            if (($value = $this->nullable($mapped[$key] ?? null)) !== null) {
                return $value;
            }
        }

        throw new RuntimeException("{$label} wajib diisi.");
    }

    private function nullable(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));

        return $value === '' || $value === '-' ? null : $value;
    }

    private function normalizeNip(mixed $value): ?string
    {
        $nip = $this->nullable($value);
        if ($nip === null) {
            return null;
        }

        if (preg_match('/[eE][+-]?\d+/', $nip) === 1) {
            throw new RuntimeException('NIP terbaca sebagai notasi ilmiah dan berisiko berubah. Gunakan kolom NIP pada template terbaru yang sudah berformat teks.');
        }

        $nip = preg_replace('/[\s.\-]+/', '', $nip) ?? $nip;
        if (preg_match('/^\d{18}$/', $nip) !== 1) {
            throw new RuntimeException('NIP harus terdiri dari tepat 18 digit. Kosongkan hanya jika pegawai memang tidak memiliki NIP.');
        }

        return $nip;
    }

    private function limited(string $value, int $maximum, string $label): string
    {
        if (mb_strlen($value) > $maximum) {
            throw new RuntimeException("{$label} maksimal {$maximum} karakter.");
        }

        return $value;
    }

    /** @param array<string, string> $choices */
    private function choice(string $value, array $choices, string $label): string
    {
        $normalized = mb_strtolower(trim($value));

        return $choices[$normalized] ?? throw new RuntimeException("Nilai {$label} '{$value}' tidak dikenali.");
    }

    /** @return array<string, string> */
    private function levelChoices(): array
    {
        $choices = [];
        foreach (JabatanOrganisasi::levelOptions() as $option) {
            $choices[mb_strtolower($option['value'])] = $option['value'];
            $choices[mb_strtolower($option['label'])] = $option['value'];
        }
        $choices['jpt pratama'] = 'jpt_pratama';

        return $choices;
    }

    /** @return array<string, string> */
    private function eselonChoices(): array
    {
        $choices = [];
        foreach (JabatanOrganisasi::eselonOptions() as $option) {
            $choices[mb_strtolower($option['value'])] = $option['value'];
            $choices[mb_strtolower($option['label'])] = $option['value'];
            $choices[str_replace('.', '_', mb_strtolower(str_replace('Eselon ', '', $option['label'])))] = $option['value'];
        }

        return $choices;
    }

    /** @return array<string, string> */
    private function assignmentChoices(): array
    {
        return [
            'definitif' => 'definitif',
            'penjabat' => 'penjabat',
            'penjabat (pj.)' => 'penjabat',
            'pj' => 'penjabat',
            'pj.' => 'penjabat',
            'plt' => 'plt',
            'plt.' => 'plt',
            'pelaksana tugas' => 'plt',
            'pelaksana tugas (plt.)' => 'plt',
            'plh' => 'plh',
            'plh.' => 'plh',
            'pelaksana harian' => 'plh',
            'pelaksana harian (plh.)' => 'plh',
        ];
    }

    /** @return array<string, string> */
    private function employeeTypeChoices(): array
    {
        return [
            'pejabat_negara' => 'pejabat_negara',
            'pejabat negara' => 'pejabat_negara',
            'pns' => 'pns',
            'pppk' => 'pppk',
            'non_asn' => 'non_asn',
            'non-asn' => 'non_asn',
            'non asn' => 'non_asn',
        ];
    }

    private function date(string $value, string $label): string
    {
        if (is_numeric($value) && (float) $value > 0) {
            return Carbon::create(1899, 12, 30)->addDays((int) floor((float) $value))->format('Y-m-d');
        }

        foreach (['Y-m-d', 'd/m/Y', 'd-m-Y'] as $format) {
            try {
                $date = Carbon::createFromFormat($format, $value);
                if ($date && $date->format($format) === $value) {
                    return $date->format('Y-m-d');
                }
            } catch (Throwable) {
                // Coba format berikutnya.
            }
        }

        throw new RuntimeException("{$label} harus berformat YYYY-MM-DD atau DD/MM/YYYY.");
    }

    private function identityKey(string $name, ?int $opdId, ?int $unitId): string
    {
        $name = preg_replace('/\s+/u', ' ', mb_strtolower(trim($name))) ?? mb_strtolower(trim($name));

        return $name.'|'.($opdId ?? 0).'|'.($unitId ?? 0);
    }

    /**
     * @param  array<int, string|null>  $cells
     * @param  array<string, string|null>  $mapped
     * @return array<string, mixed>
     */
    private function rowShell(string $entityType, int $sheetRow, array $cells, array $mapped): array
    {
        return [
            'entity_type' => $entityType,
            'sheet_row' => $sheetRow,
            'cells' => $cells,
            'mapped' => $mapped,
            'prepared' => null,
            'status' => 'invalid',
            'error_message' => null,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<string, int>
     */
    private function summary(array $rows): array
    {
        $summary = [
            'total_rows' => count($rows),
            'valid_rows' => 0,
            'invalid_rows' => 0,
            'jabatan_rows' => 0,
            'pejabat_rows' => 0,
            'create_rows' => 0,
            'update_rows' => 0,
        ];

        foreach ($rows as $row) {
            $summary[$row['status'] === 'valid' ? 'valid_rows' : 'invalid_rows']++;
            $summary[$row['entity_type'].'_rows']++;
            if ($row['status'] === 'valid' && isset($row['prepared']['action'])) {
                $summary[$row['prepared']['action'].'_rows']++;
            }
        }

        return $summary;
    }
}
