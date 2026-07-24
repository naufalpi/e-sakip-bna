<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TargetIndikatorSubKegiatan extends Model
{
    use LogsActivity;

    protected $table = 'target_indikator_sub_kegiatan';

    protected $fillable = ['indikator_sub_kegiatan_id', 'periode_tahun_id', 'target', 'target_text'];

    protected function casts(): array
    {
        return [
            'target' => 'decimal:4',
        ];
    }

    public function indikator(): BelongsTo
    {
        return $this->belongsTo(IndikatorSubKegiatan::class, 'indikator_sub_kegiatan_id');
    }

    public function periodeTahun(): BelongsTo
    {
        return $this->belongsTo(PeriodeTahun::class);
    }
}
