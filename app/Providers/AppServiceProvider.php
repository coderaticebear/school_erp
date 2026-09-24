<?php

namespace App\Providers;

use App\Models\Divisions;
use App\Models\Login;
use App\Models\Subjects;
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

        // Marks for a subject in a division: admins, or a teacher assigned to the division who teaches the subject.
        Gate::define('enter-marks', function (Login $user, Divisions $division, Subjects $subject): bool {
            if ($user->role === Login::ROLE_ADMIN) {
                return true;
            }

            return Gate::forUser($user)->allows('teach-division', $division)
                && $user->teacher->subjects()->whereKey($subject->id)->exists();
        });
    }
}
