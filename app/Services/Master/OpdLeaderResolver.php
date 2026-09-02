<?php

namespace App\Services\Master;

use App\Models\Opd;
use App\Models\RiwayatPejabatJabatan;
use Carbon\CarbonImmutable;
use DateTimeInterface;

class OpdLeaderResolver
{
    /**
     * @return array{
     *     name: string|null,
     *     nip: string|null,
     *     position: string,
     *     rank: string|null,
     *     source: string,
     *     placement_id: int|null,
     *     employee_id: int|null
     * }
     */
    public function resolve(Opd $opd, DateTimeInterface|string|null $at = null): array
    {
        $date = $at instanceof DateTimeInterface
            ? CarbonImmutable::instance($at)->toDateString()
            : CarbonImmutable::parse($at ?: now())->toDateString();

        $placement = RiwayatPejabatJabatan::query()
            ->with([
                'pegawai:id,nama,nip,pangkat_golongan,status',
                'jabatanOrganisasi:id,opd_id,parent_id,nama,level_jabatan,urutan,status,verification_status',
            ])
            ->whereDate('tanggal_mulai', '<=', $date)
            ->where(fn ($query) => $query
                ->whereNull('tanggal_selesai')
                ->orWhereDate('tanggal_selesai', '>=', $date))
            ->whereHas('pegawai', fn ($query) => $query->where('status', 'active'))
            ->whereHas('jabatanOrganisasi', fn ($query) => $query
                ->where('opd_id', $opd->getKey())
                ->where('status', 'active')
                ->where('verification_status', 'verified')
                ->whereNotIn('level_jabatan', ['fungsional', 'pelaksana'])
                ->where(fn ($query) => $query
                    ->where('level_jabatan', 'jpt_pratama')
                    ->orWhereNull('parent_id')))
            ->get()
            ->sortBy(fn (RiwayatPejabatJabatan $item) => [
                $item->jabatanOrganisasi?->level_jabatan === 'jpt_pratama' ? 0 : 1,
                match ($item->jenis_penugasan) {
                    'definitif' => 1,
                    'penjabat' => 2,
                    'plt' => 3,
                    'plh' => 4,
                    default => 5,
                },
                $item->jabatanOrganisasi?->urutan ?? PHP_INT_MAX,
                -$item->tanggal_mulai?->getTimestamp(),
                -$item->getKey(),
            ])
            ->first();

        if ($placement && $placement->pegawai && $placement->jabatanOrganisasi) {
            return [
                'name' => $placement->pegawai->nama ?: $placement->nama_pejabat,
                'nip' => $placement->pegawai->nip ?: $placement->nip,
                'position' => $placement->jabatanOrganisasi->nama,
                'rank' => $placement->pegawai->pangkat_golongan ?: $placement->pangkat_golongan,
                'source' => 'struktur_organisasi',
                'placement_id' => $placement->getKey(),
                'employee_id' => $placement->pegawai->getKey(),
            ];
        }

        return [
            'name' => $opd->nama_kepala ?: null,
            'nip' => $opd->nip_kepala ?: null,
            'position' => 'Kepala '.$opd->nama,
            'rank' => null,
            'source' => filled($opd->nama_kepala) || filled($opd->nip_kepala) ? 'opd_legacy' : 'unavailable',
            'placement_id' => null,
            'employee_id' => null,
        ];
    }
}
