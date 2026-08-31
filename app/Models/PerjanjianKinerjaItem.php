<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class PerjanjianKinerjaItem extends Model
{
    use LogsActivity;
    use SoftDeletes;

    protected $table = 'perjanjian_kinerja_items';

    protected $fillable = [
        'perjanjian_kinerja_id',
        'sumber_item',
        'jenis_item',
        'level_cascading',
        'cascading_source_type',
        'cascading_source_id',
        'sasaran_opd_id',
        'indikator_sasaran_opd_id',
        'opd_program_id',
        'satuan_indikator_id',
        'satuan_snapshot',
        'kode',
        'sasaran',
        'indikator',
        'target',
        'target_text',
        'urutan',
        'is_readonly',
    ];

    protected function casts(): array
    {
        return [
            'target' => 'decimal:4',
            'is_readonly' => 'boolean',
        ];
    }

    public function perjanjianKinerja(): BelongsTo
    {
        return $this->belongsTo(PerjanjianKinerja::class);
    }

    public function sasaranOpd(): BelongsTo
    {
        return $this->belongsTo(SasaranOpd::class);
    }

    public function indikatorSasaranOpd(): BelongsTo
    {
        return $this->belongsTo(IndikatorSasaranOpd::class);
    }

    public function opdProgram(): BelongsTo
    {
        return $this->belongsTo(OpdProgram::class);
    }

    public function satuanIndikator(): BelongsTo
    {
        return $this->belongsTo(SatuanIndikator::class);
    }
}
