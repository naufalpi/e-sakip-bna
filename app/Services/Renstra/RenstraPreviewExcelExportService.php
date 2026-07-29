<?php

namespace App\Services\Renstra;

use App\Models\OpdKegiatan;
use App\Models\OpdProgram;
use App\Models\OpdSubKegiatan;
use App\Models\ProgramPemerintahan;
use App\Models\RenstraOpd;
use Illuminate\Support\Collection;
use RuntimeException;
use ZipArchive;

class RenstraPreviewExcelExportService
{
    /**
     * @return array{filename: string, content: string}
     */
    public function make(RenstraOpd $renstra): array
    {
        $this->loadRelations($renstra);

        $years = $this->targetYears($renstra);
        $rows = $this->tableRows($renstra, $years);

        return [
            'filename' => $this->filename($renstra),
            'content' => $this->buildWorkbook($renstra, $years, $rows),
        ];
    }

    private function loadRelations(RenstraOpd $renstra): void
    {
        $renstra->loadMissing([
            'opd:id,kode,nama,singkatan',
            'tujuan.indikator.satuanIndikator:id,nama,simbol',
            'tujuan.indikator.targets.periodeTahun:id,tahun,nama',
            'tujuan.sasaran.indikator.satuanIndikator:id,nama,simbol',
            'tujuan.sasaran.indikator.targets.periodeTahun:id,tahun,nama',
            'tujuan.sasaran.programs.programPemerintahan:id,kode,nama,bidang_urusan_id',
            'tujuan.sasaran.programs.programPemerintahan.bidangUrusan:id,urusan_pemerintahan_id,kode,nama',
            'tujuan.sasaran.programs.programPemerintahan.bidangUrusan.urusanPemerintahan:id,kode,nama',
            'tujuan.sasaran.programs.programPemerintahan.bidangUrusan.opdPengampu:id',
            'tujuan.sasaran.programs.programRpjmd:id,kode,nama,program_pemerintahan_id',
            'tujuan.sasaran.programs.programRpjmd.programPemerintahan:id,kode,nama,bidang_urusan_id',
            'tujuan.sasaran.programs.programRpjmd.programPemerintahan.bidangUrusan:id,urusan_pemerintahan_id,kode,nama',
            'tujuan.sasaran.programs.programRpjmd.programPemerintahan.bidangUrusan.urusanPemerintahan:id,kode,nama',
            'tujuan.sasaran.programs.programRpjmd.programPemerintahan.bidangUrusan.opdPengampu:id',
            'tujuan.sasaran.programs.programRpjmd.programPemerintahanReferences:id,kode,nama,bidang_urusan_id',
            'tujuan.sasaran.programs.programRpjmd.programPemerintahanReferences.bidangUrusan:id,urusan_pemerintahan_id,kode,nama',
            'tujuan.sasaran.programs.programRpjmd.programPemerintahanReferences.bidangUrusan.urusanPemerintahan:id,kode,nama',
            'tujuan.sasaran.programs.programRpjmd.programPemerintahanReferences.bidangUrusan.opdPengampu:id',
            'tujuan.sasaran.programs.indikator.satuanIndikator:id,nama,simbol',
            'tujuan.sasaran.programs.indikator.targets.periodeTahun:id,tahun,nama',
            'tujuan.sasaran.programs.kegiatan.kegiatanPemerintahan:id,kode,nama,program_pemerintahan_id',
            'tujuan.sasaran.programs.kegiatan.indikator.satuanIndikator:id,nama,simbol',
            'tujuan.sasaran.programs.kegiatan.indikator.targets.periodeTahun:id,tahun,nama',
            'tujuan.sasaran.programs.kegiatan.subKegiatan.subKegiatanPemerintahan:id,kode,nama,kegiatan_pemerintahan_id',
            'tujuan.sasaran.programs.kegiatan.subKegiatan.anggaranTahunan.periodeTahun:id,tahun,nama',
            'tujuan.sasaran.programs.kegiatan.subKegiatan.indikator.satuanIndikator:id,nama,simbol',
            'tujuan.sasaran.programs.kegiatan.subKegiatan.indikator.targets.periodeTahun:id,tahun,nama',
        ]);
    }

    /**
     * @return array<int, int>
     */
    private function targetYears(RenstraOpd $renstra): array
    {
        return range((int) $renstra->tahun_awal, (int) $renstra->tahun_akhir + 1);
    }

    /**
     * @param  array<int, int>  $years
     * @return array<int, array<string, mixed>>
     */
    private function tableRows(RenstraOpd $renstra, array $years): array
    {
        $rows = [];
        $baselineYear = (int) $renstra->tahun_awal - 1;

        $renstra->tujuan
            ->sortBy('urutan')
            ->each(function ($tujuan) use (&$rows, $baselineYear, $years, $renstra): void {
                $this->appendRowsForNode(
                    $rows,
                    'tujuan',
                    'TUJUAN OPD: '.$this->nodeName($tujuan->tujuan),
                    $tujuan->indikator,
                    $baselineYear,
                    $years,
                    fn () => '-',
                );

                $tujuan->sasaran
                    ->sortBy('urutan')
                    ->each(function ($sasaran) use (&$rows, $baselineYear, $years, $renstra): void {
                        $this->appendRowsForNode(
                            $rows,
                            'sasaran',
                            'SASARAN OPD: '.$this->nodeName($sasaran->sasaran),
                            $sasaran->indikator,
                            $baselineYear,
                            $years,
                            fn () => '-',
                        );

                        $sasaran->programs
                            ->groupBy(fn (OpdProgram $program) => $this->bidangKey($program, $renstra))
                            ->each(function (Collection $programGroup) use (&$rows, $baselineYear, $years, $renstra): void {
                                /** @var OpdProgram|null $firstProgram */
                                $firstProgram = $programGroup->first();

                                if (! $firstProgram) {
                                    return;
                                }

                                $rows[] = [
                                    'level' => 'bidang',
                                    'label' => $this->bidangLabel($firstProgram, $renstra),
                                    'indicator' => '',
                                    'baseline' => '',
                                    'values' => $this->blankValues($years),
                                ];

                                $programGroup->sortBy('urutan')->each(function (OpdProgram $program) use (&$rows, $baselineYear, $years): void {
                                    $this->appendRowsForNode(
                                        $rows,
                                        'program',
                                        $this->nodeName($program->nama),
                                        $program->indikator,
                                        $baselineYear,
                                        $years,
                                        fn (int $year) => $this->formatCurrency($this->programBudget($program, $year)),
                                    );

                                    $program->kegiatan->sortBy('urutan')->each(function (OpdKegiatan $kegiatan) use (&$rows, $baselineYear, $years): void {
                                        $this->appendRowsForNode(
                                            $rows,
                                            'kegiatan',
                                            $this->nodeName($kegiatan->nama),
                                            $kegiatan->indikator,
                                            $baselineYear,
                                            $years,
                                            fn (int $year) => $this->formatCurrency($this->kegiatanBudget($kegiatan, $year)),
                                        );

                                        $kegiatan->subKegiatan->sortBy('urutan')->each(function (OpdSubKegiatan $subKegiatan) use (&$rows, $baselineYear, $years): void {
                                            $this->appendRowsForNode(
                                                $rows,
                                                'sub_kegiatan',
                                                $this->nodeName($subKegiatan->nama),
                                                $subKegiatan->indikator,
                                                $baselineYear,
                                                $years,
                                                fn (int $year) => $this->formatCurrency($this->subKegiatanBudget($subKegiatan, $year)),
                                            );
                                        });
                                    });
                                });
                            });
                    });
            });

        return $rows;
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @param  Collection<int, mixed>  $indicators
     * @param  array<int, int>  $years
     */
    private function appendRowsForNode(
        array &$rows,
        string $level,
        string $label,
        Collection $indicators,
        int $baselineYear,
        array $years,
        callable $budgetResolver,
    ): void {
        if ($indicators->isEmpty()) {
            $rows[] = [
                'level' => $level,
                'label' => $label,
                'indicator' => '',
                'baseline' => '',
                'values' => $this->yearValues(null, $years, $budgetResolver),
            ];

            return;
        }

        $indicators->values()->each(function ($indicator, int $index) use (&$rows, $level, $label, $baselineYear, $years, $budgetResolver) {
            $rows[] = [
                'level' => $level,
                'label' => $index === 0 ? $label : '',
                'indicator' => $this->indicatorText($indicator),
                'baseline' => $this->targetForYear($indicator, $baselineYear),
                'values' => $this->yearValues($indicator, $years, $budgetResolver, $index === 0),
            ];
        });
    }

    /**
     * @param  array<int, int>  $years
     * @return array<int, array{year: int, target: string, pagu: string}>
     */
    private function yearValues(mixed $indicator, array $years, callable $budgetResolver, bool $showBudget = true): array
    {
        return array_map(fn (int $year) => [
            'year' => $year,
            'target' => $indicator ? $this->targetForYear($indicator, $year) : '',
            'pagu' => $showBudget ? $budgetResolver($year) : '',
        ], $years);
    }

    /**
     * @param  array<int, int>  $years
     * @return array<int, array{year: int, target: string, pagu: string}>
     */
    private function blankValues(array $years): array
    {
        return array_map(fn (int $year) => ['year' => $year, 'target' => '', 'pagu' => ''], $years);
    }

    private function bidangKey(OpdProgram $program, RenstraOpd $renstra): string
    {
        $programPemerintahan = $this->programPemerintahan($program, $renstra);

        return (string) ($programPemerintahan?->bidangUrusan?->id ?? 'tanpa-bidang');
    }

    private function bidangLabel(OpdProgram $program, RenstraOpd $renstra): string
    {
        $bidang = $this->programPemerintahan($program, $renstra)?->bidangUrusan;

        if (! $bidang) {
            return 'BIDANG URUSAN BELUM DIPILIH';
        }

        return trim((string) $bidang->nama);
    }

    private function programPemerintahan(OpdProgram $program, RenstraOpd $renstra): ?ProgramPemerintahan
    {
        $opdId = filled($renstra->opd_id) ? (int) $renstra->opd_id : null;

        return $program->programPemerintahan
            ?? $program->programRpjmd?->preferredProgramPemerintahanReferenceForOpd($opdId)
            ?? $this->programPemerintahanByCode($program->kode, $renstra)
            ?? $program->programRpjmd?->programPemerintahan;
    }

    private function programPemerintahanByCode(?string $kode, RenstraOpd $renstra): ?ProgramPemerintahan
    {
        if (blank($kode)) {
            return null;
        }

        return ProgramPemerintahan::query()
            ->where('kode', $kode)
            ->where(function ($query) use ($renstra): void {
                $query->whereNull('tahun_awal')
                    ->orWhere('tahun_awal', '<=', (int) $renstra->tahun_awal);
            })
            ->where(function ($query) use ($renstra): void {
                $query->whereNull('tahun_akhir')
                    ->orWhere('tahun_akhir', '>=', (int) $renstra->tahun_awal);
            })
            ->with('bidangUrusan.urusanPemerintahan')
            ->orderByDesc('tahun_awal')
            ->first();
    }

    private function programBudget(OpdProgram $program, int $year): float
    {
        return $program->kegiatan->sum(fn (OpdKegiatan $kegiatan) => $this->kegiatanBudget($kegiatan, $year));
    }

    private function kegiatanBudget(OpdKegiatan $kegiatan, int $year): float
    {
        return $kegiatan->subKegiatan->sum(fn (OpdSubKegiatan $subKegiatan) => $this->subKegiatanBudget($subKegiatan, $year));
    }

    private function subKegiatanBudget(OpdSubKegiatan $subKegiatan, int $year): float
    {
        $budget = $subKegiatan->anggaranTahunan->first(
            fn ($anggaran) => (int) $anggaran->periodeTahun?->tahun === $year,
        );

        return $this->numeric($budget?->anggaran);
    }

    private function targetForYear(mixed $indicator, int $year): string
    {
        $target = $indicator->targets->first(
            fn ($target) => (int) $target->periodeTahun?->tahun === $year,
        );

        return $this->targetText($target?->target_text ?: $target?->target);
    }

    private function indicatorText(mixed $indicator): string
    {
        return trim((string) $indicator->indikator) ?: '-';
    }

    private function nodeLabel(?string $kode, ?string $nama): string
    {
        return trim(collect([$kode, $nama])->filter(fn ($value) => filled($value))->join(' - '));
    }

    private function nodeName(?string $nama): string
    {
        return trim((string) $nama) ?: '-';
    }

    private function targetText(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        if (is_numeric($value)) {
            return rtrim(rtrim(number_format((float) $value, 4, ',', ''), '0'), ',');
        }

        return (string) $value;
    }

    private function numeric(mixed $value): float
    {
        if ($value === null || $value === '') {
            return 0.0;
        }

        if (is_numeric($value)) {
            return (float) $value;
        }

        $raw = str_replace(' ', '', (string) $value);

        if (preg_match('/^-?\d{1,3}(\.\d{3})+(,\d+)?$/', $raw) === 1) {
            $raw = str_replace('.', '', $raw);
        }

        $raw = str_replace(',', '.', $raw);

        return is_numeric($raw) ? (float) $raw : 0.0;
    }

    private function formatCurrency(float $value): string
    {
        if ($value <= 0) {
            return '';
        }

        return number_format($value, 0, ',', '.');
    }

    /**
     * @param  array<int, int>  $years
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function buildWorkbook(RenstraOpd $renstra, array $years, array $rows): string
    {
        if (! class_exists(ZipArchive::class)) {
            throw new RuntimeException('Ekstensi PHP ZipArchive belum aktif, export .xlsx tidak bisa dibuat.');
        }

        $path = tempnam(sys_get_temp_dir(), 'sakip_renstra_preview_');
        $zip = new ZipArchive;

        if ($path === false || $zip->open($path, ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('File export sementara tidak bisa dibuat.');
        }

        $zip->addFromString('[Content_Types].xml', $this->contentTypes());
        $zip->addFromString('_rels/.rels', $this->rootRelationships());
        $zip->addFromString('docProps/app.xml', $this->appProperties());
        $zip->addFromString('docProps/core.xml', $this->coreProperties($renstra));
        $zip->addFromString('xl/workbook.xml', $this->workbookXml());
        $zip->addFromString('xl/_rels/workbook.xml.rels', $this->workbookRelationships());
        $zip->addFromString('xl/styles.xml', $this->stylesXml());
        $zip->addFromString('xl/worksheets/sheet1.xml', $this->worksheetXml($renstra, $years, $rows));
        $zip->close();

        $content = file_get_contents($path);
        @unlink($path);

        if (! is_string($content)) {
            throw new RuntimeException('File export .xlsx gagal dibaca.');
        }

        return $content;
    }

    /**
     * @param  array<int, int>  $years
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function worksheetXml(RenstraOpd $renstra, array $years, array $rows): string
    {
        $lastColumn = $this->columnName(3 + (count($years) * 2));
        $lastRow = max(count($rows) + 3, 4);

        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<dimension ref="A1:'.$lastColumn.$lastRow.'"/>'
            .'<sheetViews><sheetView workbookViewId="0"><pane ySplit="3" topLeftCell="A4" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>'
            .$this->columnsXml(count($years))
            .'<sheetData>'
            .$this->headerRowsXml($renstra, $years);

        foreach ($rows as $index => $row) {
            $xml .= $this->dataRowXml($index + 4, $row);
        }

        if ($rows === []) {
            $xml .= '<row r="4" ht="30" customHeight="1">'.$this->inlineCell('A4', 'Belum ada data Renstra OPD.', 2).'</row>';
        }

        return $xml.'</sheetData>'.$this->mergeCellsXml($years).'</worksheet>';
    }

    /**
     * @param  array<int, int>  $years
     */
    private function headerRowsXml(RenstraOpd $renstra, array $years): string
    {
        $baselineYear = (int) $renstra->tahun_awal - 1;
        $rowOne = [
            $this->inlineCell('A1', 'TUJUAN / SASARAN / BIDANG URUSAN / PROGRAM / KEGIATAN / SUB. KEGIATAN OUTPUT', 1),
            $this->inlineCell('B1', 'INDIKATOR OUTCOME / OUTPUT', 1),
            $this->inlineCell('C1', "BASE LINE\n{$baselineYear}", 1),
            $this->inlineCell('D1', 'TARGET DAN PAGU INDIKATIF TAHUN', 1),
        ];

        $rowTwo = [];
        $rowThree = [];
        $column = 4;

        foreach ($years as $year) {
            $rowTwo[] = $this->inlineCell($this->columnName($column).'2', $this->yearLabel($renstra, $year), 1);
            $rowThree[] = $this->inlineCell($this->columnName($column).'3', 'Target', 1);
            $rowThree[] = $this->inlineCell($this->columnName($column + 1).'3', 'Pagu', 1);
            $column += 2;
        }

        return '<row r="1" ht="48" customHeight="1">'.implode('', $rowOne).'</row>'
            .'<row r="2" ht="24" customHeight="1">'.implode('', $rowTwo).'</row>'
            .'<row r="3" ht="24" customHeight="1">'.implode('', $rowThree).'</row>';
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function dataRowXml(int $rowNumber, array $row): string
    {
        $style = match ($row['level']) {
            'tujuan' => 8,
            'sasaran' => 9,
            'bidang' => 4,
            'program' => 5,
            'kegiatan' => 6,
            'sub_kegiatan' => 7,
            default => 2,
        };

        $cells = [
            $this->inlineCell('A'.$rowNumber, (string) $row['label'], $style),
            $this->inlineCell('B'.$rowNumber, (string) $row['indicator'], $style),
            $this->inlineCell('C'.$rowNumber, (string) $row['baseline'], $style),
        ];

        $column = 4;
        foreach ($row['values'] as $value) {
            $cells[] = $this->inlineCell($this->columnName($column).$rowNumber, (string) $value['target'], $style);
            $cells[] = $this->inlineCell($this->columnName($column + 1).$rowNumber, (string) $value['pagu'], $style);
            $column += 2;
        }

        return '<row r="'.$rowNumber.'" ht="70" customHeight="1">'.implode('', $cells).'</row>';
    }

    private function columnsXml(int $yearCount): string
    {
        $columns = [
            '<col min="1" max="1" width="32" customWidth="1"/>',
            '<col min="2" max="2" width="28" customWidth="1"/>',
            '<col min="3" max="3" width="13" customWidth="1"/>',
        ];

        $lastColumn = 3 + ($yearCount * 2);

        if ($lastColumn >= 4) {
            $columns[] = '<col min="4" max="'.$lastColumn.'" width="13" customWidth="1"/>';
        }

        return '<cols>'.implode('', $columns).'</cols>';
    }

    /**
     * @param  array<int, int>  $years
     */
    private function mergeCellsXml(array $years): string
    {
        $lastColumn = $this->columnName(3 + (count($years) * 2));
        $merges = [
            '<mergeCell ref="A1:A3"/>',
            '<mergeCell ref="B1:B3"/>',
            '<mergeCell ref="C1:C3"/>',
            '<mergeCell ref="D1:'.$lastColumn.'1"/>',
        ];

        $column = 4;
        foreach ($years as $year) {
            $merges[] = '<mergeCell ref="'.$this->columnName($column).'2:'.$this->columnName($column + 1).'2"/>';
            $column += 2;
        }

        return '<mergeCells count="'.count($merges).'">'.implode('', $merges).'</mergeCells>';
    }

    private function inlineCell(string $reference, string $value, int $style): string
    {
        return '<c r="'.$reference.'" t="inlineStr" s="'.$style.'"><is><t xml:space="preserve">'.$this->escape($value).'</t></is></c>';
    }

    private function workbookXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            .'<sheets><sheet name="Preview Renstra OPD" sheetId="1" r:id="rId1"/></sheets></workbook>';
    }

    private function workbookRelationships(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            .'<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            .'</Relationships>';
    }

    private function contentTypes(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            .'<Default Extension="xml" ContentType="application/xml"/>'
            .'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            .'<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            .'<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            .'<Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>'
            .'<Override PartName="/docProps/app.xml" ContentType="application/vnd.openxmlformats-officedocument.extended-properties+xml"/>'
            .'</Types>';
    }

    private function rootRelationships(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            .'<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/>'
            .'<Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/extended-properties" Target="docProps/app.xml"/>'
            .'</Relationships>';
    }

    private function stylesXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<fonts count="2">'
            .'<font><sz val="10"/><name val="Arial"/></font>'
            .'<font><b/><sz val="10"/><name val="Arial"/></font>'
            .'</fonts>'
            .'<fills count="9">'
            .'<fill><patternFill patternType="none"/></fill>'
            .'<fill><patternFill patternType="gray125"/></fill>'
            .'<fill><patternFill patternType="solid"><fgColor rgb="FFFFFFFF"/><bgColor indexed="64"/></patternFill></fill>'
            .'<fill><patternFill patternType="solid"><fgColor rgb="FFDCEBFF"/><bgColor indexed="64"/></patternFill></fill>'
            .'<fill><patternFill patternType="solid"><fgColor rgb="FFFFF2CC"/><bgColor indexed="64"/></patternFill></fill>'
            .'<fill><patternFill patternType="solid"><fgColor rgb="FFE2F0D9"/><bgColor indexed="64"/></patternFill></fill>'
            .'<fill><patternFill patternType="solid"><fgColor rgb="FFFCE4D6"/><bgColor indexed="64"/></patternFill></fill>'
            .'<fill><patternFill patternType="solid"><fgColor rgb="FFDCEBFF"/><bgColor indexed="64"/></patternFill></fill>'
            .'<fill><patternFill patternType="solid"><fgColor rgb="FFEAF4FF"/><bgColor indexed="64"/></patternFill></fill>'
            .'</fills>'
            .'<borders count="2">'
            .'<border><left/><right/><top/><bottom/><diagonal/></border>'
            .'<border><left style="thin"><color rgb="FF000000"/></left><right style="thin"><color rgb="FF000000"/></right><top style="thin"><color rgb="FF000000"/></top><bottom style="thin"><color rgb="FF000000"/></bottom><diagonal/></border>'
            .'</borders>'
            .'<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            .'<cellXfs count="10">'
            .'<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
            .'<xf numFmtId="0" fontId="1" fillId="2" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf>'
            .'<xf numFmtId="0" fontId="0" fillId="2" borderId="1" xfId="0" applyFill="1" applyBorder="1" applyAlignment="1"><alignment vertical="top" wrapText="1"/></xf>'
            .'<xf numFmtId="0" fontId="0" fillId="2" borderId="1" xfId="0" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="top" wrapText="1"/></xf>'
            .'<xf numFmtId="0" fontId="1" fillId="4" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment vertical="top" wrapText="1"/></xf>'
            .'<xf numFmtId="0" fontId="0" fillId="5" borderId="1" xfId="0" applyFill="1" applyBorder="1" applyAlignment="1"><alignment vertical="top" wrapText="1"/></xf>'
            .'<xf numFmtId="0" fontId="0" fillId="6" borderId="1" xfId="0" applyFill="1" applyBorder="1" applyAlignment="1"><alignment vertical="top" wrapText="1"/></xf>'
            .'<xf numFmtId="0" fontId="0" fillId="2" borderId="1" xfId="0" applyFill="1" applyBorder="1" applyAlignment="1"><alignment vertical="top" wrapText="1"/></xf>'
            .'<xf numFmtId="0" fontId="1" fillId="7" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment vertical="top" wrapText="1"/></xf>'
            .'<xf numFmtId="0" fontId="1" fillId="8" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment vertical="top" wrapText="1"/></xf>'
            .'</cellXfs>'
            .'</styleSheet>';
    }

    private function appProperties(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties" xmlns:vt="http://schemas.openxmlformats.org/officeDocument/2006/docPropsVTypes">'
            .'<Application>E-SAKIP Banjarnegara</Application></Properties>';
    }

    private function coreProperties(RenstraOpd $renstra): string
    {
        $title = $this->escape('Preview Renstra OPD - '.$renstra->judul);

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/" xmlns:dcmitype="http://purl.org/dc/dcmitype/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">'
            .'<dc:title>'.$title.'</dc:title><dc:creator>E-SAKIP Banjarnegara</dc:creator></cp:coreProperties>';
    }

    private function yearLabel(RenstraOpd $renstra, int $year): string
    {
        return $year > (int) $renstra->tahun_akhir ? "{$year} PM" : (string) $year;
    }

    private function columnName(int $column): string
    {
        $name = '';

        while ($column > 0) {
            $column--;
            $name = chr(65 + ($column % 26)).$name;
            $column = intdiv($column, 26);
        }

        return $name;
    }

    private function filename(RenstraOpd $renstra): string
    {
        $opd = str($renstra->opd?->singkatan ?: $renstra->opd?->nama ?: 'opd')->slug('-');

        return 'preview-renstra-'.$opd.'-'.$renstra->tahun_awal.'-'.$renstra->tahun_akhir.'.xlsx';
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_COMPAT, 'UTF-8');
    }
}
