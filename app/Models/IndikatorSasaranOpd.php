<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use App\Models\Concerns\HasTargetTriwulan;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class IndikatorSasaranOpd extends Model
{
    use LogsActivity;
    use HasTargetTriwulan;
    use SoftDeletes;

    protected $table = 'indikator_sasaran_opd';

    protected $fillable = [
        'sasaran_opd_id',
        'indikator_sasaran_daerah_id',
        'satuan_indikator_id',
        'kode',
        'indikator',
        'tipe_indikator',
        'formula',
        'sumber_data',
        'urutan',
    ];

    public function sasaran(): BelongsTo
    {
        return $this->belongsTo(SasaranOpd::class, 'sasaran_opd_id');
    }

    public function indikatorSasaranDaerah(): BelongsTo
    {
        return $this->belongsTo(IndikatorSasaranDaerah::class);
    }

    public function satuanIndikator(): BelongsTo
    {
        return $this->belongsTo(SatuanIndikator::class);
    }

    public function targets(): HasMany
    {
        return $this->hasMany(TargetIndikatorSasaranOpd::class);
    }
}
