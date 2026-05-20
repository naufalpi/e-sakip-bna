<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class SubKomponenEvaluasi extends Model
{
    use LogsActivity;
    use SoftDeletes;

    protected $table = 'sub_komponen_evaluasi';

    protected $fillable = [
        'komponen_evaluasi_id',
        'kode',
        'nama',
        'deskripsi',
        'bobot',
        'urutan',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'bobot' => 'decimal:2',
            'urutan' => 'integer',
        ];
    }

    public function komponen(): BelongsTo
    {
        return $this->belongsTo(KomponenEvaluasi::class, 'komponen_evaluasi_id');
    }

    public function kriteria(): HasMany
    {
        return $this->hasMany(KriteriaEvaluasi::class)->orderBy('urutan');
    }
}
