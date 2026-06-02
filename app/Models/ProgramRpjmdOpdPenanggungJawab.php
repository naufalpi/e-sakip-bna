<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProgramRpjmdOpdPenanggungJawab extends Model
{
    use LogsActivity;

    protected $table = 'program_rpjmd_opd_penanggung_jawab';

    protected $fillable = ['program_rpjmd_id', 'opd_id', 'peran', 'is_utama'];

    protected function casts(): array
    {
        return [
            'is_utama' => 'boolean',
        ];
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(ProgramRpjmd::class, 'program_rpjmd_id');
    }

    public function opd(): BelongsTo
    {
        return $this->belongsTo(Opd::class);
    }
}
