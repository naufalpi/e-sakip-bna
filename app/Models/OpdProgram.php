<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class OpdProgram extends Model
{
    use LogsActivity;
    use SoftDeletes;

    protected $table = 'opd_program';

    protected $fillable = [
        'renstra_opd_id',
        'sasaran_opd_id',
        'program_rpjmd_id',
        'program_pemerintahan_id',
        'kode',
        'nama',
        'sasaran_program',
        'pagu_indikatif',
        'status',
        'urutan',
    ];

    protected function casts(): array
    {
        return [
            'pagu_indikatif' => 'decimal:2',
        ];
    }

    public function renstra(): BelongsTo
    {
        return $this->belongsTo(RenstraOpd::class, 'renstra_opd_id');
    }

    public function sasaran(): BelongsTo
    {
        return $this->belongsTo(SasaranOpd::class, 'sasaran_opd_id');
    }

    public function programRpjmd(): BelongsTo
    {
        return $this->belongsTo(ProgramRpjmd::class);
    }

    public function programPemerintahan(): BelongsTo
    {
        return $this->belongsTo(ProgramPemerintahan::class);
    }

    public function indikator(): HasMany
    {
        return $this->hasMany(IndikatorOpdProgram::class)->orderBy('urutan');
    }

    public function kegiatan(): HasMany
    {
        return $this->hasMany(OpdKegiatan::class)->orderBy('urutan');
    }
}
