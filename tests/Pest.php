<?php

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

// Tests\TestCase applies RefreshDatabase itself, so its migration settings (run as the schema owner) take effect.
pest()->extend(Tests\TestCase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

/**
 * Create a login with the given role (plus its teacher/student/parent profile) and authenticate as it.
 * With $withTwoFactor, the login has finished two-factor set-up.
 */
function actingAsRole(int $role, bool $withTwoFactor = false): App\Models\Login
{
    $factory = App\Models\Login::factory();

    if ($withTwoFactor) {
        $factory = $factory->withTwoFactor();
    }

    $login = $factory->create(['role' => $role]);

    match ($role) {
        App\Models\Login::ROLE_TEACHER => App\Models\Teachers::factory()->create(['login_id' => $login->id]),
        App\Models\Login::ROLE_STUDENT => App\Models\Students::factory()->create(['login_id' => $login->id]),
        App\Models\Login::ROLE_PARENT => App\Models\Parents::factory()->create(['login_id' => $login->id]),
        default => null,
    };

    test()->actingAs($login);

    return $login;
}

/**
 * The secret that LoginFactory::withTwoFactor() uses by default.
 */
const TWO_FACTOR_TEST_SECRET = 'JBSWY3DPEHPK3PXPJBSWY3DPEHPK3PXP';

/**
 * The code an authenticator app shows for a secret, $steps 30-second steps from now.
 */
function twoFactorCode(string $secret = TWO_FACTOR_TEST_SECRET, int $steps = 0): string
{
    $google2fa = new PragmaRX\Google2FA\Google2FA;

    return $google2fa->oathTotp($secret, $google2fa->getTimestamp() + $steps);
}

/**
 * Record that the signed-in user entered the password $secondsAgo seconds ago (the password.confirm page).
 */
function confirmPassword(int $secondsAgo = 0): void
{
    test()->withSession(['auth.password_confirmed_at' => time() - $secondsAgo]);
}

/**
 * Act as an admin who entered the password in the last few minutes.
 */
function actingAsConfirmedAdmin(): App\Models\Login
{
    $admin = actingAsRole(App\Models\Login::ROLE_ADMIN);
    confirmPassword();

    return $admin;
}

/**
 * Fail when any submitted password reached the session, where failed forms keep their input.
 *
 * @param  list<string>  $passwords
 */
function expectSessionWithoutPasswords(array $passwords): void
{
    $session = serialize(session()->all());

    foreach ($passwords as $password) {
        expect($session)->not->toContain($password);
    }

    expect(session()->getOldInput())
        ->not->toHaveKey('password')
        ->not->toHaveKey('parent_password')
        ->not->toHaveKey('current_password');
}
