<?php

/**
 * Security SEC-07: the demo seeders create accounts whose password is "password", so they must never
 * run in production, however they are started.
 */

use App\Models\Login;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoActivitySeeder;
use Database\Seeders\LoginSeeder;

test('a demo seeder refuses to run in production and creates nothing', function (string $seeder) {
    $this->app->detectEnvironment(fn () => 'production');

    // --force is what a deploy script passes; it skips Laravel's own "are you sure?" question.
    expect(fn () => $this->artisan('db:seed', ['--class' => $seeder, '--force' => true])->run())
        ->toThrow(RuntimeException::class, 'never runs in production');

    expect(Login::count())->toBe(0);
})->with([
    'the main seeder' => DatabaseSeeder::class,
    'the demo logins' => LoginSeeder::class,
    'the demo activity' => DemoActivitySeeder::class,
]);

test('outside production the demo accounts are still created', function () {
    $this->seed(LoginSeeder::class);

    expect(Login::where('email', 'admin@example.com')->where('role', Login::ROLE_ADMIN)->exists())->toBeTrue();
});
