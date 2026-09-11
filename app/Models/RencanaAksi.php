<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class RencanaAksi extends Model
{
    use LogsActivity;
    use SoftDeletes;

    protected $table = 'rencana_aksi';

    protected $fillable = [
        'opd_id',
        'perjanjian_kinerja_id',
        'renstra_opd_id',
        'dpa_opd_id',
        'periode_tahun_id',
        'tahun',
        'judul',
        'status',
        'catatan',
        'snapshot_dibuat_pada',
        'format_version',
    ];

    protected function casts(): array
    {
        return [
            'tahun' => 'integer',
            'snapshot_dibuat_pada' => 'datetime',
            'format_version' => 'integer',
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

    public function periodeTahun(): BelongsTo
    {
        return $this->belongsTo(PeriodeTahun::class);
    }

    public function renstraOpd(): BelongsTo
    {
        return $this->belongsTo(RenstraOpd::class);
    }

    public function dpaOpd(): BelongsTo
    {
        return $this->belongsTo(DpaOpd::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(RencanaAksiItem::class)->orderBy('urutan');
    }
}
