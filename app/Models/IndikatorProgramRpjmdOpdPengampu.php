<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IndikatorProgramRpjmdOpdPengampu extends Model
{
    use LogsActivity;

    protected $table = 'indikator_program_rpjmd_opd_pengampu';

    protected $fillable = ['indikator_program_rpjmd_id', 'opd_id', 'peran', 'is_utama'];

    protected function casts(): array
    {
        return [
            'is_utama' => 'boolean',
        ];
    }

    public function indikatorProgram(): BelongsTo
    {
        return $this->belongsTo(IndikatorProgramRpjmd::class, 'indikator_program_rpjmd_id');
    }

    public function opd(): BelongsTo
    {
        return $this->belongsTo(Opd::class);
    }
}
