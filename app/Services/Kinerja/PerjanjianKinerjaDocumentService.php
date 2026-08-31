<?php

namespace App\Services\Kinerja;

use App\Models\PerjanjianKinerja;
use App\Models\PerjanjianKinerjaItem;
use Illuminate\Support\Collection;

class PerjanjianKinerjaDocumentService
{
    public function build(PerjanjianKinerja $pk): array
    {
        $pk->loadMissing([
            'opd:id,kode,nama,singkatan,alamat,telepon,email,nama_kepala,nip_kepala',
            'periodeTahun:id,tahun,nama',
            'pegawai:id,nama,nip,pangkat_golongan',
            'atasanPegawai:id,nama,nip,pangkat_golongan',
            'penempatanPegawai.jabatanOrganisasi:id,nama,level_jabatan',
            'items.satuanIndikator:id,nama,simbol',
            'programs',
        ]);

        $isBupati = $pk->level_pk === 'bupati';
        $firstParty = [
            'name' => $pk->nama_pegawai_snapshot ?: $pk->pegawai?->nama ?: '(nama pejabat)',
            'nip' => $pk->nip_snapshot ?: $pk->pegawai?->nip,
            'position' => $pk->jabatan_snapshot ?: $pk->penempatanPegawai?->jabatanOrganisasi?->nama ?: ($isBupati ? 'Bupati Banjarnegara' : 'Pimpinan Perangkat Daerah'),
        ];
        $secondParty = $isBupati ? null : [
            'name' => $pk->nama_atasan_snapshot ?: $pk->atasanPegawai?->nama ?: '(nama atasan)',
            'nip' => $pk->nip_atasan_snapshot ?: $pk->atasanPegawai?->nip,
            'position' => $pk->jabatan_atasan_snapshot ?: 'Bupati Banjarnegara',
        ];

        $date = $pk->tanggal_dokumen ?: $pk->created_at;
        $placeDate = ($pk->tempat_penandatanganan ?: 'Banjarnegara').', '.($date?->translatedFormat('j F Y') ?: '....................');
        $programs = $pk->programs->map(fn ($program) => [
            'id' => $program->id,
            'code' => $program->kode,
            'name' => $program->nama_program,
            'budget' => (float) $program->anggaran,
            'budget_label' => $this->money($program->anggaran),
            'note' => $program->keterangan ?: 'APBD',
        ])->values();

        return [
            'id' => $pk->id,
            'is_bupati' => $isBupati,
            'level' => $pk->level_pk,
            'level_label' => $pk->levelLabel(),
            'title' => 'PERJANJIAN KINERJA TAHUN '.$pk->tahun,
            'year' => $pk->tahun,
            'document_number' => $pk->nomor_dokumen,
            'place_date' => $placeDate,
            'agency_name' => 'PEMERINTAH KABUPATEN BANJARNEGARA',
            'office_name' => $isBupati ? 'PEMERINTAH KABUPATEN BANJARNEGARA' : strtoupper((string) ($pk->opd?->nama ?: 'PERANGKAT DAERAH')),
            'address' => $isBupati ? 'Jl. A. Yani No. 16, Banjarnegara' : ($pk->opd?->alamat ?: 'Kabupaten Banjarnegara, Jawa Tengah'),
            'contact' => collect([$pk->opd?->telepon ? 'Telp. '.$pk->opd->telepon : null, $pk->opd?->email])->filter()->implode(' · '),
            'first_party' => $firstParty,
            'second_party' => $secondParty,
            'performance_groups' => $this->performanceGroups($pk->items),
            'programs' => $programs->all(),
            'total_budget' => (float) $programs->sum('budget'),
            'total_budget_label' => $this->money($programs->sum('budget')),
            'missing_targets_count' => $pk->items->filter(fn (PerjanjianKinerjaItem $item) => blank($item->target_text) && $item->target === null)->count(),
            'source_label' => $this->sourceLabel($pk),
        ];
    }

    private function performanceGroups(Collection $items): array
    {
        $groups = [];
        $sequence = 0;

        foreach ($items as $item) {
            $key = implode(':', [$item->jenis_item, $item->kode, $item->sasaran]);

            if (! isset($groups[$key])) {
                $isGoal = in_array($item->jenis_item, ['tujuan', 'tujuan_opd'], true);
                if (! $isGoal) {
                    $sequence++;
                }

                $groups[$key] = [
                    'number' => $isGoal ? null : $sequence,
                    'type' => $item->jenis_item,
                    'type_label' => $this->itemTypeLabel($item->jenis_item),
                    'code' => $item->kode,
                    'performance' => $item->sasaran,
                    'indicators' => [],
                ];
            }

            $groups[$key]['indicators'][] = [
                'id' => $item->id,
                'name' => $item->indikator,
                'target' => filled($item->target_text) ? $item->target_text : $this->number($item->target),
                'unit' => $item->satuan_snapshot ?: $item->satuanIndikator?->simbol ?: $item->satuanIndikator?->nama ?: '-',
            ];
        }

        return array_values($groups);
    }

    private function itemTypeLabel(?string $type): string
    {
        return match ($type) {
            'tujuan' => 'Tujuan Kabupaten',
            'sasaran' => 'Sasaran Kabupaten',
            'tujuan_opd' => 'Tujuan OPD',
            'sasaran_opd' => 'Sasaran OPD',
            'program_opd' => 'Program OPD',
            'kegiatan' => 'Kegiatan',
            'sub_kegiatan' => 'Sub Kegiatan',
            default => 'Hasil Kerja',
        };
    }

    private function sourceLabel(PerjanjianKinerja $pk): string
    {
        return match ($pk->sumber_data) {
            'rkpd' => 'RKPD resmi Tahun '.$pk->tahun,
            'dpa' => 'Renstra dan DPA/DPPA resmi Tahun '.$pk->tahun,
            'penugasan' => 'Penugasan pengampu kinerja',
            default => 'Input manual',
        };
    }

    private function number(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '-';
        }

        return rtrim(rtrim(number_format((float) $value, 4, ',', '.'), '0'), ',');
    }

    private function money(mixed $value): string
    {
        return 'Rp '.number_format((float) ($value ?? 0), 0, ',', '.');
    }
}
