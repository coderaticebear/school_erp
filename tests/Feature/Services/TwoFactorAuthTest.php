<?php

/**
 * Security SEC-06: the two-factor service checks codes once, keeps recovery codes as hashes and makes the QR code
 * on our own server.
 */

use App\Models\Login;
use App\Services\TwoFactorAuth;
use PragmaRX\Google2FA\Google2FA;

test('a valid code is accepted once and the same code is then rejected', function () {
    $login = Login::factory()->withTwoFactor(TWO_FACTOR_TEST_SECRET)->create();
    $service = app(TwoFactorAuth::class);
    $code = twoFactorCode();

    expect($service->verifyCode($login, $code))->toBeTrue()
        ->and($login->two_factor_last_timestep)->toBe((new Google2FA)->getTimestamp())
        ->and($service->verifyCode($login->fresh(), $code))->toBeFalse();
});

test('a code from one step before now is accepted', function () {
    $login = Login::factory()->withTwoFactor(TWO_FACTOR_TEST_SECRET)->create();

    expect(app(TwoFactorAuth::class)->verifyCode($login, twoFactorCode(steps: -1)))->toBeTrue();
});

test('a code two steps away is rejected', function (int $steps) {
    $login = Login::factory()->withTwoFactor(TWO_FACTOR_TEST_SECRET)->create();

    expect(app(TwoFactorAuth::class)->verifyCode($login, twoFactorCode(steps: $steps)))->toBeFalse();
})->with(['two steps before' => -2, 'two steps after' => 2]);

test('a code typed with spaces is accepted', function () {
    $login = Login::factory()->withTwoFactor(TWO_FACTOR_TEST_SECRET)->create();
    $code = twoFactorCode();

    expect(app(TwoFactorAuth::class)->verifyCode($login, substr($code, 0, 3).' '.substr($code, 3)))->toBeTrue();
});

test('a code that is not six digits is rejected', function (string $code) {
    $login = Login::factory()->withTwoFactor(TWO_FACTOR_TEST_SECRET)->create();

    expect(app(TwoFactorAuth::class)->verifyCode($login, $code))->toBeFalse();
})->with(['empty' => '', 'five digits' => '12345', 'seven digits' => '1234567', 'letters' => 'abcdef']);

test('a login with no secret never passes a code check', function () {
    $login = Login::factory()->create();

    expect(app(TwoFactorAuth::class)->verifyCode($login, twoFactorCode()))->toBeFalse();
});

test('each recovery code works once', function () {
    $login = Login::factory()->withTwoFactor(TWO_FACTOR_TEST_SECRET)->create();
    $service = app(TwoFactorAuth::class);
    $codes = $service->generateRecoveryCodes($login);

    expect($codes)->toHaveCount(8)
        ->each->toMatch('/^[A-Z0-9]{5}-[A-Z0-9]{5}$/');

    expect($service->useRecoveryCode($login->fresh(), $codes[0]))->toBeTrue()
        ->and($service->useRecoveryCode($login->fresh(), $codes[0]))->toBeFalse()
        ->and($service->remainingRecoveryCodes($login->fresh()))->toBe(7)
        ->and($service->useRecoveryCode($login->fresh(), $codes[1]))->toBeTrue()
        ->and($service->remainingRecoveryCodes($login->fresh()))->toBe(6);
});

test('recovery codes are stored only as hashes', function () {
    $login = Login::factory()->withTwoFactor(TWO_FACTOR_TEST_SECRET)->create();
    $codes = app(TwoFactorAuth::class)->generateRecoveryCodes($login);

    $stored = $login->fresh()->two_factor_recovery_codes;

    expect($stored)->toHaveCount(8)
        ->each->toMatch('/^[0-9a-f]{64}$/');

    expect(array_intersect($stored, $codes))->toBeEmpty();
});

test('a recovery code is accepted in lowercase, without the dash, or with spaces', function () {
    $login = Login::factory()->withTwoFactor(TWO_FACTOR_TEST_SECRET)->create();
    $service = app(TwoFactorAuth::class);
    [$first, $second, $third] = $service->generateRecoveryCodes($login);

    expect($service->useRecoveryCode($login->fresh(), strtolower($first)))->toBeTrue()
        ->and($service->useRecoveryCode($login->fresh(), str_replace('-', '', $second)))->toBeTrue()
        ->and($service->useRecoveryCode($login->fresh(), ' '.$third.' '))->toBeTrue()
        ->and($service->useRecoveryCode($login->fresh(), 'WRONG-CODES'))->toBeFalse();
});

test('new recovery codes make the old ones stop working', function () {
    $login = Login::factory()->withTwoFactor(TWO_FACTOR_TEST_SECRET)->create();
    $service = app(TwoFactorAuth::class);
    $oldCodes = $service->generateRecoveryCodes($login);
    $service->generateRecoveryCodes($login->fresh());

    expect($service->useRecoveryCode($login->fresh(), $oldCodes[0]))->toBeFalse()
        ->and($service->remainingRecoveryCodes($login->fresh()))->toBe(8);
});

test('two-factor is required when the switch is on', function () {
    config(['two-factor.enabled' => true]);

    expect(app(TwoFactorAuth::class)->required())->toBeTrue();
});

test('two-factor is not required when the switch is off outside production', function () {
    config(['two-factor.enabled' => false]);

    expect(app(TwoFactorAuth::class)->required())->toBeFalse();
});

test('two-factor is required in production even when the switch is off', function () {
    config(['two-factor.enabled' => false]);
    app()->detectEnvironment(fn () => 'production');

    try {
        expect(app(TwoFactorAuth::class)->required())->toBeTrue();
    } finally {
        app()->detectEnvironment(fn () => 'testing');
    }
});

test('the QR code is inline SVG with no outside URL', function () {
    $service = app(TwoFactorAuth::class);
    $login = Login::factory()->create();
    $svg = $service->qrSvg($service->otpauthUrl($login, $service->makeSecret()));

    $urls = [];
    preg_match_all('#https?://[^\s"\'<>]+#', $svg, $urls);

    expect($svg)->toStartWith('<svg')
        ->not->toContain('<?xml')
        ->and(array_unique($urls[0]))->toBe(['http://www.w3.org/2000/svg']);
});

test('the otpauth URL holds the secret and the school name as issuer', function () {
    config(['school.name' => "St Mary's School"]);
    $service = app(TwoFactorAuth::class);
    $login = Login::factory()->create();
    $secret = $service->makeSecret();

    expect($secret)->toHaveLength(32)
        ->and($service->otpauthUrl($login, $secret))
        ->toStartWith('otpauth://totp/')
        ->toContain('secret='.$secret)
        ->toContain('issuer='.rawurlencode("St Mary's School"));
});

test('a password confirmation lasts 15 minutes by default', function () {
    expect(config('auth.password_timeout'))->toBe(900);
});
