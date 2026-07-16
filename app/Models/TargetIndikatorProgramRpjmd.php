<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TargetIndikatorProgramRpjmd extends Model
{
    use LogsActivity;

    protected $table = 'target_indikator_program_rpjmd';

    protected $fillable = ['indikator_program_rpjmd_id', 'periode_tahun_id', 'jenis_target', 'target', 'target_text'];

    public function indikator(): BelongsTo
    {
        return $this->belongsTo(IndikatorProgramRpjmd::class, 'indikator_program_rpjmd_id');
    }

    public function periodeTahun(): BelongsTo
    {
        return $this->belongsTo(PeriodeTahun::class);
    }
}
