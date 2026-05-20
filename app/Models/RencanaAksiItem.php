<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class RencanaAksiItem extends Model
{
    use LogsActivity;
    use SoftDeletes;

    protected $table = 'rencana_aksi_items';

    protected $fillable = [
        'rencana_aksi_id',
        'perjanjian_kinerja_item_id',
        'opd_program_id',
        'opd_kegiatan_id',
        'opd_sub_kegiatan_id',
        'periode_realisasi',
        'triwulan',
        'bulan',
        'aksi',
        'indikator',
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
