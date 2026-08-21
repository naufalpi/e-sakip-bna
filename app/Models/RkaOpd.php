<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class RkaOpd extends Model
{
    use LogsActivity;
    use SoftDeletes;

    protected $table = 'rka_opd';

    protected $fillable = [
        'renja_opd_id',
        'rkpd_id',
        'opd_id',
        'opd_unit_id',
        'periode_tahun_id',
        'tahun',
        'jenis_anggaran',
        'judul',
        'nomor_dokumen',
        'tanggal_dokumen',
        'nomor_kua',
        'tanggal_kua',
        'nomor_ppas',
        'tanggal_ppas',
        'status',
        'catatan',
        'catatan_verifikasi',
        'submitted_by',
        'submitted_at',
    ];

    protected function casts(): array
    {
        return [
            'tahun' => 'integer',
            'tanggal_dokumen' => 'date',
            'tanggal_kua' => 'date',
            'tanggal_ppas' => 'date',
            'submitted_at' => 'datetime',
        ];
    }

    public function typeLabel(): string
    {
        return $this->jenis_anggaran === 'perubahan' ? 'RKA Perubahan APBD' : 'RKA APBD';
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
        return $this->hasMany(RkaOpdItem::class)->orderBy('urutan')->orderBy('id');
    }

    public function dpaDocuments(): HasMany
    {
        return $this->hasMany(DpaOpd::class);
    }
}
