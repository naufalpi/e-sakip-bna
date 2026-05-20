<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class LkjipBab extends Model
{
    use LogsActivity;
    use SoftDeletes;

    protected $table = 'lkjip_bab';

    protected $fillable = [
        'lkjip_id',
        'kode',
        'judul',
        'jenis',
        'konten',
        'urutan',
    ];

    protected function casts(): array
    {
        return [
            'urutan' => 'integer',
        ];
    }

    public function lkjip(): BelongsTo
    {
        return $this->belongsTo(Lkjip::class);
    }
}
