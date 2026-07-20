<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class KegiatanPemerintahan extends Model
{
    use LogsActivity;
    use SoftDeletes;

    protected $table = 'kegiatan_pemerintahan';

    protected $fillable = [
        'periode_tahun_id',
        'program_pemerintahan_id',
        'kode',
        'nama',
        'status',
    ];

    public function periodeTahun(): BelongsTo
    {
        return $this->belongsTo(PeriodeTahun::class);
    }

    public function programPemerintahan(): BelongsTo
    {
        return $this->belongsTo(ProgramPemerintahan::class);
    }

    public function subKegiatan(): HasMany
    {
        return $this->hasMany(SubKegiatanPemerintahan::class)->orderBy('kode');
    }
}
