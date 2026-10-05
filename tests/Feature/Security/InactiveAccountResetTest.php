<?php

/**
 * Security SEC-20: deactivated accounts get no reset email and can't reset their password, while the reply stays the
 * same as for any other email (SEC-11).
 */

use App\Models\Login;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;

test('a deactivated account gets the neutral reply and no reset email', function () {
    Notification::fake();
    Login::factory()->inactive()->create(['email' => 'former@example.test']);

    $response = $this->from('/password/reset')->post('/password/email', ['email' => 'former@example.test']);

    $response->assertRedirect('/password/reset')
        ->assertSessionHas('status', "If that address has an account, we've sent a reset link.")
        ->assertSessionHasNoErrors();
    Notification::assertNothingSent();
    $this->assertDatabaseMissing('password_reset_tokens', ['email' => 'former@example.test']);
});

test('a reset link sent before deactivation can no longer change the password', function () {
    $login = Login::factory()->create(['email' => 'former@example.test']);
    $token = Password::createToken($login);
    $login->update(['is_active' => false]);

    $response = $this->from("/password/reset/{$token}")->post('/password/reset', [
        'token' => $token,
        'email' => 'former@example.test',
        'password' => 'a-brand-new-pass',
        'password_confirmation' => 'a-brand-new-pass',
    ]);

    $response->assertRedirect("/password/reset/{$token}")
        ->assertSessionHasErrors(['email' => 'This password reset token is invalid.']);
    expect(Hash::check('password', $login->fresh()->password))->toBeTrue();
    $this->assertGuest();
});
