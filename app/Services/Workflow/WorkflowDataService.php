<?php

namespace App\Services\Workflow;

use App\Models\WorkflowSubmission;
use Illuminate\Database\Eloquent\Model;

class WorkflowDataService
{
    public function forModel(Model $model, string $module): ?array
    {
        $workflow = WorkflowSubmission::query()
            ->with([
                'histories' => fn ($query) => $query->with('actor:id,name')->oldest(),
                'submittedBy:id,name',
                'currentReviewer:id,name',
            ])
            ->where('related_table', $model->getTable())
            ->where('related_id', $model->getKey())
            ->where('module', $module)
            ->first();

        return $workflow?->toArray();
    }
}
