<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AuditLogController extends Controller
{
    public function __invoke(Request $request): Response
    {
        abort_unless($request->user()->hasPermission('activity_logs.view'), 403);

        $filters = $request->only(['search', 'action', 'model_type']);

        $logs = ActivityLog::query()
            ->with('user:id,name,email')
            ->when($filters['search'] ?? null, function (Builder $query, string $search) {
                $query->where(function (Builder $query) use ($search) {
                    $query->where('description', 'ilike', "%{$search}%")
                        ->orWhere('model_type', 'ilike', "%{$search}%")
                        ->orWhereHas('user', fn (Builder $query) => $query->where('name', 'ilike', "%{$search}%"));
                });
            })
            ->when($filters['action'] ?? null, fn (Builder $query, string $action) => $query->where('action', $action))
            ->when($filters['model_type'] ?? null, fn (Builder $query, string $modelType) => $query->where('model_type', $modelType))
            ->latest('id')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (ActivityLog $log) => [
                'id' => $log->id,
                'action' => $log->action,
                'model_type' => class_basename((string) $log->model_type),
                'model_type_full' => $log->model_type,
                'model_id' => $log->model_id,
                'description' => $log->description,
                'old_values' => $log->old_values,
                'new_values' => $log->new_values,
                'ip_address' => $log->ip_address,
                'created_at' => $log->created_at?->toDateTimeString(),
                'user' => $log->user,
            ]);

        return Inertia::render('AuditLog/Index', [
            'logs' => $logs,
            'filters' => $filters,
            'actions' => ActivityLog::query()->distinct()->orderBy('action')->pluck('action')->values(),
            'modelTypes' => ActivityLog::query()->whereNotNull('model_type')->distinct()->orderBy('model_type')->pluck('model_type')->values(),
        ]);
    }
}
