<?php

namespace App\Services\Lkjip;

use App\Models\Lkjip;
use App\Models\RealisasiProgram;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class LkjipDraftContentService
{
    /**
     * @return array{
     *     filename: string,
     *     content: string,
     *     bab: array<int, array{kode: string, judul: string, jenis: string, konten: string, urutan: int}>,
     *     metadata: array<string, mixed>,
     *     tables: array<int, array{title: string, headers: array<int, string>, rows: array<int, array<int, string>>}>
     * }
     */
    public function build(Lkjip $lkjip): array
    {
        $lkjip->loadMissing([
            'opd:id,kode,nama,singkatan,nama_kepala,nip_kepala',
            'periodeTahun:id,tahun,nama,tanggal_mulai,tanggal_selesai',
            'perjanjianKinerja.items.satuanIndikator:id,nama,simbol',
            'realisasiKinerja.programs',
            'evaluasiSakip.rekomendasi.tindakLanjut',
            'bab',
        ]);

        $bab = [
            [
                'kode' => 'BAB I',
                'judul' => 'Pendahuluan',
                'jenis' => 'pendahuluan',
                'konten' => $this->pendahuluan($lkjip),
                'urutan' => 1,
            ],
            [
                'kode' => 'BAB II',
                'judul' => 'Perencanaan Kinerja',
                'jenis' => 'perencanaan',
                'konten' => $this->perencanaan($lkjip),
                'urutan' => 2,
            ],
            [
                'kode' => 'BAB III',
                'judul' => 'Akuntabilitas Kinerja',
                'jenis' => 'akuntabilitas',
                'konten' => $this->akuntabilitas($lkjip),
                'urutan' => 3,
            ],
            [
                'kode' => 'BAB IV',
                'judul' => 'Penutup',
                'jenis' => 'penutup',
                'konten' => $this->penutup($lkjip),
                'urutan' => 4,
            ],
            [
                'kode' => 'LAMPIRAN',
                'judul' => 'Lampiran',
                'jenis' => 'lampiran',
                'konten' => $this->lampiran($lkjip),
                'urutan' => 5,
            ],
        ];

        $metadata = $this->metadata($lkjip);
        $tables = $this->tables($lkjip);

        $header = [
            '# PEMERINTAH KABUPATEN BANJARNEGARA',
            '# LAPORAN KINERJA INSTANSI PEMERINTAH (LKJIP) '.$lkjip->tahun,
            '',
            'Judul: '.$lkjip->judul,
            'OPD: '.$this->opdName($lkjip),
            'Periode: '.($lkjip->periodeTahun?->nama ?: $lkjip->tahun),
            'Nomor Dokumen: '.($lkjip->nomor_dokumen ?: '-'),
            'Dibuat otomatis: '.now()->format('Y-m-d H:i:s'),
            '',
        ];

        $content = collect($bab)
            ->map(fn (array $section) => '## '.$section['kode'].' - '.$section['judul']."\n\n".$section['konten'])
            ->prepend(implode("\n", $header))
            ->implode("\n\n");

        return [
            'filename' => $this->filename($lkjip),
            'content' => $content,
            'bab' => $bab,
            'metadata' => $metadata,
            'tables' => $tables,
        ];
    }

    private function pendahuluan(Lkjip $lkjip): string
    {
        return collect([
            'Laporan Kinerja Instansi Pemerintah ini disusun sebagai bentuk akuntabilitas kinerja '.$this->opdName($lkjip).' Tahun '.$lkjip->tahun.'.',
            $lkjip->ringkasan_eksekutif ?: 'Ringkasan eksekutif belum diisi. Narasi ini perlu dilengkapi oleh OPD sebelum dokumen final diterbitkan.',
            'Dokumen ini memuat ringkasan perencanaan kinerja, capaian indikator, realisasi anggaran, efisiensi, serta tindak lanjut atas hasil evaluasi.',
        ])->implode("\n\n");
    }

    private function perencanaan(Lkjip $lkjip): string
    {
        $pk = $lkjip->perjanjianKinerja;

        if (! $pk) {
            return 'Perjanjian Kinerja belum dikaitkan pada LKJIP ini. BAB II perlu dilengkapi setelah data PK tersedia.';
        }

        $lines = [
            'Perencanaan kinerja bersumber dari '.$pk->judul.' Tahun '.$pk->tahun.' dengan status '.$this->statusLabel($pk->status).'.',
            '',
            'Daftar sasaran dan indikator kinerja:',
        ];

        foreach ($pk->items as $index => $item) {
            $target = filled($item->target_text)
                ? $item->target_text
                : $this->number($item->target).($item->satuanIndikator?->simbol ? ' '.$item->satuanIndikator->simbol : '');

            $lines[] = ($index + 1).'. Sasaran: '.$item->sasaran;
            $lines[] = '   Indikator: '.$item->indikator;
            $lines[] = '   Target: '.$target;
        }

        if ($pk->items->isEmpty()) {
            $lines[] = 'Belum ada item indikator Perjanjian Kinerja.';
        }

        return implode("\n", $lines);
    }

    private function akuntabilitas(Lkjip $lkjip): string
    {
        $realisasi = $lkjip->realisasiKinerja;

        if (! $realisasi) {
            return 'Data realisasi kinerja belum dikaitkan pada LKJIP ini. BAB III perlu dilengkapi setelah realisasi Triwulan IV atau periode akhir tahun tersedia.';
        }

        $lines = [
            'Akuntabilitas kinerja bersumber dari realisasi '.$realisasi->periode_realisasi.' '.$lkjip->tahun.' dengan status '.$this->statusLabel($realisasi->status).'.',
            'Capaian rata-rata: '.$this->percent($realisasi->capaian_persen),
            'Serapan anggaran: '.$this->percent($realisasi->serapan_anggaran_persen),
            'Status efisiensi: '.($realisasi->status_efisiensi ? Str::headline($realisasi->status_efisiensi) : '-'),
            '',
            'Rincian capaian indikator:',
        ];

        foreach ($realisasi->programs as $index => $program) {
            $lines[] = $this->programLine($program, $index + 1);
        }

        if ($realisasi->programs->isEmpty()) {
            $lines[] = 'Belum ada rincian realisasi program.';
        }

        $lines[] = '';
        $lines[] = 'Analisis efisiensi: '.($realisasi->analisis_efisiensi ?: 'Belum ada analisis efisiensi umum.');
        $lines[] = 'Catatan realisasi: '.($realisasi->catatan ?: '-');

        return implode("\n", $lines);
    }

    private function penutup(Lkjip $lkjip): string
    {
        $programs = $lkjip->realisasiKinerja?->programs ?: collect();
        $summary = $this->achievementSummary($programs);

        return collect([
            'Secara umum, dokumen ini menunjukkan capaian kinerja '.$this->opdName($lkjip).' Tahun '.$lkjip->tahun.' berdasarkan data yang telah tersedia di sistem.',
            'Ringkasan status capaian: merah '.$summary['merah'].', kuning '.$summary['kuning'].', hijau '.$summary['hijau'].'.',
            'OPD perlu melengkapi narasi keberhasilan, permasalahan, solusi, dan langkah antisipasi tahun berikutnya sebelum LKJIP final ditetapkan.',
        ])->implode("\n\n");
    }

    private function lampiran(Lkjip $lkjip): string
    {
        $lines = [
            'Lampiran disusun dalam format tabel resmi dan menjadi bagian tidak terpisahkan dari dokumen LKJIP.',
            '1. Lampiran Matriks Perjanjian Kinerja.',
            '2. Lampiran Tabel Realisasi Kinerja dan Anggaran.',
            '3. Lampiran Rekomendasi Evaluasi dan Status Tindak Lanjut.',
            '',
            'Rekomendasi evaluasi:',
        ];

        $rekomendasi = $lkjip->evaluasiSakip?->rekomendasi ?: collect();

        foreach ($rekomendasi as $index => $item) {
            $lines[] = ($index + 1).'. '.$item->rekomendasi.' Status tindak lanjut: '.$this->statusLabel($item->status_tindak_lanjut).'.';
        }

        if ($rekomendasi->isEmpty()) {
            $lines[] = 'Belum ada rekomendasi evaluasi yang dikaitkan.';
        }

        return implode("\n", $lines);
    }

    /**
     * @return array<string, mixed>
     */
    private function metadata(Lkjip $lkjip): array
    {
        return [
            'source' => 'lkjip_auto_draft',
            'document_title' => 'Laporan Kinerja Instansi Pemerintah (LKJIP)',
            'document_subtitle' => $this->opdName($lkjip).' Tahun '.$lkjip->tahun,
            'agency_name' => 'PEMERINTAH KABUPATEN BANJARNEGARA',
            'office_name' => strtoupper($this->opdName($lkjip)),
            'address_line' => 'Kabupaten Banjarnegara, Jawa Tengah',
            'tahun' => $lkjip->tahun,
            'identity' => [
                ['label' => 'Nama Dokumen', 'value' => $lkjip->judul],
                ['label' => 'Nomor Dokumen', 'value' => $lkjip->nomor_dokumen ?: '-'],
                ['label' => 'OPD', 'value' => $this->opdName($lkjip)],
                ['label' => 'Periode', 'value' => $lkjip->periodeTahun?->nama ?: (string) $lkjip->tahun],
                ['label' => 'Status', 'value' => $this->statusLabel($lkjip->status)],
            ],
            'signature' => [
                'place_date' => 'Banjarnegara, '.now()->translatedFormat('d F Y'),
                'title' => $lkjip->opd?->nama ? 'Kepala '.$lkjip->opd->nama : 'Kepala OPD',
                'name' => $lkjip->opd?->nama_kepala ?: '(nama pejabat)',
                'nip' => $lkjip->opd?->nip_kepala ?: '-',
            ],
            'lkjip_id' => $lkjip->id,
            'opd_id' => $lkjip->opd_id,
            'periode_tahun_id' => $lkjip->periode_tahun_id,
            'perjanjian_kinerja_id' => $lkjip->perjanjian_kinerja_id,
            'realisasi_kinerja_id' => $lkjip->realisasi_kinerja_id,
            'evaluasi_sakip_id' => $lkjip->evaluasi_sakip_id,
            'generated_at' => now()->toDateTimeString(),
        ];
    }

    /**
     * @return array<int, array{title: string, headers: array<int, string>, rows: array<int, array<int, string>>}>
     */
    private function tables(Lkjip $lkjip): array
    {
        return [
            [
                'title' => 'Lampiran 1. Matriks Perjanjian Kinerja',
                'headers' => ['No', 'Sasaran', 'Indikator', 'Target'],
                'rows' => $this->pkRows($lkjip),
            ],
            [
                'title' => 'Lampiran 2. Realisasi Kinerja dan Anggaran',
                'headers' => ['No', 'Indikator', 'Target', 'Realisasi', 'Capaian', 'Anggaran', 'Serapan', 'Efisiensi'],
                'rows' => $this->realisasiRows($lkjip),
            ],
            [
                'title' => 'Lampiran 3. Rekomendasi Evaluasi',
                'headers' => ['No', 'Rekomendasi', 'Status Tindak Lanjut'],
                'rows' => $this->rekomendasiRows($lkjip),
            ],
        ];
    }

    /**
     * @return array<int, array<int, string>>
     */
    private function pkRows(Lkjip $lkjip): array
    {
        $items = $lkjip->perjanjianKinerja?->items ?: collect();

        if ($items->isEmpty()) {
            return [['-', 'Belum ada data Perjanjian Kinerja.', '-', '-']];
        }

        return $items->values()
            ->map(fn ($item, int $index) => [
                (string) ($index + 1),
                (string) $item->sasaran,
                (string) $item->indikator,
                filled($item->target_text) ? (string) $item->target_text : $this->number($item->target),
            ])
            ->all();
    }

    /**
     * @return array<int, array<int, string>>
     */
    private function realisasiRows(Lkjip $lkjip): array
    {
        $programs = $lkjip->realisasiKinerja?->programs ?: collect();

        if ($programs->isEmpty()) {
            return [['-', 'Belum ada realisasi kinerja.', '-', '-', '-', '-', '-', '-']];
        }

        return $programs->values()
            ->map(fn (RealisasiProgram $program, int $index) => [
                (string) ($index + 1),
                (string) $program->indikator,
                filled($program->target_text) ? (string) $program->target_text : $this->number($program->target),
                filled($program->realisasi_text) ? (string) $program->realisasi_text : $this->number($program->realisasi),
                $this->percent($program->capaian_persen).' / '.($program->status_capaian ?: '-'),
                $this->money($program->anggaran),
                $this->percent($program->serapan_anggaran_persen),
                $program->status_efisiensi ? Str::headline($program->status_efisiensi) : '-',
            ])
            ->all();
    }

    /**
     * @return array<int, array<int, string>>
     */
    private function rekomendasiRows(Lkjip $lkjip): array
    {
        $items = $lkjip->evaluasiSakip?->rekomendasi ?: collect();

        if ($items->isEmpty()) {
            return [['-', 'Belum ada rekomendasi evaluasi.', '-']];
        }

        return $items->values()
            ->map(fn ($item, int $index) => [
                (string) ($index + 1),
                (string) $item->rekomendasi,
                $this->statusLabel($item->status_tindak_lanjut),
            ])
            ->all();
    }

    private function programLine(RealisasiProgram $program, int $number): string
    {
        return implode("\n", [
            $number.'. Indikator: '.$program->indikator,
            '   Target: '.(filled($program->target_text) ? $program->target_text : $this->number($program->target)),
            '   Realisasi: '.(filled($program->realisasi_text) ? $program->realisasi_text : $this->number($program->realisasi)),
            '   Capaian: '.$this->percent($program->capaian_persen).' ('.($program->status_capaian ?: '-').')',
            '   Anggaran: '.$this->money($program->anggaran).' Realisasi anggaran: '.$this->money($program->realisasi_anggaran).' Serapan: '.$this->percent($program->serapan_anggaran_persen),
            '   Efisiensi: '.($program->status_efisiensi ? Str::headline($program->status_efisiensi) : '-'),
            '   Kendala: '.($program->kendala ?: '-'),
            '   Tindak lanjut: '.($program->tindak_lanjut ?: '-'),
        ]);
    }

    /**
     * @param  Collection<int, RealisasiProgram>  $programs
     * @return array{merah: int, kuning: int, hijau: int}
     */
    private function achievementSummary(Collection $programs): array
    {
        return [
            'merah' => $programs->where('status_capaian', 'merah')->count(),
            'kuning' => $programs->where('status_capaian', 'kuning')->count(),
            'hijau' => $programs->where('status_capaian', 'hijau')->count(),
        ];
    }

    private function opdName(Lkjip $lkjip): string
    {
        return $lkjip->opd?->singkatan
            ? $lkjip->opd->singkatan.' - '.$lkjip->opd->nama
            : ($lkjip->opd?->nama ?: 'OPD belum ditentukan');
    }

    private function filename(Lkjip $lkjip): string
    {
        $slug = Str::slug('draft-lkjip-'.$this->opdName($lkjip).'-'.$lkjip->tahun);

        return ($slug ?: 'draft-lkjip-'.$lkjip->id).'-'.now()->format('Ymd-His').'.md';
    }

    private function number(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '-';
        }

        return rtrim(rtrim(number_format((float) $value, 4, ',', '.'), '0'), ',');
    }

    private function percent(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '-';
        }

        return number_format((float) $value, 2, ',', '.').'%';
    }

    private function money(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '-';
        }

        return 'Rp '.number_format((float) $value, 2, ',', '.');
    }

    private function statusLabel(?string $status): string
    {
        if (! $status) {
            return '-';
        }

        return Str::headline($status);
    }
}
