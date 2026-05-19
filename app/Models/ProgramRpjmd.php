<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProgramRpjmd extends Model
{
    use LogsActivity;
    use SoftDeletes;

    protected $table = 'program_rpjmd';

    protected $fillable = [
        'strategi_daerah_id',
        'sasaran_daerah_id',
        'urusan_pemerintahan_id',
        'kode',
        'nama',
        'pagu_indikatif',
        'status',
        'urutan',
    ];

    protected function casts(): array
    {
        return [
            'pagu_indikatif' => 'decimal:2',
        ];
    }

    public function strategi(): BelongsTo
    {
        return $this->belongsTo(StrategiDaerah::class, 'strategi_daerah_id');
    }

    public function sasaran(): BelongsTo
    {
        return $this->belongsTo(SasaranDaerah::class, 'sasaran_daerah_id');
    }

    public function urusanPemerintahan(): BelongsTo
    {
        return $this->belongsTo(UrusanPemerintahan::class);
    }

    public function indikator(): HasMany
    {
        return $this->hasMany(IndikatorProgramRpjmd::class)->orderBy('urutan');
    }

    public function opdPenanggungJawab(): BelongsToMany
    {
        return $this->belongsToMany(Opd::class, 'program_rpjmd_opd_penanggung_jawab')
            ->withPivot(['peran', 'is_utama'])
            ->withTimestamps();
    }
}
