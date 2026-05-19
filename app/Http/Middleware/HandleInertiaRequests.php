<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Inspiring;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        [$message, $author] = str(Inspiring::quotes()->random())->explode('-');
        $user = $request->user();

        if ($user) {
            $user->loadMissing(['opd:id,nama,singkatan', 'roles.permissions']);
        }

        return array_merge(parent::share($request), [
            'name' => config('app.name'),
            'quote' => ['message' => trim($message), 'author' => trim($author)],
            'auth' => [
                'user' => $user ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'email_verified_at' => $user->email_verified_at?->toISOString(),
                    'created_at' => $user->created_at?->toISOString(),
                    'updated_at' => $user->updated_at?->toISOString(),
                    'status' => $user->status,
                    'opd_id' => $user->opd_id,
                    'opd' => $user->opd ? [
                        'id' => $user->opd->id,
                        'nama' => $user->opd->nama,
                        'singkatan' => $user->opd->singkatan,
                    ] : null,
                    'roles' => $user->roles->map(fn ($role) => [
                        'id' => $role->id,
                        'name' => $role->name,
                        'label' => $role->label,
                    ])->values(),
                    'permissions' => $user->roles
                        ->flatMap(fn ($role) => $role->permissions)
                        ->pluck('name')
                        ->unique()
                        ->values(),
                ] : null,
            ],
        ]);
    }
}
