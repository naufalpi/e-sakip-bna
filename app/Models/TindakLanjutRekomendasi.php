<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class TindakLanjutRekomendasi extends Model
{
    use LogsActivity;
    use SoftDeletes;

    protected $table = 'tindak_lanjut_rekomendasi';

    protected $fillable = [
        'rekomendasi_evaluasi_id',
        'opd_id',
        'uraian_tindak_lanjut',
        'status_tindak_lanjut',
        'tanggal_tindak_lanjut',
        'catatan_opd',
        'created_by',
        'diverifikasi_oleh',
        'diverifikasi_at',
        'catatan_verifikator',
    ];

    protected function casts(): array
    {
        return [
            'tanggal_tindak_lanjut' => 'date',
            'diverifikasi_at' => 'datetime',
        ];
    }

    public function rekomendasi(): BelongsTo
    {
        return $this->belongsTo(RekomendasiEvaluasi::class, 'rekomendasi_evaluasi_id');
    }

    public function opd(): BelongsTo
    {
        return $this->belongsTo(Opd::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function diverifikasiOleh(): BelongsTo
    {
        return $this->belongsTo(User::class, 'diverifikasi_oleh');
    }
}
