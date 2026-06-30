<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class RpjmdVisi extends Model
{
    use LogsActivity;
    use SoftDeletes;

    protected $table = 'rpjmd_visi';

    protected $fillable = ['rpjmd_id', 'visi', 'urutan'];

    public function rpjmd(): BelongsTo
    {
        return $this->belongsTo(Rpjmd::class);
    }

    public function misi(): HasMany
    {
        return $this->hasMany(RpjmdMisi::class)->orderBy('urutan');
    }

    public function tujuan(): HasMany
    {
        return $this->hasMany(TujuanDaerah::class, 'rpjmd_visi_id')->orderBy('urutan');
    }
}
