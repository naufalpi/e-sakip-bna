<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class RencanaAksiItem extends Model
{
    use LogsActivity;
    use SoftDeletes;

    protected $table = 'rencana_aksi_items';

    protected $fillable = [
        'rencana_aksi_id',
        'parent_id',
        'source_type',
        'source_id',
        'level',
        'kode_snapshot',
        'uraian_snapshot',
        'perjanjian_kinerja_item_id',
        'opd_program_id',
        'opd_kegiatan_id',
        'opd_sub_kegiatan_id',
        'periode_realisasi',
        'triwulan',
        'bulan',
        'aksi',
        'indikator',
        'formula_snapshot',
        'formula',
        'satuan_snapshot',
        'tipe_perhitungan_snapshot',
        'is_snapshot',
        'target',
        'target_text',
        'anggaran',
        'penanggung_jawab',
        'status',
        'urutan',
    ];

    protected function casts(): array
    {
        return [
            'bulan' => 'integer',
            'target' => 'decimal:4',
            'anggaran' => 'decimal:2',
            'is_snapshot' => 'boolean',
        ];
    }

    public function rencanaAksi(): BelongsTo
    {
        return $this->belongsTo(RencanaAksi::class);
    }

    public function perjanjianKinerjaItem(): BelongsTo
    {
        return $this->belongsTo(PerjanjianKinerjaItem::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('urutan');
    }

    public function targetTriwulan(): HasMany
    {
        return $this->hasMany(RencanaAksiTargetTriwulan::class)->orderBy('triwulan');
    }

    public function opdProgram(): BelongsTo
    {
        return $this->belongsTo(OpdProgram::class);
    }

    public function opdKegiatan(): BelongsTo
    {
        return $this->belongsTo(OpdKegiatan::class);
    }

    public function opdSubKegiatan(): BelongsTo
    {
        return $this->belongsTo(OpdSubKegiatan::class);
    }
}
