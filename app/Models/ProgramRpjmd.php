<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProgramRpjmd extends Model
{
    use LogsActivity;
    use SoftDeletes;

    protected $table = 'program_rpjmd';

    protected $fillable = [
        'strategi_daerah_id',
        'sasaran_daerah_id',
        'indikator_sasaran_daerah_id',
        'program_pemerintahan_id',
        'urusan_pemerintahan_id',
        'kode',
        'nama',
        'status',
        'is_penanggung_jawab_manual',
        'urutan',
    ];

    protected function casts(): array
    {
        return [
            'is_penanggung_jawab_manual' => 'boolean',
        ];
    }

    public function strategi(): BelongsTo
    {
        return $this->belongsTo(StrategiDaerah::class, 'strategi_daerah_id');
    }

    public function sasaran(): BelongsTo
    {
        return $this->belongsTo(SasaranDaerah::class, 'sasaran_daerah_id');
    }

    public function indikatorSasaran(): BelongsTo
    {
        return $this->belongsTo(IndikatorSasaranDaerah::class, 'indikator_sasaran_daerah_id');
    }

    public function programPemerintahan(): BelongsTo
    {
        return $this->belongsTo(ProgramPemerintahan::class);
    }

    public function programPemerintahanReferences(): BelongsToMany
    {
        return $this->belongsToMany(ProgramPemerintahan::class, 'program_rpjmd_program_pemerintahan')
            ->withTimestamps();
    }

    /**
     * @return array<int, int>
     */
    public function programPemerintahanReferenceIds(): array
    {
        $ids = $this->relationLoaded('programPemerintahanReferences')
            ? $this->programPemerintahanReferences->pluck('id')
            : $this->programPemerintahanReferences()->pluck('program_pemerintahan.id');

        if ($ids->isEmpty() && $this->program_pemerintahan_id) {
            $ids = collect([(int) $this->program_pemerintahan_id]);
        }

        return $ids
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    public function urusanPemerintahan(): BelongsTo
    {
        return $this->belongsTo(UrusanPemerintahan::class);
    }

    public function indikator(): HasMany
    {
        return $this->hasMany(IndikatorProgramRpjmd::class)->orderBy('urutan');
    }

    public function opdPenanggungJawab(): BelongsToMany
    {
        return $this->belongsToMany(Opd::class, 'program_rpjmd_opd_penanggung_jawab')
            ->withPivot(['id', 'peran', 'is_utama'])
            ->withTimestamps();
    }

    public function scopeForRpjmd(Builder $query, int $rpjmdId): Builder
    {
        return $query->where(function (Builder $query) use ($rpjmdId) {
            $query->whereHas('sasaran.tujuan', fn (Builder $query) => $query->forRpjmd($rpjmdId))
                ->orWhereHas('indikatorSasaran.sasaran.tujuan', fn (Builder $query) => $query->forRpjmd($rpjmdId));
        });
    }
}
