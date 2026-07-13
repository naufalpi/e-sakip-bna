<?php

namespace App\Services\Imports;

use RuntimeException;

class ImportColumnValidationService
{
    /**
     * @param  array<int, string>  $columns
     * @return array<string, mixed>
     */
    public function validate(string $module, array $columns): array
    {
        $columns = array_values(array_filter($columns));
        $groups = $this->requiredGroups($module);
        $missing = [];

        foreach ($groups as $label => $aliases) {
            if (! $this->hasAny($columns, $aliases)) {
                $missing[] = [
                    'label' => $label,
                    'expected' => $aliases,
                ];
            }
        }

        if ($missing !== []) {
            $message = collect($missing)
                ->map(fn (array $item) => $item['label'].' (salah satu: '.implode(', ', $item['expected']).')')
                ->implode('; ');

            throw new RuntimeException("Format kolom import {$this->moduleLabel($module)} tidak sesuai template. Kolom wajib belum ada: {$message}.");
        }

        return [
            'required_groups' => $groups,
            'unknown_columns' => array_values(array_diff($columns, $this->knownColumns($module))),
        ];
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function requiredGroups(string $module): array
    {
        return match ($module) {
            'rpjmd' => [
                'level data' => ['level', 'jenis', 'tipe', 'node_type'],
                'uraian data' => ['uraian', 'nama', 'judul', 'rpjmd_judul', 'visi', 'misi', 'tujuan', 'sasaran', 'strategi', 'program', 'indikator'],
            ],
            'renstra_opd' => [
                'level data' => ['level', 'jenis', 'tipe', 'node_type'],
                'identitas OPD' => ['opd_id', 'opd_kode', 'kode_opd', 'opd_nama', 'nama_opd'],
                'referensi RPJMD' => ['rpjmd_id', 'rpjmd_judul', 'judul_rpjmd'],
                'uraian data' => ['uraian', 'nama', 'judul', 'renstra_judul', 'tujuan', 'sasaran', 'program', 'kegiatan', 'sub_kegiatan', 'indikator'],
            ],
            default => [],
        };
    }

    /**
     * @return array<int, string>
     */
    private function knownColumns(string $module): array
    {
        $common = [
            'level',
            'jenis',
            'tipe',
            'node_type',
            'kode',
            'uraian',
            'nama',
            'judul',
            'tahun_awal',
            'tahun_akhir',
            'tahun_target',
            'periode_tahun_id',
            'target',
            'target_angka',
            'target_text',
            'target_teks',
            'pagu',
            'tipe_indikator',
            'formula',
            'rumus',
            'definisi_operasional',
            'definisi_operasional_indikator',
            'definisi',
            'alasan_pemilihan',
            'alasan_pemilihan_indikator',
            'alasan',
            'formulasi_pengukuran',
            'tipe_perhitungan',
            'tipe_perhitungan_indikator',
            'sumber_data',
            'urutan',
            'status',
            'keterangan',
            'catatan',
        ];

        return match ($module) {
            'rpjmd' => array_values(array_unique([
                ...$common,
                'rpjmd_id',
                'rpjmd_judul',
                'judul_rpjmd',
                'nomor_perda',
                'nomor_dokumen',
                'visi',
                'misi',
                'tujuan',
                'sasaran',
                'strategi',
                'program',
                'indikator',
                'indikator_tujuan',
                'indikator_sasaran',
                'indikator_program',
                'satuan_indikator_id',
                'satuan_id',
                'satuan',
                'satuan_indikator',
                'satuan_simbol',
                'simbol_satuan',
                'urusan_kode',
                'urusan_pemerintahan_id',
                'strategi_kode',
                'kode_strategi',
                'misi_ids',
                'misi_id_terkait',
                'misi_kode_terkait',
                'kode_misi_terkait',
                'indikator_tujuan_ids',
                'indikator_tujuan_id_terkait',
                'indikator_tujuan_kode_terkait',
                'kode_indikator_tujuan_terkait',
                'opd_id',
                'opd_kode',
                'kode_opd',
                'opd_nama',
                'nama_opd',
                'peran',
                'is_utama',
            ])),
            'renstra_opd' => array_values(array_unique([
                ...$common,
                'renstra_opd_id',
                'renstra_id',
                'renstra_judul',
                'nomor',
                'nomor_dokumen',
                'opd_id',
                'opd_kode',
                'kode_opd',
                'opd_nama',
                'nama_opd',
                'rpjmd_id',
                'rpjmd_judul',
                'judul_rpjmd',
                'tujuan',
                'sasaran',
                'program',
                'kegiatan',
                'sub_kegiatan',
                'indikator',
                'indikator_tujuan',
                'indikator_sasaran',
                'indikator_program',
                'indikator_sub_kegiatan',
                'tujuan_daerah_id',
                'tujuan_daerah_kode',
                'kode_tujuan_daerah',
                'indikator_tujuan_daerah_id',
                'indikator_tujuan_daerah_kode',
                'sasaran_daerah_id',
                'sasaran_daerah_kode',
                'kode_sasaran_daerah',
                'indikator_sasaran_daerah_id',
                'indikator_sasaran_daerah_kode',
                'program_rpjmd_id',
                'program_rpjmd_kode',
                'kode_program_rpjmd',
                'indikator_program_rpjmd_id',
                'indikator_program_rpjmd_kode',
                'triwulan',
                'tw',
                'target_triwulan',
                'target_triwulan_text',
                'target_anggaran',
            ])),
            default => $common,
        };
    }

    /**
     * @param  array<int, string>  $columns
     * @param  array<int, string>  $aliases
     */
    private function hasAny(array $columns, array $aliases): bool
    {
        return count(array_intersect($columns, $aliases)) > 0;
    }

    private function moduleLabel(string $module): string
    {
        return match ($module) {
            'rpjmd' => 'RPJMD',
            'renstra_opd' => 'Renstra OPD',
            default => str($module)->replace('_', ' ')->title()->toString(),
        };
    }
}
