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
        'renstra_opd_id',
        'periode_tahun_id',
        'tahun',
        'judul',
        'nomor_dokumen',
        'status',
        'catatan',
        'submitted_by',
        'submitted_at',
    ];

    protected function casts(): array
    {
        return [
            'tahun' => 'integer',
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

    public function workflowSubmission(): HasMany
    {
        return $this->hasMany(WorkflowSubmission::class, 'related_id')->where('related_table', $this->getTable());
    }
}
