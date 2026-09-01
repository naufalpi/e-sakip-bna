<?php

namespace App\Services\Penganggaran;

use App\Models\DpaOpd;
use App\Models\RiwayatPejabatJabatan;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class DpaSignatoryService
{
    /** @return array<string, array<int, array<string, mixed>>> */
    public function options(User $user, ?DpaOpd $current = null): array
    {
        $currentIds = collect([
            $current?->pengguna_anggaran_penempatan_id,
            $current?->ppkd_penempatan_id,
            $current?->sekretaris_daerah_penempatan_id,
        ])->filter()->map(fn ($id) => (int) $id)->values();

        $placements = RiwayatPejabatJabatan::query()
            ->with([
                'pegawai:id,opd_id,nama,nip,status',
                'jabatanOrganisasi:id,opd_id,parent_id,nama,level_jabatan,status,verification_status',
                'jabatanOrganisasi.opd:id,nama,singkatan',
            ])
            ->where(function ($query) use ($currentIds): void {
                $query->where(function ($query): void {
                    $query->whereDate('tanggal_mulai', '<=', today())
                        ->where(fn ($query) => $query->whereNull('tanggal_selesai')->orWhereDate('tanggal_selesai', '>=', today()))
                        ->whereHas('pegawai', fn ($query) => $query->where('status', 'active'))
                        ->whereHas('jabatanOrganisasi', fn ($query) => $query
                            ->where('status', 'active')
                            ->where('verification_status', 'verified'));
                });

                if ($currentIds->isNotEmpty()) {
                    $query->orWhereIn('id', $currentIds);
                }
            })
            ->orderByDesc('tanggal_mulai')
            ->get()
            ->filter(fn (RiwayatPejabatJabatan $placement) => $placement->pegawai && $placement->jabatanOrganisasi)
            ->unique(fn (RiwayatPejabatJabatan $placement) => $placement->pegawai_id.'-'.$placement->jabatan_organisasi_id)
            ->values();

        $budgetUsers = $placements
            ->filter(fn (RiwayatPejabatJabatan $placement) => $this->matchesRole($placement, 'pengguna_anggaran'))
            ->filter(fn (RiwayatPejabatJabatan $placement) => ! $user->hasRole('admin_opd') || $user->canAccessOpd($placement->jabatanOrganisasi?->opd_id))
            ->map(fn (RiwayatPejabatJabatan $placement) => $this->serialize($placement))
            ->values()
            ->all();

        $canVerify = $user->isSuperAdmin() || $user->hasPermission('dpa.verify');

        return [
            'budgetUsers' => $budgetUsers,
            'ppkd' => $canVerify
                ? $this->roleOptions($placements, 'ppkd', $current?->ppkd_penempatan_id)
                : [],
            'regionalSecretaries' => $canVerify
                ? $this->roleOptions($placements, 'sekretaris_daerah', $current?->sekretaris_daerah_penempatan_id)
                : [],
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function applySelections(array $payload, int $opdId, User $user, ?DpaOpd $current = null): array
    {
        foreach ([
            'pengguna_anggaran' => 'pengguna_anggaran',
            'ppkd' => 'ppkd',
            'sekretaris_daerah' => 'sekretaris_daerah',
        ] as $prefix => $role) {
            $placementField = "{$prefix}_penempatan_id";
            if (! array_key_exists($placementField, $payload)) {
                continue;
            }

            $employeeField = "{$prefix}_pegawai_id";
            $nameField = "nama_{$prefix}";
            $nipField = "nip_{$prefix}";
            $selectedId = filled($payload[$placementField]) ? (int) $payload[$placementField] : null;
            $currentPlacementId = $current?->{$placementField};

            if ($selectedId && $current && (int) $currentPlacementId === $selectedId) {
                $payload[$employeeField] = $current->{$employeeField};
                $payload[$nameField] = $current->{$nameField};
                $payload[$nipField] = $current->{$nipField};

                continue;
            }

            if ($selectedId) {
                $placement = $this->resolveActivePlacement($selectedId, $role, $opdId, $placementField);
                $payload[$employeeField] = $placement->pegawai_id;
                $payload[$nameField] = $placement->pegawai?->nama;
                $payload[$nipField] = $placement->pegawai?->nip;

                continue;
            }

            $payload[$employeeField] = null;
            if (! $user->isSuperAdmin()) {
                $payload[$nameField] = $current?->{$nameField};
                $payload[$nipField] = $current?->{$nipField};
                $payload[$placementField] = $current?->{$placementField};
                $payload[$employeeField] = $current?->{$employeeField};
            }
        }

        return $payload;
    }

    /** @param Collection<int, RiwayatPejabatJabatan> $placements */
    private function roleOptions(Collection $placements, string $role, ?int $currentPlacementId): array
    {
        return $placements
            ->filter(fn (RiwayatPejabatJabatan $placement) => $this->matchesRole($placement, $role) || (int) $placement->id === (int) $currentPlacementId)
            ->map(fn (RiwayatPejabatJabatan $placement) => $this->serialize($placement))
            ->values()
            ->all();
    }

    private function resolveActivePlacement(int $placementId, string $role, int $opdId, string $field): RiwayatPejabatJabatan
    {
        $placement = RiwayatPejabatJabatan::query()
            ->with([
                'pegawai:id,opd_id,nama,nip,status',
                'jabatanOrganisasi:id,opd_id,parent_id,nama,level_jabatan,status,verification_status',
                'jabatanOrganisasi.opd:id,nama,singkatan',
            ])
            ->whereKey($placementId)
            ->whereDate('tanggal_mulai', '<=', today())
            ->where(fn ($query) => $query->whereNull('tanggal_selesai')->orWhereDate('tanggal_selesai', '>=', today()))
            ->whereHas('pegawai', fn ($query) => $query->where('status', 'active'))
            ->whereHas('jabatanOrganisasi', fn ($query) => $query
                ->where('status', 'active')
                ->where('verification_status', 'verified'))
            ->first();

        if (! $placement || ! $this->matchesRole($placement, $role, $opdId)) {
            throw ValidationException::withMessages([
                $field => 'Pejabat yang dipilih tidak aktif atau tidak sesuai dengan peran penandatangan DPA.',
            ]);
        }

        return $placement;
    }

    private function matchesRole(RiwayatPejabatJabatan $placement, string $role, ?int $opdId = null): bool
    {
        $job = $placement->jabatanOrganisasi;
        if (! $job) {
            return false;
        }

        $jobName = mb_strtoupper($job->nama);
        $opdName = mb_strtoupper(trim(($job->opd?->singkatan ?? '').' '.($job->opd?->nama ?? '')));

        return match ($role) {
            'pengguna_anggaran' => ($job->level_jabatan === 'jpt_pratama' || $job->parent_id === null)
                && ($opdId === null || (int) $job->opd_id === $opdId),
            'ppkd' => str_contains($jobName, 'PPKD')
                || ($job->level_jabatan === 'jpt_pratama' && str($opdName)->contains(['BPKAD', 'BKAD', 'KEUANGAN DAERAH', 'PENGELOLAAN KEUANGAN'])),
            'sekretaris_daerah' => str_contains($jobName, 'SEKRETARIS DAERAH'),
            default => false,
        };
    }

    /** @return array<string, mixed> */
    private function serialize(RiwayatPejabatJabatan $placement): array
    {
        $employee = $placement->pegawai;
        $job = $placement->jabatanOrganisasi;
        $opdLabel = $job?->opd?->singkatan ?: $job?->opd?->nama;

        return [
            'placement_id' => $placement->id,
            'employee_id' => $placement->pegawai_id,
            'opd_id' => $job?->opd_id,
            'name' => $employee?->nama,
            'nip' => $employee?->nip,
            'position' => $job?->nama,
            'opd_label' => $opdLabel,
            'label' => $employee?->nama.($employee?->nip ? " · NIP {$employee->nip}" : '')." · {$job?->nama}",
        ];
    }
}
