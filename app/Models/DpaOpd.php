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
        'nama_pengguna_anggaran', 'nip_pengguna_anggaran', 'nama_ppkd', 'nip_ppkd',
        'nama_sekretaris_daerah', 'nip_sekretaris_daerah', 'status', 'catatan', 'catatan_verifikasi',
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

    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(DpaOpdItem::class)->orderBy('urutan')->orderBy('id');
    }
}
