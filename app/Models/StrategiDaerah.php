<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class StrategiDaerah extends Model
{
    use LogsActivity;
    use SoftDeletes;

    protected $table = 'strategi_daerah';

    protected $fillable = ['kode', 'strategi', 'status'];

    public function programs(): HasMany
    {
        return $this->hasMany(ProgramRpjmd::class)->orderBy('urutan');
    }
}
