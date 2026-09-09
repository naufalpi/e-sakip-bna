<?php

namespace App\Services;

use App\Models\SystemAnnouncement;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;

class SystemAnnouncementService
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function visibleFor(User $user): array
    {
        $announcements = Cache::remember(
            SystemAnnouncement::CACHE_KEY,
            now()->addMinute(),
            fn (): array => $this->displayableAnnouncements(),
        );

        $now = now();
        $roleNames = $user->roles->pluck('name')->all();
        $opdId = $user->opd_id ? (int) $user->opd_id : null;

        return collect($announcements)
            ->filter(function (array $announcement) use ($now, $roleNames, $opdId): bool {
                $startsAt = filled($announcement['starts_at']) ? CarbonImmutable::parse($announcement['starts_at']) : null;
                $endsAt = filled($announcement['ends_at']) ? CarbonImmutable::parse($announcement['ends_at']) : null;

                if (($startsAt && $startsAt->isAfter($now)) || ($endsAt && $endsAt->isBefore($now))) {
                    return false;
                }

                return match ($announcement['audience']) {
                    'roles' => count(array_intersect($roleNames, $announcement['target_roles'])) > 0,
                    'opds' => $opdId !== null && in_array($opdId, $announcement['target_opd_ids'], true),
                    default => true,
                };
            })
            ->take(10)
            ->map(fn (array $announcement): array => collect($announcement)
                ->only(['id', 'title', 'message', 'type', 'link_label', 'link_url', 'is_dismissible'])
                ->all())
            ->values()
            ->all();
    }

    /** @return array<int, array<string, mixed>> */
    private function displayableAnnouncements(): array
    {
        return SystemAnnouncement::query()
            ->where('is_active', true)
            ->where(fn ($query) => $query->whereNull('starts_at')->orWhere('starts_at', '<=', now()->addMinute()))
            ->where(fn ($query) => $query->whereNull('ends_at')->orWhere('ends_at', '>=', now()->subMinute()))
            ->orderByRaw("CASE type WHEN 'important' THEN 1 WHEN 'warning' THEN 2 ELSE 3 END")
            ->orderByDesc('starts_at')
            ->orderByDesc('id')
            ->limit(200)
            ->get([
                'id',
                'title',
                'message',
                'type',
                'audience',
                'target_roles',
                'target_opd_ids',
                'link_label',
                'link_url',
                'starts_at',
                'ends_at',
                'is_dismissible',
            ])
            ->map(fn (SystemAnnouncement $announcement): array => [
                'id' => $announcement->id,
                'title' => $announcement->title,
                'message' => $announcement->message,
                'type' => $announcement->type,
                'audience' => $announcement->audience,
                'target_roles' => array_values($announcement->target_roles ?? []),
                'target_opd_ids' => array_map('intval', array_values($announcement->target_opd_ids ?? [])),
                'link_label' => $announcement->link_label,
                'link_url' => $announcement->link_url,
                'starts_at' => $announcement->starts_at?->toIso8601String(),
                'ends_at' => $announcement->ends_at?->toIso8601String(),
                'is_dismissible' => $announcement->is_dismissible,
            ])
            ->all();
    }
}
