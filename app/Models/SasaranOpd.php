<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class SasaranOpd extends Model
{
    use LogsActivity;
    use SoftDeletes;

    protected $table = 'sasaran_opd';

    protected $fillable = ['tujuan_opd_id', 'sasaran_daerah_id', 'kode', 'sasaran', 'urutan'];

    public function tujuan(): BelongsTo
    {
        return $this->belongsTo(TujuanOpd::class, 'tujuan_opd_id');
    }

    public function sasaranDaerah(): BelongsTo
    {
        return $this->belongsTo(SasaranDaerah::class);
    }

    public function indikator(): HasMany
    {
        return $this->hasMany(IndikatorSasaranOpd::class)->orderBy('urutan');
    }

    public function programs(): HasMany
    {
        return $this->hasMany(OpdProgram::class)->orderBy('urutan');
    }
}
