<?php

use App\Http\Middleware\EnsureTwoFactor;
use App\Models\Login;

/**
 * Two-factor set-up (SEC-06, T4): a user scans the QR code, enters one correct code, and sees the recovery codes once.
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

/**
 * The secret stored on the login row, read through the model.
 */
function storedSecret(Login $login): ?string
{
    return $login->fresh()->two_factor_secret;
}

/**
 * The secret as the page shows it: blocks of 4 characters.
 */
function groupedSecret(string $secret): string
{
    return implode(' ', str_split($secret, 4));
}

test('the set-up page shows the QR code, the secret and the help text', function (int $role) {
    $login = actingAsRole($role);

    $html = $this->get(route('two-factor.setup'))->assertOk()->getContent();
    $secret = storedSecret($login);

    expect($secret)->toBeString()->not->toBeEmpty()
        ->and($html)->toContain('<svg')
        ->and($html)->toContain('role="img"')
        ->and($html)->toContain('aria-label="QR code for your authenticator app"')
        ->and($html)->toContain(groupedSecret($secret))
        ->and($html)->toContain('A parent or a school device can hold the authenticator app.')
        ->and(substr_count($html, '<h1'))->toBe(1);

    expect($html)->toMatch('/<label for="two-factor-code">\s*Code From Your App/')
        ->and($html)->toMatch('/<input[^>]*id="two-factor-code"[^>]*name="code"/');

    preg_match_all('#https?://[^\s"\'<>]+#', $html, $matches);
    $outside = array_filter($matches[0], fn (string $url) => $url !== 'http://www.w3.org/2000/svg'
        && ! str_starts_with($url, rtrim(config('app.url'), '/').'/'));
    expect(array_values($outside))->toBe([]);

    $this->get(route('two-factor.setup'))->assertOk()->assertSee(groupedSecret($secret));
    expect(storedSecret($login))->toBe($secret)
        ->and($login->fresh()->two_factor_confirmed_at)->toBeNull();
})->with($roles);

test('a correct code turns on two-factor and opens the dashboard', function (int $role, string $dashboard) {
    $login = actingAsRole($role);
    $this->get(route('two-factor.setup'))->assertOk();

    $this->post(route('two-factor.enable'), ['code' => twoFactorCode(storedSecret($login))])
        ->assertRedirect(route('two-factor.codes'));

    $login->refresh();
    expect($login->two_factor_confirmed_at)->not->toBeNull()
        ->and($login->hasTwoFactorEnabled())->toBeTrue()
        ->and($login->two_factor_recovery_codes)->toHaveCount(8)
        ->and(session(EnsureTwoFactor::SESSION_VERIFIED))->toBe($login->id)
        ->and(session()->has(EnsureTwoFactor::SESSION_STARTED))->toBeFalse();

    $this->get('/dashboard')->assertRedirect($dashboard);
})->with($roles);

test('a wrong code does not turn on two-factor', function () {
    $login = actingAsRole(Login::ROLE_TEACHER);
    $this->get(route('two-factor.setup'));

    $this->from(route('two-factor.setup'))
        ->post(route('two-factor.enable'), ['code' => '000000'])
        ->assertRedirect(route('two-factor.setup'))
        ->assertSessionHasErrors(['code' => 'That code is not correct. Check the code in your app and try again.']);

    expect($login->fresh()->two_factor_confirmed_at)->toBeNull()
        ->and($login->fresh()->two_factor_recovery_codes)->toBeNull()
        ->and(session()->getOldInput())->not->toHaveKey('code')
        ->and(session()->has(EnsureTwoFactor::SESSION_VERIFIED))->toBeFalse();
});

test('the code field is required', function () {
    actingAsRole(Login::ROLE_STUDENT);
    $this->get(route('two-factor.setup'));

    $this->from(route('two-factor.setup'))
        ->post(route('two-factor.enable'), ['code' => ''])
        ->assertRedirect(route('two-factor.setup'))
        ->assertSessionHasErrors('code');
});

test('the secret is never kept in the session', function () {
    $login = actingAsRole(Login::ROLE_PARENT);
    $this->get(route('two-factor.setup'));
    $secret = storedSecret($login);

    expect(json_encode(session()->all()))->not->toContain($secret);

    $this->post(route('two-factor.enable'), ['code' => twoFactorCode($secret)]);
    $this->get(route('two-factor.codes'));

    expect(json_encode(session()->all()))->not->toContain($secret);
});

test('the recovery codes show once', function () {
    $login = actingAsRole(Login::ROLE_ADMIN);
    $this->get(route('two-factor.setup'));
    $this->post(route('two-factor.enable'), ['code' => twoFactorCode(storedSecret($login))]);

    $html = $this->get(route('two-factor.codes'))->assertOk()
        ->assertSee('Go To Dashboard')
        ->getContent();

    preg_match_all('/\b[A-Z2-9]{5}-[A-Z2-9]{5}\b/', $html, $matches);
    expect(array_unique($matches[0]))->toHaveCount(8)
        ->and(substr_count($html, '<h1'))->toBe(1);

    foreach ($matches[0] as $code) {
        expect(app(App\Services\TwoFactorAuth::class)->useRecoveryCode($login, $code))->toBeTrue();
    }

    $second = $this->get(route('two-factor.codes'))->assertRedirect(route('dashboard'))->getContent();
    expect(preg_match('/\b[A-Z2-9]{5}-[A-Z2-9]{5}\b/', $second))->toBe(0);
});

test('the codes page without a new set goes to the dashboard', function () {
    actingAsRole(Login::ROLE_TEACHER);

    $this->get(route('two-factor.codes'))->assertRedirect(route('dashboard'));
});

test('a user who already has two-factor cannot set it up again', function (int $role) {
    $login = actingAsRole($role, withTwoFactor: true);
    app(App\Services\TwoFactorAuth::class)->generateRecoveryCodes($login);
    $before = $login->fresh();

    $this->get(route('two-factor.setup'))->assertRedirect(route('dashboard'));
    $this->post(route('two-factor.enable'), ['code' => twoFactorCode()])->assertRedirect(route('dashboard'));

    $after = $login->fresh();
    expect($after->two_factor_secret)->toBe($before->two_factor_secret)
        ->and($after->two_factor_confirmed_at->equalTo($before->two_factor_confirmed_at))->toBeTrue()
        ->and($after->two_factor_recovery_codes)->toBe($before->two_factor_recovery_codes)
        ->and(session()->has(EnsureTwoFactor::SESSION_VERIFIED))->toBeFalse();
})->with(array_map(fn (array $role) => [$role[0]], $roles));

test('an unconfirmed secret is used again', function () {
    $login = actingAsRole(Login::ROLE_STUDENT);
    $login->forceFill(['two_factor_secret' => TWO_FACTOR_TEST_SECRET])->save();

    $this->get(route('two-factor.setup'))->assertOk()->assertSee(groupedSecret(TWO_FACTOR_TEST_SECRET));

    expect(storedSecret($login))->toBe(TWO_FACTOR_TEST_SECRET);
});

test('posting a code with no stored secret goes back to set-up', function () {
    $login = actingAsRole(Login::ROLE_TEACHER);

    $this->post(route('two-factor.enable'), ['code' => '123456'])->assertRedirect(route('two-factor.setup'));

    expect($login->fresh()->two_factor_confirmed_at)->toBeNull();
});

test('a guest is sent to the login page', function () {
    $this->get(route('two-factor.setup'))->assertRedirect(route('login'));
    $this->get(route('two-factor.codes'))->assertRedirect(route('login'));
    $this->post(route('two-factor.enable'), ['code' => '123456'])->assertRedirect(route('login'));
});
