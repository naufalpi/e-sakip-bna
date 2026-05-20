<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Lhe extends Model
{
    use LogsActivity;
    use SoftDeletes;

    protected $table = 'lhe';

    protected $fillable = [
        'evaluasi_sakip_id',
        'nomor_lhe',
        'tanggal_lhe',
        'ringkasan',
        'nilai_akhir',
        'predikat',
        'predikat_evaluasi_id',
        'status',
        'disusun_oleh',
    ];

    protected function casts(): array
    {
        return [
            'tanggal_lhe' => 'date',
            'nilai_akhir' => 'decimal:2',
        ];
    }

    public function evaluasiSakip(): BelongsTo
    {
        return $this->belongsTo(EvaluasiSakip::class);
    }

    public function predikatEvaluasi(): BelongsTo
    {
        return $this->belongsTo(PredikatEvaluasi::class);
    }

    public function disusunOleh(): BelongsTo
    {
        return $this->belongsTo(User::class, 'disusun_oleh');
    }
}
