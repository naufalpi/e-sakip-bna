<?php

namespace App\Services\Imports;

use Illuminate\Http\UploadedFile;
use RuntimeException;
use SimpleXMLElement;
use ZipArchive;

class SpreadsheetImportReader
{
    /**
     * @return array<int, array<int, string|null>>
     */
    public function readRows(UploadedFile $file, int $maxRows = 1000): array
    {
        $extension = strtolower($file->getClientOriginalExtension());

        $rows = match ($extension) {
            'csv', 'txt' => $this->readCsv($file->getRealPath(), $maxRows),
            'xlsx' => $this->readXlsx($file->getRealPath(), $maxRows),
            'xls' => throw new RuntimeException('Format .xls lama belum bisa dipreview. Simpan ulang sebagai .xlsx atau .csv.'),
            default => throw new RuntimeException('Format file import tidak didukung.'),
        };

        return $this->withoutEmptyRows($rows);
    }

    /**
     * Membaca seluruh worksheet dari file .xlsx dalam urutan workbook.
     *
     * @return array<int, array<int, array<int, string|null>>>
     */
    public function readWorksheets(UploadedFile $file, int $maxRowsPerSheet = 1000): array
    {
        if (strtolower($file->getClientOriginalExtension()) !== 'xlsx') {
            throw new RuntimeException('Import jabatan hanya mendukung file Excel .xlsx.');
        }

        if (! class_exists(ZipArchive::class)) {
            throw new RuntimeException('Ekstensi PHP ZipArchive belum aktif, file .xlsx tidak bisa dibaca.');
        }

        $zip = new ZipArchive;

        if ($zip->open($file->getRealPath()) !== true) {
            throw new RuntimeException('File .xlsx tidak bisa dibuka.');
        }

        try {
            $sharedStrings = $this->readSharedStrings($zip);
            $worksheetPaths = [];

            for ($index = 0; $index < $zip->numFiles; $index++) {
                $name = $zip->getNameIndex($index);

                if (is_string($name) && preg_match('#^xl/worksheets/sheet\d+\.xml$#', $name) === 1) {
                    $worksheetPaths[] = $name;
                }
            }

            natsort($worksheetPaths);

            return collect($worksheetPaths)
                ->map(fn (string $path) => $this->withoutEmptyRows(
                    $this->readWorksheet($zip, $path, $sharedStrings, $maxRowsPerSheet)
                ))
                ->filter()
                ->values()
                ->all();
        } finally {
            $zip->close();
        }
    }

    /**
     * @param  array<int, array<int, string|null>>  $rows
     * @return array<int, string>
     */
    public function detectColumns(array $rows): array
    {
        $header = $rows[0] ?? [];

        return collect($header)
            ->map(fn ($value, int $index) => $this->normalizeColumnName($value, $index))
            ->values()
            ->all();
    }

    /**
     * @param  array<int, string|null>  $row
     * @param  array<int, string>  $columns
     * @return array<string, string|null>
     */
    public function mapRow(array $row, array $columns): array
    {
        $mapped = [];

        foreach ($columns as $index => $column) {
            $mapped[$column] = $row[$index] ?? null;
        }

        return $mapped;
    }

    /**
     * @return array<int, array<int, string|null>>
     */
    private function readCsv(string $path, int $maxRows): array
    {
        $handle = fopen($path, 'r');

        if (! $handle) {
            throw new RuntimeException('File CSV tidak bisa dibaca.');
        }

        $firstLine = fgets($handle) ?: '';
        $delimiter = substr_count($firstLine, ';') > substr_count($firstLine, ',') ? ';' : ',';
        rewind($handle);

        $rows = [];

        while (($row = fgetcsv($handle, 0, $delimiter)) !== false && count($rows) < $maxRows) {
            $rows[] = array_map(fn ($value) => $this->cleanCell($value), $row);
        }

        fclose($handle);

        return $rows;
    }

    /**
     * @return array<int, array<int, string|null>>
     */
    private function readXlsx(string $path, int $maxRows): array
    {
        if (! class_exists(ZipArchive::class)) {
            throw new RuntimeException('Ekstensi PHP ZipArchive belum aktif, file .xlsx tidak bisa dibaca.');
        }

        $zip = new ZipArchive;

        if ($zip->open($path) !== true) {
            throw new RuntimeException('File .xlsx tidak bisa dibuka.');
        }

        $sharedStrings = $this->readSharedStrings($zip);
        $worksheetPath = $this->firstWorksheetPath($zip);
        $rows = $this->readWorksheet($zip, $worksheetPath, $sharedStrings, $maxRows);

        $zip->close();

        return $rows;
    }

    /**
     * @param  array<int, string>  $sharedStrings
     * @return array<int, array<int, string|null>>
     */
    private function readWorksheet(ZipArchive $zip, string $path, array $sharedStrings, int $maxRows): array
    {
        $worksheet = $zip->getFromName($path);

        if (! is_string($worksheet)) {
            throw new RuntimeException('Worksheet tidak ditemukan di file .xlsx.');
        }

        $xml = simplexml_load_string($worksheet);

        if (! $xml instanceof SimpleXMLElement) {
            throw new RuntimeException('Worksheet .xlsx tidak bisa dibaca.');
        }

        $rows = [];

        foreach ($xml->sheetData->row as $rowNode) {
            if (count($rows) >= $maxRows) {
                break;
            }

            $cells = [];

            foreach ($rowNode->c as $cellNode) {
                $index = $this->columnIndex((string) $cellNode['r']);
                $cells[$index] = $this->xlsxCellValue($cellNode, $sharedStrings);
            }

            if ($cells !== []) {
                ksort($cells);
                $rows[] = $this->fillMissingCells($cells);
            }
        }

        return $rows;
    }

    /**
     * @return array<int, string>
     */
    private function readSharedStrings(ZipArchive $zip): array
    {
        $content = $zip->getFromName('xl/sharedStrings.xml');

        if (! is_string($content)) {
            return [];
        }

        $xml = simplexml_load_string($content);

        if (! $xml instanceof SimpleXMLElement) {
            return [];
        }

        $strings = [];

        foreach ($xml->si as $stringNode) {
            if (isset($stringNode->t)) {
                $strings[] = (string) $stringNode->t;

                continue;
            }

            $text = '';

            foreach ($stringNode->r as $run) {
                $text .= (string) $run->t;
            }

            $strings[] = $text;
        }

        return $strings;
    }

    private function firstWorksheetPath(ZipArchive $zip): string
    {
        if ($zip->locateName('xl/worksheets/sheet1.xml') !== false) {
            return 'xl/worksheets/sheet1.xml';
        }

        for ($index = 0; $index < $zip->numFiles; $index++) {
            $name = $zip->getNameIndex($index);

            if (is_string($name) && str_starts_with($name, 'xl/worksheets/sheet') && str_ends_with($name, '.xml')) {
                return $name;
            }
        }

        throw new RuntimeException('Worksheet tidak ditemukan di file .xlsx.');
    }

    /**
     * @param  array<int, string>  $sharedStrings
     */
    private function xlsxCellValue(SimpleXMLElement $cellNode, array $sharedStrings): ?string
    {
        $type = (string) $cellNode['t'];

        if ($type === 's') {
            return $this->cleanCell($sharedStrings[(int) $cellNode->v] ?? null);
        }

        if ($type === 'inlineStr') {
            return $this->cleanCell((string) ($cellNode->is->t ?? ''));
        }

        if ($type === 'b') {
            return ((string) $cellNode->v) === '1' ? 'TRUE' : 'FALSE';
        }

        return $this->cleanCell((string) $cellNode->v);
    }

    private function columnIndex(string $cellReference): int
    {
        preg_match('/([A-Z]+)/i', $cellReference, $matches);
        $letters = strtoupper($matches[1] ?? 'A');
        $index = 0;

        foreach (str_split($letters) as $letter) {
            $index = ($index * 26) + (ord($letter) - 64);
        }

        return max(0, $index - 1);
    }

    /**
     * @param  array<int, string|null>  $cells
     * @return array<int, string|null>
     */
    private function fillMissingCells(array $cells): array
    {
        $filled = [];
        $max = max(array_keys($cells));

        for ($index = 0; $index <= $max; $index++) {
            $filled[] = $cells[$index] ?? null;
        }

        return $filled;
    }

    /**
     * @param  array<int, array<int, string|null>>  $rows
     * @return array<int, array<int, string|null>>
     */
    private function withoutEmptyRows(array $rows): array
    {
        return array_values(array_filter($rows, function (array $row) {
            return collect($row)->contains(fn ($value) => filled($value));
        }));
    }

    private function normalizeColumnName(mixed $value, int $index): string
    {
        $column = str((string) $value)
            ->trim()
            ->lower()
            ->replaceMatches('/[^a-z0-9]+/', '_')
            ->trim('_')
            ->toString();

        return $column !== '' ? $column : 'kolom_'.($index + 1);
    }

    private function cleanCell(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);
        $value = preg_replace('/^\xEF\xBB\xBF/', '', $value) ?? $value;

        return $value === '' ? null : $value;
    }
}
