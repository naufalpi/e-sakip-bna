<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class RiwayatPejabatJabatan extends Model
{
    use LogsActivity;
    use SoftDeletes;

    protected $table = 'riwayat_pejabat_jabatan';

    protected $fillable = [
        'jabatan_organisasi_id',
        'pegawai_id',
        'user_id',
        'nama_pejabat',
        'nip',
        'pangkat_golongan',
        'jenis_penugasan',
        'nomor_sk',
        'tanggal_sk',
        'tanggal_mulai',
        'tanggal_selesai',
    ];

    protected function casts(): array
    {
        return [
            'tanggal_sk' => 'date',
            'tanggal_mulai' => 'date',
            'tanggal_selesai' => 'date',
        ];
    }

    public static function penugasanOptions(): array
    {
        return [
            ['value' => 'definitif', 'label' => 'Definitif'],
            ['value' => 'penjabat', 'label' => 'Penjabat (Pj.)'],
            ['value' => 'plt', 'label' => 'Pelaksana Tugas (Plt.)'],
            ['value' => 'plh', 'label' => 'Pelaksana Harian (Plh.)'],
        ];
    }

    public function jabatanOrganisasi(): BelongsTo
    {
        return $this->belongsTo(JabatanOrganisasi::class);
    }

    public function pegawai(): BelongsTo
    {
        return $this->belongsTo(Pegawai::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function penugasanKinerja(): HasMany
    {
        return $this->hasMany(PenugasanPengampuKinerja::class, 'penempatan_pegawai_id');
    }

    public function perjanjianKinerja(): HasMany
    {
        return $this->hasMany(PerjanjianKinerja::class, 'penempatan_pegawai_id');
    }
}
