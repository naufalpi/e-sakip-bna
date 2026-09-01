<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class DpaOpd extends Model
{
    use LogsActivity;
    use SoftDeletes;

    protected $table = 'dpa_opd';

    protected $fillable = [
        'rka_opd_id', 'renja_opd_id', 'rkpd_id', 'opd_id', 'opd_unit_id', 'periode_tahun_id',
        'tahun', 'jenis_anggaran', 'judul', 'nomor_dpa', 'tanggal_pengesahan',
        'nomor_perda_apbd', 'tanggal_perda_apbd', 'nomor_perkada_penjabaran', 'tanggal_perkada_penjabaran',
        'nama_pengguna_anggaran', 'nip_pengguna_anggaran', 'pengguna_anggaran_pegawai_id', 'pengguna_anggaran_penempatan_id',
        'nama_ppkd', 'nip_ppkd', 'ppkd_pegawai_id', 'ppkd_penempatan_id',
        'nama_sekretaris_daerah', 'nip_sekretaris_daerah', 'sekretaris_daerah_pegawai_id', 'sekretaris_daerah_penempatan_id',
        'status', 'catatan', 'catatan_verifikasi',
        'submitted_by', 'submitted_at',
    ];

    protected function casts(): array
    {
        return [
            'tahun' => 'integer',
            'tanggal_pengesahan' => 'date',
            'tanggal_perda_apbd' => 'date',
            'tanggal_perkada_penjabaran' => 'date',
            'submitted_at' => 'datetime',
        ];
    }

    public function typeLabel(): string
    {
        return $this->jenis_anggaran === 'perubahan' ? 'DPPA-SKPD' : 'DPA-SKPD';
    }

    public function rkaOpd(): BelongsTo
    {
        return $this->belongsTo(RkaOpd::class);
    }

    public function renjaOpd(): BelongsTo
    {
        return $this->belongsTo(RenjaOpd::class);
    }

    public function rkpd(): BelongsTo
    {
        return $this->belongsTo(Rkpd::class);
    }

    public function opd(): BelongsTo
    {
        return $this->belongsTo(Opd::class);
    }

    public function opdUnit(): BelongsTo
    {
        return $this->belongsTo(OpdUnit::class);
    }

    public function periodeTahun(): BelongsTo
    {
        return $this->belongsTo(PeriodeTahun::class);
    }

    public function penggunaAnggaran(): BelongsTo
    {
        return $this->belongsTo(Pegawai::class, 'pengguna_anggaran_pegawai_id');
    }

    public function penempatanPenggunaAnggaran(): BelongsTo
    {
        return $this->belongsTo(RiwayatPejabatJabatan::class, 'pengguna_anggaran_penempatan_id');
    }

    public function ppkd(): BelongsTo
    {
        return $this->belongsTo(Pegawai::class, 'ppkd_pegawai_id');
    }

    public function penempatanPpkd(): BelongsTo
    {
        return $this->belongsTo(RiwayatPejabatJabatan::class, 'ppkd_penempatan_id');
    }

    public function sekretarisDaerah(): BelongsTo
    {
        return $this->belongsTo(Pegawai::class, 'sekretaris_daerah_pegawai_id');
    }

    public function penempatanSekretarisDaerah(): BelongsTo
    {
        return $this->belongsTo(RiwayatPejabatJabatan::class, 'sekretaris_daerah_penempatan_id');
    }

    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(DpaOpdItem::class)->orderBy('urutan')->orderBy('id');
    }
}
