<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class TujuanDaerah extends Model
{
    use LogsActivity;
    use SoftDeletes;

    protected $table = 'tujuan_daerah';

    protected $fillable = ['rpjmd_visi_id', 'rpjmd_misi_id', 'kode', 'tujuan', 'urutan'];

    public function visi(): BelongsTo
    {
        return $this->belongsTo(RpjmdVisi::class, 'rpjmd_visi_id');
    }

    public function misi(): BelongsTo
    {
        return $this->belongsTo(RpjmdMisi::class, 'rpjmd_misi_id');
    }

    public function misiTerkait(): BelongsToMany
    {
        return $this->belongsToMany(RpjmdMisi::class, 'tujuan_daerah_misi', 'tujuan_daerah_id', 'rpjmd_misi_id')
            ->withPivot('urutan')
            ->withTimestamps()
            ->orderByPivot('urutan')
            ->orderBy('rpjmd_misi.urutan');
    }

    public function scopeForRpjmd(Builder $query, int $rpjmdId): Builder
    {
        return $query->where(function (Builder $query) use ($rpjmdId) {
            $query->whereHas('visi', fn (Builder $query) => $query->where('rpjmd_id', $rpjmdId))
                ->orWhereHas('misi', fn (Builder $query) => $query->where('rpjmd_id', $rpjmdId))
                ->orWhereHas('misiTerkait', fn (Builder $query) => $query->where('rpjmd_id', $rpjmdId));
        });
    }

    public function parentRpjmd(): ?Rpjmd
    {
        return $this->visi?->rpjmd ?? $this->misi?->rpjmd;
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
