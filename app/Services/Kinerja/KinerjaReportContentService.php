<?php

namespace App\Services\Kinerja;

use App\Models\PerjanjianKinerja;
use App\Models\RealisasiKinerja;
use App\Models\RealisasiProgram;
use App\Models\RencanaAksi;
use App\Models\RencanaAksiItem;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use InvalidArgumentException;

class KinerjaReportContentService
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
    public function build(Model $model, string $module): array
    {
        return match ($module) {
            'perjanjian_kinerja' => $this->perjanjianKinerja($this->assertModel($model, PerjanjianKinerja::class)),
            'rencana_aksi' => $this->rencanaAksi($this->assertModel($model, RencanaAksi::class)),
            'realisasi_kinerja' => $this->realisasiKinerja($this->assertModel($model, RealisasiKinerja::class)),
            default => throw new InvalidArgumentException('Modul laporan kinerja tidak valid.'),
        };
    }

    /**
     * @template T of Model
     *
     * @param  class-string<T>  $class
     * @return T
     */
    private function assertModel(Model $model, string $class): Model
    {
        if (! $model instanceof $class) {
            throw new InvalidArgumentException('Model laporan tidak sesuai modul.');
        }

        return $model;
    }

    private function perjanjianKinerja(PerjanjianKinerja $pk): array
    {
        $pk->loadMissing([
            'opd:id,kode,nama,singkatan,nama_kepala,nip_kepala',
            'periodeTahun:id,tahun,nama',
            'renstraOpd:id,judul,tahun_awal,tahun_akhir',
            'items.satuanIndikator:id,nama,simbol',
            'items.opdProgram:id,kode,nama',
        ]);

        $opdName = $this->opdName($pk);

        return [
            'title' => 'PERJANJIAN KINERJA',
            'subtitle' => $opdName.' Tahun '.$pk->tahun,
            'filename' => $this->filename('perjanjian-kinerja', $opdName, $pk->tahun),
            'sections' => [
                [
                    'heading' => 'Pernyataan Perjanjian Kinerja',
                    'content' => collect([
                        'Dokumen ini merupakan Perjanjian Kinerja '.$opdName.' Tahun '.$pk->tahun.' sebagai komitmen pencapaian sasaran strategis dan indikator kinerja yang telah ditetapkan.',
                        'Target kinerja dalam dokumen ini menjadi dasar pelaksanaan rencana aksi, pengukuran capaian, pelaporan kinerja, serta evaluasi akuntabilitas kinerja perangkat daerah.',
                    ])->implode("\n\n"),
                ],
                [
                    'heading' => 'Dasar Perencanaan',
                    'content' => collect([
                        'Periode: '.($pk->periodeTahun?->nama ?: $pk->tahun),
                        'Renstra OPD: '.($pk->renstraOpd?->judul ?: 'Belum dikaitkan'),
                        'Status dokumen: '.$this->statusLabel($pk->status),
                        'Catatan: '.($pk->catatan ?: '-'),
                    ])->implode("\n"),
                ],
                [
                    'heading' => 'Lampiran',
                    'content' => 'Lampiran matriks Perjanjian Kinerja memuat sasaran, indikator, target, satuan, dan program terkait sebagai bagian tidak terpisahkan dari dokumen ini.',
                ],
            ],
            'tables' => [
                [
                    'title' => 'Lampiran 1. Matriks Perjanjian Kinerja',
                    'headers' => ['No', 'Sasaran', 'Indikator Kinerja', 'Target', 'Satuan', 'Program Terkait'],
                    'rows' => $pk->items->isEmpty()
                        ? [['-', 'Belum ada sasaran/indikator.', '-', '-', '-', '-']]
                        : $pk->items->values()->map(fn ($item, int $index) => [
                            (string) ($index + 1),
                            (string) $item->sasaran,
                            (string) $item->indikator,
                            $this->targetText($item->target, $item->target_text),
                            $item->satuanIndikator?->simbol ?: ($item->satuanIndikator?->nama ?: '-'),
                            $item->opdProgram?->nama ?: '-',
                        ])->all(),
                ],
            ],
            'metadata' => $this->metadata($pk, 'perjanjian_kinerja', $opdName, [
                ['label' => 'Nomor Dokumen', 'value' => $pk->nomor_dokumen ?: '-'],
                ['label' => 'OPD', 'value' => $opdName],
                ['label' => 'Tahun', 'value' => (string) $pk->tahun],
                ['label' => 'Periode', 'value' => $pk->periodeTahun?->nama ?: (string) $pk->tahun],
                ['label' => 'Status', 'value' => $this->statusLabel($pk->status)],
            ]),
        ];
    }

    private function rencanaAksi(RencanaAksi $rencanaAksi): array
    {
        $rencanaAksi->loadMissing([
            'opd:id,kode,nama,singkatan,nama_kepala,nip_kepala',
            'periodeTahun:id,tahun,nama',
            'perjanjianKinerja:id,judul,tahun,status',
            'items.perjanjianKinerjaItem:id,kode,indikator',
            'items.opdProgram:id,kode,nama',
            'items.opdKegiatan:id,kode,nama',
            'items.opdSubKegiatan:id,kode,nama',
        ]);

        $opdName = $this->opdName($rencanaAksi);

        return [
            'title' => 'RENCANA AKSI KINERJA',
            'subtitle' => $opdName.' Tahun '.$rencanaAksi->tahun,
            'filename' => $this->filename('rencana-aksi', $opdName, $rencanaAksi->tahun),
            'sections' => [
                [
                    'heading' => 'Pendahuluan',
                    'content' => 'Rencana Aksi Kinerja ini memuat penjabaran Perjanjian Kinerja menjadi kegiatan, jadwal pelaksanaan, target periodik, anggaran, dan penanggung jawab pelaksanaan.',
                ],
                [
                    'heading' => 'Keterkaitan Perjanjian Kinerja',
                    'content' => collect([
                        'Perjanjian Kinerja: '.($rencanaAksi->perjanjianKinerja?->judul ?: '-'),
                        'Status PK: '.$this->statusLabel($rencanaAksi->perjanjianKinerja?->status),
                        'Periode: '.($rencanaAksi->periodeTahun?->nama ?: $rencanaAksi->tahun),
                        'Catatan: '.($rencanaAksi->catatan ?: '-'),
                    ])->implode("\n"),
                ],
                [
                    'heading' => 'Lampiran',
                    'content' => 'Lampiran rencana aksi memuat daftar aksi, indikator, target, periode realisasi, anggaran, dan unit/pejabat penanggung jawab.',
                ],
            ],
            'tables' => [
                [
                    'title' => 'Lampiran 1. Matriks Rencana Aksi',
                    'headers' => ['No', 'Aksi', 'Indikator', 'Periode', 'Target', 'Anggaran', 'Program/Kegiatan/Sub Kegiatan', 'Penanggung Jawab'],
                    'rows' => $this->rencanaAksiRows($rencanaAksi),
                ],
                [
                    'title' => 'Lampiran 2. Rekap Target dan Anggaran per Triwulan',
                    'headers' => ['Triwulan', 'Jumlah Aksi', 'Total Anggaran'],
                    'rows' => $this->rencanaAksiTriwulanRows($rencanaAksi),
                ],
            ],
            'metadata' => $this->metadata($rencanaAksi, 'rencana_aksi', $opdName, [
                ['label' => 'Nama Dokumen', 'value' => $rencanaAksi->judul],
                ['label' => 'OPD', 'value' => $opdName],
                ['label' => 'Tahun', 'value' => (string) $rencanaAksi->tahun],
                ['label' => 'Perjanjian Kinerja', 'value' => $rencanaAksi->perjanjianKinerja?->judul ?: '-'],
                ['label' => 'Status', 'value' => $this->statusLabel($rencanaAksi->status)],
            ]),
        ];
    }

    private function realisasiKinerja(RealisasiKinerja $realisasi): array
    {
        $realisasi->loadMissing([
            'opd:id,kode,nama,singkatan,nama_kepala,nip_kepala',
            'periodeTahun:id,tahun,nama',
            'perjanjianKinerja:id,judul,tahun,status',
            'rencanaAksi:id,judul,tahun,status',
            'programs.perjanjianKinerjaItem:id,kode,indikator',
            'programs.rencanaAksiItem:id,aksi',
            'programs.opdProgram:id,kode,nama',
            'programs.indikatorOpdProgram:id,kode,indikator',
        ]);

        $opdName = $this->opdName($realisasi);
        $periodLabel = $this->periodLabel($realisasi);

        return [
            'title' => 'LAPORAN REALISASI KINERJA',
            'subtitle' => $opdName.' '.$periodLabel.' Tahun '.$realisasi->tahun,
            'filename' => $this->filename('laporan-realisasi-kinerja', $opdName, $realisasi->tahun),
            'sections' => [
                [
                    'heading' => 'Ringkasan Realisasi',
                    'content' => collect([
                        'Laporan ini memuat realisasi capaian kinerja dan realisasi anggaran '.$opdName.' untuk periode '.$periodLabel.' Tahun '.$realisasi->tahun.'.',
                        'Rata-rata capaian kinerja: '.$this->percent($realisasi->capaian_persen).'.',
                        'Serapan anggaran: '.$this->percent($realisasi->serapan_anggaran_persen).'.',
                        'Status capaian: '.($realisasi->status_capaian ? Str::headline($realisasi->status_capaian) : '-').'.',
                        'Status efisiensi: '.($realisasi->status_efisiensi ? Str::headline($realisasi->status_efisiensi) : '-').'.',
                    ])->implode("\n"),
                ],
                [
                    'heading' => 'Analisis Efisiensi',
                    'content' => $realisasi->analisis_efisiensi ?: 'Analisis efisiensi belum diisi. OPD perlu melengkapi uraian efisiensi anggaran terhadap capaian kinerja.',
                ],
                [
                    'heading' => 'Catatan Pelaksanaan',
                    'content' => $realisasi->catatan ?: 'Catatan pelaksanaan belum diisi.',
                ],
            ],
            'tables' => [
                [
                    'title' => 'Lampiran 1. Realisasi Indikator Kinerja',
                    'headers' => ['No', 'Indikator', 'Target', 'Realisasi', 'Capaian', 'Status', 'Anggaran', 'Realisasi Anggaran', 'Serapan', 'Efisiensi'],
                    'rows' => $this->realisasiRows($realisasi),
                ],
                [
                    'title' => 'Lampiran 2. Kendala dan Tindak Lanjut',
                    'headers' => ['No', 'Indikator', 'Kendala', 'Tindak Lanjut'],
                    'rows' => $this->realisasiAnalysisRows($realisasi),
                ],
            ],
            'metadata' => $this->metadata($realisasi, 'realisasi_kinerja', $opdName, [
                ['label' => 'OPD', 'value' => $opdName],
                ['label' => 'Tahun', 'value' => (string) $realisasi->tahun],
                ['label' => 'Periode Realisasi', 'value' => $periodLabel],
                ['label' => 'Perjanjian Kinerja', 'value' => $realisasi->perjanjianKinerja?->judul ?: '-'],
                ['label' => 'Rencana Aksi', 'value' => $realisasi->rencanaAksi?->judul ?: '-'],
                ['label' => 'Status', 'value' => $this->statusLabel($realisasi->status)],
            ]),
        ];
    }

    /**
     * @return array<int, array<int, string>>
     */
    private function rencanaAksiRows(RencanaAksi $rencanaAksi): array
    {
        if ($rencanaAksi->items->isEmpty()) {
            return [['-', 'Belum ada item rencana aksi.', '-', '-', '-', '-', '-', '-']];
        }

        return $rencanaAksi->items->values()
            ->map(fn (RencanaAksiItem $item, int $index) => [
                (string) ($index + 1),
                (string) $item->aksi,
                $item->indikator ?: ($item->perjanjianKinerjaItem?->indikator ?: '-'),
                $this->periodItemLabel($item->periode_realisasi, $item->triwulan, $item->bulan),
                $this->targetText($item->target, $item->target_text),
                $this->money($item->anggaran),
                $this->hierarchyLabel($item->opdProgram?->nama, $item->opdKegiatan?->nama, $item->opdSubKegiatan?->nama),
                $item->penanggung_jawab ?: '-',
            ])
            ->all();
    }

    /**
     * @return array<int, array<int, string>>
     */
    private function rencanaAksiTriwulanRows(RencanaAksi $rencanaAksi): array
    {
        $rows = [];

        foreach (['tw1', 'tw2', 'tw3', 'tw4'] as $triwulan) {
            $items = $rencanaAksi->items->where('triwulan', $triwulan);
            $rows[] = [
                $this->triwulanLabel($triwulan),
                (string) $items->count(),
                $this->money($items->sum(fn (RencanaAksiItem $item) => (float) ($item->anggaran ?? 0))),
            ];
        }

        return $rows;
    }

    /**
     * @return array<int, array<int, string>>
     */
    private function realisasiRows(RealisasiKinerja $realisasi): array
    {
        if ($realisasi->programs->isEmpty()) {
            return [['-', 'Belum ada realisasi indikator.', '-', '-', '-', '-', '-', '-', '-', '-']];
        }

        return $realisasi->programs->values()
            ->map(fn (RealisasiProgram $program, int $index) => [
                (string) ($index + 1),
                (string) $program->indikator,
                $this->targetText($program->target, $program->target_text),
                $this->targetText($program->realisasi, $program->realisasi_text),
                $this->percent($program->capaian_persen),
                $program->status_capaian ? Str::headline($program->status_capaian) : '-',
                $this->money($program->anggaran),
                $this->money($program->realisasi_anggaran),
                $this->percent($program->serapan_anggaran_persen),
                $program->status_efisiensi ? Str::headline($program->status_efisiensi) : '-',
            ])
            ->all();
    }

    /**
     * @return array<int, array<int, string>>
     */
    private function realisasiAnalysisRows(RealisasiKinerja $realisasi): array
    {
        if ($realisasi->programs->isEmpty()) {
            return [['-', 'Belum ada realisasi indikator.', '-', '-']];
        }

        return $realisasi->programs->values()
            ->map(fn (RealisasiProgram $program, int $index) => [
                (string) ($index + 1),
                (string) $program->indikator,
                $program->kendala ?: '-',
                $program->tindak_lanjut ?: '-',
            ])
            ->all();
    }

    /**
     * @param  array<int, array{label: string, value: string}>  $identity
     * @return array<string, mixed>
     */
    private function metadata(Model $model, string $source, string $opdName, array $identity): array
    {
        return [
            'source' => $source.'_official_report',
            'agency_name' => 'PEMERINTAH KABUPATEN BANJARNEGARA',
            'office_name' => strtoupper($opdName),
            'address_line' => 'Kabupaten Banjarnegara, Jawa Tengah',
            'tahun' => $model->getAttribute('tahun') ?: now()->year,
            'identity' => $identity,
            'signature' => [
                'place_date' => 'Banjarnegara, '.now()->translatedFormat('d F Y'),
                'title' => $model->opd?->nama ? 'Kepala '.$model->opd->nama : 'Kepala OPD',
                'name' => $model->opd?->nama_kepala ?: '(nama pejabat)',
                'nip' => $model->opd?->nip_kepala ?: '-',
            ],
            'related_table' => $model->getTable(),
            'related_id' => $model->getKey(),
            'opd_id' => $model->getAttribute('opd_id'),
            'periode_tahun_id' => $model->getAttribute('periode_tahun_id'),
            'generated_at' => now()->toDateTimeString(),
        ];
    }

    private function opdName(Model $model): string
    {
        $opd = $model->opd;

        if (! $opd) {
            return 'OPD belum ditentukan';
        }

        return $opd->singkatan ? $opd->singkatan.' - '.$opd->nama : $opd->nama;
    }

    private function filename(string $prefix, string $opdName, int|string|null $tahun): string
    {
        $slug = Str::slug($prefix.'-'.$opdName.'-'.($tahun ?: now()->year));

        return ($slug ?: $prefix).'-'.now()->format('Ymd-His').'.pdf';
    }

    private function targetText(mixed $target, ?string $targetText): string
    {
        return filled($targetText) ? (string) $targetText : $this->number($target);
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
        return $status ? Str::headline($status) : '-';
    }

    private function periodLabel(RealisasiKinerja $realisasi): string
    {
        if ($realisasi->triwulan) {
            return $this->triwulanLabel($realisasi->triwulan);
        }

        if ($realisasi->bulan) {
            return 'Bulan '.$realisasi->bulan;
        }

        if ($realisasi->semester) {
            return 'Semester '.$realisasi->semester;
        }

        return Str::headline((string) $realisasi->periode_realisasi);
    }

    private function periodItemLabel(?string $periode, ?string $triwulan, ?int $bulan): string
    {
        if ($triwulan) {
            return $this->triwulanLabel($triwulan);
        }

        if ($bulan) {
            return 'Bulan '.$bulan;
        }

        return $periode ? Str::headline($periode) : '-';
    }

    private function triwulanLabel(string $triwulan): string
    {
        return [
            'tw1' => 'Triwulan I',
            'tw2' => 'Triwulan II',
            'tw3' => 'Triwulan III',
            'tw4' => 'Triwulan IV',
        ][$triwulan] ?? $triwulan;
    }

    private function hierarchyLabel(?string ...$labels): string
    {
        return collect($labels)->filter()->implode(' / ') ?: '-';
    }
}
