<?php

namespace App\Jobs;

use App\Models\Lkjip;
use App\Models\User;
use App\Services\Dokumen\DokumenStorageService;
use App\Services\Lkjip\LkjipDocumentRenderService;
use App\Services\Lkjip\LkjipDraftContentService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ExportLkjipDocumentJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(
        public int $lkjipId,
        public int $requestedById,
        public string $format,
    ) {}

    public function handle(
        LkjipDraftContentService $draftContentService,
        LkjipDocumentRenderService $renderService,
        DokumenStorageService $dokumenStorageService,
    ): void {
        $lkjip = Lkjip::query()->findOrFail($this->lkjipId);
        $requestedBy = User::query()->findOrFail($this->requestedById);
        $draft = $draftContentService->build($lkjip);
        $rendered = $renderService->render($draft, $this->format);

        $dokumenStorageService->storeGenerated(
            [
                'opd_id' => $lkjip->opd_id,
                'periode_tahun_id' => $lkjip->periode_tahun_id,
                'jenis' => 'lkjip',
                'judul' => 'Export '.$rendered['label'].' '.$lkjip->judul,
                'nomor_dokumen' => $lkjip->nomor_dokumen,
                'deskripsi' => 'Dokumen LKJIP format '.$rendered['label'].' yang dibuat otomatis dari data aplikasi.',
                'status' => 'draft',
                'metadata' => [
                    ...$draft['metadata'],
                    'export_format' => $this->format,
                    'renderer' => $rendered['label'],
                ],
            ],
            $rendered['contents'],
            $rendered['filename'],
            $requestedBy,
            [
                'type' => Lkjip::class,
                'id' => $lkjip->id,
                'label' => $lkjip->tahun.' - '.$lkjip->judul,
            ],
            $rendered['mime_type'],
        );
    }
}
