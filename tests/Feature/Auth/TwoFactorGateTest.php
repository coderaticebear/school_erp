<?php

use App\Http\Middleware\EnsureTwoFactor;
use App\Models\Login;

/**
 * The two-factor gate (SEC-06, T3): signed-in users must set up two-factor, then pass the challenge, before any page.
 */
$roles = [
    'admin' => [Login::ROLE_ADMIN, '/admin/dashboard'],
    'teacher' => [Login::ROLE_TEACHER, '/teacher/dashboard'],
    'student' => [Login::ROLE_STUDENT, '/student/dashboard'],
    'parent' => [Login::ROLE_PARENT, '/parent/dashboard'],
];

beforeEach(function () {
    config(['two-factor.enabled' => true]);
});

test('a user without two-factor is sent to set-up', function (int $role) {
    actingAsRole($role);

    $this->get('/dashboard')->assertRedirect(route('two-factor.setup'));

    expect(session('url.intended'))->toBe(url('/dashboard'));
})->with($roles);

test('a user with two-factor and no marker is sent to the challenge', function (int $role) {
    actingAsRole($role, withTwoFactor: true);

    $this->get('/dashboard')->assertRedirect(route('two-factor.challenge'));

    expect(session(EnsureTwoFactor::SESSION_STARTED))->toBeInt();
})->with($roles);

test('a verified user opens pages normally', function (int $role, string $dashboard) {
    $login = actingAsRole($role, withTwoFactor: true);

    $this->withSession([EnsureTwoFactor::SESSION_VERIFIED => $login->id])
        ->get('/dashboard')
        ->assertRedirect($dashboard);
})->with($roles);

test('a marker for another login does not count', function () {
    $login = actingAsRole(Login::ROLE_ADMIN, withTwoFactor: true);

    $this->withSession([EnsureTwoFactor::SESSION_VERIFIED => $login->id + 1])
        ->get('/dashboard')
        ->assertRedirect(route('two-factor.challenge'));
});

test('the two-factor pages and logout stay open for a user who is not verified', function () {
    actingAsRole(Login::ROLE_TEACHER);

    $this->get(route('two-factor.setup'))->assertOk();

    $this->post(route('logout'))->assertRedirect('/');
    $this->assertGuest();

    actingAsRole(Login::ROLE_TEACHER, withTwoFactor: true);

    $this->get(route('two-factor.challenge'))->assertOk();

    $this->post(route('logout'))->assertRedirect('/');
    $this->assertGuest();
});

test('an inactive account gets the deactivated message, not a two-factor page', function () {
    $login = Login::factory()->admin()->inactive()->create();
    $this->actingAs($login);

    $this->get('/dashboard')
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors('email');
});

test('the gate does nothing when two-factor is not required', function () {
    config(['two-factor.enabled' => false]);
    actingAsRole(Login::ROLE_ADMIN);

    $this->get('/dashboard')->assertRedirect('/admin/dashboard');
});

test('production requires two-factor even when the switch is off', function () {
    config(['two-factor.enabled' => false]);
    $environment = app()->environment();
    app()->detectEnvironment(fn () => 'production');

    try {
        actingAsRole(Login::ROLE_ADMIN);

        $this->get('/dashboard')->assertRedirect(route('two-factor.setup'));
    } finally {
        app()->detectEnvironment(fn () => $environment);
    }
});

test('guests are not affected', function () {
    $this->get('/login')->assertOk();
    $this->get('/dashboard')->assertRedirect(route('login'));
});
