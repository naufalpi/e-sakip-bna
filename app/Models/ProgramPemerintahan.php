<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProgramPemerintahan extends Model
{
    use LogsActivity;
    use SoftDeletes;

    protected $table = 'program_pemerintahan';

    protected $fillable = [
        'bidang_urusan_id',
        'tahun_awal',
        'tahun_akhir',
        'kode',
        'nama',
        'status',
    ];

    protected $casts = [
        'tahun_awal' => 'integer',
        'tahun_akhir' => 'integer',
    ];

    public function bidangUrusan(): BelongsTo
    {
        return $this->belongsTo(BidangUrusan::class);
    }

    public function kegiatan(): HasMany
    {
        return $this->hasMany(KegiatanPemerintahan::class)->orderBy('kode');
    }

    public function programRpjmd(): HasMany
    {
        return $this->hasMany(ProgramRpjmd::class);
    }

    public function programRpjmdReferences(): BelongsToMany
    {
        return $this->belongsToMany(ProgramRpjmd::class, 'program_rpjmd_program_pemerintahan')
            ->withTimestamps();
    }
}
