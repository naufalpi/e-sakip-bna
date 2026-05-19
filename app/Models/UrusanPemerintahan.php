<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class UrusanPemerintahan extends Model
{
    use LogsActivity;
    use SoftDeletes;

    protected $table = 'urusan_pemerintahan';

    protected $fillable = [
        'kode',
        'nama',
        'deskripsi',
        'status',
    ];

    public function opds(): HasMany
    {
        return $this->hasMany(Opd::class);
    }
}
