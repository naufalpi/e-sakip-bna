<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaguProgramRpjmd extends Model
{
    use LogsActivity;

    protected $table = 'pagu_program_rpjmd';

    protected $fillable = [
        'program_rpjmd_id',
        'periode_tahun_id',
        'jenis_pagu',
        'pagu_anggaran',
    ];

    protected function casts(): array
    {
        return [
            'pagu_anggaran' => 'decimal:2',
        ];
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(ProgramRpjmd::class, 'program_rpjmd_id');
    }

    public function periodeTahun(): BelongsTo
    {
        return $this->belongsTo(PeriodeTahun::class);
    }
}
