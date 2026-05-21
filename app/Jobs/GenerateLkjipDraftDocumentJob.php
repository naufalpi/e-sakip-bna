<?php

namespace App\Jobs;

use App\Models\Lkjip;
use App\Models\User;
use App\Services\Dokumen\DokumenStorageService;
use App\Services\Lkjip\LkjipDraftContentService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;

class GenerateLkjipDraftDocumentJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(
        public int $lkjipId,
        public int $requestedById,
    ) {}

    public function handle(LkjipDraftContentService $draftContentService, DokumenStorageService $dokumenStorageService): void
    {
        $lkjip = Lkjip::query()->findOrFail($this->lkjipId);
        $requestedBy = User::query()->findOrFail($this->requestedById);
        $draft = $draftContentService->build($lkjip);

        DB::transaction(function () use ($lkjip, $requestedBy, $draft, $dokumenStorageService) {
            foreach ($draft['bab'] as $bab) {
                $lkjip->bab()->updateOrCreate(
                    ['kode' => $bab['kode']],
                    [
                        'judul' => $bab['judul'],
                        'jenis' => $bab['jenis'],
                        'konten' => $bab['konten'],
                        'urutan' => $bab['urutan'],
                    ],
                );
            }

            $dokumenStorageService->storeGenerated(
                [
                    'opd_id' => $lkjip->opd_id,
                    'periode_tahun_id' => $lkjip->periode_tahun_id,
                    'jenis' => 'lkjip',
                    'judul' => 'Draft otomatis '.$lkjip->judul,
                    'nomor_dokumen' => $lkjip->nomor_dokumen,
                    'deskripsi' => 'Draft LKJIP yang dibuat otomatis dari data perencanaan, realisasi, dan evaluasi.',
                    'status' => 'draft',
                    'metadata' => $draft['metadata'],
                ],
                $draft['content'],
                $draft['filename'],
                $requestedBy,
                [
                    'type' => Lkjip::class,
                    'id' => $lkjip->id,
                    'label' => $lkjip->tahun.' - '.$lkjip->judul,
                ],
            );
        });
    }
}
