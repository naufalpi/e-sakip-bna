<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Lkjip extends Model
{
    use LogsActivity;
    use SoftDeletes;

    protected $table = 'lkjip';

    protected $fillable = [
        'opd_id',
        'periode_tahun_id',
        'perjanjian_kinerja_id',
        'realisasi_kinerja_id',
        'evaluasi_sakip_id',
        'tahun',
        'judul',
        'nomor_dokumen',
        'ringkasan_eksekutif',
        'status',
        'catatan',
        'submitted_by',
        'submitted_at',
    ];

    protected function casts(): array
    {
        return [
            'tahun' => 'integer',
            'submitted_at' => 'datetime',
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

    public function perjanjianKinerja(): BelongsTo
    {
        return $this->belongsTo(PerjanjianKinerja::class);
    }

    public function realisasiKinerja(): BelongsTo
    {
        return $this->belongsTo(RealisasiKinerja::class);
    }

    public function evaluasiSakip(): BelongsTo
    {
        return $this->belongsTo(EvaluasiSakip::class);
    }

    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function bab(): HasMany
    {
        return $this->hasMany(LkjipBab::class)->orderBy('urutan');
    }

    public function workflowSubmission(): HasMany
    {
        return $this->hasMany(WorkflowSubmission::class, 'related_id')->where('related_table', $this->getTable());
    }
}
