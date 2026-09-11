<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RencanaAksiTargetTriwulan extends Model
{
    use LogsActivity;

    protected $table = 'rencana_aksi_target_triwulan';

    protected $fillable = ['rencana_aksi_item_id', 'triwulan', 'target', 'target_text'];

    protected function casts(): array
    {
        return [
            'triwulan' => 'integer',
            'target' => 'decimal:4',
        ];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(RencanaAksiItem::class, 'rencana_aksi_item_id');
    }
}
