<?php

namespace App\Models\Concerns;

use App\Services\ActivityLogger;
use App\Services\Dashboard\DashboardCacheService;
use Illuminate\Database\Eloquent\Model;

trait LogsActivity
{
    protected static array $activityHidden = [
        'created_at',
        'updated_at',
        'deleted_at',
        'password',
        'remember_token',
    ];

    public static function bootLogsActivity(): void
    {
        static::created(function (Model $model) {
            app(ActivityLogger::class)->log(
                action: 'created',
                model: $model,
                oldValues: null,
                newValues: static::activityValues($model->getAttributes()),
            );

            static::invalidateDashboardCache($model);
        });

        static::updated(function (Model $model) {
            app(ActivityLogger::class)->log(
                action: 'updated',
                model: $model,
                oldValues: static::activityValues($model->getOriginal()),
                newValues: static::activityValues($model->getChanges()),
            );

            static::invalidateDashboardCache($model);
        });

        static::deleted(function (Model $model) {
            app(ActivityLogger::class)->log(
                action: 'deleted',
                model: $model,
                oldValues: static::activityValues($model->getOriginal()),
                newValues: null,
            );

            static::invalidateDashboardCache($model);
        });
    }

    protected static function activityValues(array $values): array
    {
        return collect($values)
            ->except(static::$activityHidden)
            ->all();
    }

    protected static function invalidateDashboardCache(Model $model): void
    {
        app(DashboardCacheService::class)->invalidateForModel($model);
    }
}
