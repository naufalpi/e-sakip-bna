<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class TujuanOpd extends Model
{
    use LogsActivity;
    use SoftDeletes;

    protected $table = 'tujuan_opd';

    protected $fillable = ['renstra_opd_id', 'tujuan_daerah_id', 'kode', 'tujuan', 'urutan'];

    public function renstra(): BelongsTo
    {
        return $this->belongsTo(RenstraOpd::class, 'renstra_opd_id');
    }

    public function tujuanDaerah(): BelongsTo
    {
        return $this->belongsTo(TujuanDaerah::class);
    }

    public function indikator(): HasMany
    {
        return $this->hasMany(IndikatorTujuanOpd::class)->orderBy('urutan');
    }

    public function sasaran(): HasMany
    {
        return $this->hasMany(SasaranOpd::class)->orderBy('urutan');
    }
}
