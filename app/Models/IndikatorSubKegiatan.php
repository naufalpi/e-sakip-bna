<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class IndikatorSubKegiatan extends Model
{
    use LogsActivity;
    use SoftDeletes;

    protected $table = 'indikator_sub_kegiatan';

    protected $fillable = [
        'opd_sub_kegiatan_id',
        'satuan_indikator_id',
        'kode',
        'indikator',
        'formula',
        'sumber_data',
        'urutan',
    ];

    public function subKegiatan(): BelongsTo
    {
        return $this->belongsTo(OpdSubKegiatan::class, 'opd_sub_kegiatan_id');
    }

    public function satuanIndikator(): BelongsTo
    {
        return $this->belongsTo(SatuanIndikator::class);
    }
}
