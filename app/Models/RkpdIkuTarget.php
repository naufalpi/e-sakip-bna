<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class RkpdIkuTarget extends Model
{
    use LogsActivity;
    use SoftDeletes;

    protected $table = 'rkpd_iku_targets';

    protected $fillable = [
        'rkpd_id',
        'periode_tahun_id',
        'indikator_type',
        'indikator_id',
        'target_rkpd',
        'updated_by',
    ];

    public function rkpd(): BelongsTo
    {
        return $this->belongsTo(Rkpd::class);
    }

    public function periodeTahun(): BelongsTo
    {
        return $this->belongsTo(PeriodeTahun::class);
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
