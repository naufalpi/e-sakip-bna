<?php

namespace App\Services\Perencanaan;

use App\Models\ImportBatch;
use App\Models\Opd;
use App\Models\ProgramRpjmd;
use App\Models\Rkpd;
use App\Models\SubKegiatanPemerintahan;
use App\Models\User;
use App\Services\Imports\ImportColumnValidationService;
use App\Services\Imports\SpreadsheetImportReader;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

class RkpdItemImportPreviewService
{
    private const MAX_ROWS = 1000;

    public function __construct(
        private readonly SpreadsheetImportReader $reader,
        private readonly ImportColumnValidationService $columnValidator,
    ) {}

    public function storePreview(UploadedFile $file, Rkpd $rkpd, User $user): ImportBatch
    {
        $disk = config('filesystems.default', 'local');
        $path = $file->store('imports/rkpd/'.now()->format('Y/m'), $disk);

        if (! is_string($path)) {
            throw new RuntimeException('File import gagal disimpan.');
        }

        $batch = ImportBatch::create([
            'module' => 'rkpd',
            'import_type' => 'rkpd_items',
            'status' => 'processing',
            'original_filename' => $file->getClientOriginalName(),
            'mime_type' => $file->getClientMimeType(),
            'file_size' => $file->getSize() ?: 0,
            'storage_disk' => $disk,
            'storage_path' => $path,
            'uploaded_by' => $user->id,
            'metadata' => [
                'rkpd_id' => $rkpd->id,
                'parser' => 'spreadsheet_preview',
                'max_rows' => self::MAX_ROWS,
                'note' => 'Preview import baris RKPD. Data hanya disimpan setelah tombol Terapkan Import ditekan.',
            ],
        ]);

        try {
            $rows = $this->reader->readRows($file, self::MAX_ROWS);
            $columns = $this->reader->detectColumns($rows);
            $columnValidation = $this->columnValidator->validate('rkpd', $columns);
            $summary = ['valid_rows' => 0, 'invalid_rows' => 0, 'skipped_rows' => 0];
            $seenRows = [];

            DB::transaction(function () use ($batch, $rows, $columns, $columnValidation, $rkpd, &$summary, &$seenRows) {
                foreach ($rows as $index => $row) {
                    $isHeader = $index === 0;
                    $mapped = $this->reader->mapRow($row, $columns);
                    $status = 'skipped';
                    $error = null;
                    $prepared = null;

                    if ($isHeader) {
                        $summary['skipped_rows']++;
                    } else {
                        try {
                            $prepared = $this->prepareRow($mapped, $rkpd);
                            $key = $prepared['opd_id'].'/'.$prepared['sub_kegiatan_pemerintahan_id'];

                            if (isset($seenRows[$key])) {
                                throw new RuntimeException("Sub kegiatan ini sudah tercantum pada baris {$seenRows[$key]} untuk OPD yang sama.");
                            }

                            $seenRows[$key] = $index + 1;
                            $status = 'valid';
                            $summary['valid_rows']++;
                        } catch (Throwable $exception) {
                            $status = 'invalid';
                            $error = $exception->getMessage();
                            $summary['invalid_rows']++;
                        }
                    }

                    $batch->rows()->create([
                        'row_number' => $index + 1,
                        'status' => $status,
                        'raw_data' => ['cells' => array_values($row)],
                        'normalized_data' => [
                            'is_header' => $isHeader,
                            'mapped' => $mapped,
                            'prepared' => $prepared,
                        ],
                        'error_message' => $error,
                    ]);
                }

                $batch->update([
                    'status' => 'previewed',
                    'total_rows' => count($rows),
                    'preview_rows' => min(count($rows), 100),
                    'metadata' => [
                        ...($batch->metadata ?? []),
                        'columns' => $columns,
                        'column_validation' => $columnValidation,
                        'preview' => $summary,
                    ],
                ]);
            });
        } catch (Throwable $exception) {
            $batch->update(['status' => 'failed', 'error_message' => $exception->getMessage()]);
        }

        return $batch->fresh(['uploadedBy:id,name', 'rows']);
    }

    /**
     * @param  array<string, string|null>  $mapped
     * @return array<string, mixed>
     */
    private function prepareRow(array $mapped, Rkpd $rkpd): array
    {
        $opd = Opd::query()
            ->where('status', 'active')
            ->when($this->value($mapped, ['opd_id']), fn ($query, $id) => $query->whereKey($id), fn ($query) => $query->where('kode', $this->required($mapped, ['opd_kode', 'kode_opd'], 'Kode OPD')))
            ->first();

        if (! $opd) {
            throw new RuntimeException('OPD tidak ditemukan atau tidak aktif.');
        }

        $subKegiatan = SubKegiatanPemerintahan::query()
            ->with('kegiatanPemerintahan.programPemerintahan.bidangUrusan.urusanPemerintahan')
            ->where('periode_tahun_id', $rkpd->periode_tahun_id)
            ->where('status', 'active')
            ->when($this->value($mapped, ['sub_kegiatan_id']), fn ($query, $id) => $query->whereKey($id), fn ($query) => $query->where('kode', $this->required($mapped, ['sub_kegiatan_kode', 'kode_sub_kegiatan'], 'Kode sub kegiatan')))
            ->first();

        if (! $subKegiatan) {
            throw new RuntimeException('Sub kegiatan tidak ditemukan atau tidak tersedia pada periode RKPD.');
        }

        if ($rkpd->items()
            ->where('opd_id', $opd->id)
            ->where('sub_kegiatan_pemerintahan_id', $subKegiatan->id)
            ->exists()) {
            throw new RuntimeException("Sub kegiatan {$subKegiatan->kode} sudah ada pada RKPD untuk OPD {$opd->nama}.");
        }

        $programRpjmd = null;
        if ($this->value($mapped, ['program_rpjmd_id', 'program_rpjmd_kode', 'kode_program_rpjmd'])) {
            $programRpjmd = ProgramRpjmd::query()
                ->when($rkpd->rpjmd_id, fn ($query) => $query->forRpjmd($rkpd->rpjmd_id))
                ->when($this->value($mapped, ['program_rpjmd_id']), fn ($query, $id) => $query->whereKey($id), fn ($query) => $query->where('kode', $this->value($mapped, ['program_rpjmd_kode', 'kode_program_rpjmd'])))
                ->first();

            if (! $programRpjmd) {
                throw new RuntimeException('Program RPJMD tidak ditemukan pada RPJMD RKPD ini.');
            }
        }

        $kegiatan = $subKegiatan->kegiatanPemerintahan;
        $program = $kegiatan?->programPemerintahan;
        $bidang = $program?->bidangUrusan;
        $urusan = $bidang?->urusanPemerintahan;

        return [
            'opd_id' => $opd->id,
            'urusan_pemerintahan_id' => $urusan?->id,
            'bidang_urusan_id' => $bidang?->id,
            'program_pemerintahan_id' => $program?->id,
            'kegiatan_pemerintahan_id' => $kegiatan?->id,
            'sub_kegiatan_pemerintahan_id' => $subKegiatan->id,
            'program_rpjmd_id' => $programRpjmd?->id,
            'kode' => $subKegiatan->kode,
            'nama_urusan_bidang_program_kegiatan_sub' => $subKegiatan->nama,
            'indikator' => $this->value($mapped, ['indikator']),
            'target_akhir_renstra' => $this->value($mapped, ['target_akhir_renstra']),
            'realisasi_capaian_renja_tahun_lalu' => $this->value($mapped, ['realisasi_capaian_renja_tahun_lalu']),
            'prakiraan_capaian_target_renja_tahun_berjalan' => $this->value($mapped, ['prakiraan_capaian_target_renja_tahun_berjalan']),
            'target' => $this->value($mapped, ['target']),
            'pagu_indikatif' => $this->currency($this->value($mapped, ['pagu_indikatif'])),
            'lokasi' => $this->value($mapped, ['lokasi']),
            'sumber_dana' => $this->value($mapped, ['sumber_dana']),
            'prioritas_nasional' => $this->value($mapped, ['prioritas_nasional']),
            'prioritas_daerah' => $this->value($mapped, ['prioritas_daerah']),
            'kelompok_sasaran' => $this->value($mapped, ['kelompok_sasaran']),
            'prakiraan_maju_target' => $this->value($mapped, ['prakiraan_maju_target']),
            'prakiraan_maju_pagu_indikatif' => $this->currency($this->value($mapped, ['prakiraan_maju_pagu_indikatif'])),
            'perangkat_daerah_penanggung_jawab' => $this->value($mapped, ['perangkat_daerah_penanggung_jawab']) ?: $opd->nama,
            'urutan' => $this->integer($this->value($mapped, ['urutan'])),
        ];
    }

    /** @param array<string, string|null> $mapped */
    private function required(array $mapped, array $keys, string $label): string
    {
        return $this->value($mapped, $keys) ?: throw new RuntimeException("{$label} wajib diisi.");
    }

    /** @param array<string, string|null> $mapped */
    private function value(array $mapped, array $keys): ?string
    {
        foreach ($keys as $key) {
            $value = trim((string) ($mapped[$key] ?? ''));
            if ($value !== '') return $value;
        }
        return null;
    }

    private function currency(?string $value): ?string
    {
        if ($value === null) return null;
        $normalized = str_replace(' ', '', $value);
        if (str_contains($normalized, ',') && str_contains($normalized, '.')) $normalized = str_replace(',', '.', str_replace('.', '', $normalized));
        elseif (str_contains($normalized, ',')) $normalized = str_replace(',', '.', $normalized);
        elseif (preg_match('/^\d{1,3}(\.\d{3})+$/', $normalized) === 1) $normalized = str_replace('.', '', $normalized);
        if (! is_numeric($normalized) || (float) $normalized < 0) throw new RuntimeException('Nilai pagu harus berupa angka nol atau lebih.');
        return $normalized;
    }

    private function integer(?string $value): ?int
    {
        if ($value === null) return null;
        if (filter_var($value, FILTER_VALIDATE_INT) === false || (int) $value < 1 || (int) $value > 9999) throw new RuntimeException('Urutan harus berupa angka 1 sampai 9999.');
        return (int) $value;
    }
}
