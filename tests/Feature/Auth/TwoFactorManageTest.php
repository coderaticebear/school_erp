<?php

use App\Http\Middleware\EnsureTwoFactor;
use App\Models\Login;
use App\Services\TwoFactorAuth;

/**
 * The Security page (SEC-06, T6): a user sees the two-factor status, makes new recovery codes after the password,
 * and every dashboard shows a notice when fewer than 3 recovery codes are left.
 */
$dashboards = [
    'admin' => [Login::ROLE_ADMIN, '/admin/dashboard'],
    'teacher' => [Login::ROLE_TEACHER, '/teacher/dashboard'],
    'student' => [Login::ROLE_STUDENT, '/student/dashboard'],
    'parent' => [Login::ROLE_PARENT, '/parent/dashboard'],
];

const LOW_CODES_NOTICE = 'Make new codes on the Security page.';

beforeEach(function () {
    config(['two-factor.enabled' => true]);
});

/**
 * Sign in as a user of the role who has two-factor on and passed the challenge in this session.
 */
function verifiedAsRole(int $role): Login
{
    $login = actingAsRole($role, withTwoFactor: true);
    test()->withSession([EnsureTwoFactor::SESSION_VERIFIED => $login->id]);

    return $login;
}

/**
 * Give the login a new set of recovery codes and use all but $left of them.
 *
 * @return list<string> the codes that are still unused
 */
function leaveRecoveryCodes(Login $login, int $left): array
{
    $twoFactor = app(TwoFactorAuth::class);
    $codes = $twoFactor->generateRecoveryCodes($login);

    foreach (array_slice($codes, 0, count($codes) - $left) as $code) {
        $twoFactor->useRecoveryCode($login, $code);
    }

    return array_slice($codes, count($codes) - $left);
}

test('the security page shows that two-factor is on and how many codes are left', function () {
    $login = verifiedAsRole(Login::ROLE_TEACHER);
    leaveRecoveryCodes($login, 5);

    $html = $this->get(route('security.index'))
        ->assertOk()
        ->assertSee('Two-factor sign-in is on.')
        ->assertSee('You have 5 unused recovery codes.')
        ->assertSee('Make New Recovery Codes')
        ->assertSee('data-confirm-title="Make new recovery codes?"', false)
        ->getContent();

    expect(substr_count($html, '<h1'))->toBe(1)
        ->and($html)->toContain('action="'.route('security.recovery-codes').'"');
});

test('the security page links to set-up when two-factor is off', function () {
    config(['two-factor.enabled' => false]);
    actingAsRole(Login::ROLE_PARENT);

    $this->get(route('security.index'))
        ->assertOk()
        ->assertSee('Two-factor sign-in is off.')
        ->assertSee('href="'.route('two-factor.setup').'"', false)
        ->assertDontSee('Make New Recovery Codes');
});

test('the security page needs the two-factor challenge first', function () {
    actingAsRole(Login::ROLE_STUDENT, withTwoFactor: true);

    $this->get(route('security.index'))->assertRedirect(route('two-factor.challenge'));
    $this->post(route('security.recovery-codes'))->assertRedirect(route('two-factor.challenge'));
});

test('a guest is sent to the login page from the security page', function () {
    $this->get(route('security.index'))->assertRedirect(route('login'));
    $this->post(route('security.recovery-codes'))->assertRedirect(route('login'));
});

test('new codes without a recent password go to the password page and keep the old codes', function () {
    $login = verifiedAsRole(Login::ROLE_ADMIN);
    $oldCodes = leaveRecoveryCodes($login, 8);

    $this->from(route('security.index'))
        ->post(route('security.recovery-codes'))
        ->assertRedirect(route('password.confirm'));

    expect(app(TwoFactorAuth::class)->useRecoveryCode($login->fresh(), $oldCodes[0]))->toBeTrue();
});

test('new codes after the password replace the old codes and show once', function () {
    $login = verifiedAsRole(Login::ROLE_STUDENT);
    $oldCodes = leaveRecoveryCodes($login, 2);

    $this->withSession(['auth.password_confirmed_at' => time()])
        ->post(route('security.recovery-codes'))
        ->assertRedirect(route('security.index'));

    $newCodes = session(App\Http\Controllers\TwoFactorController::FLASH_RECOVERY_CODES);
    expect($newCodes)->toBeArray()->toHaveCount(8);

    $html = $this->get(route('security.index'))->assertOk()->getContent();
    foreach ($newCodes as $code) {
        expect($html)->toContain($code);
    }
    expect($html)->toContain('This is the only time you will see these codes.');

    $this->get(route('security.index'))->assertOk()->assertDontSee($newCodes[0]);

    $twoFactor = app(TwoFactorAuth::class);
    expect($twoFactor->useRecoveryCode($login->fresh(), $oldCodes[0]))->toBeFalse()
        ->and($twoFactor->useRecoveryCode($login->fresh(), $oldCodes[1]))->toBeFalse()
        ->and($twoFactor->useRecoveryCode($login->fresh(), $newCodes[0]))->toBeTrue()
        ->and($twoFactor->remainingRecoveryCodes($login->fresh()))->toBe(7);
});

test('a user with no two-factor cannot make recovery codes', function () {
    config(['two-factor.enabled' => false]);
    $login = actingAsRole(Login::ROLE_TEACHER);

    $this->withSession(['auth.password_confirmed_at' => time()])
        ->post(route('security.recovery-codes'))
        ->assertRedirect(route('two-factor.setup'));

    expect($login->fresh()->two_factor_recovery_codes)->toBeNull();
});

test('the dashboard shows the notice when 2 recovery codes are left', function (int $role, string $dashboard) {
    $login = verifiedAsRole($role);
    leaveRecoveryCodes($login, 2);

    $this->get($dashboard)
        ->assertOk()
        ->assertSee('You have 2 recovery codes left.')
        ->assertSee(LOW_CODES_NOTICE)
        ->assertSee('href="'.route('security.index').'"', false);
})->with($dashboards);

test('the dashboard shows no notice when 3 recovery codes are left', function (int $role, string $dashboard) {
    $login = verifiedAsRole($role);
    leaveRecoveryCodes($login, 3);

    $this->get($dashboard)->assertOk()->assertDontSee(LOW_CODES_NOTICE);
})->with($dashboards);

test('the dashboard shows no notice to a user with no two-factor', function () {
    config(['two-factor.enabled' => false]);
    actingAsRole(Login::ROLE_STUDENT);

    $this->get('/student/dashboard')->assertOk()->assertDontSee(LOW_CODES_NOTICE);
});

test('after a recovery code the user lands on the role dashboard and sees how many codes are left', function (int $role, string $dashboard) {
    $login = Login::factory()->withTwoFactor()->create(['role' => $role]);
    match ($role) {
        Login::ROLE_TEACHER => App\Models\Teachers::factory()->create(['login_id' => $login->id]),
        Login::ROLE_STUDENT => App\Models\Students::factory()->create(['login_id' => $login->id]),
        Login::ROLE_PARENT => App\Models\Parents::factory()->create(['login_id' => $login->id]),
        default => null,
    };
    $codes = leaveRecoveryCodes($login, 4);

    $this->post(route('login'), ['email' => $login->email, 'password' => 'password']);
    $this->get('/dashboard')->assertRedirect(route('two-factor.challenge'));

    $this->post(route('two-factor.verify'), ['recovery_code' => $codes[0]])->assertRedirect(url($dashboard));

    $this->get($dashboard)
        ->assertOk()
        ->assertSee('You signed in with a recovery code. You have 3 recovery codes left.')
        ->assertDontSee(LOW_CODES_NOTICE);
})->with($dashboards);
