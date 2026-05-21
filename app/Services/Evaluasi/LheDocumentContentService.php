<?php

namespace App\Services\Evaluasi;

use App\Models\EvaluasiSakip;
use App\Models\EvaluasiSakipItem;
use App\Models\RekomendasiEvaluasi;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class LheDocumentContentService
{
    /**
     * @return array{
     *     title: string,
     *     subtitle: string,
     *     filename: string,
     *     sections: array<int, array{heading: string, content: string}>,
     *     tables: array<int, array{title: string, headers: array<int, string>, rows: array<int, array<int, string>>}>,
     *     metadata: array<string, mixed>
     * }
     */
    public function build(EvaluasiSakip $evaluasi): array
    {
        $evaluasi->loadMissing([
            'opd:id,kode,nama,singkatan,nama_kepala,nip_kepala',
            'periodeTahun:id,tahun,nama',
            'evaluator:id,name',
            'items.kriteria.subKomponen.komponen',
            'lhe.disusunOleh:id,name',
            'rekomendasi.item.kriteria:id,kode,nama',
            'rekomendasi.tindakLanjut.createdBy:id,name',
            'rekomendasi.tindakLanjut.diverifikasiOleh:id,name',
        ]);

        return [
            'title' => 'Laporan Hasil Evaluasi SAKIP',
            'subtitle' => $this->opdName($evaluasi).' Tahun '.$evaluasi->tahun,
            'filename' => $this->filename($evaluasi),
            'sections' => [
                ['heading' => 'BAB I - Pendahuluan', 'content' => $this->identitas($evaluasi)],
                ['heading' => 'BAB II - Ringkasan Hasil Evaluasi', 'content' => $this->ringkasan($evaluasi)],
                ['heading' => 'BAB III - Rincian Nilai per Komponen', 'content' => $this->rincianNilai($evaluasi->items)],
                ['heading' => 'BAB IV - Rekomendasi dan Tindak Lanjut', 'content' => $this->rekomendasi($evaluasi->rekomendasi)."\n\n".$this->tindakLanjut($evaluasi->rekomendasi)],
            ],
            'tables' => $this->tables($evaluasi),
            'metadata' => [
                'source' => 'lhe_auto_export',
                'document_title' => 'Laporan Hasil Evaluasi SAKIP',
                'document_subtitle' => $this->opdName($evaluasi).' Tahun '.$evaluasi->tahun,
                'agency_name' => 'PEMERINTAH KABUPATEN BANJARNEGARA',
                'office_name' => 'INSPEKTORAT DAERAH KABUPATEN BANJARNEGARA',
                'address_line' => 'Kabupaten Banjarnegara, Jawa Tengah',
                'tahun' => $evaluasi->tahun,
                'identity' => [
                    ['label' => 'Nomor LHE', 'value' => $evaluasi->lhe?->nomor_lhe ?: '-'],
                    ['label' => 'Tanggal LHE', 'value' => $evaluasi->lhe?->tanggal_lhe?->toDateString() ?: '-'],
                    ['label' => 'OPD', 'value' => $this->opdName($evaluasi)],
                    ['label' => 'Periode', 'value' => $evaluasi->periodeTahun?->nama ?: (string) $evaluasi->tahun],
                    ['label' => 'Predikat', 'value' => $evaluasi->predikat ?: '-'],
                    ['label' => 'Nilai Akhir', 'value' => $this->number($evaluasi->nilai_akhir)],
                ],
                'signature' => [
                    'place_date' => 'Banjarnegara, '.now()->translatedFormat('d F Y'),
                    'title' => 'Inspektur Kabupaten Banjarnegara',
                    'name' => $evaluasi->lhe?->disusunOleh?->name ?: ($evaluasi->evaluator?->name ?: '(nama pejabat)'),
                    'nip' => '-',
                ],
                'evaluasi_sakip_id' => $evaluasi->id,
                'lhe_id' => $evaluasi->lhe?->id,
                'opd_id' => $evaluasi->opd_id,
                'periode_tahun_id' => $evaluasi->periode_tahun_id,
                'nilai_akhir' => $evaluasi->nilai_akhir,
                'predikat' => $evaluasi->predikat,
                'generated_at' => now()->toDateTimeString(),
            ],
        ];
    }

    private function identitas(EvaluasiSakip $evaluasi): string
    {
        return implode("\n", [
            'Nomor LHE: '.($evaluasi->lhe?->nomor_lhe ?: '-'),
            'Tanggal LHE: '.($evaluasi->lhe?->tanggal_lhe?->toDateString() ?: '-'),
            'OPD: '.$this->opdName($evaluasi),
            'Periode: '.($evaluasi->periodeTahun?->nama ?: $evaluasi->tahun),
            'Evaluator: '.($evaluasi->evaluator?->name ?: '-'),
            'Status Evaluasi: '.$this->statusLabel($evaluasi->status),
            'Status LHE: '.$this->statusLabel($evaluasi->lhe?->status),
        ]);
    }

    private function ringkasan(EvaluasiSakip $evaluasi): string
    {
        return collect([
            'Nilai akhir: '.$this->number($evaluasi->nilai_akhir),
            'Predikat: '.($evaluasi->predikat ?: '-'),
            'Catatan umum evaluasi: '.($evaluasi->catatan_umum ?: '-'),
            'Ringkasan LHE: '.($evaluasi->lhe?->ringkasan ?: 'Ringkasan LHE belum diisi.'),
        ])->implode("\n\n");
    }

    /**
     * @param  Collection<int, EvaluasiSakipItem>  $items
     */
    private function rincianNilai(Collection $items): string
    {
        if ($items->isEmpty()) {
            return 'Belum ada nilai kriteria evaluasi.';
        }

        return $items
            ->groupBy(fn (EvaluasiSakipItem $item) => $item->kriteria?->subKomponen?->komponen?->nama ?: 'Komponen tidak tersedia')
            ->map(function (Collection $items, string $komponen) {
                $lines = [$komponen];

                foreach ($items as $item) {
                    $kriteria = $item->kriteria;
                    $subKomponen = $kriteria?->subKomponen?->nama ?: '-';
                    $lines[] = '- '.$subKomponen.' / '.($kriteria?->kode ?: '-').' - '.($kriteria?->nama ?: '-');
                    $lines[] = '  Nilai: '.$this->number($item->nilai).' Skor: '.$this->number($item->skor);
                    $lines[] = '  Catatan: '.($item->catatan ?: '-');
                    $lines[] = '  Rekomendasi evaluator: '.($item->rekomendasi_text ?: '-');
                }

                return implode("\n", $lines);
            })
            ->implode("\n\n");
    }

    /**
     * @param  Collection<int, RekomendasiEvaluasi>  $rekomendasi
     */
    private function rekomendasi(Collection $rekomendasi): string
    {
        if ($rekomendasi->isEmpty()) {
            return 'Belum ada rekomendasi evaluasi.';
        }

        return $rekomendasi
            ->values()
            ->map(fn (RekomendasiEvaluasi $item, int $index) => implode("\n", [
                ($index + 1).'. '.($item->nomor ?: 'Tanpa nomor'),
                '   Rekomendasi: '.$item->rekomendasi,
                '   Prioritas: '.Str::headline($item->prioritas),
                '   Target tanggal: '.($item->target_tanggal?->toDateString() ?: '-'),
                '   Status tindak lanjut: '.$this->statusLabel($item->status_tindak_lanjut),
            ]))
            ->implode("\n\n");
    }

    /**
     * @param  Collection<int, RekomendasiEvaluasi>  $rekomendasi
     */
    private function tindakLanjut(Collection $rekomendasi): string
    {
        $lines = [];

        foreach ($rekomendasi as $item) {
            $lines[] = ($item->nomor ?: 'Rekomendasi #'.$item->id).': '.$this->statusLabel($item->status_tindak_lanjut);

            foreach ($item->tindakLanjut as $tindakLanjut) {
                $lines[] = '- '.$tindakLanjut->uraian_tindak_lanjut;
                $lines[] = '  Status: '.$this->statusLabel($tindakLanjut->status_tindak_lanjut).' Tanggal: '.($tindakLanjut->tanggal_tindak_lanjut?->toDateString() ?: '-');
                $lines[] = '  Catatan OPD: '.($tindakLanjut->catatan_opd ?: '-');
                $lines[] = '  Catatan verifikator: '.($tindakLanjut->catatan_verifikator ?: '-');
            }
        }

        return $lines ? implode("\n", $lines) : 'Belum ada tindak lanjut rekomendasi.';
    }

    /**
     * @return array<int, array{title: string, headers: array<int, string>, rows: array<int, array<int, string>>}>
     */
    private function tables(EvaluasiSakip $evaluasi): array
    {
        return [
            [
                'title' => 'Lampiran 1. Rincian Nilai Evaluasi SAKIP',
                'headers' => ['No', 'Komponen', 'Sub Komponen', 'Kriteria', 'Nilai', 'Skor'],
                'rows' => $this->nilaiRows($evaluasi->items),
            ],
            [
                'title' => 'Lampiran 2. Rekomendasi Hasil Evaluasi',
                'headers' => ['No', 'Nomor', 'Rekomendasi', 'Prioritas', 'Status Tindak Lanjut'],
                'rows' => $this->rekomendasiRows($evaluasi->rekomendasi),
            ],
        ];
    }

    /**
     * @param  Collection<int, EvaluasiSakipItem>  $items
     * @return array<int, array<int, string>>
     */
    private function nilaiRows(Collection $items): array
    {
        if ($items->isEmpty()) {
            return [['-', 'Belum ada nilai evaluasi.', '-', '-', '-', '-']];
        }

        return $items->values()
            ->map(function (EvaluasiSakipItem $item, int $index) {
                $kriteria = $item->kriteria;

                return [
                    (string) ($index + 1),
                    $kriteria?->subKomponen?->komponen?->nama ?: '-',
                    $kriteria?->subKomponen?->nama ?: '-',
                    ($kriteria?->kode ? $kriteria->kode.' - ' : '').($kriteria?->nama ?: '-'),
                    $this->number($item->nilai),
                    $this->number($item->skor),
                ];
            })
            ->all();
    }

    /**
     * @param  Collection<int, RekomendasiEvaluasi>  $rekomendasi
     * @return array<int, array<int, string>>
     */
    private function rekomendasiRows(Collection $rekomendasi): array
    {
        if ($rekomendasi->isEmpty()) {
            return [['-', '-', 'Belum ada rekomendasi evaluasi.', '-', '-']];
        }

        return $rekomendasi->values()
            ->map(fn (RekomendasiEvaluasi $item, int $index) => [
                (string) ($index + 1),
                $item->nomor ?: '-',
                $item->rekomendasi,
                Str::headline($item->prioritas),
                $this->statusLabel($item->status_tindak_lanjut),
            ])
            ->all();
    }

    private function opdName(EvaluasiSakip $evaluasi): string
    {
        return $evaluasi->opd?->singkatan
            ? $evaluasi->opd->singkatan.' - '.$evaluasi->opd->nama
            : ($evaluasi->opd?->nama ?: 'OPD belum ditentukan');
    }

    private function filename(EvaluasiSakip $evaluasi): string
    {
        return Str::slug('lhe-'.$this->opdName($evaluasi).'-'.$evaluasi->tahun).'-'.now()->format('Ymd-His').'.txt';
    }

    private function statusLabel(?string $status): string
    {
        return $status ? Str::headline($status) : '-';
    }

    private function number(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '-';
        }

        return rtrim(rtrim(number_format((float) $value, 2, ',', '.'), '0'), ',');
    }
}
