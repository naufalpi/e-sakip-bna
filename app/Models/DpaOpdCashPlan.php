<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DpaOpdCashPlan extends Model
{
    use LogsActivity;

    protected $table = 'dpa_opd_cash_plans';

    protected $fillable = ['dpa_opd_item_id', 'bulan', 'jumlah'];

    protected function casts(): array
    {
        return [
            'bulan' => 'integer',
            'jumlah' => 'decimal:2',
        ];
    }

    public function dpaOpdItem(): BelongsTo
    {
        return $this->belongsTo(DpaOpdItem::class);
    }
}
