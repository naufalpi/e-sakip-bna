<?php

namespace App\Services\Lkjip;

use App\Services\Reports\ReportDocumentRenderService;

class LkjipDocumentRenderService
{
    public function __construct(private readonly ReportDocumentRenderService $renderer) {}

    /**
     * @param  array{
     *     filename: string,
     *     content: string,
     *     bab: array<int, array{kode: string, judul: string, jenis: string, konten: string, urutan: int}>,
     *     metadata: array<string, mixed>,
     *     tables?: array<int, array{title: string, headers: array<int, string>, rows: array<int, array<int, string>>}>
     * }  $draft
     * @return array{filename: string, mime_type: string, contents: string, label: string}
     */
    public function render(array $draft, string $format): array
    {
        return $this->renderer->render([
            'title' => (string) ($draft['metadata']['document_title'] ?? 'Laporan Kinerja Instansi Pemerintah'),
            'subtitle' => (string) ($draft['metadata']['document_subtitle'] ?? ''),
            'filename' => $draft['filename'],
            'sections' => collect($draft['bab'])
                ->map(fn (array $bab) => [
                    'heading' => $bab['kode'].' - '.$bab['judul'],
                    'content' => $bab['konten'],
                ])
                ->values()
                ->all(),
            'tables' => $draft['tables'] ?? [],
            'metadata' => $draft['metadata'],
        ], $format);
    }
}
