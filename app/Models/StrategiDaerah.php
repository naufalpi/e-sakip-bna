<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class StrategiDaerah extends Model
{
    use LogsActivity;
    use SoftDeletes;

    protected $table = 'strategi_daerah';

    protected $fillable = ['sasaran_daerah_id', 'kode', 'strategi', 'arah_kebijakan', 'urutan'];

    public function sasaran(): BelongsTo
    {
        return $this->belongsTo(SasaranDaerah::class, 'sasaran_daerah_id');
    }

    public function programs(): HasMany
    {
        return $this->hasMany(ProgramRpjmd::class)->orderBy('urutan');
    }
}
