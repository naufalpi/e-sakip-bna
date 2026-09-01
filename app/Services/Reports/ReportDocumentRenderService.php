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
        $isPerformanceAgreement = data_get($report, 'metadata.layout') === 'perjanjian_kinerja';
        $html = $isPerformanceAgreement
            ? view('reports.perjanjian-kinerja', ['report' => $report, 'browserPrint' => false])->render()
            : $this->html($report);

        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper($isPerformanceAgreement
            ? [0, 0, 595.2756, 935.4331]
            : 'A4');
        $dompdf->render();

        return $dompdf->output();
    }

    /**
     * @param  array<string, mixed>  $report
     */
    private function renderWord(array $report): string
    {
        if (data_get($report, 'metadata.layout') === 'perjanjian_kinerja') {
            return $this->renderPerformanceAgreementWord($report);
        }

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

        $sectionSettings = [
            'marginTop' => 900,
            'marginRight' => 1100,
            'marginBottom' => 900,
            'marginLeft' => 1100,
        ];

        $section = $phpWord->addSection($sectionSettings);

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

        $this->addWordAppendixList($section, $report['tables'] ?? []);
        $this->addWordTables($section, $report['tables'] ?? []);
        $this->addWordSignature($section, $report);

        return $this->saveWord($phpWord);
    }

    /**
     * @param  array<string, mixed>  $report
     */
    private function renderPerformanceAgreementWord(array $report): string
    {
        $document = (array) data_get($report, 'metadata.pk_document', []);
        $first = (array) ($document['first_party'] ?? []);
        $second = (array) ($document['second_party'] ?? []);
        $isBupati = (bool) ($document['is_bupati'] ?? false);
        $isHeadOfOpd = ($document['level'] ?? null) === 'kepala_opd';
        $isStructural = ($document['level'] ?? null) === 'struktural';
        $isLowerCascading = (bool) ($document['is_lower_cascading'] ?? false);
        $isManualIndividual = (bool) ($document['is_manual_individual'] ?? false);
        $usesActivityFormat = $isLowerCascading || $isManualIndividual;
        $usesOfficialFormat = $isHeadOfOpd || $isStructural || $usesActivityFormat;

        $phpWord = new PhpWord;
        $phpWord->setDefaultFontName('Arial');
        $phpWord->setDefaultFontSize(12);
        $phpWord->addTableStyle('PkPlain', [
            'borderColor' => 'FFFFFF',
            'borderSize' => 1,
            'cellMargin' => 20,
            'layout' => Table::LAYOUT_FIXED,
        ]);
        $phpWord->addTableStyle('PkMatrix', [
            'borderColor' => '000000',
            'borderSize' => 6,
            'cellMargin' => 55,
            'layout' => Table::LAYOUT_FIXED,
        ]);

        $section = $phpWord->addSection([
            'pageSizeW' => 11906,
            'pageSizeH' => 18709,
            'marginTop' => 737,
            'marginRight' => 1021,
            'marginBottom' => 794,
            'marginLeft' => 1021,
        ]);

        $this->addPkWordLetterhead($section, $report, $document);
        $section->addText(
            (string) ($document['title'] ?? 'PERJANJIAN KINERJA'),
            ['bold' => true, 'size' => 16],
            ['alignment' => Jc::CENTER, 'spaceBefore' => 260, 'spaceAfter' => 230],
        );

        $paragraphStyle = ['alignment' => Jc::BOTH, 'spaceAfter' => 100, 'lineHeight' => 1.5];
        if ($isBupati) {
            $section->addText('Dalam rangka mewujudkan manajemen pemerintahan yang efektif, transparan, akuntabel, dan berorientasi pada hasil, saya yang bertanda tangan di bawah ini:', [], $paragraphStyle);
            $this->addPkWordIdentity($section, $first);
            $section->addText('berjanji akan mewujudkan target kinerja yang seharusnya sesuai lampiran perjanjian ini, dalam rangka mencapai target kinerja jangka menengah sebagaimana telah ditetapkan dalam dokumen perencanaan. Keberhasilan dan kegagalan pencapaian target kinerja tersebut menjadi tanggung jawab kami.', [], $paragraphStyle);
        } else {
            $section->addText('Dalam rangka mewujudkan manajemen pemerintahan yang efektif, transparan dan akuntabel serta berorientasi pada hasil, kami yang bertanda tangan di bawah ini:', [], $paragraphStyle);
            $this->addPkWordIdentity($section, $first);
            $this->addPkWordPartyRole($section, 'Selanjutnya disebut sebagai ', 'PIHAK PERTAMA');
            $this->addPkWordIdentity($section, $second);
            $this->addPkWordPartyRole($section, 'Selaku atasan PIHAK PERTAMA, selanjutnya disebut sebagai ', 'PIHAK KEDUA');
            $section->addText('PIHAK PERTAMA berjanji akan mewujudkan target kinerja yang seharusnya, sesuai lampiran perjanjian ini, dalam rangka mencapai target kinerja jangka menengah seperti yang telah ditetapkan dalam dokumen perencanaan. Keberhasilan dan kegagalan pencapaian target kinerja tersebut menjadi tanggung jawab kami.', [], $paragraphStyle);
            $section->addText('PIHAK KEDUA akan melakukan supervisi yang diperlukan serta melakukan evaluasi terhadap capaian kinerja dari perjanjian kinerja ini dan mengambil tindakan yang diperlukan dalam rangka pemberian penghargaan dan sanksi.', [], $paragraphStyle);
        }

        $this->addPkWordSignatures($section, $document, false, ! $isBupati);

        $section->addPageBreak();
        $section->addText('LAMPIRAN PERJANJIAN KINERJA TAHUN '.($document['year'] ?? ''), ['bold' => true, 'size' => 11], ['alignment' => Jc::CENTER, 'spaceAfter' => 80, 'lineHeight' => 1.5]);
        $section->addText(
            (string) ($usesOfficialFormat ? ($document['office_name'] ?? '') : ($first['position'] ?? ($document['office_name'] ?? ''))),
            ['bold' => true, 'size' => 11],
            ['alignment' => Jc::CENTER, 'spaceAfter' => 180, 'lineHeight' => 1.5],
        );

        if ($isStructural || $usesActivityFormat) {
            $identity = $section->addTable('PkPlain');
            foreach ([
                ['Nama Pejabat', $document['employee_name'] ?? ($first['name'] ?? '-')],
                ['Unit Kerja', $document['work_unit'] ?? ($document['office_name'] ?? '-')],
            ] as [$label, $value]) {
                $identity->addRow();
                $identity->addCell(1900, $this->pkInvisibleCellStyle())->addText($label, ['bold' => true, 'size' => 11], ['lineHeight' => 1.5]);
                $identity->addCell(250, $this->pkInvisibleCellStyle())->addText(':', ['size' => 11], ['lineHeight' => 1.5]);
                $identity->addCell(7450, $this->pkInvisibleCellStyle())->addText((string) $value, ['size' => 11], ['lineHeight' => 1.5]);
            }
            $section->addTextBreak(1);
        }

        $matrixTitle = $isManualIndividual
            ? 'SASARAN KEGIATAN DAN SASARAN SUB KEGIATAN'
            : ($isLowerCascading
                ? 'SASARAN KEGIATAN DAN SASARAN SUB KEGIATAN ***'
                : ($isStructural
                    ? 'SASARAN PROGRAM DAN SASARAN KEGIATAN **'
                    : 'TUJUAN DAN SASARAN STRATEGIS'.($isHeadOfOpd ? ' *' : '')));
        $matrix = $section->addTable('PkMatrix');
        $matrix->addRow();
        foreach ([[650, 'NO'], [4300, $matrixTitle], [3100, 'INDIKATOR KINERJA'], [1350, 'TARGET']] as [$width, $label]) {
            $matrix->addCell($width, ['valign' => 'center'])->addText($label, ['bold' => true, 'size' => 11], ['alignment' => Jc::CENTER, 'lineHeight' => 1.5]);
        }

        $groups = (array) ($document['performance_groups'] ?? []);
        if ($groups === []) {
            $matrix->addRow();
            $matrix->addCell(9400, ['gridSpan' => 4])->addText('Belum ada matriks kinerja.', ['size' => 11], ['alignment' => Jc::CENTER, 'lineHeight' => 1.5]);
        } else {
            foreach ($groups as $group) {
                $indicators = (array) ($group['indicators'] ?? []);
                $indicators = $indicators ?: [['name' => '-', 'target' => '-', 'unit' => '-']];
                foreach ($indicators as $index => $indicator) {
                    $matrix->addRow();
                    $mergeStyle = ['vMerge' => $index === 0 ? 'restart' : 'continue', 'valign' => 'top'];
                    $matrix->addCell(650, $mergeStyle)->addText($index === 0 ? (string) ($group['number'] ?? '') : '', ['size' => 11], ['alignment' => Jc::CENTER, 'lineHeight' => 1.5]);
                    $matrix->addCell(4300, $mergeStyle)->addText($index === 0 ? (string) ($group['performance'] ?? '-') : '', ['size' => 11], ['lineHeight' => 1.5]);
                    $matrix->addCell(3100)->addText((string) ($indicator['name'] ?? '-'), ['size' => 11], ['lineHeight' => 1.5]);
                    $unit = ($indicator['unit'] ?? '-') === '-' ? '' : ' '.(string) $indicator['unit'];
                    $matrix->addCell(1350)->addText(trim((string) ($indicator['target'] ?? '-').$unit), ['size' => 11], ['alignment' => Jc::CENTER, 'lineHeight' => 1.5]);
                }
            }
        }

        $section->addTextBreak(1);
        $this->addPkWordBudgetTable($section, $document, $usesActivityFormat, $isStructural, $usesOfficialFormat);
        $this->addPkWordSignatures($section, $document, true, $isStructural || $usesActivityFormat);

        if ($isStructural) {
            $section->addText('**) Untuk disesuaikan dengan kondisi pada masing-masing Perangkat Daerah; apabila tidak melaksanakan kegiatan, maka diisi sampai ke sasaran program.', ['italic' => true, 'size' => 11], ['alignment' => Jc::BOTH, 'spaceBefore' => 180, 'lineHeight' => 1.5]);
        } elseif ($isManualIndividual) {
            $section->addText('***) Untuk kolom kedua disesuaikan dengan kondisi yang dilaksanakan oleh pejabat pengawas pada masing-masing Perangkat Daerah (misalnya hanya melaksanakan sub kegiatan maka diisi hanya sasaran sub kegiatan, demikian juga indikatornya menyesuaikan).', ['italic' => true, 'size' => 11], ['alignment' => Jc::BOTH, 'spaceBefore' => 180, 'lineHeight' => 1.5]);
        } elseif ($usesActivityFormat) {
            $section->addText('***) Untuk kolom kedua disesuaikan dengan kondisi yang dilaksanakan oleh pejabat pengawas pada masing-masing Perangkat Daerah. Apabila hanya melaksanakan sub kegiatan, maka diisi hanya sasaran sub kegiatan; demikian juga indikatornya menyesuaikan.', ['italic' => true, 'size' => 11], ['alignment' => Jc::BOTH, 'spaceBefore' => 180, 'lineHeight' => 1.5]);
        }

        return $this->saveWord($phpWord);
    }

    /**
     * Word tetap dapat menampilkan border bawaan pada tabel layout saat borderSize nol.
     * Border putih eksplisit memastikan tabel bantu kop, identitas, dan tanda tangan
     * tidak ikut tercetak, sementara tabel matriks tetap memakai style PkMatrix.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function pkInvisibleCellStyle(array $overrides = []): array
    {
        return array_merge([
            'borderTopColor' => 'FFFFFF',
            'borderTopSize' => 1,
            'borderRightColor' => 'FFFFFF',
            'borderRightSize' => 1,
            'borderBottomColor' => 'FFFFFF',
            'borderBottomSize' => 1,
            'borderLeftColor' => 'FFFFFF',
            'borderLeftSize' => 1,
        ], $overrides);
    }

    /** @param array<string, mixed> $document */
    private function addPkWordLetterhead($section, array $report, array $document): void
    {
        $table = $section->addTable('PkPlain');
        $table->addRow();
        $logoCell = $table->addCell(1350, $this->pkInvisibleCellStyle(['valign' => 'center']));
        if ($path = $this->wordLogoPath($report)) {
            $logoCell->addImage($path, ['height' => 91, 'alignment' => Jc::CENTER]);
        }
        $copy = $table->addCell(8250, $this->pkInvisibleCellStyle(['valign' => 'center']));
        $copy->addText((string) ($document['agency_name'] ?? 'PEMERINTAH KABUPATEN BANJARNEGARA'), ['size' => 15], ['alignment' => Jc::CENTER, 'spaceAfter' => 0]);
        $copy->addText((string) ($document['office_name'] ?? 'PERANGKAT DAERAH'), ['bold' => true, 'size' => 18], ['alignment' => Jc::CENTER, 'spaceAfter' => 0]);
        $address = trim((string) ($document['address'] ?? 'Kabupaten Banjarnegara'));
        if (filled($document['telephone'] ?? null)) {
            $address .= ' Telepon '.$document['telephone'];
        }
        if (filled($document['fax'] ?? null)) {
            $address .= ' Faksimile '.$document['fax'];
        }
        $copy->addText($address, ['size' => 10], ['alignment' => Jc::CENTER, 'spaceAfter' => 0]);
        $contacts = collect([
            filled($document['website'] ?? null) ? 'Website '.$document['website'] : null,
            filled($document['email'] ?? null) ? 'Surat Elektronik '.$document['email'] : null,
        ])->filter()->implode(' ');
        if ($contacts !== '') {
            $copy->addText($contacts, ['size' => 10], ['alignment' => Jc::CENTER, 'spaceAfter' => 0]);
        }
        $copy->addText(trim((string) ($document['city'] ?? 'BANJARNEGARA').' '.(string) ($document['postal_code'] ?? '')), ['size' => 11], ['alignment' => Jc::CENTER]);
        $section->addLine(['weight' => 1.5, 'width' => 520, 'height' => 0]);
    }

    /** @param array<string, mixed> $party */
    private function addPkWordIdentity($section, array $party): void
    {
        $table = $section->addTable('PkPlain');
        foreach ([['Nama', $party['name'] ?? '-'], ['Jabatan', $party['position'] ?? '-']] as [$label, $value]) {
            $table->addRow();
            $table->addCell(1350, $this->pkInvisibleCellStyle())->addText($label, ['size' => 12], ['lineHeight' => 1.5]);
            $table->addCell(220, $this->pkInvisibleCellStyle())->addText(':', ['size' => 12], ['lineHeight' => 1.5]);
            $table->addCell(8030, $this->pkInvisibleCellStyle())->addText((string) $value, ['size' => 12], ['lineHeight' => 1.5]);
        }
    }

    private function addPkWordPartyRole($section, string $prefix, string $role): void
    {
        $run = $section->addTextRun(['spaceAfter' => 80, 'lineHeight' => 1.5]);
        $run->addText($prefix, ['size' => 12]);
        $run->addText($role, ['bold' => true, 'size' => 12]);
    }

    /** @param array<string, mixed> $document */
    private function addPkWordSignatures($section, array $document, bool $appendix, bool $showPartyLabels): void
    {
        $first = (array) ($document['first_party'] ?? []);
        $second = (array) ($document['second_party'] ?? []);
        $fontSize = $appendix ? 11 : 12;
        $paragraphStyle = ['alignment' => Jc::CENTER, 'lineHeight' => 1.5];
        $dateTable = $section->addTable('PkPlain');
        $dateTable->addRow();
        $dateTable->addCell(4800, $this->pkInvisibleCellStyle());
        $dateTable->addCell(4800, $this->pkInvisibleCellStyle())->addText(
            (string) ($document['place_date'] ?? 'Banjarnegara, ....................'),
            ['size' => $fontSize],
            array_merge($paragraphStyle, ['spaceBefore' => $appendix ? 260 : 100]),
        );

        $table = $section->addTable('PkPlain');
        $table->addRow();
        foreach ([[$second, 'Pihak Kedua'], [$first, 'Pihak Pertama']] as [$party, $label]) {
            $cell = $table->addCell(4800, $this->pkInvisibleCellStyle(['valign' => 'top']));
            if ($showPartyLabels) {
                $cell->addText($label, ['size' => $fontSize], array_merge($paragraphStyle, ['spaceAfter' => 20]));
            }
            $cell->addText(mb_strtoupper((string) ($party['position'] ?? '-')), ['bold' => true, 'size' => $fontSize], array_merge($paragraphStyle, ['spaceAfter' => 520]));
            $cell->addText((string) ($party['name'] ?? '-'), ['bold' => true, 'underline' => 'single', 'size' => $fontSize], array_merge($paragraphStyle, ['spaceAfter' => 0]));
            if (filled($party['rank'] ?? null)) {
                $cell->addText((string) $party['rank'], ['size' => $fontSize], array_merge($paragraphStyle, ['spaceAfter' => 0]));
            }
            if (filled($party['nip'] ?? null)) {
                $cell->addText('NIP. '.$party['nip'], ['size' => $fontSize], $paragraphStyle);
            }
        }
    }

    /** @param array<string, mixed> $document */
    private function addPkWordBudgetTable($section, array $document, bool $usesActivityFormat, bool $isStructural, bool $usesOfficialFormat): void
    {
        $table = $section->addTable('PkMatrix');
        $table->addRow();
        foreach ([[4200, $usesActivityFormat ? 'KEGIATAN DAN SUB KEGIATAN' : 'PROGRAM'], [2900, 'ANGGARAN'], [2500, 'KETERANGAN']] as [$width, $label]) {
            $table->addCell($width, ['valign' => 'center'])->addText($label, ['bold' => true, 'size' => 11], ['alignment' => Jc::CENTER, 'lineHeight' => 1.5]);
        }

        if ($usesActivityFormat) {
            $activities = (array) ($document['activity_budget_groups'] ?? []);
            if ($activities === []) {
                $table->addRow();
                $table->addCell(4200)->addText('Belum ada kegiatan atau sub kegiatan.', ['size' => 11], ['lineHeight' => 1.5]);
                $table->addCell(2900)->addText('Rp 0', ['size' => 11], ['lineHeight' => 1.5]);
                $table->addCell(2500)->addText('-', ['size' => 11], ['lineHeight' => 1.5]);

                return;
            }

            foreach ($activities as $index => $activity) {
                $subActivities = (array) ($activity['sub_activities'] ?? []);
                $table->addRow();
                $nameCell = $table->addCell(4200);
                $nameCell->addText(($index + 1).'. '.($activity['name'] ?? '-'), ['size' => 11], ['lineHeight' => 1.5]);
                foreach ($subActivities as $subIndex => $subActivity) {
                    $nameCell->addText(chr(97 + $subIndex).'.   '.($subActivity['name'] ?? '-'), ['size' => 11], ['indentation' => ['left' => 260], 'lineHeight' => 1.5]);
                }
                $budgetCell = $table->addCell(2900);
                $budgetCell->addText((string) ($activity['budget_label'] ?? 'Rp 0'), ['size' => 11], ['lineHeight' => 1.5]);
                foreach ($subActivities as $subActivity) {
                    $budgetCell->addText((string) ($subActivity['budget_label'] ?? 'Rp 0'), ['size' => 11], ['lineHeight' => 1.5]);
                }
                $table->addCell(2500)->addText((string) ($activity['note'] ?? '-'), ['size' => 11], ['lineHeight' => 1.5]);
            }

            return;
        }

        $programs = (array) ($document['programs'] ?? []);
        if ($programs === []) {
            $table->addRow();
            $table->addCell(4200)->addText('Belum ada program.', ['size' => 11], ['lineHeight' => 1.5]);
            $table->addCell(2900)->addText('Rp 0', ['size' => 11], ['lineHeight' => 1.5]);
            $table->addCell(2500)->addText('-', ['size' => 11], ['lineHeight' => 1.5]);

            return;
        }

        foreach ($programs as $index => $program) {
            $table->addRow();
            $nameCell = $table->addCell(4200);
            $nameCell->addText(($index + 1).'. '.($program['name'] ?? '-'), ['size' => 11], ['lineHeight' => 1.5]);
            if ($isStructural) {
                foreach ((array) ($program['activities'] ?? []) as $activityIndex => $activity) {
                    $nameCell->addText(chr(97 + $activityIndex).'.   '.($activity['name'] ?? '-'), ['size' => 11], ['lineHeight' => 1.5]);
                }
            }
            $table->addCell(2900)->addText((string) ($program['budget_label'] ?? 'Rp 0'), ['size' => 11], ['lineHeight' => 1.5]);
            $table->addCell(2500)->addText((string) ($program['note'] ?? '-'), ['size' => 11], ['lineHeight' => 1.5]);
        }

        if (! $usesOfficialFormat) {
            $table->addRow();
            $table->addCell(4200)->addText('Total Anggaran', ['bold' => true, 'size' => 11], ['lineHeight' => 1.5]);
            $table->addCell(2900)->addText((string) ($document['total_budget_label'] ?? 'Rp 0'), ['bold' => true, 'size' => 11], ['lineHeight' => 1.5]);
            $table->addCell(2500);
        }
    }

    private function saveWord(PhpWord $phpWord): string
    {
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
        .cover-logo { height: 118px; margin: 70px auto 20px; width: auto; }
        .cover-title { font-size: 22px; font-weight: 700; margin-top: 92px; text-transform: uppercase; }
        .cover-subtitle { font-size: 14px; font-weight: 700; margin-top: 14px; text-transform: uppercase; }
        .cover-year { font-size: 18px; font-weight: 700; margin-top: 34px; }
        .cover-agency { font-size: 14px; font-weight: 700; margin-top: 140px; text-transform: uppercase; }
        .letterhead { border-bottom: 3px double #111827; margin-bottom: 18px; padding-bottom: 8px; }
        .letterhead-inner { display: table; width: 100%; }
        .letterhead-logo { display: table-cell; text-align: center; vertical-align: top; width: 76px; }
        .letterhead-logo img { height: 68px; width: auto; }
        .letterhead-text { display: table-cell; padding-right: 76px; text-align: center; vertical-align: top; }
        .letterhead .agency { font-size: 15px; font-weight: 700; text-transform: uppercase; }
        .letterhead .office { font-size: 13px; font-weight: 700; text-transform: uppercase; }
        .letterhead .address { font-size: 10px; margin-top: 2px; }
        .document-number { font-size: 10px; margin: 10px 0 12px; text-align: center; }
        .meta-table th { text-align: left; width: 34%; }
        .chapter { page-break-inside: avoid; }
        .appendix-list { margin: 18px 0; page-break-inside: avoid; }
        .appendix-list ol { margin: 6px 0 0 18px; padding: 0; }
        .signature-page { page-break-before: always; }
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
    '.$this->appendixListHtml($report['tables'] ?? []).'
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
            '.$this->logoHtml($report, 'cover-logo').'
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
            <div class="letterhead-inner">
                <div class="letterhead-logo">'.$this->logoHtml($report).'</div>
                <div class="letterhead-text">
                    <div class="agency">'.e($this->agencyName($report)).'</div>
                    <div class="office">'.e($this->officeName($report)).'</div>
                    <div class="address">'.e($this->metadata($report, 'address_line', 'Kabupaten Banjarnegara, Jawa Tengah')).'</div>
                </div>
            </div>
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
     * @param  array<int, array{title: string, headers: array<int, string>, rows: array<int, array<int, string>>}>  $tables
     */
    private function appendixListHtml(array $tables): string
    {
        if ($tables === []) {
            return '';
        }

        $items = collect($tables)
            ->map(fn (array $table) => '<li>'.e($table['title']).'</li>')
            ->implode('');

        return '<section class="appendix-list">
            <h2>Daftar Lampiran</h2>
            <ol>'.$items.'</ol>
        </section>';
    }

    /**
     * @param  array<string, mixed>  $report
     */
    private function signatureHtml(array $report): string
    {
        $signature = $this->metadata($report, 'signature', []);

        return '<section class="signature-page">
            <h2>Halaman Pengesahan</h2>
            <p>Dokumen ini diterbitkan sebagai keluaran resmi E-SAKIP Kabupaten Banjarnegara dan menjadi bahan monitoring, pelaporan, serta evaluasi akuntabilitas kinerja.</p>
            <div class="document-number">Nomor Dokumen: '.e($this->documentNumber($report)).'</div>
            <div class="signature">
            <p>'.e((string) ($signature['place_date'] ?? 'Banjarnegara, '.now()->translatedFormat('d F Y'))).'</p>
            <p>'.e((string) ($signature['title'] ?? 'Pejabat Penanggung Jawab')).'</p>
            <div class="space"></div>
            <p><strong>'.e((string) ($signature['name'] ?? '(nama pejabat)')).'</strong></p>
            <p>NIP. '.e((string) ($signature['nip'] ?? '-')).'</p>
            </div>
        </section>';
    }

    private function addWordCover($section, array $report): void
    {
        if ($path = $this->wordLogoPath($report)) {
            $section->addImage($path, ['height' => 86, 'alignment' => Jc::CENTER]);
        }

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
        if ($path = $this->wordLogoPath($report)) {
            $section->addImage($path, ['height' => 54, 'alignment' => Jc::CENTER]);
        }

        $section->addText($this->agencyName($report), ['bold' => true, 'size' => 12], ['alignment' => Jc::CENTER]);
        $section->addText($this->officeName($report), ['bold' => true, 'size' => 11], ['alignment' => Jc::CENTER]);
        $section->addText((string) $this->metadata($report, 'address_line', 'Kabupaten Banjarnegara, Jawa Tengah'), ['size' => 9], ['alignment' => Jc::CENTER]);
        $section->addText('Nomor Dokumen: '.$this->documentNumber($report), ['size' => 9], ['alignment' => Jc::CENTER]);
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

    /**
     * @param  array<int, array{title: string, headers: array<int, string>, rows: array<int, array<int, string>>}>  $tables
     */
    private function addWordAppendixList($section, array $tables): void
    {
        if ($tables === []) {
            return;
        }

        $section->addTitle('Daftar Lampiran', 1);

        foreach ($tables as $index => $table) {
            $section->addText(($index + 1).'. '.$table['title'], [], ['spaceAfter' => 80]);
        }

        $section->addTextBreak();
    }

    private function addWordSignature($section, array $report): void
    {
        $signature = $this->metadata($report, 'signature', []);

        $section->addPageBreak();
        $section->addTitle('Halaman Pengesahan', 1);
        $section->addText('Dokumen ini diterbitkan sebagai keluaran resmi E-SAKIP Kabupaten Banjarnegara dan menjadi bahan monitoring, pelaporan, serta evaluasi akuntabilitas kinerja.', [], ['alignment' => Jc::BOTH, 'spaceAfter' => 120]);
        $section->addText('Nomor Dokumen: '.$this->documentNumber($report), ['bold' => true], ['alignment' => Jc::CENTER, 'spaceAfter' => 160]);
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
    private function documentNumber(array $report): string
    {
        return (string) ($this->metadata($report, 'document_number')
            ?: $this->metadata($report, 'nomor_dokumen')
            ?: $this->metadata($report, 'nomor_lhe')
            ?: '-');
    }

    /**
     * @param  array<string, mixed>  $report
     */
    private function logoHtml(array $report, string $class = ''): string
    {
        $dataUri = $this->logoDataUri($report);

        if (! $dataUri) {
            return '';
        }

        return '<img class="'.e($class).'" src="'.e($dataUri).'" alt="Lambang Kabupaten Banjarnegara">';
    }

    /**
     * @param  array<string, mixed>  $report
     */
    private function logoDataUri(array $report): ?string
    {
        $path = $this->logoPath($report);

        if (! $path || ! File::exists($path)) {
            return null;
        }

        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $mimeType = match ($extension) {
            'svg' => 'image/svg+xml',
            'png' => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            default => null,
        };

        if (! $mimeType) {
            return null;
        }

        return 'data:'.$mimeType.';base64,'.base64_encode(File::get($path));
    }

    /**
     * @param  array<string, mixed>  $report
     */
    private function wordLogoPath(array $report): ?string
    {
        $path = $this->logoPath($report);

        if (! $path || ! File::exists($path)) {
            return null;
        }

        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return in_array($extension, ['png', 'jpg', 'jpeg'], true) ? $path : null;
    }

    /**
     * @param  array<string, mixed>  $report
     */
    private function logoPath(array $report): ?string
    {
        $path = $this->metadata($report, 'logo_path', public_path('images/logo-banjarnegara.png'));

        return is_string($path) && $path !== '' ? $path : null;
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
