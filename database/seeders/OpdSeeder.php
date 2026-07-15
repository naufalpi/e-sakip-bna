<?php

namespace Database\Seeders;

use App\Models\Opd;
use App\Models\OpdUnit;
use App\Models\UrusanPemerintahan;
use Illuminate\Database\Seeder;

class OpdSeeder extends Seeder
{
    public function run(): void
    {
        $urusanByKode = UrusanPemerintahan::query()
            ->get(['id', 'kode'])
            ->keyBy('kode');

        $opdsByKode = [];

        foreach ($this->parseRows($this->opdRows()) as $row) {
            $urusanKode = explode('.', $row['kode'], 2)[0];
            $urusan = $urusanByKode->get($urusanKode);

            if (! $urusan) {
                throw new \RuntimeException("Urusan pemerintahan kode {$urusanKode} belum tersedia untuk OPD {$row['kode']}.");
            }

            $opd = Opd::query()->updateOrCreate(
                ['kode' => $row['kode']],
                [
                    'urusan_pemerintahan_id' => $urusan->id,
                    'nama' => $row['nama'],
                    'singkatan' => $this->singkatan($row['nama']),
                    'jenis' => $this->jenisOpd($row['nama']),
                    'alamat' => null,
                    'telepon' => null,
                    'email' => null,
                    'nama_kepala' => null,
                    'nip_kepala' => null,
                    'status' => 'active',
                ],
            );

            $opdsByKode[$opd->kode] = $opd;
        }

        foreach ($opdsByKode as $opd) {
            $this->upsertUnit(
                opd: $opd,
                kode: $opd->kode,
                nama: $opd->nama,
                parentId: null,
                jenisUnit: $this->jenisUnitInduk((string) $opd->jenis),
            );
        }

        foreach ($this->parseRows($this->unitRows()) as $row) {
            $opdKode = $this->opdCodeFromUnitCode($row['kode']);
            $opd = $opdsByKode[$opdKode] ?? Opd::query()->where('kode', $opdKode)->first();

            if (! $opd) {
                throw new \RuntimeException("OPD induk {$opdKode} belum tersedia untuk sub unit {$row['kode']}.");
            }

            $isInduk = $row['kode'] === $opd->kode;
            $parentId = null;

            if (! $isInduk) {
                $parentId = OpdUnit::query()
                    ->where('opd_id', $opd->id)
                    ->where('kode', $opd->kode)
                    ->value('id');
            }

            $this->upsertUnit(
                opd: $opd,
                kode: $row['kode'],
                nama: $row['nama'],
                parentId: $parentId,
                jenisUnit: $isInduk ? $this->jenisUnitInduk((string) $opd->jenis) : $this->jenisUnit($row['nama']),
            );
        }
    }

    private function upsertUnit(Opd $opd, string $kode, string $nama, ?int $parentId, string $jenisUnit): void
    {
        OpdUnit::query()->updateOrCreate(
            [
                'opd_id' => $opd->id,
                'kode' => $kode,
            ],
            [
                'parent_id' => $parentId,
                'nama' => $nama,
                'jenis_unit' => $jenisUnit,
                'nama_pimpinan' => null,
                'nip_pimpinan' => null,
                'status' => 'active',
            ],
        );
    }

    /**
     * @return array<int, array{kode: string, nama: string}>
     */
    private function parseRows(string $rows): array
    {
        return collect(preg_split('/\R/', trim($rows)) ?: [])
            ->map(fn (string $line) => trim($line))
            ->filter()
            ->map(function (string $line) {
                if (! preg_match('/^(\S+)\s+(.+)$/', $line, $matches)) {
                    throw new \RuntimeException("Format baris OPD/sub unit tidak valid: {$line}");
                }

                return [
                    'kode' => trim($matches[1]),
                    'nama' => trim($matches[2]),
                ];
            })
            ->values()
            ->all();
    }

    private function opdCodeFromUnitCode(string $unitCode): string
    {
        $parts = explode('.', $unitCode);
        $parts[array_key_last($parts)] = '0000';

        return implode('.', $parts);
    }

    private function jenisOpd(string $nama): string
    {
        return match (true) {
            str_starts_with($nama, 'Dinas') => 'Dinas',
            str_starts_with($nama, 'Badan') => 'Badan',
            str_starts_with($nama, 'Satuan') => 'Satuan',
            str_starts_with($nama, 'Sekretariat') => 'Sekretariat',
            str_starts_with($nama, 'Inspektorat') => 'Inspektorat',
            str_starts_with($nama, 'Kecamatan') => 'Kecamatan',
            default => 'Lainnya',
        };
    }

    private function jenisUnitInduk(string $jenisOpd): string
    {
        return match (strtolower($jenisOpd)) {
            'dinas' => 'dinas',
            'badan' => 'badan',
            'satuan' => 'satuan',
            'sekretariat' => 'sekretariat',
            'inspektorat' => 'inspektorat',
            'kecamatan' => 'kecamatan',
            default => 'induk',
        };
    }

    private function jenisUnit(string $nama): string
    {
        $upper = strtoupper($nama);

        return match (true) {
            str_starts_with($upper, 'UPTD PUSKESMAS') => 'puskesmas',
            str_starts_with($upper, 'SMP') || str_starts_with($upper, 'TK') || str_starts_with($upper, 'SKB') => 'sekolah',
            str_contains($upper, 'LABORATORIUM') || str_contains($upper, 'LABKES') => 'labkes',
            str_starts_with($upper, 'RSUD') => 'rsud',
            str_starts_with($upper, 'UPT') => 'uptd',
            str_starts_with($upper, 'BAGIAN') => 'bagian',
            str_starts_with($upper, 'KELURAHAN') => 'kelurahan',
            default => 'lainnya',
        };
    }

    private function singkatan(string $nama): string
    {
        $known = [
            'Dinas Pendidikan, Kepemudaan dan Olah Raga' => 'Dindikpora',
            'Dinas Kesehatan' => 'Dinkes',
            'Dinas Pekerjaan Umum dan Penataan Ruang' => 'DPUPR',
            'Dinas Perumahan Kawasan Permukiman dan Lingkungan Hidup' => 'DPKPLH',
            'Satuan Polisi Pamong Praja' => 'Satpol PP',
            'Badan Penanggulangan Bencana Daerah' => 'BPBD',
            'Dinas Sosial, Pemberdayaan Perempuan dan Perlindungan Anak' => 'Dinsos P3A',
            'Dinas Tenaga Kerja' => 'Disnaker',
            'Dinas Kependudukan dan Pencatatan Sipil' => 'Disdukcapil',
            'Dinas Pemberdayaan Masyarakat dan Desa, Pengendalian Penduduk dan Keluarga Berencana' => 'Dispermades PPKB',
            'Dinas Perhubungan' => 'Dishub',
            'Dinas Komunikasi dan Informatika' => 'Diskominfo',
            'Dinas Penanaman Modal dan Pelayanan Terpadu Satu Pintu' => 'DPMPTSP',
            'Dinas Kearsipan dan Perpustakaan' => 'Disarpus',
            'Dinas Pariwisata dan Kebudayaan' => 'Disparbud',
            'Dinas Pertanian Perikanan dan Ketahanan Pangan' => 'DPKP',
            'Dinas Perindustrian Perdagangan Koperasi dan Usaha Kecil Menengah' => 'Dinperindagkop UKM',
            'Sekretariat Daerah' => 'Setda',
            'Sekretariat DPRD' => 'Setwan',
            'Badan Perencanaan Pengembangan Riset dan Inovasi Daerah' => 'Bapperida',
            'Badan Pengelolaan Pendapatan Keuangan dan Aset Daerah' => 'BPPKAD',
            'Badan Kepegawaian dan Pengembangan Sumber Daya Manusia' => 'BKPSDM',
            'Inspektorat' => 'Inspektorat',
            'Badan Kesatuan Bangsa dan Politik' => 'Kesbangpol',
        ];

        if (isset($known[$nama])) {
            return $known[$nama];
        }

        if (str_starts_with($nama, 'Kecamatan ')) {
            return 'Kec. '.substr($nama, strlen('Kecamatan '));
        }

        return collect(preg_split('/\s+/', $nama) ?: [])
            ->filter(fn (string $word) => strlen($word) > 2)
            ->map(fn (string $word) => strtoupper(substr($word, 0, 1)))
            ->take(6)
            ->implode('');
    }

    private function opdRows(): string
    {
        return <<<'ROWS'
1.01.2.19.0.00.01.0000 Dinas Pendidikan, Kepemudaan dan Olah Raga
1.02.0.00.0.00.01.0000 Dinas Kesehatan
1.03.0.00.0.00.01.0000 Dinas Pekerjaan Umum dan Penataan Ruang
1.04.2.11.2.10.01.0000 Dinas Perumahan Kawasan Permukiman dan Lingkungan Hidup
1.05.0.00.0.00.01.0000 Satuan Polisi Pamong Praja
1.05.0.00.0.00.02.0000 Badan Penanggulangan Bencana Daerah
1.06.2.08.0.00.01.0000 Dinas Sosial, Pemberdayaan Perempuan dan Perlindungan Anak
2.07.0.00.3.32.01.0000 Dinas Tenaga Kerja
2.12.0.00.0.00.01.0000 Dinas Kependudukan dan Pencatatan Sipil
2.13.2.14.0.00.01.0000 Dinas Pemberdayaan Masyarakat dan Desa, Pengendalian Penduduk dan Keluarga Berencana
2.15.0.00.0.00.01.0000 Dinas Perhubungan
2.16.2.20.2.21.01.0000 Dinas Komunikasi dan Informatika
2.18.0.00.0.00.01.0000 Dinas Penanaman Modal dan Pelayanan Terpadu Satu Pintu
2.24.2.23.0.00.01.0000 Dinas Kearsipan dan Perpustakaan
3.26.2.22.0.00.01.0000 Dinas Pariwisata dan Kebudayaan
3.27.3.25.2.09.01.0000 Dinas Pertanian Perikanan dan Ketahanan Pangan
3.31.3.30.2.17.01.0000 Dinas Perindustrian Perdagangan Koperasi dan Usaha Kecil Menengah
4.01.0.00.0.00.01.0000 Sekretariat Daerah
4.02.0.00.0.00.01.0000 Sekretariat DPRD
5.01.5.05.0.00.01.0000 Badan Perencanaan Pengembangan Riset dan Inovasi Daerah
5.02.2.10.0.00.01.0000 Badan Pengelolaan Pendapatan Keuangan dan Aset Daerah
5.03.5.04.0.00.01.0000 Badan Kepegawaian dan Pengembangan Sumber Daya Manusia
6.01.0.00.0.00.01.0000 Inspektorat
7.01.0.00.0.00.01.0000 Kecamatan Banjarnegara
7.01.0.00.0.00.02.0000 Kecamatan Bawang
7.01.0.00.0.00.03.0000 Kecamatan Madukara
7.01.0.00.0.00.04.0000 Kecamatan Sigaluh
7.01.0.00.0.00.05.0000 Kecamatan Purwareja Klampok
7.01.0.00.0.00.06.0000 Kecamatan Susukan
7.01.0.00.0.00.07.0000 Kecamatan Mandiraja
7.01.0.00.0.00.08.0000 Kecamatan Purwanegara
7.01.0.00.0.00.09.0000 Kecamatan Wanadadi
7.01.0.00.0.00.10.0000 Kecamatan Banjarmangu
7.01.0.00.0.00.11.0000 Kecamatan Rakit
7.01.0.00.0.00.12.0000 Kecamatan Punggelan
7.01.0.00.0.00.13.0000 Kecamatan Karangkobar
7.01.0.00.0.00.14.0000 Kecamatan Wanayasa
7.01.0.00.0.00.15.0000 Kecamatan Kalibening
7.01.0.00.0.00.16.0000 Kecamatan Batur
7.01.0.00.0.00.17.0000 Kecamatan Pagentan
7.01.0.00.0.00.18.0000 Kecamatan Pejawaran
7.01.0.00.0.00.19.0000 Kecamatan Pagedongan
7.01.0.00.0.00.20.0000 Kecamatan Pandanarum
8.01.0.00.0.00.01.0000 Badan Kesatuan Bangsa dan Politik
ROWS;
    }

    private function unitRows(): string
    {
        return <<<'ROWS'
1.01.2.19.0.00.01.0000 Dinas Pendidikan, Kepemudaan dan Olah Raga
1.01.2.19.0.00.01.0124 SMPN 3 Punggelan
1.01.2.19.0.00.01.0104 SMPN 2 Banjarnegara
1.01.2.19.0.00.01.0149 SMPN 4 Kalibening
1.01.2.19.0.00.01.0160 SKB Banjarnegara
1.01.2.19.0.00.01.0119 SMPN 2 Wanadadi
1.01.2.19.0.00.01.0147 SMPN 2 Kalibening
1.01.2.19.0.00.01.0112 SMPN 1 Sigaluh
1.01.2.19.0.00.01.0122 SMPN 1 Punggelan
1.01.2.19.0.00.01.0114 SMPN 1 Madukara
1.01.2.19.0.00.01.0159 TKN Wanadadi
1.01.2.19.0.00.01.0113 SMPN 2 Satu Atap Sigaluh
1.01.2.19.0.00.01.0090 SMPN 3 Mandiraja
1.01.2.19.0.00.01.0098 SMPN 1 Bawang
1.01.2.19.0.00.01.0137 SMPN 2 Pejawaran
1.01.2.19.0.00.01.0144 SMPN 3 Wanayasa
1.01.2.19.0.00.01.0130 SMPN 3 Satu Atap Karangkobar
1.01.2.19.0.00.01.0125 SMPN 4 Punggelan
1.01.2.19.0.00.01.0157 TKN Madukara
1.01.2.19.0.00.01.0134 SMPN 4 Satu Atap Pagentan
1.01.2.19.0.00.01.0156 TKN Banjarnegara
1.01.2.19.0.00.01.0109 SMPN 1 Pagedongan
1.01.2.19.0.00.01.0111 SMPN 3 Pagedongan
1.01.2.19.0.00.01.0107 SMPN 5 Banjarnegara
1.01.2.19.0.00.01.0117 SMPN 2 Banjarmangu
1.01.2.19.0.00.01.0127 SMPN 6 Satu Atap Punggelan
1.01.2.19.0.00.01.0101 SMPN 4 Satu Atap Bawang
1.01.2.19.0.00.01.0121 SMPN 2 Rakit
1.01.2.19.0.00.01.0135 SMPN 5 Pagentan
1.01.2.19.0.00.01.0108 SMPN 6 Satu Atap Banjarnegara
1.01.2.19.0.00.01.0115 SMPN 2 Madukara
1.01.2.19.0.00.01.0110 SMPN 2 Satu Atap Pagedongan
1.01.2.19.0.00.01.0103 SMPN 1 Banjarnegara
1.01.2.19.0.00.01.0138 SMPN 3 Satu Atap Pejawaran
1.01.2.19.0.00.01.0128 SMPN 1 Karangkobar
1.01.2.19.0.00.01.0146 SMPN 1 Kalibening
1.01.2.19.0.00.01.0158 TKN Mandiraja
1.01.2.19.0.00.01.0131 SMPN 1 Pagentan
1.01.2.19.0.00.01.0097 SMPN 6 Satu Atap Purwanegara
1.01.2.19.0.00.01.0126 SMPN 5 Satu Atap Punggelan
1.01.2.19.0.00.01.0132 SMPN 2 Pagentan
1.01.2.19.0.00.01.0133 SMPN 3 Pagentan
1.01.2.19.0.00.01.0084 SMPN 4 Satu Atap Susukan
1.01.2.19.0.00.01.0120 SMPN 1 Rakit
1.01.2.19.0.00.01.0136 SMPN 1 Pejawaran
1.01.2.19.0.00.01.0099 SMPN 2 Bawang
1.01.2.19.0.00.01.0151 SMPN 6 Satu Atap Kalibening
1.01.2.19.0.00.01.0143 SMPN 2 Wanayasa
1.01.2.19.0.00.01.0100 SMPN 3 Bawang
1.01.2.19.0.00.01.0153 SMPN 2 Satu Atap Pandanarum
1.01.2.19.0.00.01.0154 SMPN 3 Satu Atap Pandanarum
1.01.2.19.0.00.01.0142 SMPN 1 Wanayasa
1.01.2.19.0.00.01.0148 SMPN 3 Kalibening
1.01.2.19.0.00.01.0155 SMPN 4 Satu Atap Pandanarum
1.01.2.19.0.00.01.0129 SMPN 2 Karangkobar
1.01.2.19.0.00.01.0123 SMPN 2 Punggelan
1.01.2.19.0.00.01.0105 SMPN 3 Banjarnegara
1.01.2.19.0.00.01.0152 SMPN 1 Pandanarum
1.01.2.19.0.00.01.0141 SMPN 2 Batur
1.01.2.19.0.00.01.0095 SMPN 4 Purwanegara
1.01.2.19.0.00.01.0087 SMPN 3 Purwareja Klampok
1.01.2.19.0.00.01.0093 SMPN 2 Purwanegara
1.01.2.19.0.00.01.0102 SMPN 5 Bawang
1.01.2.19.0.00.01.0082 SMPN 2 Susukan
1.01.2.19.0.00.01.0085 SMPN 1 Purwareja Klampok
1.01.2.19.0.00.01.0150 SMPN 5 Satu Atap Kalibening
1.01.2.19.0.00.01.0106 SMPN 4 Banjarnegara
1.01.2.19.0.00.01.0116 SMPN 1 Banjarmangu
1.01.2.19.0.00.01.0083 SMPN 3 Susukan
1.01.2.19.0.00.01.0089 SMPN 2 Mandiraja
1.01.2.19.0.00.01.0086 SMPN 2 Purwareja Klampok
1.01.2.19.0.00.01.0081 SMPN 1 SUSUKAN
1.01.2.19.0.00.01.0088 SMPN 1 Mandiraja
1.01.2.19.0.00.01.0145 SMPN 4 Wanayasa
1.01.2.19.0.00.01.0092 SMPN 1 Purwanegara
1.01.2.19.0.00.01.0140 SMPN 1 Batur
1.01.2.19.0.00.01.0118 SMPN 1 Wanadadi
1.01.2.19.0.00.01.0091 SMPN 4 Mandiraja
1.01.2.19.0.00.01.0094 SMPN 3 Purwanegara
1.01.2.19.0.00.01.0139 SMPN 4 Pejawaran
1.01.2.19.0.00.01.0096 SMPN 5 Satu Atap Purwanegara
1.02.0.00.0.00.01.0000 Dinas Kesehatan
1.02.0.00.0.00.01.0075 RSUD Hj. ANNA LASMANAH BANJARNEGARA
1.02.0.00.0.00.01.0073 UPTD Laboratorium Kesehatan
1.02.0.00.0.00.01.0053 UPTD Puskesmas MADUKARA 1
1.02.0.00.0.00.01.0038 UPTD Puskesmas SUSUKAN 1
1.02.0.00.0.00.01.0072 UPTD Puskesmas PANDANARUM
1.02.0.00.0.00.01.0039 UPTD Puskesmas SUSUKAN 2
1.02.0.00.0.00.01.0040 UPTD Puskesmas KLAMPOK 1
1.02.0.00.0.00.01.0071 UPTD Puskesmas KALIBENING
1.02.0.00.0.00.01.0054 UPTD Puskesmas MADUKARA 2
1.02.0.00.0.00.01.0041 UPTD Puskesmas KLAMPOK 2
1.02.0.00.0.00.01.0042 UPTD Puskesmas MANDIRAJA 1
1.02.0.00.0.00.01.0055 UPTD Puskesmas BANJARMANGU 1
1.02.0.00.0.00.01.0043 UPTD Puskesmas MANDIRAJA 2
1.02.0.00.0.00.01.0044 UPTD Puskesmas PURWANEGARA 1
1.02.0.00.0.00.01.0056 UPTD Puskesmas BANJARMANGU 2
1.02.0.00.0.00.01.0045 UPTD Puskesmas PURWANEGARA 2
1.02.0.00.0.00.01.0046 UPTD Puskesmas BAWANG 1
1.02.0.00.0.00.01.0057 UPTD Puskesmas WANADADI 1
1.02.0.00.0.00.01.0047 UPTD Puskesmas BAWANG 2
1.02.0.00.0.00.01.0058 UPTD Puskesmas WANADADI 2
1.02.0.00.0.00.01.0048 UPTD Puskesmas BANJARNEGARA 1
1.02.0.00.0.00.01.0049 UPTD Puskesmas BANJARNEGARA 2
1.02.0.00.0.00.01.0059 UPTD Puskesmas RAKIT 1
1.02.0.00.0.00.01.0070 UPTD Puskesmas WANAYASA 2
1.02.0.00.0.00.01.0060 UPTD Puskesmas RAKIT 2
1.02.0.00.0.00.01.0069 UPTD Puskesmas WANAYASA 1
1.02.0.00.0.00.01.0061 UPTD Puskesmas PUNGGELAN 1
1.02.0.00.0.00.01.0050 UPTD Puskesmas PAGEDONGAN
1.02.0.00.0.00.01.0062 UPTD Puskesmas PUNGGELAN 2
1.02.0.00.0.00.01.0051 UPTD Puskesmas SIGALUH 1
1.02.0.00.0.00.01.0063 UPTD Puskesmas KARANGKOBAR
1.02.0.00.0.00.01.0064 UPTD Puskesmas PAGENTAN 1
1.02.0.00.0.00.01.0052 UPTD Puskesmas SIGALUH 2
1.02.0.00.0.00.01.0065 UPTD Puskesmas PAGENTAN 2
1.02.0.00.0.00.01.0068 UPTD Puskesmas BATUR 2
1.02.0.00.0.00.01.0067 UPTD Puskesmas BATUR 1
1.02.0.00.0.00.01.0066 UPTD Puskesmas PEJAWARAN
1.02.0.00.0.00.01.0074 UPTD Gudang Farmasi
1.03.0.00.0.00.01.0000 Dinas Pekerjaan Umum dan Penataan Ruang
1.03.0.00.0.00.01.0007 UPT PEMELIHARAAN JALAN DAN IRIGASI WILAYAH I
1.03.0.00.0.00.01.0008 UPT PEMELIHARAAN JALAN DAN IRIGASI WILAYAH II
1.03.0.00.0.00.01.0009 UPT PEMELIHARAAN JALAN DAN IRIGASI WILAYAH III
1.03.0.00.0.00.01.0010 UPT PEMELIHARAAN JALAN DAN IRIGASI WILAYAH IV
1.03.0.00.0.00.01.0011 UPT PEMELIHARAAN JALAN DAN IRIGASI WILAYAH V
1.03.0.00.0.00.01.0012 UPT PERLENGKAPAN DAN PERBENGKELAN
1.04.2.11.2.10.01.0000 Dinas Perumahan Kawasan Permukiman dan Lingkungan Hidup
1.05.0.00.0.00.01.0000 Satuan Polisi Pamong Praja
1.05.0.00.0.00.02.0000 Badan Penanggulangan Bencana Daerah
1.06.2.08.0.00.01.0000 Dinas Sosial, Pemberdayaan Perempuan dan Perlindungan Anak
2.07.0.00.3.32.01.0000 Dinas Tenaga Kerja
2.12.0.00.0.00.01.0000 Dinas Kependudukan dan Pencatatan Sipil
2.13.2.14.0.00.01.0000 Dinas Pemberdayaan Masyarakat dan Desa, Pengendalian Penduduk dan Keluarga Berencana
2.15.0.00.0.00.01.0000 Dinas Perhubungan
2.16.2.20.2.21.01.0000 Dinas Komunikasi dan Informatika
2.18.0.00.0.00.01.0000 Dinas Penanaman Modal dan Pelayanan Terpadu Satu Pintu
2.24.2.23.0.00.01.0000 Dinas Kearsipan dan Perpustakaan
3.26.2.22.0.00.01.0000 Dinas Pariwisata dan Kebudayaan
3.27.3.25.2.09.01.0000 Dinas Pertanian Perikanan dan Ketahanan Pangan
3.31.3.30.2.17.01.0000 Dinas Perindustrian Perdagangan Koperasi dan Usaha Kecil Menengah
4.01.0.00.0.00.01.0001 Bagian Pemerintahan
4.01.0.00.0.00.01.0003 Bagian Kesra
4.01.0.00.0.00.01.0002 Bagian Hukum
4.01.0.00.0.00.01.0004 Bagian Perekonomian dan Sumber Daya Alam
4.01.0.00.0.00.01.0005 Bagian Administrasi Pembangunan
4.01.0.00.0.00.01.0008 Bagian Pengadaan Barang dan Jasa
4.01.0.00.0.00.01.0007 Bagian Umum
4.01.0.00.0.00.01.0006 Bagian Organisasi
4.02.0.00.0.00.01.0000 Sekretariat DPRD
5.01.5.05.0.00.01.0000 Badan Perencanaan Pengembangan Riset dan Inovasi Daerah
5.02.2.10.0.00.01.0000 Badan Pengelolaan Pendapatan Keuangan dan Aset Daerah
5.03.5.04.0.00.01.0000 Badan Kepegawaian dan Pengembangan Sumber Daya Manusia
6.01.0.00.0.00.01.0000 Inspektorat
7.01.0.00.0.00.01.0000 Kecamatan Banjarnegara
7.01.0.00.0.00.01.0001 Kelurahan Kutabanjarnegara
7.01.0.00.0.00.01.0002 Kelurahan Krandegan
7.01.0.00.0.00.01.0003 Kelurahan Parakancanggah
7.01.0.00.0.00.01.0004 Kelurahan Semarang
7.01.0.00.0.00.01.0005 Kelurahan Sokanandi
7.01.0.00.0.00.01.0006 Kelurahan Wangon
7.01.0.00.0.00.01.0007 Kelurahan Semampir
7.01.0.00.0.00.01.0008 Kelurahan Argasoka
7.01.0.00.0.00.01.0009 Kelurahan Karangtengah
7.01.0.00.0.00.02.0000 Kecamatan Bawang
7.01.0.00.0.00.03.0000 Kecamatan Madukara
7.01.0.00.0.00.03.0002 Kelurahan Kenteng
7.01.0.00.0.00.03.0001 Kelurahan Rejasa
7.01.0.00.0.00.04.0000 Kecamatan Sigaluh
7.01.0.00.0.00.04.0001 Kelurahan Kalibenda
7.01.0.00.0.00.05.0000 Kecamatan Purwareja Klampok
7.01.0.00.0.00.06.0000 Kecamatan Susukan
7.01.0.00.0.00.07.0000 Kecamatan Mandiraja
7.01.0.00.0.00.08.0000 Kecamatan Purwanegara
7.01.0.00.0.00.09.0000 Kecamatan Wanadadi
7.01.0.00.0.00.10.0000 Kecamatan Banjarmangu
7.01.0.00.0.00.11.0000 Kecamatan Rakit
7.01.0.00.0.00.12.0000 Kecamatan Punggelan
7.01.0.00.0.00.13.0000 Kecamatan Karangkobar
7.01.0.00.0.00.14.0000 Kecamatan Wanayasa
7.01.0.00.0.00.15.0000 Kecamatan Kalibening
7.01.0.00.0.00.16.0000 Kecamatan Batur
7.01.0.00.0.00.17.0000 Kecamatan Pagentan
7.01.0.00.0.00.18.0000 Kecamatan Pejawaran
7.01.0.00.0.00.19.0000 Kecamatan Pagedongan
7.01.0.00.0.00.20.0000 Kecamatan Pandanarum
8.01.0.00.0.00.01.0000 Badan Kesatuan Bangsa dan Politik
ROWS;
    }
}
