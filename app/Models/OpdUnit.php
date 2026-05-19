<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class OpdUnit extends Model
{
    use LogsActivity;
    use SoftDeletes;

    protected $fillable = [
        'opd_id',
        'parent_id',
        'kode',
        'nama',
        'jenis_unit',
        'nama_pimpinan',
        'nip_pimpinan',
        'status',
    ];

    public function opd(): BelongsTo
    {
        return $this->belongsTo(Opd::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(OpdUnit::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(OpdUnit::class, 'parent_id');
    }
}
