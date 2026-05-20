<?php

namespace App\Providers;

use App\Models\Opd;
use App\Models\PerjanjianKinerja;
use App\Models\RealisasiKinerja;
use App\Models\RencanaAksi;
use App\Models\RenstraOpd;
use App\Models\Role;
use App\Models\Rpjmd;
use App\Models\User;
use App\Policies\OpdPolicy;
use App\Policies\PerjanjianKinerjaPolicy;
use App\Policies\RealisasiKinerjaPolicy;
use App\Policies\RencanaAksiPolicy;
use App\Policies\RenstraOpdPolicy;
use App\Policies\RolePolicy;
use App\Policies\RpjmdPolicy;
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
        Gate::policy(PerjanjianKinerja::class, PerjanjianKinerjaPolicy::class);
        Gate::policy(RenstraOpd::class, RenstraOpdPolicy::class);
        Gate::policy(RencanaAksi::class, RencanaAksiPolicy::class);
        Gate::policy(RealisasiKinerja::class, RealisasiKinerjaPolicy::class);
        Gate::policy(Role::class, RolePolicy::class);
        Gate::policy(Rpjmd::class, RpjmdPolicy::class);
        Gate::policy(User::class, UserPolicy::class);

        Gate::before(function (User $user) {
            return $user->hasRole('super_admin') ? true : null;
        });
    }
}
