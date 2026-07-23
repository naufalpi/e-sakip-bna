<?php

namespace App\Services\Rpjmd;

use App\Models\IndikatorProgramRpjmd;
use App\Models\IndikatorSasaranDaerah;
use App\Models\IndikatorTujuanDaerah;
use App\Models\PeriodeTahun;
use App\Models\ProgramRpjmd;
use App\Models\Rpjmd;
use App\Models\RpjmdMisi;
use App\Models\RpjmdVisi;
use App\Models\SasaranDaerah;
use App\Models\TujuanDaerah;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use RuntimeException;
use ZipArchive;

class RpjmdPreviewExcelExportService
{
    /**
     * @return array{filename: string, content: string}
     */
    public function make(Rpjmd $rpjmd, ?int $visibleOpdId = null): array
    {
        $this->loadPreviewRelations($rpjmd);

        $years = $this->targetYears($rpjmd);
        $rows = $this->tableRows($rpjmd, $visibleOpdId);
        $filename = $this->filename($rpjmd);

        return [
            'filename' => $filename,
            'content' => $this->buildWorkbook($rpjmd, $years, $rows),
        ];
    }

    private function loadPreviewRelations(Rpjmd $rpjmd): void
    {
        $rpjmd->load([
            'periodeTahun:id,tahun,nama,status',
            'visi:id,rpjmd_id,visi,urutan',
            'visi.misi:id,rpjmd_id,rpjmd_visi_id,kode,misi,urutan',
            'visi.tujuan:id,rpjmd_visi_id,rpjmd_misi_id,kode,tujuan,urutan',
            'visi.tujuan.misiTerkait:id,rpjmd_id,rpjmd_visi_id,misi,urutan',
            'visi.tujuan.indikator.satuanIndikator:id,nama,simbol',
            'visi.tujuan.indikator.targets.periodeTahun:id,tahun,nama',
            'visi.tujuan.sasaran.indikatorTujuanTerkait:id,tujuan_daerah_id,indikator,urutan',
            'visi.tujuan.sasaran.indikator.satuanIndikator:id,nama,simbol',
            'visi.tujuan.sasaran.indikator.targets.periodeTahun:id,tahun,nama',
            'visi.tujuan.sasaran.programs.strategi:id,kode,strategi,status',
            'visi.tujuan.sasaran.programs.indikator.satuanIndikator:id,nama,simbol',
            'visi.tujuan.sasaran.programs.indikator.targets.periodeTahun:id,tahun,nama',
            'visi.tujuan.sasaran.programs.opdPenanggungJawab' => fn ($query) => $query->select('opds.id', 'opds.nama', 'opds.singkatan'),
            'visi.tujuan.sasaran.programs.programPemerintahan.bidangUrusan.opdPengampu' => fn ($query) => $query->select('opds.id', 'opds.kode', 'opds.nama', 'opds.singkatan'),
            'visi.tujuan.sasaran.programs.programPemerintahanReferences.bidangUrusan.opdPengampu' => fn ($query) => $query->select('opds.id', 'opds.kode', 'opds.nama', 'opds.singkatan'),
        ]);
    }

    /**
     * @return array<int, int>
     */
    private function targetYears(Rpjmd $rpjmd): array
    {
        $years = collect(range((int) $rpjmd->tahun_awal, (int) $rpjmd->tahun_akhir));

        PeriodeTahun::query()
            ->whereBetween('tahun', [(int) $rpjmd->tahun_awal, (int) $rpjmd->tahun_akhir + 1])
            ->pluck('tahun')
            ->each(fn ($year) => $years->push((int) $year));

        $rpjmd->visi->each(function (RpjmdVisi $visi) use ($years): void {
            $visi->tujuan->each(function (TujuanDaerah $tujuan) use ($years): void {
                $this->pushIndicatorTargetYears($years, $tujuan->indikator);
                $tujuan->sasaran->each(function (SasaranDaerah $sasaran) use ($years): void {
                    $this->pushIndicatorTargetYears($years, $sasaran->indikator);
                    $sasaran->programs->each(fn (ProgramRpjmd $program) => $this->pushIndicatorTargetYears($years, $program->indikator));
                });
            });
        });

        return $years->unique()->sort()->values()->all();
    }

    /**
     * @param  Collection<int, int>  $years
     * @param  EloquentCollection<int, IndikatorTujuanDaerah|IndikatorSasaranDaerah|IndikatorProgramRpjmd>  $indicators
     */
    private function pushIndicatorTargetYears(Collection $years, EloquentCollection $indicators): void
    {
        $indicators->each(function (IndikatorTujuanDaerah|IndikatorSasaranDaerah|IndikatorProgramRpjmd $indicator) use ($years): void {
            $indicator->targets->each(fn ($target) => $years->push((int) $target->periodeTahun->tahun));
        });
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function tableRows(Rpjmd $rpjmd, ?int $visibleOpdId = null): array
    {
        $rows = [];

        foreach ($rpjmd->visi as $visi) {
            if ($visibleOpdId && ! $this->visiHasVisiblePrograms($visi, $visibleOpdId)) {
                continue;
            }

            if ($visi->misi->isEmpty() && $visi->tujuan->isEmpty()) {
                $rows[] = $this->emptyRow(['key' => "visi-{$visi->id}", 'visi' => $this->nodeText(null, $visi->visi)]);
            }

            if ($visi->tujuan->isEmpty()) {
                foreach ($visi->misi as $misi) {
                    $rows[] = $this->emptyRow([
                        'key' => "misi-{$misi->id}",
                        'visi' => $this->nodeText(null, $visi->visi),
                        'misi' => $this->nodeText($misi->kode, $misi->misi),
                    ]);
                }
            }

            foreach ($visi->tujuan as $tujuan) {
                if ($visibleOpdId && ! $this->tujuanHasVisiblePrograms($tujuan, $visibleOpdId)) {
                    continue;
                }

                if ($tujuan->indikator->isNotEmpty() || $tujuan->sasaran->isEmpty()) {
                    $this->addAlignedRows($rows, "tujuan-{$tujuan->id}", [
                        'visi' => $this->nodeText(null, $visi->visi),
                        'misi' => $this->misiSummary($tujuan),
                        'tujuan' => $this->nodeText($tujuan->kode, $tujuan->tujuan),
                    ], $this->indicatorPreviewRows($tujuan->indikator), [], []);
                }

                foreach ($tujuan->sasaran as $sasaran) {
                    if ($visibleOpdId && ! $this->sasaranHasVisiblePrograms($sasaran, $visibleOpdId)) {
                        continue;
                    }

                    $tujuanIndicatorRows = $this->indicatorPreviewRows($this->relatedTujuanIndicators($tujuan, $sasaran));
                    $sasaranIndicatorRows = $this->indicatorPreviewRows($sasaran->indikator);
                    $programs = $visibleOpdId
                        ? $sasaran->programs->filter(fn (ProgramRpjmd $program) => $program->isRelevantForOpd($visibleOpdId))->values()
                        : $sasaran->programs;

                    if ($sasaran->indikator->isEmpty() && $programs->isEmpty()) {
                        $this->addAlignedRows($rows, "sasaran-{$sasaran->id}", [
                            'visi' => $this->nodeText(null, $visi->visi),
                            'misi' => $this->misiSummary($tujuan),
                            'tujuan' => $this->nodeText($tujuan->kode, $tujuan->tujuan),
                            'sasaran' => $this->nodeText($sasaran->kode, $sasaran->sasaran),
                        ], $tujuanIndicatorRows, [], []);
                    }

                    if ($programs->isEmpty() && $sasaran->indikator->isNotEmpty()) {
                        $this->addAlignedRows($rows, "indikator-sasaran-{$sasaran->id}", [
                            'visi' => $this->nodeText(null, $visi->visi),
                            'misi' => $this->misiSummary($tujuan),
                            'tujuan' => $this->nodeText($tujuan->kode, $tujuan->tujuan),
                            'sasaran' => $this->nodeText($sasaran->kode, $sasaran->sasaran),
                        ], $tujuanIndicatorRows, $sasaranIndicatorRows, []);
                    }

                    if ($programs->isNotEmpty()) {
                        $this->addProgramRows($rows, $visi, $tujuan, $sasaran, $tujuanIndicatorRows, $sasaranIndicatorRows, $programs);
                    }
                }
            }
        }

        return $this->suppressRepeatedValues($rows);
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @param  array<string, mixed>  $base
     * @param  array<int, array<string, mixed>>  $indikatorTujuanRows
     * @param  array<int, array<string, mixed>>  $indikatorSasaranRows
     * @param  array<int, array<string, mixed>>  $indikatorProgramRows
     */
    private function addAlignedRows(array &$rows, string $keyPrefix, array $base, array $indikatorTujuanRows, array $indikatorSasaranRows, array $indikatorProgramRows): void
    {
        $rowCount = max(count($indikatorTujuanRows), count($indikatorSasaranRows), count($indikatorProgramRows), 1);

        for ($index = 0; $index < $rowCount; $index++) {
            $indikatorTujuan = $indikatorTujuanRows[$index] ?? $this->emptyIndicatorPreview();
            $indikatorSasaran = $indikatorSasaranRows[$index] ?? $this->emptyIndicatorPreview();
            $indikatorProgram = $indikatorProgramRows[$index] ?? $this->emptyIndicatorPreview();

            $rows[] = $this->emptyRow([
                ...$base,
                'key' => "{$keyPrefix}-{$index}",
                'indikator_tujuan' => $indikatorTujuan['label'],
                'satuan_tujuan' => $indikatorTujuan['satuan'],
                'target_tujuan_by_year' => $indikatorTujuan['target_by_year'],
                'indikator_sasaran' => $indikatorSasaran['label'],
                'satuan_sasaran' => $indikatorSasaran['satuan'],
                'target_sasaran_by_year' => $indikatorSasaran['target_by_year'],
                'indikator_program' => $indikatorProgram['label'],
                'satuan_program' => $indikatorProgram['satuan'],
                'target_program_by_year' => $indikatorProgram['target_by_year'],
            ]);
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @param  array<int, array<string, mixed>>  $tujuanIndicatorRows
     * @param  array<int, array<string, mixed>>  $sasaranIndicatorRows
     * @param  EloquentCollection<int, ProgramRpjmd>|Collection<int, ProgramRpjmd>  $programs
     */
    private function addProgramRows(array &$rows, RpjmdVisi $visi, TujuanDaerah $tujuan, SasaranDaerah $sasaran, array $tujuanIndicatorRows, array $sasaranIndicatorRows, EloquentCollection|Collection $programs): void
    {
        $programRows = $programs
            ->flatMap(function (ProgramRpjmd $program) {
                return collect($this->indicatorPreviewRows($program->indikator))
                    ->map(fn (array $indikatorProgram, int $index) => [
                        'indikator_program' => $indikatorProgram,
                        'base' => $index === 0 ? [
                            'strategi' => $program->strategi ? $this->nodeText($program->strategi->kode, $program->strategi->strategi) : '-',
                            'program' => $this->nodeText($program->kode, $program->nama),
                            'opd_penanggung_jawab' => $this->joinItems($program->opdPenanggungJawab->map(fn ($opd) => $opd->singkatan ?: $opd->nama)->all()),
                            'status_keterhubungan' => $program->opdPenanggungJawab->isNotEmpty() ? 'Terhubung OPD' : 'Belum ada OPD',
                        ] : [],
                    ]);
            })
            ->values();
        $rowCount = max(count($tujuanIndicatorRows), count($sasaranIndicatorRows), $programRows->count(), 1);

        for ($index = 0; $index < $rowCount; $index++) {
            $indikatorTujuan = $tujuanIndicatorRows[$index] ?? $this->emptyIndicatorPreview();
            $indikatorSasaran = $sasaranIndicatorRows[$index] ?? $this->emptyIndicatorPreview();
            $programRow = $programRows->get($index);
            $indikatorProgram = $programRow['indikator_program'] ?? $this->emptyIndicatorPreview();

            $rows[] = $this->emptyRow([
                'key' => "sasaran-program-{$sasaran->id}-{$index}",
                'visi' => $this->nodeText(null, $visi->visi),
                'misi' => $this->misiSummary($tujuan),
                'tujuan' => $this->nodeText($tujuan->kode, $tujuan->tujuan),
                'sasaran' => $this->nodeText($sasaran->kode, $sasaran->sasaran),
                'indikator_tujuan' => $indikatorTujuan['label'],
                'satuan_tujuan' => $indikatorTujuan['satuan'],
                'target_tujuan_by_year' => $indikatorTujuan['target_by_year'],
                'indikator_sasaran' => $indikatorSasaran['label'],
                'satuan_sasaran' => $indikatorSasaran['satuan'],
                'target_sasaran_by_year' => $indikatorSasaran['target_by_year'],
                ...($programRow['base'] ?? []),
                'indikator_program' => $indikatorProgram['label'],
                'satuan_program' => $indikatorProgram['satuan'],
                'target_program_by_year' => $indikatorProgram['target_by_year'],
            ]);
        }
    }

    /**
     * @param  EloquentCollection<int, IndikatorTujuanDaerah|IndikatorSasaranDaerah|IndikatorProgramRpjmd>|Collection<int, IndikatorTujuanDaerah|IndikatorSasaranDaerah|IndikatorProgramRpjmd>  $items
     * @return array<int, array<string, mixed>>
     */
    private function indicatorPreviewRows(EloquentCollection|Collection $items): array
    {
        if ($items->isEmpty()) {
            return [$this->emptyIndicatorPreview()];
        }

        return $items
            ->map(fn (IndikatorTujuanDaerah|IndikatorSasaranDaerah|IndikatorProgramRpjmd $item) => [
                'label' => $this->nodeText($item->kode, $item->indikator),
                'satuan' => $item->satuanIndikator?->simbol ?: ($item->satuanIndikator?->nama ?: '-'),
                'target_by_year' => $this->targetByYear($item),
            ])
            ->values()
            ->all();
    }

    /**
     * @return Collection<int, IndikatorTujuanDaerah>
     */
    private function relatedTujuanIndicators(TujuanDaerah $tujuan, SasaranDaerah $sasaran): Collection
    {
        $ids = $sasaran->indikatorTujuanTerkait->pluck('id')->map(fn ($id) => (int) $id);

        if ($ids->isEmpty()) {
            return collect();
        }

        return $tujuan->indikator->filter(fn (IndikatorTujuanDaerah $indikator) => $ids->contains((int) $indikator->id))->values();
    }

    /**
     * @return array<string, string>
     */
    private function targetByYear(IndikatorTujuanDaerah|IndikatorSasaranDaerah|IndikatorProgramRpjmd $item): array
    {
        return $item->targets
            ->sortBy(fn ($target) => $target->periodeTahun->tahun)
            ->mapWithKeys(fn ($target) => [(int) $target->periodeTahun->tahun => $this->targetValue($target)])
            ->all();
    }

    private function targetValue($target): string
    {
        return $this->formatTargetNumber($target->target) ?: $this->valueText($target->target) ?: $this->valueText($target->target_text);
    }

    private function formatTargetNumber(mixed $value): string
    {
        if ($value === null || $value === '' || ! is_numeric($value)) {
            return '';
        }

        $formatted = number_format((float) $value, 4, ',', '.');

        return rtrim(rtrim($formatted, '0'), ',');
    }

    private function valueText(mixed $value): string
    {
        return trim((string) ($value ?? ''));
    }

    private function misiSummary(TujuanDaerah $tujuan): string
    {
        return $this->joinItems($tujuan->misiTerkait->map(fn (RpjmdMisi $misi) => $misi->misi)->all());
    }

    /**
     * @param  array<int, string|null>  $items
     */
    private function joinItems(array $items): string
    {
        $items = array_values(array_filter($items, fn ($item) => $item && $item !== '-'));

        return $items === [] ? '-' : implode('; ', $items);
    }

    private function nodeText(?string $kode, ?string $text): string
    {
        unset($kode);

        return $this->trimText((string) $text) ?: '-';
    }

    private function trimText(string $value): string
    {
        return trim((string) preg_replace('/\s+/', ' ', $value));
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyIndicatorPreview(): array
    {
        return [
            'label' => '-',
            'satuan' => '-',
            'target_by_year' => [],
        ];
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    private function emptyRow(array $values = []): array
    {
        return [
            'key' => '',
            'visi' => '-',
            'misi' => '-',
            'tujuan' => '-',
            'indikator_tujuan' => '-',
            'satuan_tujuan' => '-',
            'target_tujuan_by_year' => [],
            'sasaran' => '-',
            'indikator_sasaran' => '-',
            'satuan_sasaran' => '-',
            'target_sasaran_by_year' => [],
            'strategi' => '-',
            'program' => '-',
            'indikator_program' => '-',
            'satuan_program' => '-',
            'target_program_by_year' => [],
            'opd_penanggung_jawab' => '-',
            'status_keterhubungan' => '-',
            ...$values,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array<string, mixed>>
     */
    private function suppressRepeatedValues(array $rows): array
    {
        $repeatedKeys = [
            'visi',
            'misi',
            'tujuan',
            'indikator_tujuan',
            'sasaran',
            'indikator_sasaran',
            'strategi',
            'program',
            'indikator_program',
            'opd_penanggung_jawab',
        ];
        $previous = [];

        return array_map(function (array $row) use ($repeatedKeys, &$previous): array {
            foreach ($repeatedKeys as $key) {
                $value = trim((string) ($row[$key] ?? ''));

                if ($value === '' || $value === '-') {
                    $row[$key] = '';

                    continue;
                }

                if (($previous[$key] ?? null) === $value) {
                    $row[$key] = '';

                    continue;
                }

                $previous[$key] = $value;
            }

            if (($row['status_keterhubungan'] ?? null) === '-') {
                $row['status_keterhubungan'] = '';
            }

            if (blank($row['indikator_tujuan'] ?? null)) {
                $row['satuan_tujuan'] = '';
                $row['target_tujuan_by_year'] = [];
            }

            if (blank($row['indikator_sasaran'] ?? null)) {
                $row['satuan_sasaran'] = '';
                $row['target_sasaran_by_year'] = [];
            }

            if (blank($row['indikator_program'] ?? null)) {
                $row['satuan_program'] = '';
                $row['target_program_by_year'] = [];
            }

            return $row;
        }, $rows);
    }

    private function visiHasVisiblePrograms(RpjmdVisi $visi, int $opdId): bool
    {
        return $visi->tujuan->contains(fn (TujuanDaerah $tujuan) => $this->tujuanHasVisiblePrograms($tujuan, $opdId));
    }

    private function tujuanHasVisiblePrograms(TujuanDaerah $tujuan, int $opdId): bool
    {
        return $tujuan->sasaran->contains(fn (SasaranDaerah $sasaran) => $this->sasaranHasVisiblePrograms($sasaran, $opdId));
    }

    private function sasaranHasVisiblePrograms(SasaranDaerah $sasaran, int $opdId): bool
    {
        return $sasaran->programs->contains(fn (ProgramRpjmd $program) => $program->isRelevantForOpd($opdId));
    }

    /**
     * @param  array<int, int>  $years
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function buildWorkbook(Rpjmd $rpjmd, array $years, array $rows): string
    {
        if (! class_exists(ZipArchive::class)) {
            throw new RuntimeException('Ekstensi PHP ZipArchive belum aktif, export .xlsx tidak bisa dibuat.');
        }

        $path = tempnam(sys_get_temp_dir(), 'sakip_rpjmd_preview_');
        $zip = new ZipArchive;

        if ($path === false || $zip->open($path, ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('File export sementara tidak bisa dibuat.');
        }

        $zip->addFromString('[Content_Types].xml', $this->contentTypes());
        $zip->addFromString('_rels/.rels', $this->rootRelationships());
        $zip->addFromString('docProps/app.xml', $this->appProperties());
        $zip->addFromString('docProps/core.xml', $this->coreProperties($rpjmd));
        $zip->addFromString('xl/workbook.xml', $this->workbookXml());
        $zip->addFromString('xl/_rels/workbook.xml.rels', $this->workbookRelationships());
        $zip->addFromString('xl/styles.xml', $this->stylesXml());
        $zip->addFromString('xl/worksheets/sheet1.xml', $this->worksheetXml($rpjmd, $years, $rows));
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
    private function worksheetXml(Rpjmd $rpjmd, array $years, array $rows): string
    {
        $lastColumn = $this->columnName(14 + (count($years) * 3));
        $lastRow = max(count($rows) + 2, 3);
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<dimension ref="A1:'.$lastColumn.$lastRow.'"/>'
            .'<sheetViews><sheetView workbookViewId="0"><pane ySplit="2" topLeftCell="A3" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>'
            .$this->columnsXml($years)
            .'<sheetData>'
            .$this->headerRowsXml($rpjmd, $years);

        foreach ($rows as $index => $row) {
            $excelRow = $index + 3;
            $xml .= $this->dataRowXml($excelRow, $rpjmd, $years, $row);
        }

        if ($rows === []) {
            $xml .= '<row r="3" ht="30" customHeight="1">'.$this->inlineCell('A3', 'Belum ada data cascading RPJMD.', 2).'</row>';
        }

        return $xml.'</sheetData>'.$this->mergeCellsXml($years).'</worksheet>';
    }

    /**
     * @param  array<int, int>  $years
     */
    private function headerRowsXml(Rpjmd $rpjmd, array $years): string
    {
        $rowOne = [];
        $rowTwo = [];
        $column = 1;

        foreach (['Visi', 'Misi', 'Tujuan', 'Indikator Tujuan', 'Satuan'] as $label) {
            $rowOne[] = $this->inlineCell($this->columnName($column).'1', $label, 1);
            $column++;
        }

        $rowOne[] = $this->inlineCell($this->columnName($column).'1', 'Target / Prakiraan Maju', 1);
        foreach ($years as $year) {
            $rowTwo[] = $this->inlineCell($this->columnName($column).'2', $this->targetYearLabel($rpjmd, $year), 1);
            $column++;
        }

        foreach (['Sasaran Strategis', 'Indikator Kinerja Sasaran Strategis', 'Satuan'] as $label) {
            $rowOne[] = $this->inlineCell($this->columnName($column).'1', $label, 1);
            $column++;
        }

        $rowOne[] = $this->inlineCell($this->columnName($column).'1', 'Target / Prakiraan Maju', 1);
        foreach ($years as $year) {
            $rowTwo[] = $this->inlineCell($this->columnName($column).'2', $this->targetYearLabel($rpjmd, $year), 1);
            $column++;
        }

        foreach (['Strategi', 'Program RPJMD', 'Indikator Program', 'Satuan'] as $label) {
            $rowOne[] = $this->inlineCell($this->columnName($column).'1', $label, 1);
            $column++;
        }

        $rowOne[] = $this->inlineCell($this->columnName($column).'1', 'Target / Prakiraan Maju', 1);
        foreach ($years as $year) {
            $rowTwo[] = $this->inlineCell($this->columnName($column).'2', $this->targetYearLabel($rpjmd, $year), 1);
            $column++;
        }

        foreach (['OPD', 'Status'] as $label) {
            $rowOne[] = $this->inlineCell($this->columnName($column).'1', $label, 1);
            $column++;
        }

        return '<row r="1" ht="42" customHeight="1">'.implode('', $rowOne).'</row>'
            .'<row r="2" ht="30" customHeight="1">'.implode('', $rowTwo).'</row>';
    }

    /**
     * @param  array<int, int>  $years
     * @param  array<string, mixed>  $row
     */
    private function dataRowXml(int $excelRow, Rpjmd $rpjmd, array $years, array $row): string
    {
        $cells = [];
        $column = 1;
        $append = function (string $value, int $style = 2) use (&$cells, &$column, $excelRow): void {
            $cells[] = $this->inlineCell($this->columnName($column).$excelRow, $value, $style);
            $column++;
        };

        $append((string) ($row['visi'] ?? ''), 4);
        $append((string) ($row['misi'] ?? ''));
        $append((string) ($row['tujuan'] ?? ''), 4);
        $append((string) ($row['indikator_tujuan'] ?? ''));
        $append((string) ($row['satuan_tujuan'] ?? ''), 3);
        $this->appendTargetCells($append, $rpjmd, $years, $row['target_tujuan_by_year'] ?? []);
        $append((string) ($row['sasaran'] ?? ''), 4);
        $append((string) ($row['indikator_sasaran'] ?? ''));
        $append((string) ($row['satuan_sasaran'] ?? ''), 3);
        $this->appendTargetCells($append, $rpjmd, $years, $row['target_sasaran_by_year'] ?? []);
        $append((string) ($row['strategi'] ?? ''));
        $append((string) ($row['program'] ?? ''), 4);
        $append((string) ($row['indikator_program'] ?? ''));
        $append((string) ($row['satuan_program'] ?? ''), 3);
        $this->appendTargetCells($append, $rpjmd, $years, $row['target_program_by_year'] ?? []);
        $append((string) ($row['opd_penanggung_jawab'] ?? ''));
        $append((string) ($row['status_keterhubungan'] ?? ''), $row['status_keterhubungan'] === 'Terhubung OPD' ? 6 : 7);

        return '<row r="'.$excelRow.'" ht="54" customHeight="1">'.implode('', $cells).'</row>';
    }

    /**
     * @param  callable(string, int): void  $append
     * @param  array<int, int>  $years
     * @param  array<int|string, string>  $targets
     */
    private function appendTargetCells(callable $append, Rpjmd $rpjmd, array $years, array $targets): void
    {
        foreach ($years as $year) {
            $append((string) ($targets[$year] ?? ''), $this->isPrakiraanMajuYear($rpjmd, $year) ? 5 : 3);
        }
    }

    /**
     * @param  array<int, int>  $years
     */
    private function mergeCellsXml(array $years): string
    {
        $merges = [];
        $column = 1;

        for ($index = 0; $index < 5; $index++, $column++) {
            $merges[] = $this->columnName($column).'1:'.$this->columnName($column).'2';
        }

        $merges[] = $this->columnName($column).'1:'.$this->columnName($column + count($years) - 1).'1';
        $column += count($years);

        for ($index = 0; $index < 3; $index++, $column++) {
            $merges[] = $this->columnName($column).'1:'.$this->columnName($column).'2';
        }

        $merges[] = $this->columnName($column).'1:'.$this->columnName($column + count($years) - 1).'1';
        $column += count($years);

        for ($index = 0; $index < 4; $index++, $column++) {
            $merges[] = $this->columnName($column).'1:'.$this->columnName($column).'2';
        }

        $merges[] = $this->columnName($column).'1:'.$this->columnName($column + count($years) - 1).'1';
        $column += count($years);

        for ($index = 0; $index < 2; $index++, $column++) {
            $merges[] = $this->columnName($column).'1:'.$this->columnName($column).'2';
        }

        return '<mergeCells count="'.count($merges).'">'.collect($merges)->map(fn (string $ref) => '<mergeCell ref="'.$ref.'"/>')->implode('').'</mergeCells>';
    }

    /**
     * @param  array<int, int>  $years
     */
    private function columnsXml(array $years): string
    {
        $widths = [25, 26, 27, 27, 11];
        $widths = array_merge($widths, array_fill(0, count($years), 12), [27, 30, 11], array_fill(0, count($years), 12), [26, 30, 30, 11], array_fill(0, count($years), 12), [24, 18]);
        $columns = '';

        foreach ($widths as $index => $width) {
            $column = $index + 1;
            $columns .= '<col min="'.$column.'" max="'.$column.'" width="'.$width.'" customWidth="1"/>';
        }

        return '<cols>'.$columns.'</cols>';
    }

    private function inlineCell(string $cell, string $value, int $style): string
    {
        return '<c r="'.$cell.'" s="'.$style.'" t="inlineStr"><is><t xml:space="preserve">'.$this->escape($value).'</t></is></c>';
    }

    /**
     * @param  array<int, int>  $years
     */
    private function targetYearLabel(Rpjmd $rpjmd, int $year): string
    {
        return $this->isPrakiraanMajuYear($rpjmd, $year) ? "{$year} PM" : (string) $year;
    }

    /**
     * @param  array<int, int>  $years
     */
    private function isPrakiraanMajuYear(Rpjmd $rpjmd, int $year): bool
    {
        return $year > (int) $rpjmd->tahun_akhir;
    }

    private function workbookXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            .'<sheets><sheet name="Preview RPJMD" sheetId="1" r:id="rId1"/></sheets></workbook>';
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
            .'<fills count="5">'
            .'<fill><patternFill patternType="none"/></fill>'
            .'<fill><patternFill patternType="gray125"/></fill>'
            .'<fill><patternFill patternType="solid"><fgColor rgb="FFB8D1F6"/><bgColor indexed="64"/></patternFill></fill>'
            .'<fill><patternFill patternType="solid"><fgColor rgb="FFDCEBFF"/><bgColor indexed="64"/></patternFill></fill>'
            .'<fill><patternFill patternType="solid"><fgColor rgb="FFFFF3CD"/><bgColor indexed="64"/></patternFill></fill>'
            .'</fills>'
            .'<borders count="2">'
            .'<border><left/><right/><top/><bottom/><diagonal/></border>'
            .'<border><left style="thin"><color rgb="FFB8C7D9"/></left><right style="thin"><color rgb="FFB8C7D9"/></right><top style="thin"><color rgb="FFB8C7D9"/></top><bottom style="thin"><color rgb="FFB8C7D9"/></bottom><diagonal/></border>'
            .'</borders>'
            .'<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            .'<cellXfs count="8">'
            .'<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
            .'<xf numFmtId="0" fontId="1" fillId="2" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf>'
            .'<xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyBorder="1" applyAlignment="1"><alignment vertical="top" wrapText="1"/></xf>'
            .'<xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="top" wrapText="1"/></xf>'
            .'<xf numFmtId="0" fontId="1" fillId="0" borderId="1" xfId="0" applyFont="1" applyBorder="1" applyAlignment="1"><alignment vertical="top" wrapText="1"/></xf>'
            .'<xf numFmtId="0" fontId="1" fillId="3" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="top" wrapText="1"/></xf>'
            .'<xf numFmtId="0" fontId="1" fillId="0" borderId="1" xfId="0" applyFont="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="top" wrapText="1"/></xf>'
            .'<xf numFmtId="0" fontId="1" fillId="4" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="top" wrapText="1"/></xf>'
            .'</cellXfs>'
            .'</styleSheet>';
    }

    private function appProperties(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties" xmlns:vt="http://schemas.openxmlformats.org/officeDocument/2006/docPropsVTypes">'
            .'<Application>E-SAKIP Kabupaten Banjarnegara</Application></Properties>';
    }

    private function coreProperties(Rpjmd $rpjmd): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">'
            .'<dc:creator>E-SAKIP Kabupaten Banjarnegara</dc:creator>'
            .'<dc:title>'.$this->escape($rpjmd->judul).'</dc:title>'
            .'<dcterms:created xsi:type="dcterms:W3CDTF">'.now()->toISOString().'</dcterms:created>'
            .'</cp:coreProperties>';
    }

    private function filename(Rpjmd $rpjmd): string
    {
        $slug = str($rpjmd->judul)
            ->lower()
            ->replaceMatches('/[^a-z0-9]+/', '-')
            ->trim('-')
            ->limit(80, '')
            ->toString();

        return 'preview-cascading-rpjmd-'.$slug.'.xlsx';
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
}
