<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IndikatorSubKegiatanPemerintahan extends Model
{
    use LogsActivity;

    protected $table = 'indikator_sub_kegiatan_pemerintahan';

    protected $fillable = [
        'sub_kegiatan_pemerintahan_id',
        'indikator',
        'satuan_indikator_id',
        'is_utama',
        'urutan',
    ];

    protected function casts(): array
    {
        return [
            'is_utama' => 'boolean',
            'urutan' => 'integer',
        ];
    }

    public function subKegiatanPemerintahan(): BelongsTo
    {
        return $this->belongsTo(SubKegiatanPemerintahan::class);
    }

    public function satuanIndikator(): BelongsTo
    {
        return $this->belongsTo(SatuanIndikator::class);
    }
}
