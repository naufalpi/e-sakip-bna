<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class RkaOpdItem extends Model
{
    use LogsActivity;
    use SoftDeletes;

    protected $table = 'rka_opd_items';

    protected $fillable = [
        'rka_opd_id',
        'renja_opd_item_id',
        'urusan_pemerintahan_id',
        'bidang_urusan_id',
        'program_pemerintahan_id',
        'kegiatan_pemerintahan_id',
        'sub_kegiatan_pemerintahan_id',
        'kode_urusan',
        'nama_urusan',
        'kode_bidang',
        'nama_bidang',
        'kode_program',
        'nama_program',
        'kode_kegiatan',
        'nama_kegiatan',
        'kode_sub_kegiatan',
        'nama_sub_kegiatan',
        'tolok_ukur_kinerja',
        'target_kinerja',
        'satuan_kinerja',
        'sumber_pendanaan',
        'lokasi',
        'kelompok_sasaran',
        'bulan_mulai',
        'bulan_selesai',
        'jenis_belanja',
        'alokasi_tahun_sebelumnya',
        'pagu_renja',
        'pagu_rka',
        'pagu_belanja_operasi',
        'pagu_belanja_modal',
        'pagu_belanja_tidak_terduga',
        'pagu_belanja_transfer',
        'pagu_usulan',
        'pagu_belanja_operasi_usulan',
        'pagu_belanja_modal_usulan',
        'pagu_belanja_tidak_terduga_usulan',
        'pagu_belanja_transfer_usulan',
        'pagu_hasil_verifikasi',
        'pagu_belanja_operasi_hasil_verifikasi',
        'pagu_belanja_modal_hasil_verifikasi',
        'pagu_belanja_tidak_terduga_hasil_verifikasi',
        'pagu_belanja_transfer_hasil_verifikasi',
        'alokasi_tahun_berikutnya',
        'alasan_penyesuaian',
        'catatan',
        'urutan',
    ];

    protected function casts(): array
    {
        return [
            'bulan_mulai' => 'integer',
            'bulan_selesai' => 'integer',
            'urutan' => 'integer',
            'alokasi_tahun_sebelumnya' => 'decimal:2',
            'pagu_renja' => 'decimal:2',
            'pagu_rka' => 'decimal:2',
            'pagu_belanja_operasi' => 'decimal:2',
            'pagu_belanja_modal' => 'decimal:2',
            'pagu_belanja_tidak_terduga' => 'decimal:2',
            'pagu_belanja_transfer' => 'decimal:2',
            'pagu_usulan' => 'decimal:2',
            'pagu_belanja_operasi_usulan' => 'decimal:2',
            'pagu_belanja_modal_usulan' => 'decimal:2',
            'pagu_belanja_tidak_terduga_usulan' => 'decimal:2',
            'pagu_belanja_transfer_usulan' => 'decimal:2',
            'pagu_hasil_verifikasi' => 'decimal:2',
            'pagu_belanja_operasi_hasil_verifikasi' => 'decimal:2',
            'pagu_belanja_modal_hasil_verifikasi' => 'decimal:2',
            'pagu_belanja_tidak_terduga_hasil_verifikasi' => 'decimal:2',
            'pagu_belanja_transfer_hasil_verifikasi' => 'decimal:2',
            'alokasi_tahun_berikutnya' => 'decimal:2',
        ];
    }

    public function rkaOpd(): BelongsTo
    {
        return $this->belongsTo(RkaOpd::class);
    }

    public function renjaOpdItem(): BelongsTo
    {
        return $this->belongsTo(RenjaOpdItem::class);
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

    public function dpaItems(): HasMany
    {
        return $this->hasMany(DpaOpdItem::class);
    }
}
