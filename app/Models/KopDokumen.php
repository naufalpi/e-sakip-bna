<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KopDokumen extends Model
{
    use LogsActivity;

    protected $table = 'kop_dokumen';

    protected $fillable = [
        'scope_key',
        'opd_id',
        'nama_pemerintah',
        'nama_instansi',
        'alamat',
        'telepon',
        'faksimile',
        'website',
        'email',
        'kota',
        'kode_pos',
        'logo_path',
        'is_active',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function opd(): BelongsTo
    {
        return $this->belongsTo(Opd::class);
    }
}
