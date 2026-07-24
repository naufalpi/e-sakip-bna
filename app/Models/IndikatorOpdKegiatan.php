<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class IndikatorOpdKegiatan extends Model
{
    use LogsActivity;
    use SoftDeletes;

    protected $table = 'indikator_opd_kegiatan';

    protected $fillable = [
        'opd_kegiatan_id',
        'satuan_indikator_id',
        'kode',
        'indikator',
        'tipe_indikator',
        'formula',
        'sumber_data',
        'urutan',
    ];

    public function kegiatan(): BelongsTo
    {
        return $this->belongsTo(OpdKegiatan::class, 'opd_kegiatan_id');
    }

    public function satuanIndikator(): BelongsTo
    {
        return $this->belongsTo(SatuanIndikator::class);
    }

    public function targets(): HasMany
    {
        return $this->hasMany(TargetIndikatorOpdKegiatan::class);
    }
}
