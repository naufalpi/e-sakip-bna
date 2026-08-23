<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class SubKegiatanPemerintahan extends Model
{
    use LogsActivity;
    use SoftDeletes;

    protected $table = 'sub_kegiatan_pemerintahan';

    protected $fillable = [
        'periode_tahun_id',
        'kegiatan_pemerintahan_id',
        'kode',
        'nama',
        'sasaran_sub_kegiatan',
        'indikator_sub_kegiatan',
        'satuan_indikator_id',
        'definisi_operasional',
        'status',
    ];

    public function periodeTahun(): BelongsTo
    {
        return $this->belongsTo(PeriodeTahun::class);
    }

    public function kegiatanPemerintahan(): BelongsTo
    {
        return $this->belongsTo(KegiatanPemerintahan::class);
    }

    public function satuanIndikator(): BelongsTo
    {
        return $this->belongsTo(SatuanIndikator::class);
    }

    public function indikatorReferensi(): HasMany
    {
        return $this->hasMany(IndikatorSubKegiatanPemerintahan::class)
            ->orderByDesc('is_utama')
            ->orderBy('urutan')
            ->orderBy('id');
    }
}
