<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class PenugasanPengampuKinerja extends Model
{
    use LogsActivity;
    use SoftDeletes;

    protected $table = 'penugasan_pengampu_kinerja';

    protected $fillable = [
        'pegawai_id',
        'penempatan_pegawai_id',
        'opd_id',
        'periode_tahun_id',
        'tahun',
        'sumber_kinerja_type',
        'sumber_kinerja_id',
        'sumber_kinerja_label',
        'peran',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'tahun' => 'integer',
        ];
    }

    public static function sourceOptions(): array
    {
        return [
            ['value' => 'sasaran', 'label' => 'Sasaran OPD'],
            ['value' => 'program', 'label' => 'Program'],
            ['value' => 'kegiatan', 'label' => 'Kegiatan'],
            ['value' => 'sub_kegiatan', 'label' => 'Sub Kegiatan'],
        ];
    }

    public function pegawai(): BelongsTo
    {
        return $this->belongsTo(Pegawai::class);
    }

    public function penempatan(): BelongsTo
    {
        return $this->belongsTo(RiwayatPejabatJabatan::class, 'penempatan_pegawai_id');
    }

    public function opd(): BelongsTo
    {
        return $this->belongsTo(Opd::class);
    }

    public function periodeTahun(): BelongsTo
    {
        return $this->belongsTo(PeriodeTahun::class);
    }
}
