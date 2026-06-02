<?php

namespace App\Services\Notifications;

use App\Models\Notification;
use App\Models\User;
use App\Notifications\SakipMailNotification;
use Illuminate\Support\Collection;

class SakipNotificationService
{
    /**
     * @param  Collection<int, User>|array<int, User>|User  $users
     * @param  array<string, mixed>  $data
     */
    public function notify(Collection|array|User $users, string $type, string $title, string $message, array $data = [], ?string $url = null): int
    {
        $recipients = $users instanceof User ? collect([$users]) : collect($users);
        $created = 0;

        foreach ($recipients->filter(fn (User $user) => $user->status === 'active')->unique('id') as $user) {
            $dedupeKey = $data['dedupe_key'] ?? null;

            if ($dedupeKey && Notification::query()
                ->where('user_id', $user->id)
                ->where('type', $type)
                ->where('data->dedupe_key', $dedupeKey)
                ->exists()) {
                continue;
            }

            Notification::create([
                'user_id' => $user->id,
                'type' => $type,
                'title' => $title,
                'message' => $message,
                'data' => [
                    ...$data,
                    'url' => $url,
                    'email_enabled' => (bool) config('sakip.notifications.email_enabled', false),
                ],
            ]);

            $created++;

            if (config('sakip.notifications.email_enabled', false) && filled($user->email)) {
                $user->notify(new SakipMailNotification($title, $message, $url));
            }
        }

        return $created;
    }
}
