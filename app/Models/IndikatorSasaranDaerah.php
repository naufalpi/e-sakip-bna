<?php

namespace App\Models;

use App\Models\Concerns\HasTargetTriwulan;
use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class IndikatorSasaranDaerah extends Model
{
    use HasTargetTriwulan;
    use LogsActivity;
    use SoftDeletes;

    protected $table = 'indikator_sasaran_daerah';

    protected $fillable = [
        'sasaran_daerah_id',
        'satuan_indikator_id',
        'opd_id',
        'kode',
        'indikator',
        'definisi_operasional',
        'alasan_pemilihan',
        'formulasi_pengukuran',
        'tipe_perhitungan',
        'sumber_data',
        'urutan',
    ];

    public function sasaran(): BelongsTo
    {
        return $this->belongsTo(SasaranDaerah::class, 'sasaran_daerah_id');
    }

    public function satuanIndikator(): BelongsTo
    {
        return $this->belongsTo(SatuanIndikator::class);
    }

    public function opd(): BelongsTo
    {
        return $this->belongsTo(Opd::class);
    }

    public function targets(): HasMany
    {
        return $this->hasMany(TargetIndikatorSasaranDaerah::class);
    }

    public function programs(): HasMany
    {
        return $this->hasMany(ProgramRpjmd::class, 'indikator_sasaran_daerah_id')->orderBy('urutan');
    }
}
