<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class RenjaOpdItem extends Model
{
    use LogsActivity;
    use SoftDeletes;

    protected $table = 'renja_opd_items';

    protected $fillable = [
        'renja_opd_id',
        'program_pemerintahan_id',
        'kegiatan_pemerintahan_id',
        'sub_kegiatan_pemerintahan_id',
        'indikator_sub_kegiatan_id',
        'kode',
        'nama_sub_kegiatan',
        'indikator',
        'target_akhir_renstra',
        'realisasi_capaian_renja_tahun_lalu',
        'prakiraan_capaian_target_renja_tahun_berjalan',
        'target',
        'pagu_indikatif',
        'lokasi',
        'sumber_dana',
        'prioritas_nasional',
        'prioritas_daerah',
        'kelompok_sasaran',
        'prakiraan_maju_target',
        'prakiraan_maju_pagu_indikatif',
        'status',
        'urutan',
    ];

    protected function casts(): array
    {
        return [
            'pagu_indikatif' => 'decimal:2',
            'prakiraan_maju_pagu_indikatif' => 'decimal:2',
        ];
    }

    public function renjaOpd(): BelongsTo
    {
        return $this->belongsTo(RenjaOpd::class);
    }

    public function programPemerintahan(): BelongsTo
    {
        return $this->belongsTo(ProgramPemerintahan::class);
    }

    public function kegiatanPemerintahan(): BelongsTo
    {
        return $this->belongsTo(KegiatanPemerintahan::class);
    }

    public function subKegiatanPemerintahan(): BelongsTo
    {
        return $this->belongsTo(SubKegiatanPemerintahan::class);
    }

    public function indikatorSubKegiatan(): BelongsTo
    {
        return $this->belongsTo(IndikatorSubKegiatan::class);
    }
}
