<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class SasaranDaerah extends Model
{
    use LogsActivity;
    use SoftDeletes;

    protected $table = 'sasaran_daerah';

    protected $fillable = ['tujuan_daerah_id', 'kode', 'sasaran', 'urutan'];

    public function tujuan(): BelongsTo
    {
        return $this->belongsTo(TujuanDaerah::class, 'tujuan_daerah_id');
    }

    public function indikatorTujuanTerkait(): BelongsToMany
    {
        return $this->belongsToMany(
            IndikatorTujuanDaerah::class,
            'sasaran_daerah_indikator_tujuan',
            'sasaran_daerah_id',
            'indikator_tujuan_daerah_id',
        )->withTimestamps();
    }

    public function indikator(): HasMany
    {
        return $this->hasMany(IndikatorSasaranDaerah::class)->orderBy('urutan');
    }

    public function programs(): HasMany
    {
        return $this->hasMany(ProgramRpjmd::class)->orderBy('urutan');
    }
}
