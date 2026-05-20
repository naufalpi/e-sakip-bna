<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class IndikatorOpdProgram extends Model
{
    use LogsActivity;
    use SoftDeletes;

    protected $table = 'indikator_opd_program';

    protected $fillable = [
        'opd_program_id',
        'indikator_program_rpjmd_id',
        'satuan_indikator_id',
        'kode',
        'indikator',
        'tipe_indikator',
        'formula',
        'sumber_data',
        'urutan',
    ];

    public function program(): BelongsTo
    {
        return $this->belongsTo(OpdProgram::class, 'opd_program_id');
    }

    public function indikatorProgramRpjmd(): BelongsTo
    {
        return $this->belongsTo(IndikatorProgramRpjmd::class);
    }

    public function satuanIndikator(): BelongsTo
    {
        return $this->belongsTo(SatuanIndikator::class);
    }

    public function targets(): HasMany
    {
        return $this->hasMany(TargetIndikatorOpdProgram::class);
    }
}
