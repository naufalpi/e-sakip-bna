<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class RenstraOpd extends Model
{
    use LogsActivity;
    use SoftDeletes;

    protected $table = 'renstra_opd';

    protected $fillable = [
        'opd_id',
        'rpjmd_id',
        'periode_tahun_id',
        'judul',
        'nomor_dokumen',
        'tahun_awal',
        'tahun_akhir',
        'status',
        'keterangan',
    ];

    protected function casts(): array
    {
        return [
            'tahun_awal' => 'integer',
            'tahun_akhir' => 'integer',
        ];
    }

    public function opd(): BelongsTo
    {
        return $this->belongsTo(Opd::class);
    }

    public function rpjmd(): BelongsTo
    {
        return $this->belongsTo(Rpjmd::class);
    }

    public function periodeTahun(): BelongsTo
    {
        return $this->belongsTo(PeriodeTahun::class);
    }

    public function tujuan(): HasMany
    {
        return $this->hasMany(TujuanOpd::class)->orderBy('urutan');
    }

    public function programs(): HasMany
    {
        return $this->hasMany(OpdProgram::class)->orderBy('urutan');
    }
}
