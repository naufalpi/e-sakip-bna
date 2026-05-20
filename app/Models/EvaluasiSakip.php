<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class EvaluasiSakip extends Model
{
    use LogsActivity;
    use SoftDeletes;

    protected $table = 'evaluasi_sakip';

    protected $fillable = [
        'opd_id',
        'periode_tahun_id',
        'tahun',
        'evaluator_id',
        'tanggal_evaluasi',
        'status',
        'nilai_akhir',
        'predikat',
        'catatan_umum',
    ];

    protected function casts(): array
    {
        return [
            'tahun' => 'integer',
            'tanggal_evaluasi' => 'date',
            'nilai_akhir' => 'decimal:2',
        ];
    }

    public function opd(): BelongsTo
    {
        return $this->belongsTo(Opd::class);
    }

    public function periodeTahun(): BelongsTo
    {
        return $this->belongsTo(PeriodeTahun::class);
    }

    public function evaluator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'evaluator_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(EvaluasiSakipItem::class)->orderBy('id');
    }

    public function lhe(): HasOne
    {
        return $this->hasOne(Lhe::class);
    }

    public function rekomendasi(): HasMany
    {
        return $this->hasMany(RekomendasiEvaluasi::class);
    }
}
