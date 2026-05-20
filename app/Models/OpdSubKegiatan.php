<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class OpdSubKegiatan extends Model
{
    use LogsActivity;
    use SoftDeletes;

    protected $table = 'opd_sub_kegiatan';

    protected $fillable = ['opd_kegiatan_id', 'kode', 'nama', 'pagu_indikatif', 'urutan'];

    protected function casts(): array
    {
        return [
            'pagu_indikatif' => 'decimal:2',
        ];
    }

    public function kegiatan(): BelongsTo
    {
        return $this->belongsTo(OpdKegiatan::class, 'opd_kegiatan_id');
    }

    public function indikator(): HasMany
    {
        return $this->hasMany(IndikatorSubKegiatan::class)->orderBy('urutan');
    }
}
