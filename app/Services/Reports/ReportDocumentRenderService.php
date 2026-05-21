<?php

namespace App\Services\Reports;

use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use InvalidArgumentException;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\SimpleType\Jc;
use PhpOffice\PhpWord\Style\Table;

class ReportDocumentRenderService
{
    /**
     * @param  array{
     *     title: string,
     *     subtitle?: string|null,
     *     filename: string,
     *     sections: array<int, array{heading: string, content: string}>,
     *     tables?: array<int, array{title: string, headers: array<int, string>, rows: array<int, array<int, string>>}>,
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
        $options->set('isHtml5ParserEnabled', true);

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
        $phpWord->addTitleStyle(1, ['bold' => true, 'size' => 14], ['spaceBefore' => 120, 'spaceAfter' => 160]);
        $phpWord->addTitleStyle(2, ['bold' => true, 'size' => 12], ['spaceBefore' => 200, 'spaceAfter' => 100]);
        $phpWord->addTableStyle('OfficialTable', [
            'borderColor' => '555555',
            'borderSize' => 6,
            'cellMargin' => 80,
            'layout' => Table::LAYOUT_FIXED,
        ], [
            'bgColor' => 'E5E7EB',
        ]);

        $section = $phpWord->addSection([
            'marginTop' => 900,
            'marginRight' => 1100,
            'marginBottom' => 900,
            'marginLeft' => 1100,
        ]);

        $header = $section->addHeader();
        $header->addText($this->agencyName($report), ['bold' => true, 'size' => 9], ['alignment' => Jc::CENTER]);
        $header->addText($this->officeName($report), ['size' => 8], ['alignment' => Jc::CENTER]);

        $footer = $section->addFooter();
        $footer->addPreserveText('Halaman {PAGE} dari {NUMPAGES}', ['size' => 8], ['alignment' => Jc::RIGHT]);

        $this->addWordCover($section, $report);

        $section->addPageBreak();
        $this->addWordLetterhead($section, $report);

        foreach ($report['sections'] as $reportSection) {
            $section->addTitle($reportSection['heading'], 1);
            $this->addWordParagraphs($section, $reportSection['content']);
        }

        $this->addWordTables($section, $report['tables'] ?? []);
        $this->addWordSignature($section, $report);

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
            ->map(fn (array $section) => '<section class="chapter"><h2>'.e($section['heading']).'</h2>'.$this->paragraphsHtml($section['content']).'</section>')
            ->implode('');

        return '<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 24mm 20mm 22mm 20mm; }
        body { color: #111827; font-family: "DejaVu Sans", sans-serif; font-size: 11px; line-height: 1.55; }
        h1, h2, h3 { color: #0f172a; }
        h1 { font-size: 20px; margin: 0 0 6px; text-align: center; text-transform: uppercase; }
        h2 { border-bottom: 1px solid #64748b; font-size: 14px; margin: 22px 0 10px; padding-bottom: 4px; text-transform: uppercase; }
        h3 { font-size: 12px; margin: 16px 0 8px; }
        p { margin: 0 0 8px; text-align: justify; }
        table { border-collapse: collapse; margin: 8px 0 16px; width: 100%; }
        th, td { border: 1px solid #64748b; padding: 5px 6px; vertical-align: top; }
        th { background: #e5e7eb; text-align: center; }
        .cover { page-break-after: always; text-align: center; }
        .cover-title { font-size: 22px; font-weight: 700; margin-top: 92px; text-transform: uppercase; }
        .cover-subtitle { font-size: 14px; font-weight: 700; margin-top: 14px; text-transform: uppercase; }
        .cover-year { font-size: 18px; font-weight: 700; margin-top: 34px; }
        .cover-agency { font-size: 14px; font-weight: 700; margin-top: 140px; text-transform: uppercase; }
        .letterhead { border-bottom: 3px double #111827; margin-bottom: 18px; padding-bottom: 8px; text-align: center; }
        .letterhead .agency { font-size: 15px; font-weight: 700; text-transform: uppercase; }
        .letterhead .office { font-size: 13px; font-weight: 700; text-transform: uppercase; }
        .letterhead .address { font-size: 10px; margin-top: 2px; }
        .meta-table th { text-align: left; width: 34%; }
        .chapter { page-break-inside: avoid; }
        .signature { margin-left: auto; margin-top: 32px; page-break-inside: avoid; width: 260px; }
        .signature p { margin-bottom: 4px; text-align: center; }
        .signature .space { height: 54px; }
        .small { color: #475569; font-size: 9px; text-align: center; }
    </style>
</head>
<body>
    '.$this->coverHtml($report).'
    '.$this->letterheadHtml($report).'
    '.$this->identityTableHtml($report).'
    '.$sections.'
    '.$this->tablesHtml($report['tables'] ?? []).'
    '.$this->signatureHtml($report).'
    <div class="small">Dokumen dibuat otomatis dari E-SAKIP Kabupaten Banjarnegara pada '.e(now()->format('Y-m-d H:i:s')).'.</div>
</body>
</html>';
    }

    /**
     * @param  array<string, mixed>  $report
     */
    private function coverHtml(array $report): string
    {
        return '<div class="cover">
            <div class="cover-title">'.e($report['title']).'</div>
            <div class="cover-subtitle">'.e((string) ($report['subtitle'] ?? '')).'</div>
            <div class="cover-year">TAHUN '.e((string) $this->metadata($report, 'tahun', date('Y'))).'</div>
            <div class="cover-agency">'.e($this->agencyName($report)).'<br>'.e($this->officeName($report)).'</div>
        </div>';
    }

    /**
     * @param  array<string, mixed>  $report
     */
    private function letterheadHtml(array $report): string
    {
        return '<div class="letterhead">
            <div class="agency">'.e($this->agencyName($report)).'</div>
            <div class="office">'.e($this->officeName($report)).'</div>
            <div class="address">'.e($this->metadata($report, 'address_line', 'Kabupaten Banjarnegara, Jawa Tengah')).'</div>
        </div>';
    }

    /**
     * @param  array<string, mixed>  $report
     */
    private function identityTableHtml(array $report): string
    {
        $rows = collect($this->metadata($report, 'identity', []))
            ->map(fn (array $row) => '<tr><th>'.e((string) ($row['label'] ?? '')).'</th><td>'.e((string) ($row['value'] ?? '-')).'</td></tr>')
            ->implode('');

        return $rows ? '<table class="meta-table">'.$rows.'</table>' : '';
    }

    private function paragraphsHtml(string $content): string
    {
        return collect(preg_split("/\r\n|\n|\r/", $content) ?: [])
            ->map(fn (string $line) => trim($line) === '' ? '<p>&nbsp;</p>' : '<p>'.e($line).'</p>')
            ->implode('');
    }

    /**
     * @param  array<int, array{title: string, headers: array<int, string>, rows: array<int, array<int, string>>}>  $tables
     */
    private function tablesHtml(array $tables): string
    {
        return collect($tables)
            ->map(function (array $table) {
                $headers = collect($table['headers'])->map(fn (string $header) => '<th>'.e($header).'</th>')->implode('');
                $rows = collect($table['rows'])->map(function (array $row) {
                    return '<tr>'.collect($row)->map(fn (string $cell) => '<td>'.e($cell).'</td>')->implode('').'</tr>';
                })->implode('');

                return '<section><h2>'.e($table['title']).'</h2><table><thead><tr>'.$headers.'</tr></thead><tbody>'.$rows.'</tbody></table></section>';
            })
            ->implode('');
    }

    /**
     * @param  array<string, mixed>  $report
     */
    private function signatureHtml(array $report): string
    {
        $signature = $this->metadata($report, 'signature', []);

        return '<div class="signature">
            <p>'.e((string) ($signature['place_date'] ?? 'Banjarnegara, '.now()->translatedFormat('d F Y'))).'</p>
            <p>'.e((string) ($signature['title'] ?? 'Pejabat Penanggung Jawab')).'</p>
            <div class="space"></div>
            <p><strong>'.e((string) ($signature['name'] ?? '(nama pejabat)')).'</strong></p>
            <p>NIP. '.e((string) ($signature['nip'] ?? '-')).'</p>
        </div>';
    }

    private function addWordCover($section, array $report): void
    {
        $section->addTextBreak(4);
        $section->addText($report['title'], ['bold' => true, 'size' => 16], ['alignment' => Jc::CENTER, 'spaceAfter' => 180]);

        if (filled($report['subtitle'] ?? null)) {
            $section->addText((string) $report['subtitle'], ['bold' => true, 'size' => 13], ['alignment' => Jc::CENTER, 'spaceAfter' => 180]);
        }

        $section->addText('TAHUN '.$this->metadata($report, 'tahun', date('Y')), ['bold' => true, 'size' => 14], ['alignment' => Jc::CENTER]);
        $section->addTextBreak(8);
        $section->addText($this->agencyName($report), ['bold' => true, 'size' => 12], ['alignment' => Jc::CENTER]);
        $section->addText($this->officeName($report), ['bold' => true, 'size' => 12], ['alignment' => Jc::CENTER]);
    }

    private function addWordLetterhead($section, array $report): void
    {
        $section->addText($this->agencyName($report), ['bold' => true, 'size' => 12], ['alignment' => Jc::CENTER]);
        $section->addText($this->officeName($report), ['bold' => true, 'size' => 11], ['alignment' => Jc::CENTER]);
        $section->addText((string) $this->metadata($report, 'address_line', 'Kabupaten Banjarnegara, Jawa Tengah'), ['size' => 9], ['alignment' => Jc::CENTER]);
        $section->addLine(['weight' => 1.5, 'width' => 460, 'height' => 0]);
        $section->addTextBreak();

        $identity = $this->metadata($report, 'identity', []);

        if ($identity) {
            $table = $section->addTable('OfficialTable');

            foreach ($identity as $row) {
                $table->addRow();
                $table->addCell(2600)->addText((string) ($row['label'] ?? ''), ['bold' => true]);
                $table->addCell(6500)->addText((string) ($row['value'] ?? '-'));
            }

            $section->addTextBreak();
        }
    }

    private function addWordParagraphs($section, string $content): void
    {
        foreach (preg_split("/\r\n|\n|\r/", $content) ?: [] as $line) {
            if (trim($line) === '') {
                $section->addTextBreak();

                continue;
            }

            $section->addText($line, [], ['spaceAfter' => 80, 'alignment' => Jc::BOTH]);
        }
    }

    /**
     * @param  array<int, array{title: string, headers: array<int, string>, rows: array<int, array<int, string>>}>  $tables
     */
    private function addWordTables($section, array $tables): void
    {
        foreach ($tables as $tableData) {
            $section->addTitle($tableData['title'], 1);
            $table = $section->addTable('OfficialTable');
            $table->addRow();

            foreach ($tableData['headers'] as $header) {
                $table->addCell(1800)->addText($header, ['bold' => true]);
            }

            foreach ($tableData['rows'] as $row) {
                $table->addRow();

                foreach ($row as $cell) {
                    $table->addCell(1800)->addText($cell, ['size' => 9]);
                }
            }

            $section->addTextBreak();
        }
    }

    private function addWordSignature($section, array $report): void
    {
        $signature = $this->metadata($report, 'signature', []);

        $section->addTextBreak(2);
        $section->addText((string) ($signature['place_date'] ?? 'Banjarnegara, '.now()->translatedFormat('d F Y')), [], ['alignment' => Jc::RIGHT]);
        $section->addText((string) ($signature['title'] ?? 'Pejabat Penanggung Jawab'), [], ['alignment' => Jc::RIGHT]);
        $section->addTextBreak(3);
        $section->addText((string) ($signature['name'] ?? '(nama pejabat)'), ['bold' => true], ['alignment' => Jc::RIGHT]);
        $section->addText('NIP. '.(string) ($signature['nip'] ?? '-'), [], ['alignment' => Jc::RIGHT]);
    }

    /**
     * @param  array<string, mixed>  $report
     */
    private function agencyName(array $report): string
    {
        return (string) $this->metadata($report, 'agency_name', 'PEMERINTAH KABUPATEN BANJARNEGARA');
    }

    /**
     * @param  array<string, mixed>  $report
     */
    private function officeName(array $report): string
    {
        return (string) $this->metadata($report, 'office_name', 'E-SAKIP KABUPATEN BANJARNEGARA');
    }

    /**
     * @param  array<string, mixed>  $report
     */
    private function metadata(array $report, string $key, mixed $default = null): mixed
    {
        return data_get($report, 'metadata.'.$key, $default);
    }

    private function filename(string $filename, string $extension): string
    {
        $basename = pathinfo($filename, PATHINFO_FILENAME);

        return ($basename ? Str::slug($basename) : 'dokumen').'.'.$extension;
    }
}
