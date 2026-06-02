<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PredikatEvaluasi extends Model
{
    use LogsActivity;

    protected $table = 'predikat_evaluasi';

    protected $fillable = [
        'kode',
        'nama',
        'nilai_min',
        'nilai_max',
        'deskripsi',
        'warna',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'nilai_min' => 'decimal:2',
            'nilai_max' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function evaluasiSakip(): HasMany
    {
        return $this->hasMany(EvaluasiSakip::class);
    }

    public function lhe(): HasMany
    {
        return $this->hasMany(Lhe::class);
    }
}
