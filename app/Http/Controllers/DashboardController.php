<?php

namespace App\Http\Controllers;

use App\Models\Opd;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $user = $request->user();

        $opdQuery = Opd::query();

        if ($user->hasRole('admin_opd')) {
            $opdQuery->whereKey($user->opd_id);
        }

        return Inertia::render('Dashboard', [
            'stats' => [
                'opd_count' => $opdQuery->count(),
                'user_count' => $user->hasPermission('users.view') ? User::count() : null,
                'role_count' => $user->hasPermission('roles.view') ? Role::count() : null,
                'permission_count' => $user->hasPermission('roles.view') ? Permission::count() : null,
            ],
        ]);
    }
}
