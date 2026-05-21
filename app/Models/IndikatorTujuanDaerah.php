<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use App\Models\Concerns\HasTargetTriwulan;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class IndikatorTujuanDaerah extends Model
{
    use LogsActivity;
    use HasTargetTriwulan;
    use SoftDeletes;

    protected $table = 'indikator_tujuan_daerah';

    protected $fillable = ['tujuan_daerah_id', 'satuan_indikator_id', 'kode', 'indikator', 'tipe_indikator', 'formula', 'sumber_data', 'urutan'];

    public function tujuan(): BelongsTo
    {
        return $this->belongsTo(TujuanDaerah::class, 'tujuan_daerah_id');
    }

    public function satuanIndikator(): BelongsTo
    {
        return $this->belongsTo(SatuanIndikator::class);
    }

    public function targets(): HasMany
    {
        return $this->hasMany(TargetIndikatorTujuanDaerah::class);
    }
}
