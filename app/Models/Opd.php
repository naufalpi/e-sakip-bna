<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Opd extends Model
{
    use LogsActivity;
    use SoftDeletes;

    protected $fillable = [
        'urusan_pemerintahan_id',
        'kode',
        'nama',
        'singkatan',
        'jenis',
        'alamat',
        'telepon',
        'email',
        'nama_kepala',
        'nip_kepala',
        'status',
    ];

    public function urusanPemerintahan(): BelongsTo
    {
        return $this->belongsTo(UrusanPemerintahan::class);
    }

    public function units(): HasMany
    {
        return $this->hasMany(OpdUnit::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
