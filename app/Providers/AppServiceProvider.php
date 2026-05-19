<?php

namespace App\Providers;

use App\Models\Opd;
use App\Models\Role;
use App\Models\User;
use App\Policies\OpdPolicy;
use App\Policies\RolePolicy;
use App\Policies\UserPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(Opd::class, OpdPolicy::class);
        Gate::policy(Role::class, RolePolicy::class);
        Gate::policy(User::class, UserPolicy::class);

        Gate::before(function (User $user) {
            return $user->hasRole('super_admin') ? true : null;
        });
    }
}
