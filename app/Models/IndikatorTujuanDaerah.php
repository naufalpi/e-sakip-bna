<?php

namespace App\Models;

use App\Models\Concerns\HasTargetTriwulan;
use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class IndikatorTujuanDaerah extends Model
{
    use HasTargetTriwulan;
    use LogsActivity;
    use SoftDeletes;

    protected $table = 'indikator_tujuan_daerah';

    protected $fillable = [
        'tujuan_daerah_id',
        'satuan_indikator_id',
        'opd_id',
        'kode',
        'indikator',
        'definisi_operasional',
        'alasan_pemilihan',
        'formulasi_pengukuran',
        'tipe_perhitungan',
        'sumber_data',
        'urutan',
    ];

    public function tujuan(): BelongsTo
    {
        return $this->belongsTo(TujuanDaerah::class, 'tujuan_daerah_id');
    }

    public function satuanIndikator(): BelongsTo
    {
        return $this->belongsTo(SatuanIndikator::class);
    }

    public function opd(): BelongsTo
    {
        return $this->belongsTo(Opd::class);
    }

    public function targets(): HasMany
    {
        return $this->hasMany(TargetIndikatorTujuanDaerah::class);
    }
}
