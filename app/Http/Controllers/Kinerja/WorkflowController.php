<?php

namespace App\Http\Controllers\Kinerja;

use App\Http\Controllers\Controller;
use App\Http\Requests\Kinerja\WorkflowTransitionRequest;
use App\Services\Kinerja\WorkflowService;
use App\Services\Workflow\WorkflowModuleRegistry;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;

class WorkflowController extends Controller
{
    public function transition(WorkflowTransitionRequest $request, string $module, int $id, WorkflowService $workflowService, WorkflowModuleRegistry $registry): RedirectResponse
    {
        $modelClass = $registry->modelClass($module);
        /** @var Model $model */
        $model = $modelClass::query()->findOrFail($id);

        $workflowService->transition(
            $model,
            $module,
            (string) $request->validated('action'),
            $request->user(),
            $request->validated('note'),
            $request->validated('current_reviewer_id'),
            ['ip' => $request->ip()]
        );

        return back()->with('success', 'Status workflow berhasil diperbarui.');
    }
}
