<?php

use App\Models\Login;
use App\Services\TwoFactorAuth;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

/**
 * The two-factor:reset command (SEC-06, T7): resets any account by email, also an admin, and prints no secret.
 */
function loginWithTwoFactorAndSession(int $role, string $email): array
{
    $login = Login::factory()->withTwoFactor()->create([
        'role' => $role,
        'email' => $email,
        'remember_token' => 'old-remember-token',
    ]);
    $codes = app(TwoFactorAuth::class)->generateRecoveryCodes($login);

    DB::table('sessions')->insert([
        'id' => 'session-'.$login->id,
        'user_id' => $login->id,
        'ip_address' => '127.0.0.1',
        'user_agent' => 'Pest',
        'payload' => 'payload',
        'last_activity' => now()->timestamp,
    ]);

    return [$login, $codes];
}

test('two-factor:reset resets an account by email in any letter case, admins too, and prints no secret', function (int $role) {
    [$login, $codes] = loginWithTwoFactorAndSession($role, 'Lost.Phone@school.test');

    $exitCode = Artisan::call('two-factor:reset', ['email' => 'lost.phone@SCHOOL.test']);
    $output = Artisan::output();

    expect($exitCode)->toBe(0)
        ->and($output)->toContain('Two-factor sign-in reset for Lost.Phone@school.test.')
        ->not->toContain(TWO_FACTOR_TEST_SECRET);

    foreach ($codes as $code) {
        expect($output)->not->toContain($code);
    }

    $login->refresh();
    expect($login->two_factor_secret)->toBeNull()
        ->and($login->two_factor_confirmed_at)->toBeNull()
        ->and($login->two_factor_recovery_codes)->toBeNull()
        ->and($login->two_factor_last_timestep)->toBeNull()
        ->and($login->remember_token)->not->toBe('old-remember-token')
        ->and(DB::table('sessions')->where('user_id', $login->id)->count())->toBe(0);
})->with([
    'admin' => [Login::ROLE_ADMIN],
    'teacher' => [Login::ROLE_TEACHER],
    'student' => [Login::ROLE_STUDENT],
    'parent' => [Login::ROLE_PARENT],
]);

test('two-factor:reset fails for an unknown email and changes nothing', function () {
    [$login] = loginWithTwoFactorAndSession(Login::ROLE_ADMIN, 'principal@school.test');

    $this->artisan('two-factor:reset', ['email' => 'nobody@school.test'])
        ->expectsOutputToContain('No account has the email nobody@school.test.')
        ->assertFailed();

    expect($login->fresh()->hasTwoFactorEnabled())->toBeTrue()
        ->and(DB::table('sessions')->where('user_id', $login->id)->count())->toBe(1);
});

test('two-factor:reset asks for the email when none is given', function () {
    [$login] = loginWithTwoFactorAndSession(Login::ROLE_TEACHER, 'teacher@school.test');

    $this->artisan('two-factor:reset')
        ->expectsQuestion('Email address of the account', 'teacher@school.test')
        ->assertSuccessful();

    expect($login->fresh()->hasTwoFactorEnabled())->toBeFalse();
});
