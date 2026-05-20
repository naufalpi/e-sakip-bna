<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TargetTriwulanIndikator extends Model
{
    protected $table = 'target_triwulan_indikator';

    protected $fillable = [
        'related_table',
        'related_id',
        'periode_tahun_id',
        'triwulan',
        'target_text',
        'target_angka',
        'target_anggaran',
    ];

    protected function casts(): array
    {
        return [
            'target_angka' => 'decimal:4',
            'target_anggaran' => 'decimal:2',
        ];
    }

    public function periodeTahun(): BelongsTo
    {
        return $this->belongsTo(PeriodeTahun::class);
    }
}
