<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class OpdSubKegiatan extends Model
{
    use LogsActivity;
    use SoftDeletes;

    protected $table = 'opd_sub_kegiatan';

    protected $fillable = [
        'opd_kegiatan_id',
        'sub_kegiatan_pemerintahan_id',
        'opd_unit_id',
        'kode',
        'nama',
        'sasaran_sub_kegiatan',
        'pagu_indikatif',
        'urutan',
    ];

    protected function casts(): array
    {
        return [
            'pagu_indikatif' => 'decimal:2',
        ];
    }

    public function kegiatan(): BelongsTo
    {
        return $this->belongsTo(OpdKegiatan::class, 'opd_kegiatan_id');
    }

    public function subKegiatanPemerintahan(): BelongsTo
    {
        return $this->belongsTo(SubKegiatanPemerintahan::class);
    }

    public function opdUnit(): BelongsTo
    {
        return $this->belongsTo(OpdUnit::class);
    }

    public function indikator(): HasMany
    {
        return $this->hasMany(IndikatorSubKegiatan::class)->orderBy('urutan');
    }
}
