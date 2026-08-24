<?php

namespace App\Services\Penganggaran;

use App\Models\RkaOpd;
use RuntimeException;
use ZipArchive;

class RkaPreviewExcelExportService
{
    public function __construct(private readonly RkaPreviewTableService $tableService) {}

    /** @return array{filename: string, content: string} */
    public function make(RkaOpd $rka): array
    {
        $rka->loadMissing(['opd:id,kode,nama,singkatan', 'opdUnit:id,kode,nama']);
        $preview = $this->tableService->build($rka);

        return [
            'filename' => $this->filename($rka),
            'content' => $this->buildWorkbook($rka, $preview),
        ];
    }

    /** @param array{rows: array<int, array<string, mixed>>, total: array<string, float>} $preview */
    private function buildWorkbook(RkaOpd $rka, array $preview): string
    {
        if (! class_exists(ZipArchive::class)) {
            throw new RuntimeException('Ekstensi PHP ZipArchive belum aktif, export .xlsx tidak bisa dibuat.');
        }

        $path = tempnam(sys_get_temp_dir(), 'sakip_rka_preview_');
        $zip = new ZipArchive;

        if ($path === false || $zip->open($path, ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('File export sementara tidak bisa dibuat.');
        }

        $zip->addFromString('[Content_Types].xml', $this->contentTypes());
        $zip->addFromString('_rels/.rels', $this->rootRelationships());
        $zip->addFromString('docProps/app.xml', $this->appProperties());
        $zip->addFromString('docProps/core.xml', $this->coreProperties($rka));
        $zip->addFromString('xl/workbook.xml', $this->workbookXml());
        $zip->addFromString('xl/_rels/workbook.xml.rels', $this->workbookRelationships());
        $zip->addFromString('xl/styles.xml', $this->stylesXml());
        $zip->addFromString('xl/worksheets/sheet1.xml', $this->worksheetXml($rka, $preview));
        $zip->close();

        $content = file_get_contents($path);
        @unlink($path);

        if (! is_string($content)) {
            throw new RuntimeException('File export .xlsx gagal dibaca.');
        }

        return $content;
    }

    /** @param array{rows: array<int, array<string, mixed>>, total: array<string, float>} $preview */
    private function worksheetXml(RkaOpd $rka, array $preview): string
    {
        $dataStart = 14;
        $footerRow = $dataStart + max(1, count($preview['rows']));
        $lastRow = max($footerRow, $dataStart);
        $sheetData = $this->documentHeaderRows($rka).$this->tableHeaderRows($rka);

        foreach ($preview['rows'] as $index => $row) {
            $sheetData .= $this->dataRow($dataStart + $index, $row);
        }

        if ($preview['rows'] === []) {
            $sheetData .= '<row r="14" ht="34" customHeight="1">'.$this->inlineCell('A14', 'Belum ada rincian sub kegiatan.', 12).'</row>';
        }

        $sheetData .= $this->footerRow($footerRow, $preview['total']);

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<dimension ref="A1:K'.$lastRow.'"/>'
            .'<sheetViews><sheetView workbookViewId="0"><pane ySplit="13" topLeftCell="A14" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>'
            .'<sheetFormatPr defaultRowHeight="18"/>'
            .$this->columnsXml()
            .'<sheetData>'.$sheetData.'</sheetData>'
            .$this->mergeCellsXml($footerRow)
            .'<pageMargins left="0.25" right="0.25" top="0.4" bottom="0.4" header="0.2" footer="0.2"/>'
            .'<pageSetup orientation="landscape" paperSize="9" fitToWidth="1" fitToHeight="0"/>'
            .'</worksheet>';
    }

    private function documentHeaderRows(RkaOpd $rka): string
    {
        $organization = trim(collect([$rka->opd?->kode, $rka->opd?->nama])->filter()->join(' · ')) ?: '-';
        $unit = $rka->opdUnit
            ? trim(collect([$rka->opdUnit->kode, $rka->opdUnit->nama])->filter()->join(' · '))
            : '-';

        return '<row r="1" ht="22" customHeight="1">'
            .$this->inlineCell('A1', 'RENCANA KERJA DAN ANGGARAN', 1)
            .$this->inlineCell('J1', "REKAPITULASI\nRKA-BELANJA\nSKPD", 2)
            .'</row>'
            .'<row r="2" ht="22" customHeight="1">'.$this->inlineCell('A2', 'SATUAN KERJA PERANGKAT DAERAH', 1).'</row>'
            .'<row r="3" ht="22" customHeight="1">'.$this->inlineCell('A3', 'Pemerintah Kabupaten Banjarnegara Tahun Anggaran '.$rka->tahun, 2).'</row>'
            .'<row r="4" ht="8" customHeight="1"/>'
            .'<row r="5" ht="24" customHeight="1">'.$this->inlineCell('A5', 'Organisasi', 3).$this->inlineCell('B5', ':', 3).$this->inlineCell('C5', $organization, 4).'</row>'
            .'<row r="6" ht="24" customHeight="1">'.$this->inlineCell('A6', 'Unit Organisasi', 3).$this->inlineCell('B6', ':', 3).$this->inlineCell('C6', $unit, 4).'</row>'
            .'<row r="7" ht="8" customHeight="1"/>'
            .'<row r="8" ht="22" customHeight="1">'.$this->inlineCell('A8', 'RINCIAN ANGGARAN BELANJA', 5).'</row>'
            .'<row r="9" ht="22" customHeight="1">'.$this->inlineCell('A9', 'BERDASARKAN PROGRAM DAN KEGIATAN', 5).'</row>'
            .'<row r="10" ht="8" customHeight="1"/>';
    }

    private function tableHeaderRows(RkaOpd $rka): string
    {
        return '<row r="11" ht="24" customHeight="1">'
            .$this->inlineCell('A11', 'KODE', 6)
            .$this->inlineCell('B11', 'URAIAN', 6)
            .$this->inlineCell('C11', 'SUMBER DANA', 6)
            .$this->inlineCell('D11', 'LOKASI', 6)
            .$this->inlineCell('E11', 'JUMLAH', 6)
            .'</row>'
            .'<row r="12" ht="24" customHeight="1">'
            .$this->inlineCell('E12', 'TAHUN '.($rka->tahun - 1), 6)
            .$this->inlineCell('F12', 'TAHUN '.$rka->tahun, 6)
            .$this->inlineCell('K12', 'TAHUN '.($rka->tahun + 1), 6)
            .'</row>'
            .'<row r="13" ht="38" customHeight="1">'
            .$this->inlineCell('F13', 'BELANJA OPERASI', 6)
            .$this->inlineCell('G13', 'BELANJA MODAL', 6)
            .$this->inlineCell('H13', 'BELANJA TIDAK TERDUGA', 6)
            .$this->inlineCell('I13', 'BELANJA TRANSFER', 6)
            .$this->inlineCell('J13', 'JUMLAH (RP)', 6)
            .'</row>';
    }

    /** @param array<string, mixed> $row */
    private function dataRow(int $rowNumber, array $row): string
    {
        $textStyle = $this->textStyle((string) $row['level']);
        $numberStyle = $this->numberStyle((string) $row['level']);
        $description = str_repeat('   ', $this->indent((string) $row['level'])).(string) $row['description'];
        $budget = $row['budget'];

        return '<row r="'.$rowNumber.'" ht="34" customHeight="1">'
            .$this->inlineCell('A'.$rowNumber, (string) $row['code'], $textStyle)
            .$this->inlineCell('B'.$rowNumber, $description, $textStyle)
            .$this->inlineCell('C'.$rowNumber, (string) $row['source'], $textStyle)
            .$this->inlineCell('D'.$rowNumber, (string) $row['location'], $textStyle)
            .$this->numberCell('E'.$rowNumber, (float) $budget['previous'], $numberStyle)
            .$this->numberCell('F'.$rowNumber, (float) $budget['operational'], $numberStyle)
            .$this->numberCell('G'.$rowNumber, (float) $budget['capital'], $numberStyle)
            .$this->numberCell('H'.$rowNumber, (float) $budget['unexpected'], $numberStyle)
            .$this->numberCell('I'.$rowNumber, (float) $budget['transfer'], $numberStyle)
            .$this->numberCell('J'.$rowNumber, (float) $budget['total'], $numberStyle)
            .$this->numberCell('K'.$rowNumber, (float) $budget['next'], $numberStyle)
            .'</row>';
    }

    /** @param array<string, float> $total */
    private function footerRow(int $rowNumber, array $total): string
    {
        return '<row r="'.$rowNumber.'" ht="28" customHeight="1">'
            .$this->inlineCell('A'.$rowNumber, 'JUMLAH ANGGARAN BELANJA', 19)
            .$this->numberCell('E'.$rowNumber, $total['previous'], 20)
            .$this->numberCell('F'.$rowNumber, $total['operational'], 20)
            .$this->numberCell('G'.$rowNumber, $total['capital'], 20)
            .$this->numberCell('H'.$rowNumber, $total['unexpected'], 20)
            .$this->numberCell('I'.$rowNumber, $total['transfer'], 20)
            .$this->numberCell('J'.$rowNumber, $total['total'], 20)
            .$this->numberCell('K'.$rowNumber, $total['next'], 20)
            .'</row>';
    }

    private function columnsXml(): string
    {
        return '<cols>'
            .'<col min="1" max="1" width="18" customWidth="1"/>'
            .'<col min="2" max="2" width="46" customWidth="1"/>'
            .'<col min="3" max="3" width="14" customWidth="1"/>'
            .'<col min="4" max="4" width="17" customWidth="1"/>'
            .'<col min="5" max="11" width="15" customWidth="1"/>'
            .'</cols>';
    }

    private function mergeCellsXml(int $footerRow): string
    {
        $merges = [
            'A1:I1', 'A2:I2', 'A3:I3', 'J1:K3', 'C5:K5', 'C6:K6', 'A8:K8', 'A9:K9',
            'A11:A13', 'B11:B13', 'C11:C13', 'D11:D13', 'E11:K11', 'E12:E13', 'F12:J12', 'K12:K13',
            'A'.$footerRow.':D'.$footerRow,
        ];

        return '<mergeCells count="'.count($merges).'">'
            .implode('', array_map(fn (string $range) => '<mergeCell ref="'.$range.'"/>', $merges))
            .'</mergeCells>';
    }

    private function textStyle(string $level): int
    {
        return ['opd' => 7, 'urusan' => 8, 'bidang' => 9, 'program' => 10, 'kegiatan' => 11, 'sub_kegiatan' => 12][$level] ?? 12;
    }

    private function numberStyle(string $level): int
    {
        return ['opd' => 13, 'urusan' => 14, 'bidang' => 15, 'program' => 16, 'kegiatan' => 17, 'sub_kegiatan' => 18][$level] ?? 18;
    }

    private function indent(string $level): int
    {
        return ['opd' => 0, 'urusan' => 1, 'bidang' => 2, 'program' => 3, 'kegiatan' => 4, 'sub_kegiatan' => 5][$level] ?? 0;
    }

    private function inlineCell(string $reference, string $value, int $style): string
    {
        return '<c r="'.$reference.'" t="inlineStr" s="'.$style.'"><is><t xml:space="preserve">'.$this->escape($value).'</t></is></c>';
    }

    private function numberCell(string $reference, float $value, int $style): string
    {
        return '<c r="'.$reference.'" s="'.$style.'"><v>'.number_format($value, 2, '.', '').'</v></c>';
    }

    private function workbookXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            .'<sheets><sheet name="Preview RKA" sheetId="1" r:id="rId1"/></sheets></workbook>';
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
        $textStyles = '';
        $numberStyles = '';
        foreach ([4, 5, 6, 7, 8, 2] as $fillId) {
            $textStyles .= '<xf numFmtId="0" fontId="'.($fillId === 2 ? 0 : 1).'" fillId="'.$fillId.'" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment vertical="top" wrapText="1"/></xf>';
            $numberStyles .= '<xf numFmtId="164" fontId="'.($fillId === 2 ? 0 : 1).'" fillId="'.$fillId.'" borderId="1" xfId="0" applyNumberFormat="1" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="right" vertical="top" wrapText="1"/></xf>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<numFmts count="1"><numFmt numFmtId="164" formatCode="[$Rp-421] #,##0"/></numFmts>'
            .'<fonts count="3"><font><sz val="10"/><name val="Aptos"/></font><font><b/><sz val="10"/><name val="Aptos"/></font><font><b/><sz val="12"/><name val="Aptos Display"/></font></fonts>'
            .'<fills count="10">'
            .'<fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill>'
            .$this->fill('FFFFFFFF').$this->fill('FFF1F5F9').$this->fill('FFE2E8F0').$this->fill('FFDBEAFE')
            .$this->fill('FFECFEFF').$this->fill('FFFFFBEB').$this->fill('FFECFDF5').$this->fill('FFCBD5E1')
            .'</fills>'
            .'<borders count="2"><border><left/><right/><top/><bottom/><diagonal/></border><border><left style="thin"><color rgb="FF94A3B8"/></left><right style="thin"><color rgb="FF94A3B8"/></right><top style="thin"><color rgb="FF94A3B8"/></top><bottom style="thin"><color rgb="FF94A3B8"/></bottom><diagonal/></border></borders>'
            .'<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            .'<cellXfs count="21">'
            .'<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
            .'<xf numFmtId="0" fontId="2" fillId="2" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>'
            .'<xf numFmtId="0" fontId="1" fillId="2" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf>'
            .'<xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1" applyAlignment="1"><alignment vertical="center"/></xf>'
            .'<xf numFmtId="0" fontId="0" fillId="2" borderId="0" xfId="0" applyFill="1" applyAlignment="1"><alignment vertical="center"/></xf>'
            .'<xf numFmtId="0" fontId="1" fillId="2" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>'
            .'<xf numFmtId="0" fontId="1" fillId="3" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf>'
            .$textStyles.$numberStyles
            .'<xf numFmtId="0" fontId="1" fillId="9" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="right" vertical="center"/></xf>'
            .'<xf numFmtId="164" fontId="1" fillId="9" borderId="1" xfId="0" applyNumberFormat="1" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="right" vertical="center"/></xf>'
            .'</cellXfs></styleSheet>';
    }

    private function fill(string $color): string
    {
        return '<fill><patternFill patternType="solid"><fgColor rgb="'.$color.'"/><bgColor indexed="64"/></patternFill></fill>';
    }

    private function appProperties(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties" xmlns:vt="http://schemas.openxmlformats.org/officeDocument/2006/docPropsVTypes"><Application>E-SAKIP Banjarnegara</Application></Properties>';
    }

    private function coreProperties(RkaOpd $rka): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/" xmlns:dcmitype="http://purl.org/dc/dcmitype/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"><dc:title>'.$this->escape('Preview RKA - '.$rka->judul).'</dc:title><dc:creator>E-SAKIP Banjarnegara</dc:creator></cp:coreProperties>';
    }

    private function filename(RkaOpd $rka): string
    {
        $opd = str($rka->opd?->singkatan ?: $rka->opd?->nama ?: 'opd')->slug('-');

        return 'preview-rka-'.$opd.'-'.$rka->tahun.'.xlsx';
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_COMPAT, 'UTF-8');
    }
}
