<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class IndikatorTujuanOpd extends Model
{
    use LogsActivity;
    use SoftDeletes;

    protected $table = 'indikator_tujuan_opd';

    protected $fillable = [
        'tujuan_opd_id',
        'indikator_tujuan_daerah_id',
        'satuan_indikator_id',
        'kode',
        'indikator',
        'formula',
        'sumber_data',
        'urutan',
    ];

    public function tujuan(): BelongsTo
    {
        return $this->belongsTo(TujuanOpd::class, 'tujuan_opd_id');
    }

    public function indikatorTujuanDaerah(): BelongsTo
    {
        return $this->belongsTo(IndikatorTujuanDaerah::class);
    }

    public function satuanIndikator(): BelongsTo
    {
        return $this->belongsTo(SatuanIndikator::class);
    }

    public function targets(): HasMany
    {
        return $this->hasMany(TargetIndikatorTujuanOpd::class);
    }
}
