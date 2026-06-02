<?php

namespace App\Http\Controllers\Perencanaan;

use App\Http\Controllers\Controller;
use App\Http\Requests\Perencanaan\ReviewTargetRevisionRequest;
use App\Http\Requests\Perencanaan\StoreTargetRevisionRequest;
use App\Models\TargetRevision;
use App\Services\Perencanaan\TargetRevisionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TargetRevisionController extends Controller
{
    public function index(Request $request, TargetRevisionService $service): Response
    {
        abort_unless($service->canViewIndex($request->user()), 403);

        $filters = [
            'status' => $request->string('status')->toString(),
            'module' => $request->string('module')->toString(),
            'search' => $request->string('search')->toString(),
        ];

        $revisions = $service->visibleQuery($request->user())
            ->when($filters['status'], fn ($query) => $query->where('status', $filters['status']))
            ->when($filters['module'], fn ($query) => $query->where('module', $filters['module']))
            ->when($filters['search'], function ($query) use ($filters) {
                $search = '%'.$filters['search'].'%';
                $query->where(function ($query) use ($search) {
                    $query->where('reason', 'ilike', $search)
                        ->orWhere('document_number', 'ilike', $search)
                        ->orWhere('target_table', 'ilike', $search);
                });
            })
            ->latest()
            ->paginate(15)
            ->withQueryString()
            ->through(fn (TargetRevision $revision) => [
                'id' => $revision->id,
                'module' => $revision->module,
                'target_table' => $revision->target_table,
                'target_id' => $revision->target_id,
                'status' => $revision->status,
                'old_values' => $revision->old_values,
                'new_values' => $revision->new_values,
                'reason' => $revision->reason,
                'document_number' => $revision->document_number,
                'document_date' => $revision->document_date?->toDateString(),
                'review_note' => $revision->review_note,
                'requested_by' => $revision->requestedBy?->name,
                'reviewed_by' => $revision->reviewedBy?->name,
                'opd' => $revision->opd?->singkatan ?: $revision->opd?->nama,
                'created_at' => $revision->created_at?->toISOString(),
                'reviewed_at' => $revision->reviewed_at?->toISOString(),
                'applied_at' => $revision->applied_at?->toISOString(),
            ]);

        return Inertia::render('Perencanaan/TargetRevision/Index', [
            'revisions' => $revisions,
            'filters' => $filters,
            'can' => [
                'review' => $service->canReview($request->user()),
            ],
        ]);
    }

    public function store(StoreTargetRevisionRequest $request, TargetRevisionService $service): RedirectResponse
    {
        $service->submit($request->user(), $request->validated());

        return back()->with('success', 'Pengajuan revisi target berhasil dikirim untuk review.');
    }

    public function approve(ReviewTargetRevisionRequest $request, TargetRevision $targetRevision, TargetRevisionService $service): RedirectResponse
    {
        $data = $request->validated();
        $service->approve($request->user(), $targetRevision, $data['note'] ?? null);

        return back()->with('success', 'Revisi target disetujui dan nilai target telah diperbarui.');
    }

    public function reject(ReviewTargetRevisionRequest $request, TargetRevision $targetRevision, TargetRevisionService $service): RedirectResponse
    {
        $data = $request->validated();
        $note = trim((string) ($data['note'] ?? ''));

        if ($note === '') {
            return back()->withErrors(['note' => 'Catatan penolakan wajib diisi.']);
        }

        $service->reject($request->user(), $targetRevision, $note);

        return back()->with('success', 'Revisi target ditolak.');
    }
}
