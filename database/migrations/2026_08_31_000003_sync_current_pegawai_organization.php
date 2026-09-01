<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $today = now()->toDateString();

        $currentPlacements = DB::table('riwayat_pejabat_jabatan as placement')
            ->join('pegawai as employee', 'employee.id', '=', 'placement.pegawai_id')
            ->join('jabatan_organisasi as job', 'job.id', '=', 'placement.jabatan_organisasi_id')
            ->whereNull('placement.deleted_at')
            ->whereNull('employee.deleted_at')
            ->whereNull('job.deleted_at')
            ->whereDate('placement.tanggal_mulai', '<=', $today)
            ->where(fn ($query) => $query
                ->whereNull('placement.tanggal_selesai')
                ->orWhereDate('placement.tanggal_selesai', '>=', $today))
            ->get([
                'placement.id',
                'placement.pegawai_id',
                'placement.jenis_penugasan',
                'placement.tanggal_mulai',
                'employee.user_id',
                'employee.nama',
                'employee.nip',
                'employee.pangkat_golongan',
                'job.opd_id',
                'job.opd_unit_id',
            ]);

        $priority = ['definitif' => 1, 'penjabat' => 2, 'plt' => 3, 'plh' => 4];

        foreach ($currentPlacements->groupBy('pegawai_id') as $employeeId => $placements) {
            foreach ($placements as $placement) {
                DB::table('riwayat_pejabat_jabatan')
                    ->where('id', $placement->id)
                    ->update([
                        'user_id' => $placement->user_id,
                        'nama_pejabat' => $placement->nama,
                        'nip' => $placement->nip,
                        'pangkat_golongan' => $placement->pangkat_golongan,
                        'updated_at' => now(),
                    ]);
            }

            $primary = $placements->sort(function (object $left, object $right) use ($priority): int {
                $rankComparison = ($priority[$left->jenis_penugasan] ?? 5) <=> ($priority[$right->jenis_penugasan] ?? 5);
                if ($rankComparison !== 0) {
                    return $rankComparison;
                }

                $dateComparison = strcmp((string) $right->tanggal_mulai, (string) $left->tanggal_mulai);

                return $dateComparison !== 0 ? $dateComparison : ((int) $right->id <=> (int) $left->id);
            })->first();

            if ($primary) {
                DB::table('pegawai')
                    ->where('id', $employeeId)
                    ->update([
                        'opd_id' => $primary->opd_id,
                        'opd_unit_id' => $primary->opd_unit_id,
                        'updated_at' => now(),
                    ]);
            }
        }
    }

    public function down(): void
    {
        // Data synchronization is intentionally not reversed.
    }
};
