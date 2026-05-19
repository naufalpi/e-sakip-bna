<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TargetIndikatorProgramRpjmd extends Model
{
    protected $table = 'target_indikator_program_rpjmd';

    protected $fillable = ['indikator_program_rpjmd_id', 'periode_tahun_id', 'target', 'target_text', 'pagu'];

    protected function casts(): array
    {
        return [
            'target' => 'decimal:4',
            'pagu' => 'decimal:2',
        ];
    }

    public function indikator(): BelongsTo
    {
        return $this->belongsTo(IndikatorProgramRpjmd::class, 'indikator_program_rpjmd_id');
    }

    public function periodeTahun(): BelongsTo
    {
        return $this->belongsTo(PeriodeTahun::class);
    }
}
