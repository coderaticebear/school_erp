<?php

use App\Models\Login;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$twoFactorFields = [
    'two_factor_secret',
    'two_factor_confirmed_at',
    'two_factor_recovery_codes',
    'two_factor_last_timestep',
];

test('the login table has the two-factor columns', function () use ($twoFactorFields) {
    expect(Schema::hasColumns('login', $twoFactorFields))->toBeTrue();
});

test('the secret and the recovery codes are encrypted in the database', function () {
    $login = Login::factory()->withTwoFactor('JBSWY3DPEHPK3PXPJBSWY3DPEHPK3PXP')->create();
    $login->forceFill(['two_factor_recovery_codes' => ['first-code', 'second-code']])->save();

    $row = DB::table('login')->where('id', $login->id)->first();

    expect($row->two_factor_secret)->not->toBe('JBSWY3DPEHPK3PXPJBSWY3DPEHPK3PXP')
        ->and($row->two_factor_secret)->not->toContain('JBSWY3DPEHPK3PXP')
        ->and($row->two_factor_recovery_codes)->not->toContain('first-code');

    $fresh = $login->fresh();

    expect($fresh->two_factor_secret)->toBe('JBSWY3DPEHPK3PXPJBSWY3DPEHPK3PXP')
        ->and($fresh->two_factor_recovery_codes)->toBe(['first-code', 'second-code']);
});

test('the two-factor fields are not in the array form', function () use ($twoFactorFields) {
    $login = Login::factory()->withTwoFactor()->create();
    $login->forceFill(['two_factor_last_timestep' => 123])->save();

    expect($login->fresh()->toArray())->not->toHaveKeys($twoFactorFields);
});

test('the two-factor fields are not mass assignable', function () use ($twoFactorFields) {
    $login = Login::create([
        'email' => 'mass@example.com',
        'password' => bcrypt('password'),
        'role' => Login::ROLE_ADMIN,
        'is_active' => true,
        'two_factor_secret' => 'JBSWY3DPEHPK3PXPJBSWY3DPEHPK3PXP',
        'two_factor_confirmed_at' => now(),
        'two_factor_recovery_codes' => ['code'],
        'two_factor_last_timestep' => 123,
    ])->fresh();

    foreach ($twoFactorFields as $field) {
        expect($login->isFillable($field))->toBeFalse()
            ->and($login->getAttribute($field))->toBeNull();
    }
});

test('two-factor is enabled only after set-up', function () {
    expect(Login::factory()->create()->hasTwoFactorEnabled())->toBeFalse()
        ->and(Login::factory()->withTwoFactor()->create()->hasTwoFactorEnabled())->toBeTrue();
});

test('two-factor is not enabled when set-up is not confirmed', function () {
    $login = Login::factory()->withTwoFactor()->create();
    $login->forceFill(['two_factor_confirmed_at' => null])->save();

    expect($login->fresh()->hasTwoFactorEnabled())->toBeFalse();
});
