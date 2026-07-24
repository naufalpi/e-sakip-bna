<?php

namespace App\Services\Imports;

use RuntimeException;
use ZipArchive;

class ImportTemplateService
{
    /**
     * @return array{filename: string, content: string}
     */
    public function make(string $module): array
    {
        [$filename, $sheets] = match ($module) {
            'rpjmd' => ['template-import-rpjmd-banjarnegara.xlsx', $this->rpjmdSheets()],
            'renstra_opd' => ['template-import-renstra-opd-banjarnegara.xlsx', $this->renstraSheets()],
            default => throw new RuntimeException('Template import tidak tersedia.'),
        };

        return [
            'filename' => $filename,
            'content' => $this->buildWorkbook($sheets),
        ];
    }

    /**
     * @return array<string, array<int, array<int, string|int|float|null>>>
     */
    private function rpjmdSheets(): array
    {
        return [
            'Template RPJMD' => [
                ['level', 'kode', 'uraian', 'rpjmd_judul', 'nomor_perda', 'tahun_awal', 'tahun_akhir', 'tahun_target', 'target', 'target_text', 'definisi_operasional', 'alasan_pemilihan', 'formulasi_pengukuran', 'tipe_perhitungan', 'sumber_data', 'urusan_kode', 'opd_kode', 'opd_nama', 'peran', 'is_utama', 'keterangan', 'satuan', 'strategi_kode', 'misi_kode_terkait', 'indikator_tujuan_kode_terkait'],
                ['rpjmd', 'RPJMD-2025', 'RPJMD Kabupaten Banjarnegara Tahun 2025-2029', 'RPJMD Kabupaten Banjarnegara Tahun 2025-2029', 'Perda Nomor ... Tahun 2025', 2025, 2029, null, null, null, null, null, null, null, null, null, null, null, null, null, 'Baris identitas dokumen RPJMD.'],
                ['visi', 'V-1', 'Banjarnegara yang maju, sejahtera, dan berdaya saing', 'RPJMD Kabupaten Banjarnegara Tahun 2025-2029', null, 2025, 2029, null, null, null, null, null, null, null, null, null, null, null, null, null, null],
                ['misi', 'M-1', 'Meningkatkan kualitas pembangunan manusia dan pelayanan publik', 'RPJMD Kabupaten Banjarnegara Tahun 2025-2029', null, 2025, 2029, null, null, null, null, null, null, null, null, null, null, null, null, null, null],
                ['tujuan', 'T-1', 'Meningkatnya kesejahteraan masyarakat', 'RPJMD Kabupaten Banjarnegara Tahun 2025-2029', null, 2025, 2029, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, 'M-1', null],
                ['indikator_tujuan', 'IT-1', 'Indeks Pembangunan Manusia', 'RPJMD Kabupaten Banjarnegara Tahun 2025-2029', null, 2025, 2029, 2025, 75, '75 poin', 'Indikator komposit kualitas pembangunan manusia.', 'Menggambarkan capaian dasar pembangunan manusia daerah.', 'Mengacu metodologi IPM yang diterbitkan BPS.', 'non_kumulatif', 'BPS', null, '1.01', 'Dinas Contoh', null, null, null, 'Angka', null, null, null],
                ['sasaran', 'S-1', 'Meningkatnya kualitas layanan dasar', 'RPJMD Kabupaten Banjarnegara Tahun 2025-2029', null, 2025, 2029, null, null, null, null, null, null, null, null, null, null, null, null, null, null],
                ['indikator_sasaran', 'IS-1', 'Indeks Kepuasan Masyarakat', 'RPJMD Kabupaten Banjarnegara Tahun 2025-2029', null, 2025, 2029, 2025, 80, '80 poin', 'Nilai kepuasan masyarakat terhadap layanan dasar.', 'Menjadi ukuran mutu pelayanan publik.', 'Nilai hasil survei IKM perangkat daerah.', 'non_kumulatif', 'Survei IKM', null, '1.01', 'Dinas Contoh', null, null, null, 'Angka', null, null, null],
                ['program', 'PR-1', 'Program Penunjang Urusan Pemerintahan Daerah', 'RPJMD Kabupaten Banjarnegara Tahun 2025-2029', null, 2025, 2029, null, null, null, null, null, null, null, null, '1.01', null, null, null, null, null],
                ['indikator_program', 'IPR-1', 'Persentase layanan penunjang yang terpenuhi', 'RPJMD Kabupaten Banjarnegara Tahun 2025-2029', null, 2025, 2029, 2025, 90, '90 persen', 'Persentase pemenuhan layanan penunjang perangkat daerah.', 'Menunjukkan kesiapan administrasi dan dukungan layanan.', '(Jumlah layanan terpenuhi / seluruh layanan) x 100', 'kumulatif', 'Laporan OPD', null, '1.01', 'Dinas Contoh', null, null, null, 'Persen', null, null, null],
                ['opd_penanggung_jawab', null, 'Penanggung jawab Program Penunjang Urusan Pemerintahan Daerah', 'RPJMD Kabupaten Banjarnegara Tahun 2025-2029', null, 2025, 2029, null, null, null, null, null, null, null, null, null, '1.01', 'Dinas Contoh', 'penanggung_jawab', '1', 'Gunakan opd_kode sesuai master OPD.'],
            ],
            'Petunjuk' => [
                ['Kolom', 'Keterangan'],
                ['level', 'Wajib. Nilai: rpjmd, visi, misi, tujuan, indikator_tujuan, sasaran, indikator_sasaran, program, indikator_program, opd_penanggung_jawab. Strategi dipilih dari Master Strategi Daerah pada baris program.'],
                ['uraian', 'Wajib. Isi judul/nama/uraian sesuai level.'],
                ['tahun_target', 'Wajib untuk baris target atau indikator yang sekaligus berisi target. Tahun harus tersedia di master periode.'],
                ['tipe_perhitungan', 'Isi kumulatif atau non_kumulatif. Kosong dianggap non_kumulatif.'],
                ['satuan', 'Gunakan nama atau simbol yang sama dengan Master Satuan Indikator.'],
                ['strategi_kode', 'Opsional pada baris program. Gunakan kode dari Master Strategi Daerah.'],
                ['misi_kode_terkait', 'Opsional pada baris tujuan. Pisahkan beberapa kode misi dengan koma untuk tujuan lintas misi.'],
                ['indikator_tujuan_kode_terkait', 'Opsional pada baris sasaran untuk pola sasaran melalui indikator tujuan. Pisahkan beberapa kode dengan koma.'],
                ['opd_kode', 'Untuk baris indikator dapat diisi sebagai PD penanggung jawab. Untuk opd_penanggung_jawab wajib diisi.'],
            ],
        ];
    }

    /**
     * @return array<string, array<int, array<int, string|int|float|null>>>
     */
    private function renstraSheets(): array
    {
        return [
            'Template Renstra' => [
                ['level', 'opd_kode', 'opd_nama', 'rpjmd_judul', 'renstra_judul', 'nomor_dokumen', 'tahun_awal', 'tahun_akhir', 'kode', 'uraian', 'sasaran_level', 'tahun_target', 'target', 'target_text', 'pagu', 'tipe_indikator', 'formula', 'sumber_data', 'tujuan_daerah_kode', 'indikator_tujuan_daerah_kode', 'sasaran_daerah_kode', 'indikator_sasaran_daerah_kode', 'program_rpjmd_kode', 'indikator_program_rpjmd_kode', 'keterangan'],
                ['renstra', '1.01', 'Dinas Contoh', 'RPJMD Kabupaten Banjarnegara Tahun 2025-2029', 'Renstra Dinas Contoh Tahun 2025-2029', 'RENSTRA/001/2025', 2025, 2029, 'REN-1', 'Renstra Dinas Contoh Tahun 2025-2029', null, null, null, null, null, null, null, null, null, null, null, null, null, null, 'Baris identitas Renstra OPD.'],
                ['tujuan', '1.01', 'Dinas Contoh', 'RPJMD Kabupaten Banjarnegara Tahun 2025-2029', 'Renstra Dinas Contoh Tahun 2025-2029', null, 2025, 2029, 'TO-1', 'Meningkatnya kualitas layanan OPD', null, null, null, null, null, null, null, null, 'T-1', null, null, null, null, null, null],
                ['indikator_tujuan', '1.01', 'Dinas Contoh', 'RPJMD Kabupaten Banjarnegara Tahun 2025-2029', 'Renstra Dinas Contoh Tahun 2025-2029', null, 2025, 2029, 'ITO-1', 'Indeks layanan OPD', null, 2025, 80, '80 poin', null, 'positif', '(realisasi / target) x 100', 'Laporan OPD', null, 'IT-1', null, null, null, null, null],
                ['sasaran', '1.01', 'Dinas Contoh', 'RPJMD Kabupaten Banjarnegara Tahun 2025-2029', 'Renstra Dinas Contoh Tahun 2025-2029', null, 2025, 2029, 'SO-1', 'Meningkatnya capaian sasaran OPD', null, null, null, null, null, null, null, null, null, null, 'S-1', null, null, null, null],
                ['indikator_sasaran', '1.01', 'Dinas Contoh', 'RPJMD Kabupaten Banjarnegara Tahun 2025-2029', 'Renstra Dinas Contoh Tahun 2025-2029', null, 2025, 2029, 'ISO-1', 'Persentase capaian sasaran OPD', null, 2025, 85, '85 persen', null, 'positif', '(realisasi / target) x 100', 'Laporan OPD', null, null, null, 'IS-1', null, null, null],
                ['program', '1.01', 'Dinas Contoh', 'RPJMD Kabupaten Banjarnegara Tahun 2025-2029', 'Renstra Dinas Contoh Tahun 2025-2029', null, 2025, 2029, 'P-1', 'Program Penunjang Urusan Pemerintahan Daerah', 'Meningkatnya layanan penunjang perangkat daerah', null, null, null, 1000000000, null, null, null, null, null, null, null, 'PR-1', null, null],
                ['indikator_program', '1.01', 'Dinas Contoh', 'RPJMD Kabupaten Banjarnegara Tahun 2025-2029', 'Renstra Dinas Contoh Tahun 2025-2029', null, 2025, 2029, 'IP-1', 'Persentase layanan program terpenuhi', null, 2025, 90, '90 persen', 1000000000, 'positif', '(realisasi / target) x 100', 'Laporan OPD', null, null, null, null, null, 'IPR-1', null],
                ['kegiatan', '1.01', 'Dinas Contoh', 'RPJMD Kabupaten Banjarnegara Tahun 2025-2029', 'Renstra Dinas Contoh Tahun 2025-2029', null, 2025, 2029, 'K-1', 'Administrasi umum perangkat daerah', 'Tersedianya layanan administrasi perangkat daerah', null, null, null, 600000000, null, null, null, null, null, null, null, null, null, null],
                ['indikator_kegiatan', '1.01', 'Dinas Contoh', 'RPJMD Kabupaten Banjarnegara Tahun 2025-2029', 'Renstra Dinas Contoh Tahun 2025-2029', null, 2025, 2029, 'IK-1', 'Persentase layanan kegiatan terpenuhi', null, 2025, 80, '80 persen', null, 'positif', '(realisasi / target) x 100', 'Laporan kegiatan', null, null, null, null, null, null, null],
                ['target_kegiatan', '1.01', 'Dinas Contoh', 'RPJMD Kabupaten Banjarnegara Tahun 2025-2029', 'Renstra Dinas Contoh Tahun 2025-2029', null, 2025, 2029, null, 'Target indikator kegiatan', null, 2026, 85, '85 persen', null, null, null, null, null, null, null, null, null, null, null],
                ['sub_kegiatan', '1.01', 'Dinas Contoh', 'RPJMD Kabupaten Banjarnegara Tahun 2025-2029', 'Renstra Dinas Contoh Tahun 2025-2029', null, 2025, 2029, 'SK-1', 'Penyediaan jasa penunjang administrasi', 'Tersedianya dokumen administrasi perangkat daerah', null, null, null, 300000000, null, null, null, null, null, null, null, null, null, null],
                ['indikator_sub_kegiatan', '1.01', 'Dinas Contoh', 'RPJMD Kabupaten Banjarnegara Tahun 2025-2029', 'Renstra Dinas Contoh Tahun 2025-2029', null, 2025, 2029, 'ISK-1', 'Jumlah dokumen administrasi selesai', null, 2025, 25, '25 dokumen', null, 'positif', '(realisasi / target) x 100', 'Laporan sub kegiatan', null, null, null, null, null, null, null],
                ['target_sub_kegiatan', '1.01', 'Dinas Contoh', 'RPJMD Kabupaten Banjarnegara Tahun 2025-2029', 'Renstra Dinas Contoh Tahun 2025-2029', null, 2025, 2029, null, 'Target indikator sub kegiatan', null, 2026, 30, '30 dokumen', null, null, null, null, null, null, null, null, null, null, null],
            ],
            'Petunjuk' => [
                ['Kolom', 'Keterangan'],
                ['level', 'Wajib. Nilai: renstra, tujuan, indikator_tujuan, target_tujuan, sasaran, indikator_sasaran, target_sasaran, program, indikator_program, target_program, kegiatan, indikator_kegiatan, target_kegiatan, sub_kegiatan, indikator_sub_kegiatan, target_sub_kegiatan.'],
                ['opd_kode', 'Wajib. Harus sama dengan kode OPD di master OPD.'],
                ['rpjmd_judul', 'Wajib jika tidak memakai rpjmd_id. Harus sama dengan judul RPJMD existing.'],
                ['tahun_target', 'Wajib untuk semua target tahunan. Tahun harus tersedia di master periode.'],
            ],
        ];
    }

    /**
     * @param  array<string, array<int, array<int, string|int|float|null>>>  $sheets
     */
    private function buildWorkbook(array $sheets): string
    {
        if (! class_exists(ZipArchive::class)) {
            throw new RuntimeException('Ekstensi PHP ZipArchive belum aktif, template .xlsx tidak bisa dibuat.');
        }

        $path = tempnam(sys_get_temp_dir(), 'sakip_template_');
        $zip = new ZipArchive;

        if ($path === false || $zip->open($path, ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('File template sementara tidak bisa dibuat.');
        }

        $zip->addFromString('[Content_Types].xml', $this->contentTypes(count($sheets)));
        $zip->addFromString('_rels/.rels', $this->rootRelationships());
        $zip->addFromString('docProps/app.xml', $this->appProperties());
        $zip->addFromString('docProps/core.xml', $this->coreProperties());
        $zip->addFromString('xl/workbook.xml', $this->workbookXml(array_keys($sheets)));
        $zip->addFromString('xl/_rels/workbook.xml.rels', $this->workbookRelationships(count($sheets)));
        $zip->addFromString('xl/styles.xml', $this->stylesXml());

        $index = 1;
        foreach ($sheets as $rows) {
            $zip->addFromString("xl/worksheets/sheet{$index}.xml", $this->worksheetXml($rows));
            $index++;
        }

        $zip->close();

        $content = file_get_contents($path);
        @unlink($path);

        if (! is_string($content)) {
            throw new RuntimeException('Template .xlsx gagal dibaca.');
        }

        return $content;
    }

    private function worksheetXml(array $rows): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>'
            .'<sheetData>';

        foreach ($rows as $rowIndex => $row) {
            $excelRow = $rowIndex + 1;
            $xml .= '<row r="'.$excelRow.'">';

            foreach ($row as $columnIndex => $value) {
                $cell = $this->columnName($columnIndex + 1).$excelRow;
                $xml .= '<c r="'.$cell.'" t="inlineStr"><is><t xml:space="preserve">'.$this->escape($value).'</t></is></c>';
            }

            $xml .= '</row>';
        }

        return $xml.'</sheetData></worksheet>';
    }

    /**
     * @param  array<int, string>  $sheetNames
     */
    private function workbookXml(array $sheetNames): string
    {
        $sheets = '';

        foreach ($sheetNames as $index => $name) {
            $sheetId = $index + 1;
            $sheets .= '<sheet name="'.$this->escapeAttribute($name).'" sheetId="'.$sheetId.'" r:id="rId'.$sheetId.'"/>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            .'<sheets>'.$sheets.'</sheets></workbook>';
    }

    private function workbookRelationships(int $sheetCount): string
    {
        $relationships = '';

        for ($index = 1; $index <= $sheetCount; $index++) {
            $relationships .= '<Relationship Id="rId'.$index.'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet'.$index.'.xml"/>';
        }

        $relationships .= '<Relationship Id="rId'.($sheetCount + 1).'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>';

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'.$relationships.'</Relationships>';
    }

    private function contentTypes(int $sheetCount): string
    {
        $overrides = '';

        for ($index = 1; $index <= $sheetCount; $index++) {
            $overrides .= '<Override PartName="/xl/worksheets/sheet'.$index.'.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            .'<Default Extension="xml" ContentType="application/xml"/>'
            .'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            .'<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            .'<Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>'
            .'<Override PartName="/docProps/app.xml" ContentType="application/vnd.openxmlformats-officedocument.extended-properties+xml"/>'
            .$overrides
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
            .'<fonts count="1"><font><sz val="11"/><name val="Calibri"/></font></fonts>'
            .'<fills count="1"><fill><patternFill patternType="none"/></fill></fills>'
            .'<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
            .'<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            .'<cellXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/></cellXfs>'
            .'</styleSheet>';
    }

    private function appProperties(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties" xmlns:vt="http://schemas.openxmlformats.org/officeDocument/2006/docPropsVTypes">'
            .'<Application>E-SAKIP Kabupaten Banjarnegara</Application></Properties>';
    }

    private function coreProperties(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">'
            .'<dc:creator>E-SAKIP Kabupaten Banjarnegara</dc:creator>'
            .'<dc:title>Template Import E-SAKIP</dc:title>'
            .'<dcterms:created xsi:type="dcterms:W3CDTF">'.now()->toISOString().'</dcterms:created>'
            .'</cp:coreProperties>';
    }

    private function columnName(int $index): string
    {
        $name = '';

        while ($index > 0) {
            $index--;
            $name = chr(65 + ($index % 26)).$name;
            $index = intdiv($index, 26);
        }

        return $name;
    }

    private function escape(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_XML1 | ENT_COMPAT, 'UTF-8');
    }

    private function escapeAttribute(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
