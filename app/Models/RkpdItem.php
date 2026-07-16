<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class RkpdItem extends Model
{
    use LogsActivity;
    use SoftDeletes;

    protected $fillable = [
        'rkpd_id',
        'renja_opd_id',
        'renja_opd_item_id',
        'opd_id',
        'opd_unit_id',
        'urusan_pemerintahan_id',
        'bidang_urusan_id',
        'program_pemerintahan_id',
        'kegiatan_pemerintahan_id',
        'sub_kegiatan_pemerintahan_id',
        'program_rpjmd_id',
        'kode',
        'nama_urusan_bidang_program_kegiatan_sub',
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
        'perangkat_daerah_penanggung_jawab',
        'status',
        'urutan',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'pagu_indikatif' => 'decimal:2',
            'prakiraan_maju_pagu_indikatif' => 'decimal:2',
            'metadata' => 'array',
        ];
    }

    public function rkpd(): BelongsTo
    {
        return $this->belongsTo(Rkpd::class);
    }

    public function renjaOpd(): BelongsTo
    {
        return $this->belongsTo(RenjaOpd::class);
    }

    public function renjaOpdItem(): BelongsTo
    {
        return $this->belongsTo(RenjaOpdItem::class);
    }

    public function opd(): BelongsTo
    {
        return $this->belongsTo(Opd::class);
    }

    public function opdUnit(): BelongsTo
    {
        return $this->belongsTo(OpdUnit::class);
    }

    public function urusanPemerintahan(): BelongsTo
    {
        return $this->belongsTo(UrusanPemerintahan::class);
    }

    public function bidangUrusan(): BelongsTo
    {
        return $this->belongsTo(BidangUrusan::class);
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

    public function programRpjmd(): BelongsTo
    {
        return $this->belongsTo(ProgramRpjmd::class);
    }
}
