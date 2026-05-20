<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class IndikatorSasaranDaerah extends Model
{
    use LogsActivity;
    use SoftDeletes;

    protected $table = 'indikator_sasaran_daerah';

    protected $fillable = ['sasaran_daerah_id', 'satuan_indikator_id', 'kode', 'indikator', 'tipe_indikator', 'formula', 'sumber_data', 'urutan'];

    public function sasaran(): BelongsTo
    {
        return $this->belongsTo(SasaranDaerah::class, 'sasaran_daerah_id');
    }

    public function satuanIndikator(): BelongsTo
    {
        return $this->belongsTo(SatuanIndikator::class);
    }

    public function targets(): HasMany
    {
        return $this->hasMany(TargetIndikatorSasaranDaerah::class);
    }
}
