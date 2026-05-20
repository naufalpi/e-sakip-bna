<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class KomponenEvaluasi extends Model
{
    use LogsActivity;
    use SoftDeletes;

    protected $table = 'komponen_evaluasi';

    protected $fillable = [
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

    public function subKomponen(): HasMany
    {
        return $this->hasMany(SubKomponenEvaluasi::class)->orderBy('urutan');
    }
}
