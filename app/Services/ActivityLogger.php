<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Model;

class ActivityLogger
{
    public function __construct(private readonly ActivityLogService $activityLogService) {}

    public function log(string $action, ?Model $model = null, ?array $oldValues = null, ?array $newValues = null, ?string $description = null): void
    {
        $this->activityLogService->log($action, $model, $oldValues, $newValues, $description);
    }
}
