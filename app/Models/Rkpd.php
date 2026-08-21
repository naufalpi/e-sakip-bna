<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Rkpd extends Model
{
    use LogsActivity;
    use SoftDeletes;

    protected $table = 'rkpd';

    protected $fillable = [
        'rpjmd_id',
        'periode_tahun_id',
        'tahun',
        'judul',
        'nomor_dokumen',
        'status',
        'catatan',
        'submitted_by',
        'submitted_at',
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
    ];

    protected function casts(): array
    {
        return [
            'submitted_at' => 'datetime',
            'nomor_versi' => 'integer',
            'is_active_version' => 'boolean',
            'tanggal_berlaku' => 'date',
            'disahkan_pada' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::created(function (self $rkpd): void {
            if ($rkpd->root_version_id === null) {
                $rkpd->forceFill(['root_version_id' => $rkpd->id])->saveQuietly();
            }
        });
    }

    public function versionLabel(): string
    {
        return match ($this->jenis_versi) {
            'ditetapkan' => 'RKPD Ditetapkan',
            'perubahan' => in_array($this->status, ['approved', 'locked'], true)
                ? 'RKPD Perubahan Ditetapkan'
                : 'RKPD Perubahan',
            default => 'RKPD Awal',
        };
    }

    public function isArchivedVersion(): bool
    {
        return ! $this->is_active_version
            && in_array($this->status, ['approved', 'locked'], true);
    }

    public function isOfficialVersion(): bool
    {
        return in_array($this->jenis_versi, ['ditetapkan', 'perubahan'], true)
            && in_array($this->status, ['approved', 'locked'], true);
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
        return $this->hasMany(self::class, 'root_version_id')->orderBy('nomor_versi');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'disahkan_oleh');
    }

    public function rpjmd(): BelongsTo
    {
        return $this->belongsTo(Rpjmd::class);
    }

    public function periodeTahun(): BelongsTo
    {
        return $this->belongsTo(PeriodeTahun::class);
    }

    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(RkpdItem::class)->orderBy('urutan')->orderBy('id');
    }

    public function ikuTargets(): HasMany
    {
        return $this->hasMany(RkpdIkuTarget::class);
    }

    public function renjaOpd(): HasMany
    {
        return $this->hasMany(RenjaOpd::class);
    }
}
