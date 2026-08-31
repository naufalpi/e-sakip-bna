<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class DpaOpdItem extends Model
{
    use LogsActivity;
    use SoftDeletes;

    protected $table = 'dpa_opd_items';

    protected $fillable = [
        'dpa_opd_id', 'rka_opd_item_id', 'urusan_pemerintahan_id', 'bidang_urusan_id',
        'program_pemerintahan_id', 'kegiatan_pemerintahan_id', 'sub_kegiatan_pemerintahan_id',
        'kode_urusan', 'nama_urusan', 'kode_bidang', 'nama_bidang', 'kode_program', 'nama_program',
        'kode_kegiatan', 'nama_kegiatan', 'kode_sub_kegiatan', 'nama_sub_kegiatan',
        'tolok_ukur_kinerja', 'target_kinerja', 'satuan_kinerja', 'sumber_pendanaan', 'lokasi',
        'kelompok_sasaran', 'bulan_mulai', 'bulan_selesai', 'jenis_belanja', 'alokasi_tahun_sebelumnya',
        'pagu_rka', 'pagu_dpa', 'alokasi_tahun_berikutnya',
        'alasan_penyesuaian', 'catatan', 'urutan',
    ];

    protected function casts(): array
    {
        return [
            'bulan_mulai' => 'integer',
            'bulan_selesai' => 'integer',
            'urutan' => 'integer',
            'alokasi_tahun_sebelumnya' => 'decimal:2',
            'pagu_rka' => 'decimal:2',
            'pagu_dpa' => 'decimal:2',
            'alokasi_tahun_berikutnya' => 'decimal:2',
        ];
    }

    public function dpaOpd(): BelongsTo
    {
        return $this->belongsTo(DpaOpd::class);
    }

    public function rkaOpdItem(): BelongsTo
    {
        return $this->belongsTo(RkaOpdItem::class);
    }

    public function cashPlans(): HasMany
    {
        return $this->hasMany(DpaOpdCashPlan::class)->orderBy('bulan');
    }
}
