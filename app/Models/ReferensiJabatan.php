<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ReferensiJabatan extends Model
{
    use LogsActivity;
    use SoftDeletes;

    protected $table = 'referensi_jabatan';

    protected $fillable = [
        'identity_key',
        'kode',
        'nama',
        'jenis_jabatan',
        'jenjang',
        'kelas_jabatan',
        'kualifikasi',
        'dasar_hukum',
        'berlaku_mulai',
        'berlaku_sampai',
        'verification_status',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'kelas_jabatan' => 'integer',
            'berlaku_mulai' => 'date',
            'berlaku_sampai' => 'date',
        ];
    }

    public static function jenisOptions(): array
    {
        return [
            ['value' => 'fungsional', 'label' => 'Jabatan Fungsional'],
            ['value' => 'pelaksana', 'label' => 'Jabatan Pelaksana'],
        ];
    }

    public static function jenisLabels(): array
    {
        return collect(self::jenisOptions())->pluck('label', 'value')->all();
    }

    public static function verificationLabels(): array
    {
        return [
            'draft' => 'Perlu validasi',
            'verified' => 'Terverifikasi',
        ];
    }

    public static function makeIdentityKey(string $type, string $name, ?string $level = null): string
    {
        $normalize = static fn (?string $value): string => preg_replace('/\s+/u', ' ', mb_strtolower(trim((string) $value))) ?: '';

        return hash('sha256', $normalize($type).'|'.$normalize($name).'|'.$normalize($level));
    }

    public function placements(): HasMany
    {
        return $this->hasMany(JabatanOrganisasi::class);
    }

    public function isAvailableForPlacement(): bool
    {
        $today = now()->toDateString();

        return $this->status === 'active'
            && $this->verification_status === 'verified'
            && (! $this->berlaku_mulai || $this->berlaku_mulai->toDateString() <= $today)
            && (! $this->berlaku_sampai || $this->berlaku_sampai->toDateString() >= $today);
    }
}
