<?php

namespace App\Models;

use App\Models\Concerns\HasTargetTriwulan;
use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class IndikatorProgramRpjmd extends Model
{
    use HasTargetTriwulan;
    use LogsActivity;
    use SoftDeletes;

    protected $table = 'indikator_program_rpjmd';

    protected $fillable = [
        'program_rpjmd_id',
        'satuan_indikator_id',
        'opd_id',
        'kode',
        'indikator',
        'definisi_operasional',
        'alasan_pemilihan',
        'formulasi_pengukuran',
        'tipe_perhitungan',
        'sumber_data',
        'urutan',
    ];

    public function program(): BelongsTo
    {
        return $this->belongsTo(ProgramRpjmd::class, 'program_rpjmd_id');
    }

    public function satuanIndikator(): BelongsTo
    {
        return $this->belongsTo(SatuanIndikator::class);
    }

    public function opd(): BelongsTo
    {
        return $this->belongsTo(Opd::class);
    }

    public function targets(): HasMany
    {
        return $this->hasMany(TargetIndikatorProgramRpjmd::class);
    }
}
