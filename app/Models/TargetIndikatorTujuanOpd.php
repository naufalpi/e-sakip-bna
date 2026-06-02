<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TargetIndikatorTujuanOpd extends Model
{
    use LogsActivity;

    protected $table = 'target_indikator_tujuan_opd';

    protected $fillable = ['indikator_tujuan_opd_id', 'periode_tahun_id', 'target', 'target_text'];

    protected function casts(): array
    {
        return [
            'target' => 'decimal:4',
        ];
    }

    public function indikator(): BelongsTo
    {
        return $this->belongsTo(IndikatorTujuanOpd::class, 'indikator_tujuan_opd_id');
    }

    public function periodeTahun(): BelongsTo
    {
        return $this->belongsTo(PeriodeTahun::class);
    }
}
