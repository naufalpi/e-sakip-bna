<?php

namespace App\Http\Controllers\Kinerja;

use App\Http\Controllers\Controller;
use App\Services\Workflow\WorkflowInboxService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WorkflowInboxController extends Controller
{
    public function __invoke(Request $request, WorkflowInboxService $workflowInboxService): Response
    {
        abort_unless($workflowInboxService->canAccess($request->user()), 403);

        return Inertia::render('Workflow/Inbox', $workflowInboxService->payload($request->user(), $request->only([
            'search',
            'module',
            'status',
            'scope',
        ])));
    }
}
