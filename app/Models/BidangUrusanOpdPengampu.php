<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BidangUrusanOpdPengampu extends Model
{
    use LogsActivity;

    protected $table = 'bidang_urusan_opd_pengampu';

    protected $fillable = [
        'bidang_urusan_id',
        'opd_id',
        'peran',
        'is_utama',
    ];

    protected $casts = [
        'is_utama' => 'boolean',
    ];

    public function bidangUrusan(): BelongsTo
    {
        return $this->belongsTo(BidangUrusan::class);
    }

    public function opd(): BelongsTo
    {
        return $this->belongsTo(Opd::class);
    }
}
