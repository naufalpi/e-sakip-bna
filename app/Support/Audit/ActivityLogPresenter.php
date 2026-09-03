<?php

namespace App\Support\Audit;

use App\Models\ActivityLog;
use Illuminate\Support\Str;

class ActivityLogPresenter
{
    /** @var array<string, string> */
    private const ACTION_LABELS = [
        'created' => 'Ditambahkan',
        'updated' => 'Diperbarui',
        'deleted' => 'Dihapus',
        'roles_synced' => 'Hak akses diperbarui',
        'permissions_synced' => 'Izin diperbarui',
        'planning_sync_previewed' => 'Sinkronisasi diperiksa',
        'planning_sync_applied' => 'Sinkronisasi diterapkan',
    ];

    /** @var array<string, string> */
    private const MODEL_LABELS = [
        'ActivityLog' => 'Audit log',
        'AnggaranSubKegiatanRenstra' => 'Anggaran sub kegiatan RENSTRA',
        'BidangUrusan' => 'Bidang urusan',
        'BidangUrusanOpdPengampu' => 'Pengampu bidang urusan',
        'Dokumen' => 'Dokumen',
        'DokumenRelation' => 'Relasi dokumen',
        'DpaOpd' => 'DPA OPD',
        'DpaOpdCashPlan' => 'Rencana kas DPA',
        'DpaOpdItem' => 'Sub kegiatan DPA',
        'EvaluasiSakip' => 'Evaluasi SAKIP',
        'EvaluasiSakipItem' => 'Nilai evaluasi SAKIP',
        'ImportBatch' => 'Proses impor data',
        'IndikatorOpdKegiatan' => 'Indikator kegiatan OPD',
        'IndikatorOpdProgram' => 'Indikator program OPD',
        'IndikatorProgramRpjmd' => 'Indikator program RPJMD',
        'IndikatorSasaranDaerah' => 'Indikator sasaran daerah',
        'IndikatorSasaranOpd' => 'Indikator sasaran OPD',
        'IndikatorSubKegiatan' => 'Indikator sub kegiatan',
        'IndikatorSubKegiatanPemerintahan' => 'Metadata indikator sub kegiatan',
        'IndikatorTujuanDaerah' => 'Indikator tujuan daerah',
        'IndikatorTujuanOpd' => 'Indikator tujuan OPD',
        'JabatanOrganisasi' => 'Jabatan organisasi',
        'KegiatanPemerintahan' => 'Kegiatan',
        'KopDokumen' => 'Kop dokumen',
        'Lhe' => 'LHE',
        'Lkjip' => 'LKjIP',
        'LkjipBab' => 'Bab LKjIP',
        'Opd' => 'Perangkat daerah',
        'OpdKegiatan' => 'Kegiatan OPD',
        'OpdProgram' => 'Program OPD',
        'OpdSubKegiatan' => 'Sub kegiatan OPD',
        'OpdUnit' => 'Unit perangkat daerah',
        'PaguProgramRpjmd' => 'Pagu program RPJMD',
        'Pegawai' => 'Pegawai',
        'PenugasanPengampuKinerja' => 'Penugasan kinerja',
        'PeriodeTahun' => 'Periode tahun',
        'PerjanjianKinerja' => 'Perjanjian Kinerja',
        'PerjanjianKinerjaItem' => 'Item Perjanjian Kinerja',
        'PerjanjianKinerjaProgram' => 'Program Perjanjian Kinerja',
        'Permission' => 'Izin aplikasi',
        'PlanningSyncBatch' => 'Sinkronisasi RKPD dan RENJA',
        'PredikatEvaluasi' => 'Predikat evaluasi',
        'ProgramPemerintahan' => 'Program',
        'ProgramRpjmd' => 'Program RPJMD',
        'RealisasiKinerja' => 'Realisasi kinerja',
        'RealisasiProgram' => 'Realisasi program',
        'RekomendasiEvaluasi' => 'Rekomendasi evaluasi',
        'RenjaOpd' => 'RENJA OPD',
        'RenjaOpdItem' => 'Sub kegiatan RENJA',
        'RenstraOpd' => 'RENSTRA OPD',
        'RencanaAksi' => 'Rencana Aksi',
        'RencanaAksiItem' => 'Item Rencana Aksi',
        'RiwayatPejabatJabatan' => 'Penempatan jabatan pegawai',
        'RkaOpd' => 'RKA OPD',
        'RkaOpdItem' => 'Sub kegiatan RKA',
        'Rkpd' => 'RKPD',
        'RkpdIkuTarget' => 'Target IKU RKPD',
        'RkpdItem' => 'Item RKPD',
        'Role' => 'Peran pengguna',
        'Rpjmd' => 'RPJMD',
        'RpjmdMisi' => 'Misi RPJMD',
        'RpjmdVisi' => 'Visi RPJMD',
        'SasaranDaerah' => 'Sasaran daerah',
        'SasaranOpd' => 'Sasaran OPD',
        'SatuanIndikator' => 'Satuan indikator',
        'StrategiDaerah' => 'Strategi daerah',
        'SubKegiatanPemerintahan' => 'Sub kegiatan',
        'SystemSetting' => 'Pengaturan sistem',
        'TargetRevision' => 'Usulan perubahan target',
        'TargetTriwulanIndikator' => 'Target triwulan',
        'TindakLanjutRekomendasi' => 'Tindak lanjut rekomendasi',
        'TujuanDaerah' => 'Tujuan daerah',
        'TujuanOpd' => 'Tujuan OPD',
        'UrusanPemerintahan' => 'Urusan pemerintahan',
        'User' => 'Akun pengguna',
        'WorkflowHistory' => 'Riwayat persetujuan',
        'WorkflowSubmission' => 'Pengajuan dokumen',
    ];

    /** @var array<string, string> */
    private const FIELD_LABELS = [
        'action' => 'Aksi',
        'alamat' => 'Alamat',
        'anggaran' => 'Anggaran',
        'catatan' => 'Catatan',
        'description' => 'Keterangan',
        'email' => 'Email',
        'indikator' => 'Indikator',
        'is_active_version' => 'Versi aktif',
        'jabatan' => 'Jabatan',
        'jenis_anggaran' => 'Jenis anggaran',
        'jenis_versi' => 'Jenis versi',
        'judul' => 'Judul',
        'kode' => 'Kode',
        'nama' => 'Nama',
        'nama_kegiatan' => 'Nama kegiatan',
        'nama_program' => 'Nama program',
        'nama_sub_kegiatan' => 'Nama sub kegiatan',
        'nip' => 'NIP',
        'nomor_dokumen' => 'Nomor dokumen',
        'opd_id' => 'Perangkat daerah',
        'opd_unit_id' => 'Unit perangkat daerah',
        'pagu_dpa' => 'Pagu DPA',
        'pagu_indikatif' => 'Pagu indikatif',
        'pagu_rka' => 'Pagu RKA',
        'periode_tahun_id' => 'Periode tahun',
        'role_ids' => 'Peran pengguna',
        'status' => 'Status',
        'tahun' => 'Tahun',
        'target' => 'Target',
        'urutan' => 'Urutan',
        'username' => 'Nama pengguna',
    ];

    /** @var array<string, string> */
    private const VALUE_LABELS = [
        'active' => 'Aktif',
        'inactive' => 'Tidak aktif',
        'draft' => 'Draft',
        'submitted' => 'Diajukan',
        'verified' => 'Terverifikasi',
        'approved' => 'Disetujui',
        'locked' => 'Dikunci',
        'revision' => 'Perlu revisi',
        'rejected' => 'Ditolak',
        'murni' => 'APBD',
        'perubahan' => 'Perubahan APBD',
        'awal' => 'RENJA Akhir Draft',
        'ditetapkan' => 'Ditetapkan',
    ];

    /** @return array<string, mixed> */
    public function present(ActivityLog $log): array
    {
        $modelName = class_basename((string) $log->model_type);
        $modelLabel = self::MODEL_LABELS[$modelName] ?? Str::headline($modelName ?: 'Sistem');
        $actionLabel = self::ACTION_LABELS[$log->action] ?? Str::headline($log->action);
        $subject = $this->subject($log);

        return [
            'id' => $log->id,
            'action' => $log->action,
            'action_label' => $actionLabel,
            'model_type' => $log->model_type,
            'model_label' => $modelLabel,
            'model_id' => $log->model_id,
            'subject' => $subject,
            'summary' => $log->description ?: $this->summary($actionLabel, $modelLabel, $subject),
            'changes' => $this->changes($log),
            'ip_address' => $log->ip_address,
            'device_label' => $this->deviceLabel($log->user_agent),
            'created_at' => $log->created_at?->toIso8601String(),
            'user' => $log->user ? [
                'name' => $log->user->name,
                'email' => $log->user->email,
            ] : null,
        ];
    }

    public function actionLabel(string $action): string
    {
        return self::ACTION_LABELS[$action] ?? Str::headline($action);
    }

    public function modelLabel(string $modelType): string
    {
        $modelName = class_basename($modelType);

        return self::MODEL_LABELS[$modelName] ?? Str::headline($modelName);
    }

    private function subject(ActivityLog $log): ?string
    {
        $values = [...($log->old_values ?? []), ...($log->new_values ?? [])];

        foreach (['judul', 'nama', 'nama_sub_kegiatan', 'nama_kegiatan', 'nama_program', 'kode', 'email'] as $field) {
            if (filled($values[$field] ?? null) && is_scalar($values[$field])) {
                return Str::limit((string) $values[$field], 120);
            }
        }

        return null;
    }

    private function summary(string $actionLabel, string $modelLabel, ?string $subject): string
    {
        $suffix = $subject ? ' “'.$subject.'”' : '';

        return "{$modelLabel}{$suffix} ".mb_strtolower($actionLabel).'.';
    }

    /** @return array<int, array{field: string, field_label: string, from: string, to: string}> */
    private function changes(ActivityLog $log): array
    {
        $oldValues = $log->old_values ?? [];
        $newValues = $log->new_values ?? [];
        $fields = match ($log->action) {
            'created' => array_keys($newValues),
            'deleted' => array_keys($oldValues),
            default => array_keys($newValues),
        };

        return collect($fields)
            ->unique()
            ->reject(fn (string $field): bool => in_array($field, ['created_at', 'updated_at', 'deleted_at', 'password', 'remember_token'], true))
            ->map(function (string $field) use ($oldValues, $newValues, $log): array {
                return [
                    'field' => $field,
                    'field_label' => $this->fieldLabel($field),
                    'from' => $log->action === 'created' ? '—' : $this->valueLabel($oldValues[$field] ?? null),
                    'to' => $log->action === 'deleted' ? '—' : $this->valueLabel($newValues[$field] ?? null),
                ];
            })
            ->values()
            ->all();
    }

    private function fieldLabel(string $field): string
    {
        if (isset(self::FIELD_LABELS[$field])) {
            return self::FIELD_LABELS[$field];
        }

        return Str::headline(Str::replaceEnd('_id', '', $field));
    }

    private function valueLabel(mixed $value): string
    {
        if (is_null($value) || $value === '') {
            return 'Kosong';
        }

        if (is_bool($value)) {
            return $value ? 'Ya' : 'Tidak';
        }

        if (is_string($value) && isset(self::VALUE_LABELS[$value])) {
            return self::VALUE_LABELS[$value];
        }

        if (is_array($value)) {
            $value = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        return Str::limit((string) $value, 300);
    }

    private function deviceLabel(?string $userAgent): string
    {
        if (blank($userAgent)) {
            return 'Perangkat tidak diketahui';
        }

        $browser = match (true) {
            str_contains($userAgent, 'Edg/') => 'Microsoft Edge',
            str_contains($userAgent, 'Chrome/') => 'Google Chrome',
            str_contains($userAgent, 'Firefox/') => 'Mozilla Firefox',
            str_contains($userAgent, 'Safari/') => 'Safari',
            default => 'Browser lain',
        };
        $platform = match (true) {
            str_contains($userAgent, 'Windows') => 'Windows',
            str_contains($userAgent, 'Android') => 'Android',
            str_contains($userAgent, 'iPhone'), str_contains($userAgent, 'iPad') => 'iOS',
            str_contains($userAgent, 'Macintosh') => 'macOS',
            str_contains($userAgent, 'Linux') => 'Linux',
            default => null,
        };

        return $platform ? "{$browser} · {$platform}" : $browser;
    }
}
