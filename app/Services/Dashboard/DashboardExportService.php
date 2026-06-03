<?php

namespace App\Services\Dashboard;

use Symfony\Component\HttpFoundation\StreamedResponse;

class DashboardExportService
{
    /**
     * @param  array<string, mixed>  $dashboard
     */
    public function csv(array $dashboard): StreamedResponse
    {
        $tahun = (int) data_get($dashboard, 'dashboard.tahun', now()->year);
        $scope = (string) data_get($dashboard, 'dashboard.type', 'kabupaten');
        $filename = 'dashboard-e-sakip-'.$scope.'-'.$tahun.'-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($dashboard) {
            $output = fopen('php://output', 'w');
            fwrite($output, "\xEF\xBB\xBF");

            $this->writeSection($output, 'Ringkasan Dashboard');
            $this->writeRows($output, [
                ['Indikator', 'Nilai'],
                ['Tahun', data_get($dashboard, 'dashboard.tahun')],
                ['Jumlah OPD', data_get($dashboard, 'stats.opd_count')],
                ['Rata-rata capaian', data_get($dashboard, 'stats.avg_capaian')],
                ['Rata-rata evaluasi', data_get($dashboard, 'stats.avg_evaluasi')],
                ['Rekomendasi terbuka', data_get($dashboard, 'stats.rekomendasi_terbuka_count')],
                ['Workflow pending', data_get($dashboard, 'stats.workflow_pending_count')],
            ]);

            $this->writeSection($output, 'Kelengkapan Modul');
            $this->writeRows($output, [['Modul', 'OPD Terisi', 'Total OPD', 'Persen']]);
            foreach (data_get($dashboard, 'moduleCompletion', []) as $row) {
                $this->writeRows($output, [[
                    $row['label'] ?? '-',
                    $row['count'] ?? 0,
                    $row['total'] ?? 0,
                    ($row['percent'] ?? 0).'%',
                ]]);
            }

            $this->writeSection($output, 'Ranking Monitoring OPD');
            $this->writeRows($output, [['Ranking', 'OPD', 'Progress', 'Capaian', 'Nilai Evaluasi', 'Predikat', 'Rekomendasi Terbuka', 'Skor']]);
            foreach (data_get($dashboard, 'opdPerformanceRanking', []) as $row) {
                $this->writeRows($output, [[
                    $row['rank'] ?? '-',
                    ($row['singkatan'] ?? null) ?: ($row['nama'] ?? '-'),
                    ($row['progress_percent'] ?? 0).'%',
                    $this->percentValue($row['capaian_persen'] ?? null),
                    $row['nilai_evaluasi'] ?? '-',
                    $row['predikat'] ?? '-',
                    $row['rekomendasi_terbuka_count'] ?? 0,
                    $row['monitoring_score'] ?? 0,
                ]]);
            }

            $this->writeSection($output, 'Drilldown Capaian Indikator');
            $this->writeRows($output, [['OPD', 'Indikator', 'Periode', 'Target', 'Realisasi', 'Capaian', 'Status Capaian', 'Serapan', 'Efisiensi']]);
            foreach (data_get($dashboard, 'achievementIndicatorDrilldown', []) as $row) {
                $this->writeRows($output, [[
                    $row['opd'] ?? '-',
                    $row['indikator'] ?? '-',
                    $row['triwulan_label'] ?: ($row['periode_realisasi'] ?? '-'),
                    $row['target_text'] ?: ($row['target'] ?? '-'),
                    $row['realisasi_text'] ?: ($row['realisasi'] ?? '-'),
                    $this->percentValue($row['capaian_persen'] ?? null),
                    $row['status_capaian'] ?? '-',
                    $this->percentValue($row['serapan_anggaran_persen'] ?? null),
                    $row['status_efisiensi'] ?? '-',
                ]]);
            }

            $this->writeSection($output, 'Drilldown per Sasaran');
            $this->writeRows($output, [['OPD', 'Sasaran', 'Jumlah Indikator', 'Rata-rata Capaian', 'Merah', 'Kuning', 'Hijau']]);
            foreach (data_get($dashboard, 'sasaranDrilldown', []) as $row) {
                $this->writeRows($output, [[
                    $row['opd'] ?? '-',
                    $row['sasaran'] ?? '-',
                    $row['indicator_count'] ?? 0,
                    $this->percentValue($row['avg_capaian'] ?? null),
                    $row['merah_count'] ?? 0,
                    $row['kuning_count'] ?? 0,
                    $row['hijau_count'] ?? 0,
                ]]);
            }

            $this->writeSection($output, 'Drilldown per Program');
            $this->writeRows($output, [['OPD', 'Program', 'Jumlah Indikator', 'Rata-rata Capaian', 'Serapan', 'Anggaran', 'Realisasi Anggaran', 'Efisiensi Dominan']]);
            foreach (data_get($dashboard, 'programDrilldown', []) as $row) {
                $this->writeRows($output, [[
                    $row['opd'] ?? '-',
                    $row['program'] ?? '-',
                    $row['indicator_count'] ?? 0,
                    $this->percentValue($row['avg_capaian'] ?? null),
                    $this->percentValue($row['avg_serapan'] ?? null),
                    $row['total_anggaran'] ?? 0,
                    $row['total_realisasi_anggaran'] ?? 0,
                    $row['dominant_efficiency_status'] ?? '-',
                ]]);
            }

            fclose($output);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * @param  resource  $output
     */
    private function writeSection($output, string $title): void
    {
        fputcsv($output, []);
        fputcsv($output, [$title]);
    }

    /**
     * @param  resource  $output
     * @param  array<int, array<int, mixed>>  $rows
     */
    private function writeRows($output, array $rows): void
    {
        foreach ($rows as $row) {
            fputcsv($output, $row);
        }
    }

    private function percentValue(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '-';
        }

        return number_format((float) $value, 2, ',', '.').'%';
    }
}
