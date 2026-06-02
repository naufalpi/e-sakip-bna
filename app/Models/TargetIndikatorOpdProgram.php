<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TargetIndikatorOpdProgram extends Model
{
    use LogsActivity;

    protected $table = 'target_indikator_opd_program';

    protected $fillable = ['indikator_opd_program_id', 'periode_tahun_id', 'target', 'target_text', 'pagu'];

    protected function casts(): array
    {
        return [
            'target' => 'decimal:4',
            'pagu' => 'decimal:2',
        ];
    }

    public function indikator(): BelongsTo
    {
        return $this->belongsTo(IndikatorOpdProgram::class, 'indikator_opd_program_id');
    }

    public function periodeTahun(): BelongsTo
    {
        return $this->belongsTo(PeriodeTahun::class);
    }
}
