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

    public function scopeRelevantForOpd(Builder $query, int $opdId): Builder
    {
        return $query->where(function (Builder $query) use ($opdId): void {
            $query
                ->whereHas('opdPenanggungJawab', fn (Builder $query) => $query->whereKey($opdId))
                ->orWhere(function (Builder $query) use ($opdId): void {
                    $query
                        ->where(function (Builder $query): void {
                            $query
                                ->where('is_penanggung_jawab_manual', false)
                                ->orWhereNull('is_penanggung_jawab_manual');
                        })
                        ->where(function (Builder $query) use ($opdId): void {
                            $query
                                ->whereHas('programPemerintahan.bidangUrusan.opdPengampu', fn (Builder $query) => $query->whereKey($opdId))
                                ->orWhereHas('programPemerintahanReferences.bidangUrusan.opdPengampu', fn (Builder $query) => $query->whereKey($opdId))
                                ->orWhereHas('programPemerintahan', fn (Builder $query) => $this->applyProgramPenunjangFilter($query))
                                ->orWhereHas('programPemerintahanReferences', fn (Builder $query) => $this->applyProgramPenunjangFilter($query));
                        });
                });
        });
    }

    public function isRelevantForOpd(int $opdId): bool
    {
        if ($this->opdPenanggungJawab->contains('id', $opdId)) {
            return true;
        }

        if ($this->is_penanggung_jawab_manual) {
            return false;
        }

        $this->loadMissing([
            'programPemerintahan.bidangUrusan.opdPengampu',
            'programPemerintahanReferences.bidangUrusan.opdPengampu',
        ]);

        $references = collect()
            ->when($this->programPemerintahan, fn ($references) => $references->push($this->programPemerintahan))
            ->merge($this->programPemerintahanReferences)
            ->unique('id')
            ->values();

        if ($references->contains(fn (ProgramPemerintahan $program) => $this->isProgramPenunjang($program->nama))) {
            return true;
        }

        return $references
            ->pluck('bidangUrusan')
            ->filter()
            ->flatMap(fn ($bidang) => $bidang->opdPengampu)
            ->contains('id', $opdId);
    }

    public function preferredProgramPemerintahanReferenceForOpd(?int $opdId = null): ?ProgramPemerintahan
    {
        $this->loadMissing([
            'programPemerintahan.bidangUrusan.opdPengampu',
            'programPemerintahanReferences.bidangUrusan.opdPengampu',
        ]);

        $references = collect()
            ->when($this->programPemerintahan, fn ($references) => $references->push($this->programPemerintahan))
            ->merge($this->programPemerintahanReferences)
            ->unique('id')
            ->sortBy('kode')
            ->values();

        if ($references->isEmpty()) {
            return null;
        }

        if (! $opdId) {
            return $references->first();
        }

        $opd = Opd::query()->find($opdId, ['id', 'kode']);
        $opdBidangCodes = $this->bidangCodesFromOpdCode($opd?->kode);

        if ($opdBidangCodes !== []) {
            $matchedByCode = $references
                ->map(fn (ProgramPemerintahan $program) => [
                    'program' => $program,
                    'position' => array_search($this->bidangCodeFromProgramCode($program->kode), $opdBidangCodes, true),
                ])
                ->filter(fn (array $item) => $item['position'] !== false)
                ->sortBy(fn (array $item) => str_pad((string) $item['position'], 3, '0', STR_PAD_LEFT).'-'.$item['program']->kode)
                ->first();

            if ($matchedByCode) {
                return $matchedByCode['program'];
            }
        }

        return $references->first(
            fn (ProgramPemerintahan $program) => $program->bidangUrusan?->opdPengampu->contains('id', $opdId),
        ) ?? $references->first();
    }

    private function applyProgramPenunjangFilter(Builder $query): Builder
    {
        return $query
            ->whereRaw('LOWER(nama) LIKE ?', ['%program penunjang urusan pemerintahan daerah%'])
            ->where(function (Builder $query): void {
                $query
                    ->whereRaw('LOWER(nama) LIKE ?', ['%kabupaten/kota%'])
                    ->orWhereRaw('LOWER(nama) LIKE ?', ['%kab/kota%']);
            });
    }

    private function isProgramPenunjang(?string $name): bool
    {
        $normalized = strtolower((string) preg_replace('/\s+/', ' ', trim((string) $name)));

        return str_contains($normalized, 'program penunjang urusan pemerintahan daerah')
            && (str_contains($normalized, 'kabupaten/kota') || str_contains($normalized, 'kab/kota'));
    }

    private function bidangCodeFromProgramCode(?string $code): ?string
    {
        $parts = explode('.', (string) $code);

        if (count($parts) < 2) {
            return null;
        }

        return $parts[0].'.'.$parts[1];
    }

    /**
     * @return array<int, string>
     */
    private function bidangCodesFromOpdCode(?string $code): array
    {
        $parts = explode('.', (string) $code);
        $codes = [];

        for ($index = 0; $index + 1 < count($parts); $index += 2) {
            $first = $parts[$index];
            $second = $parts[$index + 1];

            if (! preg_match('/^[1-8]$/', $first) || ! preg_match('/^\d{2}$/', $second)) {
                continue;
            }

            $codes[] = $first.'.'.$second;
        }

        return array_values(array_unique($codes));
    }
}
