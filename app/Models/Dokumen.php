<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Dokumen extends Model
{
    use LogsActivity;
    use SoftDeletes;

    protected $table = 'dokumen';

    protected $fillable = [
        'opd_id',
        'periode_tahun_id',
        'jenis',
        'judul',
        'nomor_dokumen',
        'deskripsi',
        'status',
        'original_filename',
        'mime_type',
        'file_size',
        'file_hash',
        'storage_disk',
        'storage_path',
        'uploaded_by',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'file_size' => 'integer',
            'metadata' => 'array',
        ];
    }

    public function opd(): BelongsTo
    {
        return $this->belongsTo(Opd::class);
    }

    public function periodeTahun(): BelongsTo
    {
        return $this->belongsTo(PeriodeTahun::class);
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function relations(): HasMany
    {
        return $this->hasMany(DokumenRelation::class);
    }
}
