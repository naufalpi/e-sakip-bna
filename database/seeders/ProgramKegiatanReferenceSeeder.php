<?php

namespace Database\Seeders;

use App\Models\BidangUrusan;
use App\Models\KegiatanPemerintahan;
use App\Models\PeriodeTahun;
use App\Models\ProgramPemerintahan;
use App\Models\SatuanIndikator;
use App\Models\SubKegiatanPemerintahan;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ProgramKegiatanReferenceSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            $periodeId = $this->periodeTahunId();
            $rpjmdRange = $this->rpjmdRange();
            $programIds = $this->seedProgram($rpjmdRange);
            $kegiatanIds = $this->seedKegiatan($periodeId, $programIds);
            $this->seedSubKegiatan($periodeId, $kegiatanIds);
        });
    }

    /**
     * @param  array{tahun_awal: int, tahun_akhir: int}  $rpjmdRange
     * @return array<string, int>
     */
    private function seedProgram(array $rpjmdRange): array
    {
        $bidangIds = BidangUrusan::query()->pluck('id', 'kode');
        $programIds = [];

        foreach ($this->readTsv('program_pemerintahan.tsv') as $row) {
            $bidangKode = $this->prefixKode($row['kode'], 2);
            $bidangId = $bidangIds[$bidangKode] ?? null;

            if (! $bidangId) {
                throw new RuntimeException("Bidang urusan {$bidangKode} untuk program {$row['kode']} belum tersedia.");
            }

            $program = ProgramPemerintahan::query()->updateOrCreate(
                [
                    'tahun_awal' => $rpjmdRange['tahun_awal'],
                    'tahun_akhir' => $rpjmdRange['tahun_akhir'],
                    'bidang_urusan_id' => $bidangId,
                    'kode' => $row['kode'],
                ],
                [
                    'nama' => $row['nama'],
                    'status' => 'active',
                ],
            );

            $programIds[$program->kode] = $program->id;
        }

        return $programIds;
    }

    /**
     * @param  array<string, int>  $programIds
     * @return array<string, int>
     */
    private function seedKegiatan(int $periodeId, array $programIds): array
    {
        $kegiatanIds = [];

        foreach ($this->readTsv('kegiatan_pemerintahan.tsv') as $row) {
            $programKode = $this->prefixKode($row['kode'], 3);
            $programId = $programIds[$programKode] ?? null;

            if (! $programId) {
                throw new RuntimeException("Program {$programKode} untuk kegiatan {$row['kode']} belum tersedia.");
            }

            $kegiatan = KegiatanPemerintahan::query()->updateOrCreate(
                [
                    'periode_tahun_id' => $periodeId,
                    'program_pemerintahan_id' => $programId,
                    'kode' => $row['kode'],
                ],
                [
                    'nama' => $row['nama'],
                    'status' => 'active',
                ],
            );

            $kegiatanIds[$kegiatan->kode] = $kegiatan->id;
        }

        return $kegiatanIds;
    }

    /**
     * @param  array<string, int>  $kegiatanIds
     */
    private function seedSubKegiatan(int $periodeId, array $kegiatanIds): void
    {
        $satuanIds = SatuanIndikator::query()
            ->pluck('id', 'nama')
            ->mapWithKeys(fn (int $id, string $nama) => [mb_strtolower($nama) => $id])
            ->all();

        foreach ($this->readTsv('sub_kegiatan_pemerintahan.tsv') as $row) {
            $kegiatanKode = $this->prefixKode($row['kode'], 5);
            $kegiatanId = $kegiatanIds[$kegiatanKode] ?? null;

            if (! $kegiatanId) {
                throw new RuntimeException("Kegiatan {$kegiatanKode} untuk sub kegiatan {$row['kode']} belum tersedia.");
            }

            SubKegiatanPemerintahan::query()->updateOrCreate(
                [
                    'periode_tahun_id' => $periodeId,
                    'kegiatan_pemerintahan_id' => $kegiatanId,
                    'kode' => $row['kode'],
                ],
                [
                    'nama' => $row['nama'],
                    'sasaran_sub_kegiatan' => $row['sasaran_sub_kegiatan'] ?? null,
                    'indikator_sub_kegiatan' => $row['indikator_sub_kegiatan'] ?? null,
                    'satuan_indikator_id' => $this->satuanIndikatorId($row['satuan'] ?? null, $satuanIds),
                    'definisi_operasional' => $row['definisi_operasional'] ?? null,
                    'status' => 'active',
                ],
            );
        }
    }

    private function periodeTahunId(): int
    {
        $periode = PeriodeTahun::query()
            ->where('status', 'active')
            ->orderByDesc('tahun')
            ->first(['id'])
            ?? PeriodeTahun::query()->orderByDesc('tahun')->first(['id']);

        if (! $periode) {
            throw new RuntimeException('Periode tahun belum tersedia untuk seeder program/kegiatan.');
        }

        return (int) $periode->id;
    }

    /**
     * @return array{tahun_awal: int, tahun_akhir: int}
     */
    private function rpjmdRange(): array
    {
        $rpjmd = DB::table('rpjmd')
            ->where('tahun_awal', 2025)
            ->where('tahun_akhir', 2029)
            ->first(['tahun_awal', 'tahun_akhir'])
            ?? DB::table('rpjmd')
                ->orderByDesc('tahun_awal')
                ->first(['tahun_awal', 'tahun_akhir']);

        if ($rpjmd) {
            return [
                'tahun_awal' => (int) $rpjmd->tahun_awal,
                'tahun_akhir' => (int) $rpjmd->tahun_akhir,
            ];
        }

        $tahun = PeriodeTahun::query()
            ->where('status', 'active')
            ->orderByDesc('tahun')
            ->value('tahun')
            ?? PeriodeTahun::query()->orderByDesc('tahun')->value('tahun');

        if (! $tahun) {
            throw new RuntimeException('Periode RPJMD belum bisa ditentukan untuk seeder program.');
        }

        return [
            'tahun_awal' => (int) $tahun,
            'tahun_akhir' => (int) $tahun + 4,
        ];
    }

    /**
     * @return array<int, array<string, string|null>>
     */
    private function readTsv(string $filename): array
    {
        $path = database_path("seeders/data/{$filename}");

        if (! is_file($path)) {
            throw new RuntimeException("File data {$filename} tidak ditemukan.");
        }

        $handle = fopen($path, 'r');

        if ($handle === false) {
            throw new RuntimeException("File data {$filename} tidak bisa dibaca.");
        }

        $rows = [];

        try {
            while (($columns = fgetcsv($handle, null, "\t", '"', '\\')) !== false) {
                $kode = trim((string) ($columns[0] ?? ''));
                $nama = $this->normalizeName(implode(' ', array_slice($columns, 1)));

                if ($kode === '' && $nama === '') {
                    continue;
                }

                if ($kode === '' || $nama === '') {
                    throw new RuntimeException("Baris data {$filename} tidak valid: ".json_encode($columns));
                }

                $rows[] = $filename === 'sub_kegiatan_pemerintahan.tsv'
                    ? $this->subKegiatanRow($columns)
                    : [
                        'kode' => $kode,
                        'nama' => $nama,
                    ];
            }
        } finally {
            fclose($handle);
        }

        return $rows;
    }

    /**
     * @param  array<int, string|null>  $columns
     * @return array<string, string|null>
     */
    private function subKegiatanRow(array $columns): array
    {
        $columns = array_pad($columns, 6, '');

        return [
            'kode' => trim((string) $columns[0]),
            'nama' => $this->normalizeName((string) $columns[1]),
            'sasaran_sub_kegiatan' => $this->nullableName((string) $columns[2]),
            'indikator_sub_kegiatan' => $this->nullableName((string) $columns[3]),
            'satuan' => $this->nullableName((string) $columns[4]),
            'definisi_operasional' => $this->nullableText((string) $columns[5]),
        ];
    }

    private function prefixKode(string $kode, int $segments): string
    {
        $parts = explode('.', $kode);

        if (count($parts) < $segments) {
            throw new RuntimeException("Kode {$kode} tidak memiliki {$segments} segmen.");
        }

        return implode('.', array_slice($parts, 0, $segments));
    }

    private function normalizeName(string $name): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', $name));
    }

    private function nullableName(string $name): ?string
    {
        $name = $this->normalizeName($name);

        return $name === '' ? null : $name;
    }

    private function nullableText(string $text): ?string
    {
        $text = trim(str_replace(["\r\n", "\r"], "\n", $text));

        return $text === '' ? null : $text;
    }

    /**
     * @param  array<string, int>  $satuanIds
     */
    private function satuanIndikatorId(?string $satuan, array &$satuanIds): ?int
    {
        $nama = $this->normalizeSatuanName($satuan);

        if ($nama === null) {
            return null;
        }

        $key = mb_strtolower($nama);

        if (isset($satuanIds[$key])) {
            return (int) $satuanIds[$key];
        }

        $satuanIndikator = SatuanIndikator::query()->firstOrCreate(
            ['nama' => $nama],
            [
                'simbol' => mb_strlen($nama) <= 30 ? $nama : null,
                'jenis' => 'jumlah',
                'status' => 'active',
            ],
        );

        $satuanIds[$key] = (int) $satuanIndikator->id;

        return (int) $satuanIndikator->id;
    }

    private function normalizeSatuanName(?string $satuan): ?string
    {
        $satuan = $this->nullableName((string) $satuan);

        if ($satuan === null) {
            return null;
        }

        return match (mb_strtolower($satuan)) {
            '%', 'persen', 'persentase' => 'Persen',
            'rp', 'rupiah' => 'Rupiah',
            default => $satuan,
        };
    }
}
