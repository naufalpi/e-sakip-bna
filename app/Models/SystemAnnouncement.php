<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Cache;

class SystemAnnouncement extends Model
{
    use LogsActivity;
    use SoftDeletes;

    public const CACHE_KEY = 'system-announcements:displayable:v1';

    /** @var array<int, string> */
    public const TYPES = ['info', 'warning', 'important'];

    /** @var array<int, string> */
    public const AUDIENCES = ['all', 'roles', 'opds'];

    protected $fillable = [
        'title',
        'message',
        'type',
        'audience',
        'target_roles',
        'target_opd_ids',
        'link_label',
        'link_url',
        'starts_at',
        'ends_at',
        'is_active',
        'is_dismissible',
    ];

    protected function casts(): array
    {
        return [
            'target_roles' => 'array',
            'target_opd_ids' => 'array',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'is_active' => 'boolean',
            'is_dismissible' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        $clearCache = static function (): void {
            Cache::forget(self::CACHE_KEY);
        };

        static::saved($clearCache);
        static::deleted($clearCache);
        static::restored($clearCache);
    }

    public function scopeRunningAt(Builder $query, mixed $date = null): Builder
    {
        $date ??= now();

        return $query
            ->where('is_active', true)
            ->where(fn (Builder $query) => $query->whereNull('starts_at')->orWhere('starts_at', '<=', $date))
            ->where(fn (Builder $query) => $query->whereNull('ends_at')->orWhere('ends_at', '>=', $date));
    }

    public function displayStatus(): string
    {
        if (! $this->is_active) {
            return 'inactive';
        }

        if ($this->starts_at?->isFuture()) {
            return 'scheduled';
        }

        if ($this->ends_at?->isPast()) {
            return 'ended';
        }

        return 'active';
    }
}
