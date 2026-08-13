<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use App\Support\DocumentVersionLabel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class RenstraOpd extends Model
{
    use LogsActivity;
    use SoftDeletes;

    protected $table = 'renstra_opd';

    protected $fillable = [
        'opd_id',
        'rpjmd_id',
        'periode_tahun_id',
        'judul',
        'nomor_dokumen',
        'tahun_awal',
        'tahun_akhir',
        'status',
        'keterangan',
        'jenis_versi',
        'nomor_versi',
        'parent_version_id',
        'root_version_id',
        'is_active_version',
        'perlu_penyesuaian_rpjmd',
        'rpjmd_perubahan_terbaru_id',
        'rpjmd_penyesuaian_terdeteksi_pada',
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
            'perlu_penyesuaian_rpjmd' => 'boolean',
            'tanggal_berlaku' => 'date',
            'disahkan_pada' => 'datetime',
            'rpjmd_penyesuaian_terdeteksi_pada' => 'datetime',
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

    public function opd(): BelongsTo
    {
        return $this->belongsTo(Opd::class);
    }

    public function rpjmd(): BelongsTo
    {
        return $this->belongsTo(Rpjmd::class);
    }

    public function rpjmdPerubahanTerbaru(): BelongsTo
    {
        return $this->belongsTo(Rpjmd::class, 'rpjmd_perubahan_terbaru_id');
    }

    public function periodeTahun(): BelongsTo
    {
        return $this->belongsTo(PeriodeTahun::class);
    }

    public function tujuan(): HasMany
    {
        return $this->hasMany(TujuanOpd::class)->orderBy('urutan');
    }

    public function programs(): HasMany
    {
        return $this->hasMany(OpdProgram::class)->orderBy('urutan');
    }

    protected static function booted(): void
    {
        static::created(function (self $renstra): void {
            if ($renstra->root_version_id === null) {
                $renstra->forceFill(['root_version_id' => $renstra->id])->saveQuietly();
            }
        });
    }
}
