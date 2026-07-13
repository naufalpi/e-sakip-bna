<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Rpjmd extends Model
{
    use LogsActivity;
    use SoftDeletes;

    protected $table = 'rpjmd';

    protected $fillable = [
        'periode_tahun_id',
        'judul',
        'nomor_perda',
        'tahun_awal',
        'tahun_akhir',
        'status',
        'struktur_tujuan_mode',
        'struktur_sasaran_mode',
        'keterangan',
    ];

    protected function casts(): array
    {
        return [
            'tahun_awal' => 'integer',
            'tahun_akhir' => 'integer',
        ];
    }

    public function periodeTahun(): BelongsTo
    {
        return $this->belongsTo(PeriodeTahun::class);
    }

    public function visi(): HasMany
    {
        return $this->hasMany(RpjmdVisi::class)->orderBy('urutan');
    }

    public function misi(): HasMany
    {
        return $this->hasMany(RpjmdMisi::class)->orderBy('urutan');
    }
}
