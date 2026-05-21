<?php

namespace App\Services\Reports;

use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use InvalidArgumentException;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;

class ReportDocumentRenderService
{
    /**
     * @param  array{
     *     title: string,
     *     subtitle?: string|null,
     *     filename: string,
     *     sections: array<int, array{heading: string, content: string}>,
     *     metadata?: array<string, mixed>
     * }  $report
     * @return array{filename: string, mime_type: string, contents: string, label: string}
     */
    public function render(array $report, string $format): array
    {
        return match ($format) {
            'pdf' => [
                'filename' => $this->filename($report['filename'], 'pdf'),
                'mime_type' => 'application/pdf',
                'contents' => $this->renderPdf($report),
                'label' => 'PDF',
            ],
            'word' => [
                'filename' => $this->filename($report['filename'], 'docx'),
                'mime_type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'contents' => $this->renderWord($report),
                'label' => 'Word',
            ],
            default => throw new InvalidArgumentException('Format dokumen tidak valid.'),
        };
    }

    /**
     * @param  array<string, mixed>  $report
     */
    private function renderPdf(array $report): string
    {
        $options = new Options;
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isRemoteEnabled', false);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($this->html($report), 'UTF-8');
        $dompdf->setPaper('A4');
        $dompdf->render();

        return $dompdf->output();
    }

    /**
     * @param  array<string, mixed>  $report
     */
    private function renderWord(array $report): string
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

        $section->addTitle($report['title'], 1);

        if (filled($report['subtitle'] ?? null)) {
            $section->addText((string) $report['subtitle'], ['italic' => true]);
        }

        $section->addText('Dibuat otomatis: '.now()->format('Y-m-d H:i:s'), ['italic' => true, 'size' => 9]);
        $section->addTextBreak();

        foreach ($report['sections'] as $reportSection) {
            $section->addTitle($reportSection['heading'], 2);

            foreach (preg_split("/\r\n|\n|\r/", $reportSection['content']) ?: [] as $line) {
                if (trim($line) === '') {
                    $section->addTextBreak();

                    continue;
                }

                $section->addText($line, [], ['spaceAfter' => 80]);
            }
        }

        $cacheDirectory = storage_path('framework/cache');
        File::ensureDirectoryExists($cacheDirectory);

        $temporaryPath = $cacheDirectory.'/report-export-'.Str::uuid().'.docx';

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
     * @param  array<string, mixed>  $report
     */
    private function html(array $report): string
    {
        $sections = collect($report['sections'])
            ->map(fn (array $section) => '<section><h2>'.e($section['heading']).'</h2><div class="content">'.nl2br(e($section['content'])).'</div></section>')
            ->implode('');

        $subtitle = filled($report['subtitle'] ?? null)
            ? '<div class="subtitle">'.e((string) $report['subtitle']).'</div>'
            : '';

        return '<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 30mm 22mm 25mm 22mm; }
        body { color: #111827; font-family: "DejaVu Sans", sans-serif; font-size: 11px; line-height: 1.55; }
        h1 { color: #0f172a; font-size: 19px; margin: 0 0 6px; text-align: center; }
        h2 { border-bottom: 1px solid #cbd5e1; color: #0f172a; font-size: 14px; margin: 22px 0 10px; padding-bottom: 4px; }
        .subtitle, .meta { color: #475569; font-size: 10px; margin-bottom: 8px; text-align: center; }
        .content { white-space: normal; }
        section { page-break-inside: avoid; }
    </style>
</head>
<body>
    <h1>'.e($report['title']).'</h1>
    '.$subtitle.'
    <div class="meta">Dibuat otomatis: '.e(now()->format('Y-m-d H:i:s')).'</div>
    '.$sections.'
</body>
</html>';
    }

    private function filename(string $filename, string $extension): string
    {
        $basename = pathinfo($filename, PATHINFO_FILENAME);

        return ($basename ? Str::slug($basename) : 'dokumen').'.'.$extension;
    }
}
