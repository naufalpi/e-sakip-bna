<?php

namespace App\Services\Master;

use App\Models\ImportBatch;
use App\Models\ImportBatchRow;
use App\Models\JabatanOrganisasi;
use App\Models\Pegawai;
use App\Models\RiwayatPejabatJabatan;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class JabatanOrganisasiImportApplyService
{
    public function apply(ImportBatch $batch, User $user): ImportBatch
    {
        if ($batch->module !== 'jabatan_organisasi' || $batch->import_type !== 'jabatan_dan_pejabat') {
            throw ValidationException::withMessages(['import_batch_id' => 'Batch import tidak sesuai dengan Master Jabatan Organisasi.']);
        }

        if ($batch->status !== 'previewed') {
            throw ValidationException::withMessages(['import_batch_id' => 'Batch hanya dapat diterapkan setelah preview selesai.']);
        }

        $invalidRows = $batch->rows()->where('status', 'invalid')->count();
        $validRows = $batch->rows()->where('status', 'valid')->count();

        if ($invalidRows > 0) {
            throw ValidationException::withMessages(['import_batch_id' => 'Perbaiki seluruh baris tidak valid sebelum menerapkan import.']);
        }

        if ($validRows === 0) {
            throw ValidationException::withMessages(['import_batch_id' => 'Tidak ada data valid yang dapat diterapkan.']);
        }

        DB::transaction(function () use ($batch, $user): void {
            $batch->update(['status' => 'processing']);
            $rows = $batch->rows()->where('status', 'valid')->orderBy('row_number')->lockForUpdate()->get();
            $jobRows = $rows->filter(fn (ImportBatchRow $row) => ($row->normalized_data['entity_type'] ?? null) === 'jabatan');
            $officialRows = $rows->filter(fn (ImportBatchRow $row) => ($row->normalized_data['entity_type'] ?? null) === 'pejabat');
            $jobsByKey = [];

            foreach ($jobRows as $row) {
                $prepared = $row->normalized_data['prepared'] ?? [];
                $payload = [
                    'opd_id' => $prepared['opd_id'] ?? null,
                    'opd_unit_id' => $prepared['opd_unit_id'] ?? null,
                    'nama' => $prepared['nama'],
                    'level_jabatan' => $prepared['level_jabatan'],
                    'eselon' => $prepared['eselon'] ?? null,
                    'urutan' => $prepared['urutan'] ?? 0,
                    'status' => $prepared['status'] ?? 'active',
                ];

                if ($existingId = $prepared['existing_id'] ?? null) {
                    $job = JabatanOrganisasi::query()->lockForUpdate()->find($existingId);
                    if (! $job) {
                        throw ValidationException::withMessages(['import_batch_id' => "Jabatan pada baris {$row->raw_data['sheet_row']} berubah atau sudah dihapus. Upload ulang file untuk validasi terbaru."]);
                    }
                    $job->update($payload);
                } else {
                    $job = JabatanOrganisasi::create([...$payload, 'parent_id' => null]);
                }

                $jobsByKey[$prepared['identity_key']] = $job;
            }

            foreach ($jobRows as $row) {
                $prepared = $row->normalized_data['prepared'] ?? [];
                $job = $jobsByKey[$prepared['identity_key']];
                $parent = $prepared['parent_key'] ? ($jobsByKey[$prepared['parent_key']] ?? null) : null;

                if (! $parent && ($prepared['parent_existing_id'] ?? null)) {
                    $parent = JabatanOrganisasi::query()->find($prepared['parent_existing_id']);
                }

                if ($prepared['parent_key'] && ! $parent) {
                    throw ValidationException::withMessages(['import_batch_id' => "Atasan jabatan pada baris {$row->raw_data['sheet_row']} berubah atau sudah dihapus. Upload ulang file."]);
                }

                $job->update(['parent_id' => $parent?->id]);
                $row->update([
                    'status' => 'imported',
                    'normalized_data' => [...($row->normalized_data ?? []), 'applied' => ['jabatan_organisasi_id' => $job->id]],
                ]);
            }

            foreach ($officialRows as $row) {
                $prepared = $row->normalized_data['prepared'] ?? [];
                $job = $jobsByKey[$prepared['jabatan_key']] ?? null;

                if (! $job && ($prepared['jabatan_existing_id'] ?? null)) {
                    $job = JabatanOrganisasi::query()->find($prepared['jabatan_existing_id']);
                }

                if (! $job) {
                    throw ValidationException::withMessages(['import_batch_id' => "Jabatan pejabat pada baris {$row->raw_data['sheet_row']} berubah atau sudah dihapus. Upload ulang file."]);
                }

                $existingId = $prepared['existing_id'] ?? null;
                $history = $existingId ? RiwayatPejabatJabatan::query()->lockForUpdate()->find($existingId) : null;

                if ($existingId && ! $history) {
                    throw ValidationException::withMessages(['import_batch_id' => "Riwayat pejabat pada baris {$row->raw_data['sheet_row']} berubah atau sudah dihapus. Upload ulang file."]);
                }

                $pegawai = $history?->pegawai ?: $this->resolvePegawai($prepared, $job);
                $overlap = RiwayatPejabatJabatan::query()
                    ->where('jabatan_organisasi_id', $job->id)
                    ->when($job->allowsMultipleHolders(), fn ($query) => $query->where('pegawai_id', $pegawai->id))
                    ->when($existingId, fn ($query) => $query->whereKeyNot($existingId))
                    ->whereDate('tanggal_mulai', '<=', $prepared['tanggal_selesai'] ?? '9999-12-31')
                    ->where(fn ($query) => $query->whereNull('tanggal_selesai')->orWhereDate('tanggal_selesai', '>=', $prepared['tanggal_mulai']))
                    ->lockForUpdate()
                    ->exists();

                if ($overlap) {
                    throw ValidationException::withMessages(['import_batch_id' => "Masa tugas pada baris {$row->raw_data['sheet_row']} sudah bertumpang tindih dengan data terbaru. Upload ulang file."]);
                }

                $payload = [
                    'jabatan_organisasi_id' => $job->id,
                    'pegawai_id' => $pegawai->id,
                    'user_id' => $prepared['user_id'] ?? null,
                    'nama_pejabat' => $prepared['nama_pejabat'],
                    'nip' => $prepared['nip'] ?? null,
                    'pangkat_golongan' => $prepared['pangkat_golongan'] ?? null,
                    'jenis_penugasan' => $prepared['jenis_penugasan'],
                    'nomor_sk' => $prepared['nomor_sk'] ?? null,
                    'tanggal_sk' => $prepared['tanggal_sk'] ?? null,
                    'tanggal_mulai' => $prepared['tanggal_mulai'],
                    'tanggal_selesai' => $prepared['tanggal_selesai'] ?? null,
                ];

                if ($history) {
                    $history->update($payload);
                } else {
                    $history = RiwayatPejabatJabatan::create($payload);
                }

                $row->update([
                    'status' => 'imported',
                    'normalized_data' => [...($row->normalized_data ?? []), 'applied' => ['riwayat_pejabat_jabatan_id' => $history->id]],
                ]);
            }

            $batch->update([
                'status' => 'imported',
                'error_message' => null,
                'metadata' => [
                    ...($batch->metadata ?? []),
                    'applied' => [
                        'jabatan_rows' => $jobRows->count(),
                        'pejabat_rows' => $officialRows->count(),
                        'applied_by' => $user->id,
                        'applied_at' => now()->toISOString(),
                    ],
                ],
            ]);
        });

        return $batch->fresh(['uploadedBy:id,name', 'rows']);
    }

    private function resolvePegawai(array $prepared, JabatanOrganisasi $job): Pegawai
    {
        $pegawai = null;

        if ($prepared['nip'] ?? null) {
            $pegawai = Pegawai::query()->where('nip', $prepared['nip'])->first();
        }

        if (! $pegawai && ($prepared['user_id'] ?? null)) {
            $pegawai = Pegawai::query()->where('user_id', $prepared['user_id'])->first();
        }

        if (! $pegawai) {
            $pegawai = Pegawai::query()
                ->where('opd_id', $job->opd_id)
                ->whereRaw('LOWER(nama) = ?', [mb_strtolower($prepared['nama_pejabat'])])
                ->first();
        }

        $payload = [
            'opd_id' => $job->opd_id,
            'opd_unit_id' => $job->opd_unit_id,
            'user_id' => $prepared['user_id'] ?? null,
            'nama' => $prepared['nama_pejabat'],
            'nip' => $prepared['nip'] ?? null,
            'pangkat_golongan' => $prepared['pangkat_golongan'] ?? null,
            'jenis_pegawai' => $prepared['jenis_pegawai'] ?? 'pns',
            'status' => 'active',
        ];

        if ($pegawai) {
            $pegawai->update($payload);

            return $pegawai;
        }

        return Pegawai::create($payload);
    }
}
