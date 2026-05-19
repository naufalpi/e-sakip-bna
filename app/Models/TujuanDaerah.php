<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class TujuanDaerah extends Model
{
    use LogsActivity;
    use SoftDeletes;

    protected $table = 'tujuan_daerah';

    protected $fillable = ['rpjmd_misi_id', 'kode', 'tujuan', 'urutan'];

    public function misi(): BelongsTo
    {
        return $this->belongsTo(RpjmdMisi::class, 'rpjmd_misi_id');
    }

    public function indikator(): HasMany
    {
        return $this->hasMany(IndikatorTujuanDaerah::class)->orderBy('urutan');
    }

    public function sasaran(): HasMany
    {
        return $this->hasMany(SasaranDaerah::class)->orderBy('urutan');
    }
}
