<?php

namespace Database\Seeders;

use App\Models\BidangUrusan;
use App\Models\KegiatanPemerintahan;
use App\Models\ProgramPemerintahan;
use App\Models\SubKegiatanPemerintahan;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ProgramKegiatanReferenceSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            $programIds = $this->seedProgram();
            $kegiatanIds = $this->seedKegiatan($programIds);
            $this->seedSubKegiatan($kegiatanIds);
        });
    }

    /**
     * @return array<string, int>
     */
    private function seedProgram(): array
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
    private function seedKegiatan(array $programIds): array
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
    private function seedSubKegiatan(array $kegiatanIds): void
    {
        foreach ($this->readTsv('sub_kegiatan_pemerintahan.tsv') as $row) {
            $kegiatanKode = $this->prefixKode($row['kode'], 5);
            $kegiatanId = $kegiatanIds[$kegiatanKode] ?? null;

            if (! $kegiatanId) {
                throw new RuntimeException("Kegiatan {$kegiatanKode} untuk sub kegiatan {$row['kode']} belum tersedia.");
            }

            SubKegiatanPemerintahan::query()->updateOrCreate(
                [
                    'kegiatan_pemerintahan_id' => $kegiatanId,
                    'kode' => $row['kode'],
                ],
                [
                    'nama' => $row['nama'],
                    'status' => 'active',
                ],
            );
        }
    }

    /**
     * @return array<int, array{kode: string, nama: string}>
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

                $rows[] = [
                    'kode' => $kode,
                    'nama' => $nama,
                ];
            }
        } finally {
            fclose($handle);
        }

        return $rows;
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
}
