<?php

namespace App\Http\Controllers\Kinerja;

use App\Http\Controllers\Controller;
use App\Http\Requests\Kinerja\WorkflowTransitionRequest;
use App\Models\RenjaOpd;
use App\Models\Rkpd;
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

        $action = (string) $request->validated('action');

        $metadata = ['ip' => $request->ip()];
        if ($action === 'correct') {
            $metadata['correction_reference'] = $request->validated('correction_reference');
        }

        $workflowService->transition(
            $model,
            $module,
            $action,
            $request->user(),
            $request->validated('note'),
            $request->validated('current_reviewer_id'),
            $metadata
        );

        if ($action === 'correct') {
            return back()->with('success', 'Persetujuan dibatalkan untuk koreksi data. Dokumen kini berstatus Perlu Perbaikan.');
        }

        if ($model instanceof Rkpd && $action === 'approve') {
            $successMessage = $model->jenis_versi === 'perubahan'
                ? 'RKPD Perubahan berhasil disetujui dan menjadi versi aktif.'
                : 'RKPD berhasil disetujui dan versi ditetapkan telah dibuat.';
            $destination = Rkpd::query()
                ->where('root_version_id', $model->root_version_id ?: $model->id)
                ->where('is_active_version', true)
                ->orderByDesc('nomor_versi')
                ->first();

            if ($destination) {
                return redirect()->route('rkpd.show', $destination)
                    ->with('success', $successMessage);
            }
        }

        if ($model instanceof RenjaOpd && $action === 'approve') {
            $successMessage = $model->jenis_versi === 'perubahan'
                ? 'RENJA Perubahan berhasil disetujui dan menjadi versi aktif.'
                : 'RENJA berhasil disetujui dan versi ditetapkan telah dibuat.';
            $destination = RenjaOpd::query()
                ->where('root_version_id', $model->root_version_id ?: $model->id)
                ->where('is_active_version', true)
                ->orderByDesc('nomor_versi')
                ->first();

            if ($destination) {
                return redirect()->route('renja-opd.show', $destination)
                    ->with('success', $successMessage);
            }
        }

        return back()->with('success', 'Status pengajuan berhasil diperbarui.');
    }
}
