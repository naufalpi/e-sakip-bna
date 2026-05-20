<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class EvaluasiSakipItem extends Model
{
    use LogsActivity;
    use SoftDeletes;

    protected $table = 'evaluasi_sakip_items';

    protected $fillable = [
        'evaluasi_sakip_id',
        'kriteria_evaluasi_id',
        'nilai',
        'skor',
        'catatan',
        'rekomendasi_text',
    ];

    protected function casts(): array
    {
        return [
            'nilai' => 'decimal:2',
            'skor' => 'decimal:2',
        ];
    }

    public function evaluasiSakip(): BelongsTo
    {
        return $this->belongsTo(EvaluasiSakip::class);
    }

    public function kriteria(): BelongsTo
    {
        return $this->belongsTo(KriteriaEvaluasi::class, 'kriteria_evaluasi_id');
    }
}
