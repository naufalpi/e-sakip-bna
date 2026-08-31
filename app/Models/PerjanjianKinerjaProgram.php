<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PerjanjianKinerjaProgram extends Model
{
    use LogsActivity;

    protected $table = 'perjanjian_kinerja_programs';

    protected $fillable = [
        'perjanjian_kinerja_id',
        'program_rpjmd_id',
        'opd_program_id',
        'program_pemerintahan_id',
        'kode',
        'nama_program',
        'anggaran',
        'keterangan',
        'urutan',
    ];

    protected function casts(): array
    {
        return [
            'anggaran' => 'decimal:2',
            'urutan' => 'integer',
        ];
    }

    public function perjanjianKinerja(): BelongsTo
    {
        return $this->belongsTo(PerjanjianKinerja::class);
    }

    public function programRpjmd(): BelongsTo
    {
        return $this->belongsTo(ProgramRpjmd::class);
    }

    public function opdProgram(): BelongsTo
    {
        return $this->belongsTo(OpdProgram::class);
    }

    public function programPemerintahan(): BelongsTo
    {
        return $this->belongsTo(ProgramPemerintahan::class);
    }
}
