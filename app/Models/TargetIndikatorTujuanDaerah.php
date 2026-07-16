<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TargetIndikatorTujuanDaerah extends Model
{
    use LogsActivity;

    protected $table = 'target_indikator_tujuan_daerah';

    protected $fillable = ['indikator_tujuan_daerah_id', 'periode_tahun_id', 'jenis_target', 'target', 'target_text'];

    public function indikator(): BelongsTo
    {
        return $this->belongsTo(IndikatorTujuanDaerah::class, 'indikator_tujuan_daerah_id');
    }

    public function periodeTahun(): BelongsTo
    {
        return $this->belongsTo(PeriodeTahun::class);
    }
}
