<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class JabatanOrganisasi extends Model
{
    use LogsActivity;
    use SoftDeletes;

    protected $table = 'jabatan_organisasi';

    protected $fillable = [
        'opd_id',
        'opd_unit_id',
        'parent_id',
        'nama',
        'level_jabatan',
        'eselon',
        'urutan',
        'status',
        'verification_status',
        'proposed_by',
        'verified_by',
        'verified_at',
        'verification_note',
    ];

    protected function casts(): array
    {
        return [
            'urutan' => 'integer',
            'verified_at' => 'datetime',
        ];
    }

    public static function levelOptions(): array
    {
        return [
            ['value' => 'kepala_daerah', 'label' => 'Kepala Daerah', 'rank' => 1],
            ['value' => 'jpt_pratama', 'label' => 'Jabatan Pimpinan Tinggi Pratama', 'rank' => 2],
            ['value' => 'administrator', 'label' => 'Jabatan Administrator', 'rank' => 3],
            ['value' => 'pengawas', 'label' => 'Jabatan Pengawas', 'rank' => 4],
            ['value' => 'fungsional', 'label' => 'Jabatan Fungsional', 'rank' => 5],
            ['value' => 'pelaksana', 'label' => 'Jabatan Pelaksana', 'rank' => 6],
        ];
    }

    public static function levelLabels(): array
    {
        return collect(self::levelOptions())->pluck('label', 'value')->all();
    }

    public static function eselonOptions(): array
    {
        return [
            ['value' => 'ii_a', 'label' => 'Eselon II.a'],
            ['value' => 'ii_b', 'label' => 'Eselon II.b'],
            ['value' => 'iii_a', 'label' => 'Eselon III.a'],
            ['value' => 'iii_b', 'label' => 'Eselon III.b'],
            ['value' => 'iv_a', 'label' => 'Eselon IV.a'],
            ['value' => 'iv_b', 'label' => 'Eselon IV.b'],
            ['value' => 'non_eselon', 'label' => 'Non-eselon'],
        ];
    }

    public static function verificationLabels(): array
    {
        return [
            'pending' => 'Menunggu verifikasi',
            'verified' => 'Terverifikasi',
            'rejected' => 'Perlu perbaikan',
        ];
    }

    public function isVerified(): bool
    {
        return $this->verification_status === 'verified';
    }

    public function isPendingVerification(): bool
    {
        return $this->verification_status === 'pending';
    }

    public function allowsMultipleHolders(): bool
    {
        return in_array($this->level_jabatan, ['fungsional', 'pelaksana'], true);
    }

    public function isStructural(): bool
    {
        return ! $this->allowsMultipleHolders();
    }

    public function opd(): BelongsTo
    {
        return $this->belongsTo(Opd::class);
    }

    public function opdUnit(): BelongsTo
    {
        return $this->belongsTo(OpdUnit::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function proposedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'proposed_by');
    }

    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function riwayatPejabat(): HasMany
    {
        return $this->hasMany(RiwayatPejabatJabatan::class)->orderByDesc('tanggal_mulai');
    }
}
