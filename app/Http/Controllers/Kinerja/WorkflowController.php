<?php

namespace App\Http\Controllers\Kinerja;

use App\Http\Controllers\Controller;
use App\Http\Requests\Kinerja\WorkflowTransitionRequest;
use App\Models\PerjanjianKinerja;
use App\Models\RealisasiKinerja;
use App\Models\RencanaAksi;
use App\Services\Kinerja\WorkflowService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;

class WorkflowController extends Controller
{
    public function transition(WorkflowTransitionRequest $request, string $module, int $id, WorkflowService $workflowService): RedirectResponse
    {
        $modelClass = $this->modelForModule($module);
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

    /**
     * @return class-string<Model>
     */
    private function modelForModule(string $module): string
    {
        return match ($module) {
            'perjanjian_kinerja' => PerjanjianKinerja::class,
            'rencana_aksi' => RencanaAksi::class,
            'realisasi_kinerja' => RealisasiKinerja::class,
            default => abort(404),
        };
    }
}
