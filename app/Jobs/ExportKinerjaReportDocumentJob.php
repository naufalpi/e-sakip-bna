<?php

namespace App\Jobs;

use App\Models\PerjanjianKinerja;
use App\Models\RealisasiKinerja;
use App\Models\RencanaAksi;
use App\Models\User;
use App\Services\Dokumen\DokumenStorageService;
use App\Services\Kinerja\KinerjaReportContentService;
use App\Services\Reports\ReportDocumentRenderService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Queue\Queueable;
use InvalidArgumentException;

class ExportKinerjaReportDocumentJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(
        public string $module,
        public int $modelId,
        public int $requestedById,
        public string $format,
    ) {}

    public function handle(
        KinerjaReportContentService $contentService,
        ReportDocumentRenderService $renderService,
        DokumenStorageService $dokumenStorageService,
    ): void {
        $model = $this->findModel();
        $requestedBy = User::query()->findOrFail($this->requestedById);
        $report = $contentService->build($model, $this->module);
        $rendered = $renderService->render($report, $this->format);

        $dokumenStorageService->storeGenerated(
            [
                'opd_id' => $model->getAttribute('opd_id'),
                'periode_tahun_id' => $model->getAttribute('periode_tahun_id'),
                'jenis' => $this->documentType(),
                'judul' => $this->documentTitle($rendered['label'], $report['subtitle']),
                'nomor_dokumen' => $model->getAttribute('nomor_dokumen'),
                'deskripsi' => 'Dokumen '.$this->moduleLabel().' format '.$rendered['label'].' yang dibuat otomatis dari data aplikasi.',
                'status' => $model->getAttribute('status') ?: 'draft',
                'metadata' => [
                    ...$report['metadata'],
                    'export_format' => $this->format,
                    'renderer' => $rendered['label'],
                    'module' => $this->module,
                ],
            ],
            $rendered['contents'],
            $rendered['filename'],
            $requestedBy,
            [
                'type' => $model::class,
                'id' => $model->getKey(),
                'label' => $report['subtitle'],
            ],
            $rendered['mime_type'],
        );
    }

    private function findModel(): Model
    {
        return match ($this->module) {
            'perjanjian_kinerja' => PerjanjianKinerja::query()->findOrFail($this->modelId),
            'rencana_aksi' => RencanaAksi::query()->findOrFail($this->modelId),
            'realisasi_kinerja' => RealisasiKinerja::query()->findOrFail($this->modelId),
            default => throw new InvalidArgumentException('Modul laporan kinerja tidak valid.'),
        };
    }

    private function documentType(): string
    {
        return match ($this->module) {
            'perjanjian_kinerja' => 'perjanjian_kinerja',
            'rencana_aksi' => 'rencana_aksi',
            'realisasi_kinerja' => 'realisasi_kinerja',
            default => 'lainnya',
        };
    }

    private function moduleLabel(): string
    {
        return match ($this->module) {
            'perjanjian_kinerja' => 'Perjanjian Kinerja',
            'rencana_aksi' => 'Rencana Aksi',
            'realisasi_kinerja' => 'Laporan Realisasi Kinerja',
            default => 'Laporan Kinerja',
        };
    }

    private function documentTitle(string $formatLabel, string $subtitle): string
    {
        return $this->moduleLabel().' '.$formatLabel.' '.$subtitle;
    }
}
