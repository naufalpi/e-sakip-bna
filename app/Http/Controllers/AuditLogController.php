<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Support\Audit\ActivityLogPresenter;
use App\Support\Pagination\PerPagePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AuditLogController extends Controller
{
    public function index(Request $request, ActivityLogPresenter $presenter): Response
    {
        abort_unless($request->user()->hasPermission('activity_logs.view'), 403);

        $filters = $request->only(['search', 'action', 'model_type', 'per_page']);
        $filters['per_page'] = PerPagePaginator::selection($request);

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
            ->pipe(fn (Builder $query) => PerPagePaginator::paginate($query, $request))
            ->through(fn (ActivityLog $log) => $presenter->present($log));

        $actions = ActivityLog::query()->distinct()->orderBy('action')->pluck('action')
            ->map(fn (string $action): array => ['value' => $action, 'label' => $presenter->actionLabel($action)])
            ->values();
        $modelTypes = ActivityLog::query()->whereNotNull('model_type')->distinct()->orderBy('model_type')->pluck('model_type')
            ->map(fn (string $modelType): array => ['value' => $modelType, 'label' => $presenter->modelLabel($modelType)])
            ->values();

        return Inertia::render('AuditLog/Index', [
            'logs' => $logs,
            'filters' => $filters,
            'actions' => $actions,
            'modelTypes' => $modelTypes,
            'stats' => [
                'total' => ActivityLog::query()->count(),
                'today' => ActivityLog::query()->whereDate('created_at', today())->count(),
                'users' => ActivityLog::query()->whereNotNull('user_id')->distinct()->count('user_id'),
            ],
            'canClear' => $request->user()->isSuperAdmin(),
        ]);
    }

    public function destroyAll(Request $request): RedirectResponse
    {
        abort_unless($request->user()->isSuperAdmin(), 403);

        $deleted = ActivityLog::query()->delete();

        return redirect()->route('audit-log.index')
            ->with('success', number_format($deleted, 0, ',', '.').' audit log berhasil dihapus.');
    }
}
