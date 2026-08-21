<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Pegawai extends Model
{
    use LogsActivity;
    use SoftDeletes;

    protected $table = 'pegawai';

    protected $fillable = [
        'opd_id',
        'opd_unit_id',
        'user_id',
        'nama',
        'nip',
        'pangkat_golongan',
        'jenis_pegawai',
        'status',
    ];

    public static function jenisOptions(): array
    {
        return [
            ['value' => 'pejabat_negara', 'label' => 'Pejabat Negara'],
            ['value' => 'pns', 'label' => 'PNS'],
            ['value' => 'pppk', 'label' => 'PPPK'],
            ['value' => 'non_asn', 'label' => 'Non-ASN'],
        ];
    }

    public function opd(): BelongsTo
    {
        return $this->belongsTo(Opd::class);
    }

    public function opdUnit(): BelongsTo
    {
        return $this->belongsTo(OpdUnit::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function penempatan(): HasMany
    {
        return $this->hasMany(RiwayatPejabatJabatan::class)->orderByDesc('tanggal_mulai');
    }

    public function penugasanKinerja(): HasMany
    {
        return $this->hasMany(PenugasanPengampuKinerja::class)->orderByDesc('tahun');
    }

    public function perjanjianKinerja(): HasMany
    {
        return $this->hasMany(PerjanjianKinerja::class)->orderByDesc('tahun');
    }
}
