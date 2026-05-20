<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class OpdKegiatan extends Model
{
    use LogsActivity;
    use SoftDeletes;

    protected $table = 'opd_kegiatan';

    protected $fillable = ['opd_program_id', 'kode', 'nama', 'pagu_indikatif', 'urutan'];

    protected function casts(): array
    {
        return [
            'pagu_indikatif' => 'decimal:2',
        ];
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(OpdProgram::class, 'opd_program_id');
    }

    public function subKegiatan(): HasMany
    {
        return $this->hasMany(OpdSubKegiatan::class)->orderBy('urutan');
    }
}
