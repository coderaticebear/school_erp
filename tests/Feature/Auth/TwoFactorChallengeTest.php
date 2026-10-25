<?php

use App\Http\Middleware\EnsureTwoFactor;
use App\Models\Login;
use App\Services\TwoFactorAuth;
use Illuminate\Support\Carbon;

/**
 * The two-factor challenge (SEC-06, T5): after the password, a user with two-factor enters a code from the app or a
 * recovery code. Wrong codes get one neutral reply, attempts are limited, and the challenge ends after 10 minutes.
 */
$roles = [
    'admin' => [Login::ROLE_ADMIN, '/admin/dashboard'],
    'teacher' => [Login::ROLE_TEACHER, '/teacher/dashboard'],
    'student' => [Login::ROLE_STUDENT, '/student/dashboard'],
    'parent' => [Login::ROLE_PARENT, '/parent/dashboard'],
];

const WRONG_CODE_MESSAGE = 'That code is not correct. Try again.';

const TOO_MANY_ATTEMPTS_MESSAGE = 'Too many attempts. Please wait a minute and try again.';

beforeEach(function () {
    config(['two-factor.enabled' => true]);
});

afterEach(function () {
    Carbon::setTestNow();
});

/**
 * A login with two-factor turned on and its teacher, student or parent profile, not signed in.
 */
function challengeLogin(int $role): Login
{
    $login = Login::factory()->withTwoFactor()->create(['role' => $role]);

    match ($role) {
        Login::ROLE_TEACHER => App\Models\Teachers::factory()->create(['login_id' => $login->id]),
        Login::ROLE_STUDENT => App\Models\Students::factory()->create(['login_id' => $login->id]),
        Login::ROLE_PARENT => App\Models\Parents::factory()->create(['login_id' => $login->id]),
        default => null,
    };

    return $login;
}

test('a user signs in with the password and a code from the app', function (int $role, string $dashboard) {
    $login = challengeLogin($role);

    $this->post(route('login'), ['email' => $login->email, 'password' => 'password'])->assertRedirect('/dashboard');
    $this->get('/dashboard')->assertRedirect(route('two-factor.challenge'));
    expect(session()->has(EnsureTwoFactor::SESSION_VERIFIED))->toBeFalse();

    $this->get(route('two-factor.challenge'))->assertOk()->assertSee('Enter Your Code');

    $this->post(route('two-factor.verify'), ['code' => twoFactorCode()])
        ->assertRedirect(url($dashboard))
        ->assertSessionHasNoErrors();

    expect(session(EnsureTwoFactor::SESSION_VERIFIED))->toBe($login->id)
        ->and(session()->has(EnsureTwoFactor::SESSION_STARTED))->toBeFalse();

    $this->get($dashboard)->assertOk();
})->with($roles);

test('the page the user first asked for opens after the code', function () {
    $login = challengeLogin(Login::ROLE_ADMIN);

    $this->get('/admin/academic-years')->assertRedirect(route('login'));
    $this->post(route('login'), ['email' => $login->email, 'password' => 'password'])
        ->assertRedirect(url('/admin/academic-years'));
    $this->get('/admin/academic-years')->assertRedirect(route('two-factor.challenge'));

    $this->post(route('two-factor.verify'), ['code' => twoFactorCode()])
        ->assertRedirect(url('/admin/academic-years'));

    $this->get('/admin/academic-years')->assertOk();
});

test('a wrong, a used and a used recovery code get the same reply', function () {
    $login = actingAsRole(Login::ROLE_TEACHER, withTwoFactor: true);
    $recoveryCodes = app(TwoFactorAuth::class)->generateRecoveryCodes($login);
    app(TwoFactorAuth::class)->useRecoveryCode($login, $recoveryCodes[0]);

    $code = twoFactorCode();
    $this->post(route('two-factor.verify'), ['code' => $code])->assertRedirect(route('teacher.dashboard'));
    session()->forget(EnsureTwoFactor::SESSION_VERIFIED);

    $attempts = [
        'replayed code' => ['code' => $code],
        'wrong code' => ['code' => $code === '000000' ? '111111' : '000000'],
        'used recovery code' => ['recovery_code' => $recoveryCodes[0]],
    ];

    foreach ($attempts as $input) {
        $field = array_key_first($input);

        $this->from(route('two-factor.challenge'))
            ->post(route('two-factor.verify'), $input)
            ->assertRedirect(route('two-factor.challenge'))
            ->assertSessionHasErrors([$field => WRONG_CODE_MESSAGE]);

        expect(session()->has(EnsureTwoFactor::SESSION_VERIFIED))->toBeFalse()
            ->and(session()->getOldInput())->not->toHaveKey('code')
            ->and(session()->getOldInput())->not->toHaveKey('recovery_code');
    }

    $this->get('/dashboard')->assertRedirect(route('two-factor.challenge'));
});

test('one of the two fields is required', function () {
    actingAsRole(Login::ROLE_STUDENT, withTwoFactor: true);

    $this->from(route('two-factor.challenge'))
        ->post(route('two-factor.verify'), ['code' => '', 'recovery_code' => ''])
        ->assertRedirect(route('two-factor.challenge'))
        ->assertSessionHasErrors(['code', 'recovery_code']);
});

test('a recovery code signs the user in once and tells how many are left', function () {
    $login = actingAsRole(Login::ROLE_PARENT, withTwoFactor: true);
    $recoveryCodes = app(TwoFactorAuth::class)->generateRecoveryCodes($login);

    $this->post(route('two-factor.verify'), ['recovery_code' => strtolower(str_replace('-', '', $recoveryCodes[0]))])
        ->assertRedirect(route('parent.dashboard'))
        ->assertSessionHas('success', 'You signed in with a recovery code. You have 7 recovery codes left.');

    expect(session(EnsureTwoFactor::SESSION_VERIFIED))->toBe($login->id)
        ->and(app(TwoFactorAuth::class)->remainingRecoveryCodes($login->fresh()))->toBe(7);

    session()->forget(EnsureTwoFactor::SESSION_VERIFIED);

    $this->from(route('two-factor.challenge'))
        ->post(route('two-factor.verify'), ['recovery_code' => $recoveryCodes[0]])
        ->assertSessionHasErrors(['recovery_code' => WRONG_CODE_MESSAGE]);

    expect(session()->has(EnsureTwoFactor::SESSION_VERIFIED))->toBeFalse();
});

test('the reply asks for new codes when few recovery codes are left', function () {
    $login = actingAsRole(Login::ROLE_STUDENT, withTwoFactor: true);
    $recoveryCodes = app(TwoFactorAuth::class)->generateRecoveryCodes($login);

    foreach (array_slice($recoveryCodes, 0, 6) as $used) {
        app(TwoFactorAuth::class)->useRecoveryCode($login, $used);
    }

    $this->post(route('two-factor.verify'), ['recovery_code' => $recoveryCodes[6]])
        ->assertSessionHas('success', 'You signed in with a recovery code. You have 1 recovery code left. Ask for new codes soon.');
});

test('the sixth attempt in one minute is blocked with the same reply for any code', function () {
    $login = actingAsRole(Login::ROLE_ADMIN, withTwoFactor: true);
    $recoveryCodes = app(TwoFactorAuth::class)->generateRecoveryCodes($login);
    $wrong = twoFactorCode() === '000000' ? '111111' : '000000';

    foreach (range(1, 5) as $attempt) {
        $this->from(route('two-factor.challenge'))
            ->post(route('two-factor.verify'), ['code' => $wrong])
            ->assertSessionHasErrors(['code' => WRONG_CODE_MESSAGE]);
    }

    foreach (['right code' => twoFactorCode(), 'wrong code' => $wrong] as $code) {
        $this->from(route('two-factor.challenge'))
            ->post(route('two-factor.verify'), ['code' => $code])
            ->assertRedirect(route('two-factor.challenge'))
            ->assertSessionHasErrors(['code' => TOO_MANY_ATTEMPTS_MESSAGE]);
    }

    $this->from(route('two-factor.challenge'))
        ->post(route('two-factor.verify'), ['recovery_code' => $recoveryCodes[0]])
        ->assertSessionHasErrors(['recovery_code' => TOO_MANY_ATTEMPTS_MESSAGE]);

    $this->postJson(route('two-factor.verify'), ['code' => twoFactorCode()])
        ->assertStatus(429)
        ->assertJson(['message' => TOO_MANY_ATTEMPTS_MESSAGE]);

    expect(session()->has(EnsureTwoFactor::SESSION_VERIFIED))->toBeFalse()
        ->and(app(TwoFactorAuth::class)->remainingRecoveryCodes($login->fresh()))->toBe(8);
});

test('the limit also covers the set-up form', function () {
    $login = actingAsRole(Login::ROLE_TEACHER);
    $this->get(route('two-factor.setup'))->assertOk();
    $secret = $login->fresh()->two_factor_secret;
    $wrong = twoFactorCode($secret) === '000000' ? '111111' : '000000';

    foreach (range(1, 5) as $attempt) {
        $this->from(route('two-factor.setup'))->post(route('two-factor.enable'), ['code' => $wrong]);
    }

    $this->from(route('two-factor.setup'))
        ->post(route('two-factor.enable'), ['code' => twoFactorCode($secret)])
        ->assertRedirect(route('two-factor.setup'))
        ->assertSessionHasErrors(['code' => TOO_MANY_ATTEMPTS_MESSAGE]);

    expect($login->fresh()->two_factor_confirmed_at)->toBeNull();
});

test('the challenge ends 10 minutes after the password', function () {
    Carbon::setTestNow('2026-10-23 09:00:00');
    actingAsRole(Login::ROLE_STUDENT, withTwoFactor: true);

    $this->withSession([EnsureTwoFactor::SESSION_STARTED => now()->subMinutes(11)->timestamp])
        ->post(route('two-factor.verify'), ['code' => twoFactorCode()])
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors(['email' => 'Your sign-in took too long. Please sign in again.']);

    $this->assertGuest();
    expect(session()->has(EnsureTwoFactor::SESSION_VERIFIED))->toBeFalse();
});

test('the code still works 9 minutes after the password', function () {
    Carbon::setTestNow('2026-10-23 09:00:00');
    $login = actingAsRole(Login::ROLE_STUDENT, withTwoFactor: true);

    $this->withSession([EnsureTwoFactor::SESSION_STARTED => now()->subMinutes(9)->timestamp])
        ->post(route('two-factor.verify'), ['code' => twoFactorCode()])
        ->assertRedirect(route('student.dashboard'));

    $this->assertAuthenticatedAs($login);
    expect(session(EnsureTwoFactor::SESSION_VERIFIED))->toBe($login->id);
});

test('a user without two-factor is sent to set-up', function () {
    $login = actingAsRole(Login::ROLE_TEACHER);

    $this->get(route('two-factor.challenge'))->assertRedirect(route('two-factor.setup'));
    $this->post(route('two-factor.verify'), ['code' => '123456'])->assertRedirect(route('two-factor.setup'));

    expect(session()->has(EnsureTwoFactor::SESSION_VERIFIED))->toBeFalse()
        ->and($login->fresh()->two_factor_confirmed_at)->toBeNull();
});

test('a verified user is sent to the dashboard and keeps the recovery codes', function () {
    $login = actingAsRole(Login::ROLE_ADMIN, withTwoFactor: true);
    $recoveryCodes = app(TwoFactorAuth::class)->generateRecoveryCodes($login);

    $this->withSession([EnsureTwoFactor::SESSION_VERIFIED => $login->id])
        ->get(route('two-factor.challenge'))
        ->assertRedirect(route('dashboard'));

    $this->post(route('two-factor.verify'), ['recovery_code' => $recoveryCodes[0]])
        ->assertRedirect(route('dashboard'));

    expect(app(TwoFactorAuth::class)->remainingRecoveryCodes($login->fresh()))->toBe(8);
});

test('the challenge page has one heading, linked labels and a logout form', function () {
    actingAsRole(Login::ROLE_PARENT, withTwoFactor: true);

    $html = $this->get(route('two-factor.challenge'))->assertOk()->getContent();

    expect(substr_count($html, '<h1'))->toBe(1)
        ->and($html)->toContain('Enter Your Code')
        ->and($html)->toMatch('/<label for="two-factor-code">\s*Code From Your App/')
        ->and($html)->toMatch('/<input[^>]*id="two-factor-code"[^>]*name="code"/')
        ->and($html)->toMatch('/<label for="two-factor-recovery-code">\s*Recovery Code/')
        ->and($html)->toMatch('/<input[^>]*id="two-factor-recovery-code"[^>]*name="recovery_code"/')
        ->and($html)->toContain('Verify Code')
        ->and($html)->toContain('Use Recovery Code')
        ->and($html)->toContain('action="'.route('logout').'"')
        ->and(substr_count($html, 'action="'.route('two-factor.verify').'"'))->toBe(2);

    preg_match_all('#https?://[^\s"\'<>]+#', $html, $matches);
    $outside = array_filter($matches[0], fn (string $url) => $url !== 'http://www.w3.org/2000/svg'
        && ! str_starts_with($url, rtrim(config('app.url'), '/').'/'));
    expect(array_values($outside))->toBe([]);
});

test('a guest is sent to the login page', function () {
    $this->get(route('two-factor.challenge'))->assertRedirect(route('login'));
    $this->post(route('two-factor.verify'), ['code' => '123456'])->assertRedirect(route('login'));
});
