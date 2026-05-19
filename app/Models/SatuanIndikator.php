<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SatuanIndikator extends Model
{
    use LogsActivity;
    use SoftDeletes;

    protected $table = 'satuan_indikator';

    protected $fillable = [
        'nama',
        'simbol',
        'jenis',
        'deskripsi',
        'status',
    ];
}
