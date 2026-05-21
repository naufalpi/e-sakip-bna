<?php

namespace App\Services\Lkjip;

use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use InvalidArgumentException;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;

class LkjipDocumentRenderService
{
    /**
     * @param  array{
     *     filename: string,
     *     content: string,
     *     bab: array<int, array{kode: string, judul: string, jenis: string, konten: string, urutan: int}>,
     *     metadata: array<string, mixed>
     * }  $draft
     * @return array{filename: string, mime_type: string, contents: string, label: string}
     */
    public function render(array $draft, string $format): array
    {
        return match ($format) {
            'pdf' => [
                'filename' => $this->filename($draft['filename'], 'pdf'),
                'mime_type' => 'application/pdf',
                'contents' => $this->renderPdf($draft),
                'label' => 'PDF',
            ],
            'word' => [
                'filename' => $this->filename($draft['filename'], 'docx'),
                'mime_type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'contents' => $this->renderWord($draft),
                'label' => 'Word',
            ],
            default => throw new InvalidArgumentException('Format export LKJIP tidak valid.'),
        };
    }

    /**
     * @param  array<string, mixed>  $draft
     */
    private function renderPdf(array $draft): string
    {
        $options = new Options;
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isRemoteEnabled', false);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($this->html($draft), 'UTF-8');
        $dompdf->setPaper('A4');
        $dompdf->render();

        return $dompdf->output();
    }

    /**
     * @param  array<string, mixed>  $draft
     */
    private function renderWord(array $draft): string
    {
        $phpWord = new PhpWord;
        $phpWord->setDefaultFontName('Arial');
        $phpWord->setDefaultFontSize(11);
        $phpWord->addTitleStyle(1, ['bold' => true, 'size' => 16], ['spaceAfter' => 240]);
        $phpWord->addTitleStyle(2, ['bold' => true, 'size' => 13], ['spaceBefore' => 240, 'spaceAfter' => 120]);

        $section = $phpWord->addSection([
            'marginTop' => 1200,
            'marginRight' => 1000,
            'marginBottom' => 1200,
            'marginLeft' => 1000,
        ]);

        $section->addTitle('Draft LKJIP', 1);
        $section->addText('Pemerintah Kabupaten Banjarnegara', ['bold' => true]);
        $section->addText('Dibuat otomatis: '.now()->format('Y-m-d H:i:s'), ['italic' => true, 'size' => 9]);
        $section->addTextBreak();

        foreach ($draft['bab'] as $bab) {
            $section->addTitle($bab['kode'].' - '.$bab['judul'], 2);

            foreach (preg_split("/\r\n|\n|\r/", $bab['konten']) ?: [] as $line) {
                if (trim($line) === '') {
                    $section->addTextBreak();

                    continue;
                }

                $section->addText($line, [], ['spaceAfter' => 80]);
            }
        }

        $cacheDirectory = storage_path('framework/cache');
        File::ensureDirectoryExists($cacheDirectory);

        $temporaryPath = $cacheDirectory.'/lkjip-export-'.Str::uuid().'.docx';

        try {
            IOFactory::createWriter($phpWord, 'Word2007')->save($temporaryPath);

            return (string) file_get_contents($temporaryPath);
        } finally {
            if (File::exists($temporaryPath)) {
                File::delete($temporaryPath);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $draft
     */
    private function html(array $draft): string
    {
        $sections = collect($draft['bab'])
            ->map(fn (array $bab) => '<section><h2>'.e($bab['kode'].' - '.$bab['judul']).'</h2><div class="content">'.nl2br(e($bab['konten'])).'</div></section>')
            ->implode('');

        return '<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 30mm 22mm 25mm 22mm; }
        body { color: #111827; font-family: "DejaVu Sans", sans-serif; font-size: 11px; line-height: 1.55; }
        h1 { color: #0f172a; font-size: 20px; margin: 0 0 6px; text-align: center; }
        h2 { border-bottom: 1px solid #cbd5e1; color: #0f172a; font-size: 14px; margin: 22px 0 10px; padding-bottom: 4px; }
        .meta { color: #475569; font-size: 10px; margin-bottom: 18px; text-align: center; }
        .content { white-space: normal; }
        section { page-break-inside: avoid; }
    </style>
</head>
<body>
    <h1>Draft LKJIP</h1>
    <div class="meta">Pemerintah Kabupaten Banjarnegara<br>Dibuat otomatis: '.e(now()->format('Y-m-d H:i:s')).'</div>
    '.$sections.'
</body>
</html>';
    }

    private function filename(string $draftFilename, string $extension): string
    {
        $basename = pathinfo($draftFilename, PATHINFO_FILENAME);

        return ($basename ? Str::slug($basename) : 'draft-lkjip').'.'.$extension;
    }
}
