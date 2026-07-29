<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AnggaranSubKegiatanRenstra extends Model
{
    use LogsActivity;

    protected $table = 'anggaran_sub_kegiatan_renstra';

    protected $fillable = [
        'opd_sub_kegiatan_id',
        'periode_tahun_id',
        'anggaran',
    ];

    protected function casts(): array
    {
        return [
            'anggaran' => 'decimal:2',
        ];
    }

    public function subKegiatan(): BelongsTo
    {
        return $this->belongsTo(OpdSubKegiatan::class, 'opd_sub_kegiatan_id');
    }

    public function periodeTahun(): BelongsTo
    {
        return $this->belongsTo(PeriodeTahun::class);
    }
}
