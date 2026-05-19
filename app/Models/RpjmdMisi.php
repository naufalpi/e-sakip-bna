<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class RpjmdMisi extends Model
{
    use LogsActivity;
    use SoftDeletes;

    protected $table = 'rpjmd_misi';

    protected $fillable = ['rpjmd_id', 'rpjmd_visi_id', 'kode', 'misi', 'urutan'];

    public function rpjmd(): BelongsTo
    {
        return $this->belongsTo(Rpjmd::class);
    }

    public function visi(): BelongsTo
    {
        return $this->belongsTo(RpjmdVisi::class, 'rpjmd_visi_id');
    }

    public function tujuan(): HasMany
    {
        return $this->hasMany(TujuanDaerah::class)->orderBy('urutan');
    }
}
