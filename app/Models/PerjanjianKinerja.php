<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PerjanjianKinerja extends Model
{
    use LogsActivity;
    use SoftDeletes;

    protected $table = 'perjanjian_kinerja';

    protected $fillable = [
        'opd_id',
        'pegawai_id',
        'penempatan_pegawai_id',
        'atasan_pegawai_id',
        'renstra_opd_id',
        'rkpd_id',
        'renja_opd_id',
        'dpa_opd_id',
        'periode_tahun_id',
        'tahun',
        'tipe_pk',
        'level_pk',
        'sumber_data',
        'nama_pegawai_snapshot',
        'nip_snapshot',
        'jabatan_snapshot',
        'nama_atasan_snapshot',
        'nip_atasan_snapshot',
        'jabatan_atasan_snapshot',
        'snapshot_dibuat_pada',
        'judul',
        'nomor_dokumen',
        'tanggal_dokumen',
        'tempat_penandatanganan',
        'status',
        'catatan',
        'submitted_by',
        'submitted_at',
    ];

    protected function casts(): array
    {
        return [
            'tahun' => 'integer',
            'tanggal_dokumen' => 'date',
            'snapshot_dibuat_pada' => 'datetime',
            'submitted_at' => 'datetime',
        ];
    }

    public function opd(): BelongsTo
    {
        return $this->belongsTo(Opd::class);
    }

    public function renstraOpd(): BelongsTo
    {
        return $this->belongsTo(RenstraOpd::class);
    }

    public function rkpd(): BelongsTo
    {
        return $this->belongsTo(Rkpd::class);
    }

    public function renjaOpd(): BelongsTo
    {
        return $this->belongsTo(RenjaOpd::class);
    }

    public function dpaOpd(): BelongsTo
    {
        return $this->belongsTo(DpaOpd::class);
    }

    public function pegawai(): BelongsTo
    {
        return $this->belongsTo(Pegawai::class);
    }

    public function penempatanPegawai(): BelongsTo
    {
        return $this->belongsTo(RiwayatPejabatJabatan::class, 'penempatan_pegawai_id');
    }

    public function atasanPegawai(): BelongsTo
    {
        return $this->belongsTo(Pegawai::class, 'atasan_pegawai_id');
    }

    public function periodeTahun(): BelongsTo
    {
        return $this->belongsTo(PeriodeTahun::class);
    }

    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PerjanjianKinerjaItem::class)->orderBy('urutan');
    }

    public function programs(): HasMany
    {
        return $this->hasMany(PerjanjianKinerjaProgram::class)->orderBy('urutan')->orderBy('id');
    }

    public function levelLabel(): string
    {
        return match ($this->level_pk) {
            'bupati' => 'PK Bupati',
            'kepala_opd' => 'PK Kepala OPD',
            'struktural' => 'PK Struktural',
            'individu' => 'PK JF/Pelaksana',
            default => 'Perjanjian Kinerja',
        };
    }

    public function workflowSubmission(): HasMany
    {
        return $this->hasMany(WorkflowSubmission::class, 'related_id')->where('related_table', $this->getTable());
    }
}
