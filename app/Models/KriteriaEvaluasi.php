<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class KriteriaEvaluasi extends Model
{
    use LogsActivity;
    use SoftDeletes;

    protected $table = 'kriteria_evaluasi';

    protected $fillable = [
        'sub_komponen_evaluasi_id',
        'kode',
        'nama',
        'panduan',
        'bobot',
        'nilai_maksimal',
        'urutan',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'bobot' => 'decimal:2',
            'nilai_maksimal' => 'decimal:2',
            'urutan' => 'integer',
        ];
    }

    public function subKomponen(): BelongsTo
    {
        return $this->belongsTo(SubKomponenEvaluasi::class, 'sub_komponen_evaluasi_id');
    }
}
