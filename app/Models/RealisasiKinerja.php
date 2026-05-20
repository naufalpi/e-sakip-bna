<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class RealisasiKinerja extends Model
{
    use LogsActivity;
    use SoftDeletes;

    protected $table = 'realisasi_kinerja';

    protected $fillable = [
        'opd_id',
        'perjanjian_kinerja_id',
        'rencana_aksi_id',
        'periode_tahun_id',
        'tahun',
        'periode_realisasi',
        'triwulan',
        'bulan',
        'semester',
        'status',
        'catatan',
    ];

    protected function casts(): array
    {
        return [
            'tahun' => 'integer',
            'bulan' => 'integer',
            'semester' => 'integer',
        ];
    }

    public function opd(): BelongsTo
    {
        return $this->belongsTo(Opd::class);
    }

    public function perjanjianKinerja(): BelongsTo
    {
        return $this->belongsTo(PerjanjianKinerja::class);
    }

    public function rencanaAksi(): BelongsTo
    {
        return $this->belongsTo(RencanaAksi::class);
    }

    public function periodeTahun(): BelongsTo
    {
        return $this->belongsTo(PeriodeTahun::class);
    }

    public function programs(): HasMany
    {
        return $this->hasMany(RealisasiProgram::class)->orderBy('urutan');
    }
}
