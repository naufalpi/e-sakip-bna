<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use App\Support\DocumentVersionLabel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Rpjmd extends Model
{
    use LogsActivity;
    use SoftDeletes;

    protected $table = 'rpjmd';

    protected $fillable = [
        'periode_tahun_id',
        'judul',
        'nomor_perda',
        'tahun_awal',
        'tahun_akhir',
        'status',
        'struktur_tujuan_mode',
        'struktur_sasaran_mode',
        'keterangan',
        'jenis_versi',
        'nomor_versi',
        'parent_version_id',
        'root_version_id',
        'is_active_version',
        'alasan_perubahan',
        'dasar_perubahan',
        'tanggal_berlaku',
        'disahkan_oleh',
        'disahkan_pada',
        'dibatalkan_oleh',
        'dibatalkan_pada',
        'alasan_pembatalan',
    ];

    protected function casts(): array
    {
        return [
            'tahun_awal' => 'integer',
            'tahun_akhir' => 'integer',
            'nomor_versi' => 'integer',
            'is_active_version' => 'boolean',
            'tanggal_berlaku' => 'date',
            'disahkan_pada' => 'datetime',
            'dibatalkan_pada' => 'datetime',
        ];
    }

    public function parentVersion(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_version_id');
    }

    public function rootVersion(): BelongsTo
    {
        return $this->belongsTo(self::class, 'root_version_id');
    }

    public function changeVersions(): HasMany
    {
        return $this->hasMany(self::class, 'parent_version_id')->orderBy('nomor_versi');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'disahkan_oleh');
    }

    public function canceledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dibatalkan_oleh');
    }

    public function versionLabel(): string
    {
        return DocumentVersionLabel::make($this->jenis_versi, $this->nomor_versi);
    }

    public function isArchivedVersion(): bool
    {
        return ! $this->is_active_version && in_array($this->status, ['approved', 'locked'], true);
    }

    protected static function booted(): void
    {
        static::created(function (self $rpjmd): void {
            if ($rpjmd->root_version_id === null) {
                $rpjmd->forceFill(['root_version_id' => $rpjmd->id])->saveQuietly();
            }
        });
    }

    public function periodeTahun(): BelongsTo
    {
        return $this->belongsTo(PeriodeTahun::class);
    }

    public function visi(): HasMany
    {
        return $this->hasMany(RpjmdVisi::class)->orderBy('urutan');
    }

    public function misi(): HasMany
    {
        return $this->hasMany(RpjmdMisi::class)->orderBy('urutan');
    }
}
