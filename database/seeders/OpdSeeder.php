<?php

namespace Database\Seeders;

use App\Models\Opd;
use App\Models\UrusanPemerintahan;
use Illuminate\Database\Seeder;

class OpdSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->opds() as $opd) {
            $urusan = UrusanPemerintahan::query()
                ->where('kode', $opd['urusan_kode'])
                ->firstOrFail();

            Opd::updateOrCreate(
                ['kode' => $opd['kode']],
                [
                    'urusan_pemerintahan_id' => $urusan->id,
                    'nama' => $opd['nama'],
                    'singkatan' => $opd['singkatan'],
                    'jenis' => $opd['jenis'],
                    'alamat' => $this->clean($opd['alamat'] ?? null),
                    'telepon' => $this->clean($opd['telepon'] ?? null),
                    'email' => $this->cleanEmail($opd['email'] ?? null),
                    'nama_kepala' => $this->clean($opd['nama_kepala'] ?? null),
                    'nip_kepala' => $this->clean($opd['nip_kepala'] ?? null),
                    'status' => $opd['status'],
                ],
            );
        }
    }

    private function clean(?string $value): ?string
    {
        $value = trim((string) $value);

        return in_array($value, ['', '-', '--'], true) ? null : $value;
    }

    private function cleanEmail(?string $value): ?string
    {
        $value = $this->clean($value);

        if (! $value || str_starts_with($value, '-@')) {
            return null;
        }

        return $value;
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function opds(): array
    {
        return [
            ['urusan_kode' => '1', 'kode' => '1.02.0.00.0.00.01.0000', 'nama' => 'Dinas Kesehatan', 'singkatan' => 'Dinkes', 'jenis' => 'Dinas', 'alamat' => '-', 'telepon' => '-', 'email' => '-@banjarnegarakab.go.id', 'nama_kepala' => '-', 'nip_kepala' => '-', 'status' => 'active'],
            ['urusan_kode' => '1', 'kode' => '1.01.2.19.0.00.01.0000', 'nama' => 'Dinas Pendidikan, Kepemudaan dan Olah Raga', 'singkatan' => 'Dindikpora', 'jenis' => 'Dinas', 'alamat' => '-', 'telepon' => '-', 'email' => '-@banjarnegarakab.go.id', 'nama_kepala' => '-', 'nip_kepala' => '-', 'status' => 'active'],
            ['urusan_kode' => '1', 'kode' => '1.03.0.00.0.00.01.0000', 'nama' => 'Dinas Pekerjaan Umum dan Penataan Ruang', 'singkatan' => 'DPUPR', 'jenis' => 'Dinas', 'alamat' => '-', 'telepon' => '-', 'email' => '-@banjarnegarakab.go.id', 'nama_kepala' => '-', 'nip_kepala' => '-', 'status' => 'active'],
            ['urusan_kode' => '1', 'kode' => '1.04.2.11.2.10.01.0000', 'nama' => 'Dinas Perumahan Kawasan Permukiman dan Lingkungan Hidup', 'singkatan' => 'DPKPLH', 'jenis' => 'Dinas', 'alamat' => '-', 'telepon' => '-', 'email' => '-@banjarnegarakab.go.id', 'nama_kepala' => '-', 'nip_kepala' => '-', 'status' => 'active'],
            ['urusan_kode' => '1', 'kode' => '1.05.0.00.0.00.01.0000', 'nama' => 'Satuan Polisi Pamong Praja', 'singkatan' => 'Satpol PP', 'jenis' => 'Dinas', 'alamat' => '-', 'telepon' => '-', 'email' => '-@banjarnegarakab.go.id', 'nama_kepala' => '-', 'nip_kepala' => '-', 'status' => 'active'],
            ['urusan_kode' => '1', 'kode' => '1.05.0.00.0.00.02.0000', 'nama' => 'Badan Penanggulangan Bencana Daerah', 'singkatan' => 'BPBD', 'jenis' => 'Badan', 'alamat' => '-', 'telepon' => '-', 'email' => '-@banjarnegarakab.go.id', 'nama_kepala' => '-', 'nip_kepala' => '-', 'status' => 'active'],
            ['urusan_kode' => '1', 'kode' => '1.06.2.08.0.00.01.0000', 'nama' => 'Dinas Sosial, Pemberdayaan Perempuan dan Perlindungan Anak', 'singkatan' => 'Dinsos P3A', 'jenis' => 'Dinas', 'alamat' => '-', 'telepon' => '-', 'email' => '-@banjarnegarakab.go.id', 'nama_kepala' => '-', 'nip_kepala' => '-', 'status' => 'active'],
            ['urusan_kode' => '2', 'kode' => '2.07.0.00.3.32.01.0000', 'nama' => 'Dinas Tenaga Kerja', 'singkatan' => 'Disnaker', 'jenis' => 'Dinas', 'alamat' => '-', 'telepon' => '-', 'email' => '-@banjarnegarakab.go.id', 'nama_kepala' => '-', 'nip_kepala' => '-', 'status' => 'active'],
            ['urusan_kode' => '2', 'kode' => '2.12.0.00.0.00.01.0000', 'nama' => 'Dinas Kependudukan dan Pencatatan Sipil', 'singkatan' => 'Disdukcapil', 'jenis' => 'Dinas', 'alamat' => '-', 'telepon' => '-', 'email' => '-@banjarnegarakab.go.id', 'nama_kepala' => '-', 'nip_kepala' => '-', 'status' => 'active'],
            ['urusan_kode' => '2', 'kode' => '2.13.2.14.0.00.01.0000', 'nama' => 'Dinas Pemberdayaan Masyarakat dan Desa, Pengendalian Penduduk dan Keluarga Berencana', 'singkatan' => 'Dispermades', 'jenis' => 'Dinas', 'alamat' => '-', 'telepon' => '-', 'email' => '-@banjarnegarakab.go.id', 'nama_kepala' => '-', 'nip_kepala' => '-', 'status' => 'active'],
            ['urusan_kode' => '2', 'kode' => '2.15.0.00.0.00.01.0000', 'nama' => 'Dinas Perhubungan', 'singkatan' => 'Dishub', 'jenis' => 'Dinas', 'alamat' => '-', 'telepon' => '-', 'email' => '-@banjarnegarakab.go.id', 'nama_kepala' => '-', 'nip_kepala' => '-', 'status' => 'active'],
            ['urusan_kode' => '2', 'kode' => '2.16.2.20.2.21.01.0000', 'nama' => 'Dinas Komunikasi dan Informatika', 'singkatan' => 'Diskominfo', 'jenis' => 'Dinas', 'alamat' => '-', 'telepon' => '-', 'email' => '-@banjarnegarakab.go.id', 'nama_kepala' => '-', 'nip_kepala' => '-', 'status' => 'active'],
            ['urusan_kode' => '2', 'kode' => '2.18.0.00.0.00.01.0000', 'nama' => 'Dinas Penanaman Modal dan Pelayanan Terpadu Satu Pintu', 'singkatan' => 'DPMPTSP', 'jenis' => 'Dinas', 'alamat' => '-', 'telepon' => '-', 'email' => '-@banjarnegarakab.go.id', 'nama_kepala' => '-', 'nip_kepala' => '-', 'status' => 'active'],
            ['urusan_kode' => '2', 'kode' => '2.24.2.23.0.00.01.0000', 'nama' => 'Dinas Kearsipan dan Perpustakaan', 'singkatan' => 'Disarpus', 'jenis' => 'Dinas', 'alamat' => '-', 'telepon' => '-', 'email' => '-@banjarnegarakab.go.id', 'nama_kepala' => '-', 'nip_kepala' => '-', 'status' => 'active'],
            ['urusan_kode' => '3', 'kode' => '3.26.2.22.0.00.01.0000', 'nama' => 'Dinas Pariwisata dan Kebudayaan', 'singkatan' => 'Disparbud', 'jenis' => 'Dinas', 'alamat' => '-', 'telepon' => '-', 'email' => '-@banjarnegarakab.go.id', 'nama_kepala' => '-', 'nip_kepala' => '-', 'status' => 'active'],
            ['urusan_kode' => '3', 'kode' => '3.27.3.25.2.09.01.0000', 'nama' => 'Dinas Pertanian Perikanan dan Ketahanan Pangan', 'singkatan' => 'DPKP', 'jenis' => 'Dinas', 'alamat' => '-', 'telepon' => '-', 'email' => '-@banjarnegarakab.go.id', 'nama_kepala' => '-', 'nip_kepala' => '-', 'status' => 'active'],
            ['urusan_kode' => '3', 'kode' => '3.31.3.30.2.17.01.0000', 'nama' => 'Dinas Perindustrian Perdagangan Koperasi dan Usaha Kecil Menengah', 'singkatan' => 'Disperindagkop', 'jenis' => 'Dinas', 'alamat' => '-', 'telepon' => '-', 'email' => '-@banjarnegarakab.go.id', 'nama_kepala' => '-', 'nip_kepala' => '-', 'status' => 'active'],
            ['urusan_kode' => '4', 'kode' => '4.01.0.00.0.00.01.0000', 'nama' => 'Sekretariat Daerah', 'singkatan' => 'Setda', 'jenis' => 'Badan', 'alamat' => '-', 'telepon' => '-', 'email' => '-@banjarnegarakab.go.id', 'nama_kepala' => '-', 'nip_kepala' => '-', 'status' => 'active'],
            ['urusan_kode' => '4', 'kode' => '4.02.0.00.0.00.01.0000', 'nama' => 'Sekretariat DPRD', 'singkatan' => 'Setwan', 'jenis' => 'Dinas', 'alamat' => '-', 'telepon' => '-', 'email' => '-@banjarnegarakab.go.id', 'nama_kepala' => '-', 'nip_kepala' => '-', 'status' => 'active'],
            ['urusan_kode' => '5', 'kode' => '5.01.5.05.0.00.01.0000', 'nama' => 'Badan Perencanaan, Pengembangan Riset Dan Inovasi Daerah', 'singkatan' => 'Bapperida', 'jenis' => 'Badan', 'alamat' => '-', 'telepon' => '-', 'email' => '-@banjarnegarakab.go.id', 'nama_kepala' => '-', 'nip_kepala' => '-', 'status' => 'active'],
            ['urusan_kode' => '5', 'kode' => '5.02.2.10.0.00.01.0000', 'nama' => 'Badan Pengelolaan Pendapatan Keuangan dan Aset Daerah', 'singkatan' => 'BPPKAD', 'jenis' => 'Badan', 'alamat' => '-', 'telepon' => '-', 'email' => '-@banjarnegarakab.go.id', 'nama_kepala' => '-', 'nip_kepala' => '-', 'status' => 'active'],
            ['urusan_kode' => '5', 'kode' => '5.03.5.04.0.00.01.0000', 'nama' => 'Badan Kepegawaian dan Pengembangan Sumber Daya Manusia', 'singkatan' => 'BKPSDM', 'jenis' => 'Badan', 'alamat' => '-', 'telepon' => '-', 'email' => '-@banjarnegarakab.go.id', 'nama_kepala' => '-', 'nip_kepala' => '-', 'status' => 'active'],
            ['urusan_kode' => '6', 'kode' => '6.01.0.00.0.00.01.0000', 'nama' => 'Inspektorat Daerah', 'singkatan' => 'Inspektorat', 'jenis' => 'Dinas', 'alamat' => '-', 'telepon' => '-', 'email' => '-@banjarnegarakab.go.id', 'nama_kepala' => '-', 'nip_kepala' => '-', 'status' => 'active'],
            ['urusan_kode' => '7', 'kode' => '7.01.0.00.0.00.01.0000', 'nama' => 'Kecamatan Banjarnegara', 'singkatan' => 'Kec. Banjarnegara', 'jenis' => 'Kecamatan', 'alamat' => '-', 'telepon' => '-', 'email' => '-@banjarnegarakab.go.id', 'nama_kepala' => '-', 'nip_kepala' => '-', 'status' => 'active'],
            ['urusan_kode' => '7', 'kode' => '7.01.0.00.0.00.02.0000', 'nama' => 'Kecamatan Bawang', 'singkatan' => 'Kec. Bawang', 'jenis' => 'Kecamatan', 'alamat' => '-', 'telepon' => '-', 'email' => '-@banjarnegarakab.go.id', 'nama_kepala' => '-', 'nip_kepala' => '-', 'status' => 'active'],
            ['urusan_kode' => '7', 'kode' => '7.01.0.00.0.00.03.0000', 'nama' => 'Kecamatan Madukara', 'singkatan' => 'Kec. Madukara', 'jenis' => 'Kecamatan', 'alamat' => '-', 'telepon' => '-', 'email' => '-@banjarnegarakab.go.id', 'nama_kepala' => '-', 'nip_kepala' => '-', 'status' => 'active'],
            ['urusan_kode' => '7', 'kode' => '7.01.0.00.0.00.04.0000', 'nama' => 'Kecamatan Sigaluh', 'singkatan' => 'Kec. Sigaluh', 'jenis' => 'Kecamatan', 'alamat' => '-', 'telepon' => '-', 'email' => '-@banjarnegarakab.go.id', 'nama_kepala' => '-', 'nip_kepala' => '-', 'status' => 'active'],
            ['urusan_kode' => '7', 'kode' => '7.01.0.00.0.00.05.0000', 'nama' => 'Kecamatan Purwareja Klampok', 'singkatan' => 'Kec. Purwareja Klampok', 'jenis' => 'Kecamatan', 'alamat' => '-', 'telepon' => '-', 'email' => '-@banjarnegarakab.go.id', 'nama_kepala' => '-', 'nip_kepala' => '-', 'status' => 'active'],
            ['urusan_kode' => '7', 'kode' => '7.01.0.00.0.00.06.0000', 'nama' => 'Kecamatan Susukan', 'singkatan' => 'Kec. Susukan', 'jenis' => 'Kecamatan', 'alamat' => '-', 'telepon' => '-', 'email' => '-@banjarnegarakab.go.id', 'nama_kepala' => '-', 'nip_kepala' => '-', 'status' => 'active'],
            ['urusan_kode' => '7', 'kode' => '7.01.0.00.0.00.07.0000', 'nama' => 'Kecamatan Mandiraja', 'singkatan' => 'Kec. Mandiraja', 'jenis' => 'Kecamatan', 'alamat' => '-', 'telepon' => '-', 'email' => '-@banjarnegarakab.go.id', 'nama_kepala' => '-', 'nip_kepala' => '-', 'status' => 'active'],
            ['urusan_kode' => '7', 'kode' => '7.01.0.00.0.00.08.0000', 'nama' => 'Kecamatan Purwanegara', 'singkatan' => 'Kec. Purwanegara', 'jenis' => 'Kecamatan', 'alamat' => '-', 'telepon' => '-', 'email' => '-@banjarnegarakab.go.id', 'nama_kepala' => '-', 'nip_kepala' => '-', 'status' => 'active'],
            ['urusan_kode' => '7', 'kode' => '7.01.0.00.0.00.09.0000', 'nama' => 'Kecamatan Wanadadi', 'singkatan' => 'Kec. Wanadadi', 'jenis' => 'Kecamatan', 'alamat' => '-', 'telepon' => '-', 'email' => '-@banjarnegarakab.go.id', 'nama_kepala' => '-', 'nip_kepala' => '-', 'status' => 'active'],
            ['urusan_kode' => '7', 'kode' => '7.01.0.00.0.00.10.0000', 'nama' => 'Kecamatan Banjarmangu', 'singkatan' => 'Kec. Banjarmangu', 'jenis' => 'Kecamatan', 'alamat' => '-', 'telepon' => '-', 'email' => '-@banjarnegarakab.go.id', 'nama_kepala' => '-', 'nip_kepala' => '-', 'status' => 'active'],
            ['urusan_kode' => '7', 'kode' => '7.01.0.00.0.00.11.0000', 'nama' => 'Kecamatan Rakit', 'singkatan' => 'Kec. Rakit', 'jenis' => 'Kecamatan', 'alamat' => '--', 'telepon' => '-', 'email' => '-@banjarnegarakab.go.id', 'nama_kepala' => '-', 'nip_kepala' => '-', 'status' => 'active'],
            ['urusan_kode' => '7', 'kode' => '7.01.0.00.0.00.12.0000', 'nama' => 'Kecamatan Punggelan', 'singkatan' => 'Kec. Punggelan', 'jenis' => 'Kecamatan', 'alamat' => '-', 'telepon' => '-', 'email' => '-@banjarnegarakab.go.id', 'nama_kepala' => '-', 'nip_kepala' => '-', 'status' => 'active'],
            ['urusan_kode' => '7', 'kode' => '7.01.0.00.0.00.13.0000', 'nama' => 'Kecamatan Karangkobar', 'singkatan' => 'Kec. Karangkobar', 'jenis' => 'Kecamatan', 'alamat' => '-', 'telepon' => '-', 'email' => '-@banjarnegarakab.go.id', 'nama_kepala' => '-', 'nip_kepala' => '-', 'status' => 'active'],
            ['urusan_kode' => '7', 'kode' => '7.01.0.00.0.00.14.0000', 'nama' => 'Kecamatan Wanayasa', 'singkatan' => 'Kec. Wanayasa', 'jenis' => 'Kecamatan', 'alamat' => '-', 'telepon' => '-', 'email' => '-@banjarnegarakab.go.id', 'nama_kepala' => '-', 'nip_kepala' => '-', 'status' => 'active'],
            ['urusan_kode' => '7', 'kode' => '7.01.0.00.0.00.15.0000', 'nama' => 'Kecamatan Kalibening', 'singkatan' => 'Kec. Kalibening', 'jenis' => 'Kecamatan', 'alamat' => '-', 'telepon' => '-', 'email' => '-@banjarnegarakab.go.id', 'nama_kepala' => '-', 'nip_kepala' => '', 'status' => 'active'],
            ['urusan_kode' => '7', 'kode' => '7.01.0.00.0.00.16.0000', 'nama' => 'Kecamatan Batur', 'singkatan' => 'Kec. Batur', 'jenis' => 'Kecamatan', 'alamat' => '-', 'telepon' => '-', 'email' => '-@banjarnegarakab.go.id', 'nama_kepala' => '-', 'nip_kepala' => '-', 'status' => 'active'],
            ['urusan_kode' => '7', 'kode' => '7.01.0.00.0.00.17.0000', 'nama' => 'Kecamatan Pagentan', 'singkatan' => 'Kec. Pagentan', 'jenis' => 'Kecamatan', 'alamat' => '-', 'telepon' => '-', 'email' => '-@banjarnegarakab.go.id', 'nama_kepala' => '-', 'nip_kepala' => '-', 'status' => 'active'],
            ['urusan_kode' => '7', 'kode' => '7.01.0.00.0.00.18.0000', 'nama' => 'Kecamatan Pejawaran', 'singkatan' => 'Kec. Pejawaran', 'jenis' => 'Kecamatan', 'alamat' => '-', 'telepon' => '-', 'email' => '-@banjarnegarakab.go.id', 'nama_kepala' => '-', 'nip_kepala' => '-', 'status' => 'active'],
            ['urusan_kode' => '7', 'kode' => '7.01.0.00.0.00.19.0000', 'nama' => 'Kecamatan Pagedongan', 'singkatan' => 'Kec. Pagedongan', 'jenis' => 'Kecamatan', 'alamat' => '-', 'telepon' => '-', 'email' => '-@banjarnegarakab.go.id', 'nama_kepala' => '-', 'nip_kepala' => '-', 'status' => 'active'],
            ['urusan_kode' => '7', 'kode' => '7.01.0.00.0.00.20.0000', 'nama' => 'Kecamatan Pandanarum', 'singkatan' => 'Kec. Pandanarum', 'jenis' => 'Kecamatan', 'alamat' => '-', 'telepon' => '-', 'email' => '-@banjarnegarakab.go.id', 'nama_kepala' => '-', 'nip_kepala' => '-', 'status' => 'active'],
            ['urusan_kode' => '8', 'kode' => '8.01.0.00.0.00.01.0000', 'nama' => 'Badan Kesatuan Bangsa dan Politik', 'singkatan' => 'Kesbangpol', 'jenis' => 'Badan', 'alamat' => '-', 'telepon' => '-', 'email' => '-@banjarnegarakab.go.id', 'nama_kepala' => '-', 'nip_kepala' => '-', 'status' => 'active'],
        ];
    }
}
