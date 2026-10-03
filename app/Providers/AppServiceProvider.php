<?php

namespace App\Providers;

use App\Models\Divisions;
use App\Models\Login;
use App\Models\Students;
use App\Models\Subjects;
use Illuminate\Contracts\Validation\UncompromisedVerifier;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\NotPwnedVerifier;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Security SEC-14: the password breach check (Have I Been Pwned) gives up after 5 seconds instead of 30,
        // so a slow service can't hang a Save button. Laravel then accepts the password and reports the error.
        $this->app->extend(UncompromisedVerifier::class, fn () => new NotPwnedVerifier($this->app->make(HttpFactory::class), 5));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Surface N+1 queries during development and tests.
        Model::preventLazyLoading(! $this->app->isProduction());

        // Security SEC-14: one rule wherever a password is set, including the reset form: at least 12 characters,
        // and not found in a known data breach. Only the first 5 characters of the password's SHA-1 hash are sent.
        Password::defaults(fn () => Password::min(12)->uncompromised());

        // Admins can manage any division; teachers only the divisions they are assigned to.
        Gate::define('teach-division', function (Login $user, Divisions $division): bool {
            if ($user->role === Login::ROLE_ADMIN) {
                return true;
            }

            return $user->role === Login::ROLE_TEACHER
                && $user->teacher !== null
                && $user->teacher->divisions()->whereKey($division->id)->exists();
        });

        // A student's own records: the student, their parent, or an admin.
        Gate::define('view-student', function (Login $user, Students $student): bool {
            return match ($user->role) {
                Login::ROLE_ADMIN => true,
                Login::ROLE_STUDENT => $user->student?->id === $student->id,
                Login::ROLE_PARENT => $user->parent !== null && $user->parent->id === $student->parent_id,
                default => false,
            };
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
