<?php

namespace App\Providers;

use App\Models\Divisions;
use App\Models\Login;
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
        // Admins can manage any division; teachers only the divisions they are assigned to.
        Gate::define('teach-division', function (Login $user, Divisions $division): bool {
            if ($user->role === Login::ROLE_ADMIN) {
                return true;
            }

            return $user->role === Login::ROLE_TEACHER
                && $user->teacher !== null
                && $user->teacher->divisions()->whereKey($division->id)->exists();
        });
    }
}
