<?php

namespace App\Models;

use App\Models\Concerns\HasTargetTriwulan;
use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class IndikatorSasaranOpd extends Model
{
    use HasTargetTriwulan;
    use LogsActivity;
    use SoftDeletes;

    protected $table = 'indikator_sasaran_opd';

    protected $fillable = [
        'sasaran_opd_id',
        'indikator_sasaran_daerah_id',
        'satuan_indikator_id',
        'kode',
        'indikator',
        'tipe_indikator',
        'definisi_operasional',
        'formula',
        'formulasi_pengukuran',
        'tipe_perhitungan',
        'opd_penanggung_jawab_id',
        'pd_penanggung_jawab',
        'sumber_data',
        'urutan',
    ];

    public function sasaran(): BelongsTo
    {
        return $this->belongsTo(SasaranOpd::class, 'sasaran_opd_id');
    }

    public function indikatorSasaranDaerah(): BelongsTo
    {
        return $this->belongsTo(IndikatorSasaranDaerah::class);
    }

    public function satuanIndikator(): BelongsTo
    {
        return $this->belongsTo(SatuanIndikator::class);
    }

    public function opdPenanggungJawab(): BelongsTo
    {
        return $this->belongsTo(Opd::class, 'opd_penanggung_jawab_id');
    }

    public function targets(): HasMany
    {
        return $this->hasMany(TargetIndikatorSasaranOpd::class);
    }
}
