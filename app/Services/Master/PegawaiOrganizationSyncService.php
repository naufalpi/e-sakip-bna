<?php

namespace App\Services\Master;

use App\Models\JabatanOrganisasi;
use App\Models\Pegawai;
use App\Models\RiwayatPejabatJabatan;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class PegawaiOrganizationSyncService
{
    /**
     * Keep the live employee master and its active placement identity aligned.
     * Historical placements and PK snapshots are intentionally left untouched.
     */
    public function syncEmployee(Pegawai $pegawai, ?CarbonInterface $at = null): void
    {
        $date = ($at ?? now())->toDateString();

        $currentPlacements = $this->currentPlacements($pegawai, $date);

        foreach ($currentPlacements as $placement) {
            $this->syncPlacementIdentity($placement, $pegawai);
        }

        $primaryPlacement = $currentPlacements->first(
            fn (RiwayatPejabatJabatan $placement) => $placement->jabatanOrganisasi !== null
        );

        if (! $primaryPlacement?->jabatanOrganisasi) {
            return;
        }

        $pegawai->forceFill([
            'opd_id' => $primaryPlacement->jabatanOrganisasi->opd_id,
            'opd_unit_id' => $primaryPlacement->jabatanOrganisasi->opd_unit_id,
        ])->save();
    }

    /** @return array{opd_id: int|null, opd_unit_id: int|null}|null */
    public function currentOrganization(Pegawai $pegawai, ?CarbonInterface $at = null): ?array
    {
        $placement = $this->currentPlacements($pegawai, ($at ?? now())->toDateString())
            ->first(fn (RiwayatPejabatJabatan $item) => $item->jabatanOrganisasi !== null);

        if (! $placement?->jabatanOrganisasi) {
            return null;
        }

        return [
            'opd_id' => $placement->jabatanOrganisasi->opd_id,
            'opd_unit_id' => $placement->jabatanOrganisasi->opd_unit_id,
        ];
    }

    public function syncPlacementIdentity(RiwayatPejabatJabatan $placement, ?Pegawai $pegawai = null): void
    {
        $pegawai ??= $placement->pegawai;
        if (! $pegawai) {
            return;
        }

        $placement->forceFill([
            'user_id' => $pegawai->user_id,
            'nama_pejabat' => $pegawai->nama,
            'nip' => $pegawai->nip,
            'pangkat_golongan' => $pegawai->pangkat_golongan,
        ])->save();
    }

    public function syncCurrentHolders(JabatanOrganisasi $jabatan, ?CarbonInterface $at = null): void
    {
        $date = ($at ?? now())->toDateString();

        $jabatan->riwayatPejabat()
            ->with('pegawai')
            ->whereNotNull('pegawai_id')
            ->whereDate('tanggal_mulai', '<=', $date)
            ->where(fn (Builder $query) => $query
                ->whereNull('tanggal_selesai')
                ->orWhereDate('tanggal_selesai', '>=', $date))
            ->get()
            ->each(fn (RiwayatPejabatJabatan $placement) => $this->syncEmployee($placement->pegawai, $at));
    }

    /** @return Collection<int, RiwayatPejabatJabatan> */
    private function currentPlacements(Pegawai $pegawai, string $date): Collection
    {
        return $pegawai->penempatan()
            ->with('jabatanOrganisasi:id,opd_id,opd_unit_id')
            ->whereDate('tanggal_mulai', '<=', $date)
            ->where(fn (Builder $query) => $query
                ->whereNull('tanggal_selesai')
                ->orWhereDate('tanggal_selesai', '>=', $date))
            ->orderByRaw("CASE jenis_penugasan WHEN 'definitif' THEN 1 WHEN 'penjabat' THEN 2 WHEN 'plt' THEN 3 WHEN 'plh' THEN 4 ELSE 5 END")
            ->orderByDesc('tanggal_mulai')
            ->orderByDesc('id')
            ->get();
    }
}
