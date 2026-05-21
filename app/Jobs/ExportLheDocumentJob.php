<?php

namespace App\Jobs;

use App\Models\EvaluasiSakip;
use App\Models\User;
use App\Services\Dokumen\DokumenStorageService;
use App\Services\Evaluasi\LheDocumentContentService;
use App\Services\Reports\ReportDocumentRenderService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ExportLheDocumentJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(
        public int $evaluasiSakipId,
        public int $requestedById,
        public string $format,
    ) {}

    public function handle(
        LheDocumentContentService $contentService,
        ReportDocumentRenderService $renderService,
        DokumenStorageService $dokumenStorageService,
    ): void {
        $evaluasi = EvaluasiSakip::query()->findOrFail($this->evaluasiSakipId);
        $requestedBy = User::query()->findOrFail($this->requestedById);
        $report = $contentService->build($evaluasi);
        $rendered = $renderService->render($report, $this->format);

        $dokumenStorageService->storeGenerated(
            [
                'opd_id' => $evaluasi->opd_id,
                'periode_tahun_id' => $evaluasi->periode_tahun_id,
                'jenis' => 'lhe',
                'judul' => 'LHE '.$rendered['label'].' '.$report['subtitle'],
                'nomor_dokumen' => $evaluasi->lhe?->nomor_lhe,
                'deskripsi' => 'Dokumen LHE format '.$rendered['label'].' yang dibuat otomatis dari data evaluasi SAKIP.',
                'status' => $evaluasi->lhe?->status ?: 'draft',
                'metadata' => [
                    ...$report['metadata'],
                    'export_format' => $this->format,
                    'renderer' => $rendered['label'],
                ],
            ],
            $rendered['contents'],
            $rendered['filename'],
            $requestedBy,
            [
                'type' => EvaluasiSakip::class,
                'id' => $evaluasi->id,
                'label' => $report['subtitle'],
            ],
            $rendered['mime_type'],
        );
    }
}
