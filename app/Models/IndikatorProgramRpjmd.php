<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class IndikatorProgramRpjmd extends Model
{
    use LogsActivity;
    use SoftDeletes;

    protected $table = 'indikator_program_rpjmd';

    protected $fillable = ['program_rpjmd_id', 'satuan_indikator_id', 'kode', 'indikator', 'formula', 'sumber_data', 'urutan'];

    public function program(): BelongsTo
    {
        return $this->belongsTo(ProgramRpjmd::class, 'program_rpjmd_id');
    }

    public function satuanIndikator(): BelongsTo
    {
        return $this->belongsTo(SatuanIndikator::class);
    }

    public function targets(): HasMany
    {
        return $this->hasMany(TargetIndikatorProgramRpjmd::class);
    }
}
