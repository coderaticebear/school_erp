<?php

use App\Http\Middleware\EnsureTwoFactor;
use App\Models\Login;
use App\Models\Students;
use App\Models\Teachers;
use App\Services\TwoFactorAuth;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Password;

/**
 * Two-factor edge cases (SEC-06, T9): other ways in must not skip the code step, and account changes keep two-factor.
 */
beforeEach(function () {
    config(['two-factor.enabled' => true]);
});

/**
 * Act as an admin who passed the two-factor challenge and entered the password in the last few minutes.
 */
function actingAsVerifiedConfirmedAdmin(): Login
{
    $admin = actingAsRole(Login::ROLE_ADMIN, withTwoFactor: true);
    test()->withSession([EnsureTwoFactor::SESSION_VERIFIED => $admin->id, 'auth.password_confirmed_at' => time()]);

    return $admin;
}

/**
 * The Remember Me cookie value that Laravel's session guard reads: id, remember token and password hash.
 */
function rememberCookie(Login $login): array
{
    return [Auth::guard()->getRecallerName() => "{$login->id}|{$login->remember_token}|{$login->password}"];
}

test('a Remember Me cookie signs the user in but does not skip the challenge', function () {
    $login = Login::factory()->teacher()->withTwoFactor()->create(['remember_token' => 'remember-me-token']);

    $this->withCookies(rememberCookie($login))
        ->get('/teacher/dashboard')
        ->assertRedirect(route('two-factor.challenge'));

    $this->assertAuthenticatedAs($login);
    expect(Auth::viaRemember())->toBeTrue();
});

test('a Remember Me cookie for a user without two-factor goes to set-up', function () {
    $login = Login::factory()->teacher()->create(['remember_token' => 'remember-me-token']);

    $this->withCookies(rememberCookie($login))
        ->get('/teacher/dashboard')
        ->assertRedirect(route('two-factor.setup'));

    $this->assertAuthenticatedAs($login);
});

test('the sign-in after a password reset does not skip the challenge', function () {
    $login = Login::factory()->teacher()->withTwoFactor()->create();

    $this->post('/password/reset', [
        'token' => Password::createToken($login),
        'email' => $login->email,
        'password' => 'a-brand-new-pass',
        'password_confirmation' => 'a-brand-new-pass',
    ])->assertSessionHasNoErrors()->assertRedirect('/dashboard');

    $this->assertAuthenticatedAs($login);

    $this->get('/dashboard')->assertRedirect(route('two-factor.challenge'));
    $this->get('/teacher/dashboard')->assertRedirect(route('two-factor.challenge'));
});

test('the sign-in after a password reset sends a user without two-factor to set-up', function () {
    $login = Login::factory()->teacher()->create();

    $this->post('/password/reset', [
        'token' => Password::createToken($login),
        'email' => $login->email,
        'password' => 'a-brand-new-pass',
        'password_confirmation' => 'a-brand-new-pass',
    ])->assertSessionHasNoErrors();

    $this->get('/dashboard')->assertRedirect(route('two-factor.setup'));
});

test('deactivating and activating a student keeps two-factor on', function () {
    actingAsVerifiedConfirmedAdmin();
    $login = Login::factory()->student()->withTwoFactor()->create([
        'two_factor_recovery_codes' => ['hash-one', 'hash-two'],
    ]);
    $student = Students::factory()->create(['login_id' => $login->id]);
    $before = $login->fresh()->only(['two_factor_secret', 'two_factor_confirmed_at', 'two_factor_recovery_codes']);

    $this->post(route('admin.students.toggle-active', $student))->assertRedirect();
    expect($login->fresh()->is_active)->toBeFalse();

    $this->post(route('admin.students.toggle-active', $student))->assertRedirect();

    $after = $login->fresh();
    expect($after->is_active)->toBeTrue();
    expect($after->hasTwoFactorEnabled())->toBeTrue();
    expect($after->only(array_keys($before)))->toEqual($before);
});

test('deactivating and activating a teacher keeps two-factor on', function () {
    actingAsVerifiedConfirmedAdmin();
    $login = Login::factory()->teacher()->withTwoFactor()->create();
    $teacher = Teachers::factory()->create(['login_id' => $login->id]);
    $before = $login->fresh()->only(['two_factor_secret', 'two_factor_confirmed_at', 'two_factor_recovery_codes']);

    $this->post(route('admin.teachers.toggle-active', $teacher))->assertRedirect();
    expect($login->fresh()->is_active)->toBeFalse();

    $this->post(route('admin.teachers.toggle-active', $teacher))->assertRedirect();

    $after = $login->fresh();
    expect($after->is_active)->toBeTrue();
    expect($after->only(array_keys($before)))->toEqual($before);
});

test('create-admin prints one password, and the new admin must set up two-factor at the first sign-in', function () {
    Artisan::call('create-admin', ['email' => 'principal@school.test']);
    $output = Artisan::output();

    expect(substr_count($output, 'Password:'))->toBe(1);
    preg_match('/Password: (\S+)/', $output, $printed);

    $admin = Login::sole();
    expect($admin->hasTwoFactorEnabled())->toBeFalse();
    expect($output)->not->toContain('two_factor')->not->toContain('otpauth');

    $this->post('/login', ['email' => 'principal@school.test', 'password' => $printed[1]])->assertRedirect('/dashboard');
    $this->assertAuthenticatedAs($admin);

    $this->get('/dashboard')->assertRedirect(route('two-factor.setup'));
    $this->get('/admin/dashboard')->assertRedirect(route('two-factor.setup'));
});

test('in production, TWO_FACTOR_REQUIRED=false does not skip the challenge after the password', function () {
    config(['two-factor.enabled' => false]);
    $environment = app()->environment();
    app()->detectEnvironment(fn () => 'production');

    try {
        $login = Login::factory()->teacher()->withTwoFactor()->create();
        $this->withoutMiddleware(ValidateCsrfToken::class);

        $this->post('/login', ['email' => $login->email, 'password' => 'password'])->assertRedirect('/dashboard');

        $this->get('/teacher/dashboard')->assertRedirect(route('two-factor.challenge'));
    } finally {
        app()->detectEnvironment(fn () => $environment);
    }
});

test('enable() on a login that another request already enabled keeps the first recovery codes', function () {
    $twoFactor = app(TwoFactorAuth::class);
    $login = Login::factory()->teacher()->create();
    $twoFactor->pendingSecret($login);
    $staleCopy = Login::findOrFail($login->id);

    $firstCodes = $twoFactor->enable($login);
    $storedHashes = $login->fresh()->two_factor_recovery_codes;

    expect($firstCodes)->toHaveCount(8);
    expect($twoFactor->enable($staleCopy))->toBeNull();
    expect($login->fresh()->two_factor_recovery_codes)->toBe($storedHashes);
    expect($twoFactor->useRecoveryCode($login->fresh(), $firstCodes[0]))->toBeTrue();
});

test('a second set-up request after two-factor is on goes to the dashboard and makes no new codes', function () {
    $login = Login::factory()->teacher()->create();
    app(TwoFactorAuth::class)->pendingSecret($login);
    $this->actingAs($login);

    $this->post(route('two-factor.enable'), ['code' => twoFactorCode($login->two_factor_secret)])
        ->assertRedirect(route('two-factor.codes'));
    $storedHashes = $login->fresh()->two_factor_recovery_codes;

    $this->post(route('two-factor.enable'), ['code' => twoFactorCode($login->two_factor_secret, 1)])
        ->assertRedirect(route('dashboard'));

    expect($login->fresh()->two_factor_recovery_codes)->toBe($storedHashes);
});
