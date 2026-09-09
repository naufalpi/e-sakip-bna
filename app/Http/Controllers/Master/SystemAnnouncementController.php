<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Http\Requests\Master\SystemAnnouncementRequest;
use App\Models\Opd;
use App\Models\Role;
use App\Models\SystemAnnouncement;
use App\Support\Pagination\PerPagePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SystemAnnouncementController extends Controller
{
    public function index(Request $request): Response
    {
        $this->ensureSuperAdmin($request);

        $filters = [
            ...$request->only(['search', 'status', 'type', 'audience']),
            'per_page' => PerPagePaginator::selection($request),
        ];

        $query = SystemAnnouncement::query()
            ->when($filters['search'] ?? null, function (Builder $query, string $search): void {
                $needle = '%'.mb_strtolower(trim($search)).'%';

                $query->where(function (Builder $query) use ($needle): void {
                    $query->whereRaw('LOWER(title) LIKE ?', [$needle])
                        ->orWhereRaw('LOWER(message) LIKE ?', [$needle]);
                });
            })
            ->when($filters['type'] ?? null, fn (Builder $query, string $type) => $query->where('type', $type))
            ->when($filters['audience'] ?? null, fn (Builder $query, string $audience) => $query->where('audience', $audience));

        $this->applyStatusFilter($query, $filters['status'] ?? null);

        $items = PerPagePaginator::paginate(
            $query
                ->orderByDesc('is_active')
                ->orderByRaw("CASE type WHEN 'important' THEN 1 WHEN 'warning' THEN 2 ELSE 3 END")
                ->orderByDesc('starts_at')
                ->orderByDesc('id'),
            $request,
        )->through(fn (SystemAnnouncement $announcement): array => $this->serialize($announcement));

        return Inertia::render('Master/SystemAnnouncement/Index', [
            'items' => $items,
            'filters' => $filters,
            'summary' => $this->summary(),
            'roleOptions' => Role::query()->orderBy('label')->get(['name', 'label']),
            'opdOptions' => Opd::query()
                ->where('status', 'active')
                ->orderBy('nama')
                ->get(['id', 'kode', 'nama', 'singkatan']),
        ]);
    }

    public function store(SystemAnnouncementRequest $request): RedirectResponse
    {
        SystemAnnouncement::create($request->announcementData());

        return back()->with('success', 'Pengumuman berhasil ditambahkan.');
    }

    public function update(SystemAnnouncementRequest $request, SystemAnnouncement $systemAnnouncement): RedirectResponse
    {
        $systemAnnouncement->update($request->announcementData());

        return back()->with('success', 'Pengumuman berhasil diperbarui.');
    }

    public function toggle(Request $request, SystemAnnouncement $systemAnnouncement): RedirectResponse
    {
        $this->ensureSuperAdmin($request);

        $systemAnnouncement->update(['is_active' => ! $systemAnnouncement->is_active]);

        return back()->with('success', $systemAnnouncement->is_active ? 'Pengumuman diaktifkan.' : 'Pengumuman dinonaktifkan.');
    }

    public function destroy(Request $request, SystemAnnouncement $systemAnnouncement): RedirectResponse
    {
        $this->ensureSuperAdmin($request);

        $systemAnnouncement->delete();

        return back()->with('success', 'Pengumuman berhasil dihapus.');
    }

    private function ensureSuperAdmin(Request $request): void
    {
        abort_unless($request->user()?->isSuperAdmin(), 403);
    }

    private function applyStatusFilter(Builder $query, ?string $status): void
    {
        $now = now();

        match ($status) {
            'active' => $query->runningAt($now),
            'scheduled' => $query->where('is_active', true)->where('starts_at', '>', $now),
            'ended' => $query->where('is_active', true)->whereNotNull('ends_at')->where('ends_at', '<', $now),
            'inactive' => $query->where('is_active', false),
            default => null,
        };
    }

    /** @return array<string, int> */
    private function summary(): array
    {
        $now = now();

        return [
            'active' => SystemAnnouncement::query()->runningAt($now)->count(),
            'scheduled' => SystemAnnouncement::query()->where('is_active', true)->where('starts_at', '>', $now)->count(),
            'ended' => SystemAnnouncement::query()->where('is_active', true)->whereNotNull('ends_at')->where('ends_at', '<', $now)->count(),
            'inactive' => SystemAnnouncement::query()->where('is_active', false)->count(),
        ];
    }

    /** @return array<string, mixed> */
    private function serialize(SystemAnnouncement $announcement): array
    {
        return [
            'id' => $announcement->id,
            'title' => $announcement->title,
            'message' => $announcement->message,
            'type' => $announcement->type,
            'audience' => $announcement->audience,
            'target_roles' => array_values($announcement->target_roles ?? []),
            'target_opd_ids' => array_map('intval', array_values($announcement->target_opd_ids ?? [])),
            'link_label' => $announcement->link_label,
            'link_url' => $announcement->link_url,
            'starts_at' => $announcement->starts_at?->format('Y-m-d\TH:i'),
            'ends_at' => $announcement->ends_at?->format('Y-m-d\TH:i'),
            'is_active' => $announcement->is_active,
            'is_dismissible' => $announcement->is_dismissible,
            'display_status' => $announcement->displayStatus(),
            'created_at' => $announcement->created_at?->toIso8601String(),
        ];
    }
}
