<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class RealisasiProgram extends Model
{
    use LogsActivity;
    use SoftDeletes;

    protected $table = 'realisasi_program';

    protected $fillable = [
        'realisasi_kinerja_id',
        'perjanjian_kinerja_item_id',
        'rencana_aksi_item_id',
        'opd_program_id',
        'indikator_opd_program_id',
        'indikator',
        'target',
        'target_text',
        'realisasi',
        'realisasi_text',
        'capaian_persen',
        'anggaran',
        'realisasi_anggaran',
        'kendala',
        'tindak_lanjut',
        'urutan',
    ];

    protected function casts(): array
    {
        return [
            'target' => 'decimal:4',
            'realisasi' => 'decimal:4',
            'capaian_persen' => 'decimal:2',
            'anggaran' => 'decimal:2',
            'realisasi_anggaran' => 'decimal:2',
        ];
    }

    public function realisasiKinerja(): BelongsTo
    {
        return $this->belongsTo(RealisasiKinerja::class);
    }

    public function perjanjianKinerjaItem(): BelongsTo
    {
        return $this->belongsTo(PerjanjianKinerjaItem::class);
    }

    public function rencanaAksiItem(): BelongsTo
    {
        return $this->belongsTo(RencanaAksiItem::class);
    }

    public function opdProgram(): BelongsTo
    {
        return $this->belongsTo(OpdProgram::class);
    }

    public function indikatorOpdProgram(): BelongsTo
    {
        return $this->belongsTo(IndikatorOpdProgram::class);
    }
}
