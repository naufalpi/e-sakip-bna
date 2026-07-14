<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class BidangUrusan extends Model
{
    use LogsActivity;
    use SoftDeletes;

    protected $table = 'bidang_urusan';

    protected $fillable = [
        'urusan_pemerintahan_id',
        'kode',
        'nama',
        'status',
    ];

    public function urusanPemerintahan(): BelongsTo
    {
        return $this->belongsTo(UrusanPemerintahan::class);
    }

    public function program(): HasMany
    {
        return $this->hasMany(ProgramPemerintahan::class)->orderBy('kode');
    }
}
