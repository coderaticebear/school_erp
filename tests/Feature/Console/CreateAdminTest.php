<?php

use App\Models\Login;
use Illuminate\Support\Facades\Artisan;

test('create-admin makes an active admin who can sign in with the password it prints', function () {
    $exitCode = Artisan::call('create-admin', ['email' => 'principal@school.test']);
    preg_match('/Password: (\S+)/', Artisan::output(), $printed);

    expect($exitCode)->toBe(0);
    expect($printed)->toHaveKey(1);
    expect(strlen($printed[1]))->toBeGreaterThanOrEqual(24);

    $admin = Login::sole();
    expect($admin->email)->toBe('principal@school.test');
    expect($admin->role)->toBe(Login::ROLE_ADMIN);
    expect($admin->is_active)->toBeTrue();
    expect($admin->password)->not->toBe($printed[1]);

    $this->post('/login', ['email' => 'principal@school.test', 'password' => $printed[1]])->assertRedirect('/dashboard');
    $this->assertAuthenticatedAs($admin);
});

test('create-admin asks for the email when none is given', function () {
    $this->artisan('create-admin')
        ->expectsQuestion('Email address for the new admin', 'office@school.test')
        ->assertSuccessful();

    expect(Login::where('email', 'office@school.test')->where('role', Login::ROLE_ADMIN)->exists())->toBeTrue();
});

test('create-admin refuses an email that already has an account, in any letter case', function () {
    Login::factory()->teacher()->create(['email' => 'Office@School.test']);

    $this->artisan('create-admin', ['email' => 'office@school.test'])
        ->expectsOutputToContain('already exists')
        ->assertFailed();

    expect(Login::count())->toBe(1);
});

test('create-admin refuses an invalid email', function () {
    $this->artisan('create-admin', ['email' => 'not-an-email'])
        ->expectsOutputToContain('must be a valid email address')
        ->assertFailed();

    expect(Login::count())->toBe(0);
});
