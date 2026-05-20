<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class RekomendasiEvaluasi extends Model
{
    use LogsActivity;
    use SoftDeletes;

    protected $table = 'rekomendasi_evaluasi';

    protected $fillable = [
        'evaluasi_sakip_id',
        'evaluasi_sakip_item_id',
        'opd_id',
        'nomor',
        'rekomendasi',
        'prioritas',
        'status_tindak_lanjut',
        'target_tanggal',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'target_tanggal' => 'date',
        ];
    }

    public function evaluasiSakip(): BelongsTo
    {
        return $this->belongsTo(EvaluasiSakip::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(EvaluasiSakipItem::class, 'evaluasi_sakip_item_id');
    }

    public function opd(): BelongsTo
    {
        return $this->belongsTo(Opd::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function tindakLanjut(): HasMany
    {
        return $this->hasMany(TindakLanjutRekomendasi::class)->latest('id');
    }
}
