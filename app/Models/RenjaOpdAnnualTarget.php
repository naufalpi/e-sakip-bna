<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RenjaOpdAnnualTarget extends Model
{
    use LogsActivity;

    protected $table = 'renja_opd_annual_targets';

    protected $fillable = [
        'renja_opd_id',
        'level',
        'indicator_type',
        'indicator_id',
        'kode_snapshot',
        'uraian_snapshot',
        'indikator_snapshot',
        'satuan_indikator_id',
        'satuan_snapshot',
        'formula_snapshot',
        'hierarchy_snapshot',
        'target_renstra',
        'target_renstra_text',
        'target_renja',
        'target_renja_text',
        'is_adjusted',
        'alasan_penyesuaian',
        'bootstrap_source',
        'urutan',
    ];

    protected function casts(): array
    {
        return [
            'hierarchy_snapshot' => 'array',
            'target_renstra' => 'decimal:4',
            'target_renja' => 'decimal:4',
            'is_adjusted' => 'boolean',
            'urutan' => 'integer',
        ];
    }

    public function renjaOpd(): BelongsTo
    {
        return $this->belongsTo(RenjaOpd::class);
    }
}
