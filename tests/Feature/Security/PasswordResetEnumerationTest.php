<?php

/**
 * Security SEC-11: the password-reset pages never reveal which email addresses have accounts.
 */

use App\Models\Login;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Testing\TestResponse;

/**
 * The forgot-password form's one reply, whether or not the email has an account.
 */
function assertNeutralResetLinkReply(TestResponse $response): void
{
    $response->assertRedirect('/password/reset')
        ->assertSessionHas('status', "If that address has an account, we've sent a reset link.")
        ->assertSessionHasNoErrors()
        ->assertSessionMissing('_old_input');
}

test('a real account gets the neutral reply and a reset email', function () {
    Notification::fake();
    $login = Login::factory()->create(['email' => 'real@example.test']);

    $response = $this->from('/password/reset')->post('/password/email', ['email' => 'real@example.test']);

    assertNeutralResetLinkReply($response);
    Notification::assertSentTo($login, ResetPassword::class);
});

test('an email with no account gets the same reply and no email', function () {
    Notification::fake();

    $response = $this->from('/password/reset')->post('/password/email', ['email' => 'nobody@example.test']);

    assertNeutralResetLinkReply($response);
    Notification::assertNothingSent();
});

test('a repeat request within the throttle window gets the same reply and no second email', function () {
    Notification::fake();
    $login = Login::factory()->create(['email' => 'real@example.test']);
    Password::createToken($login);

    $response = $this->from('/password/reset')->post('/password/email', ['email' => 'real@example.test']);

    assertNeutralResetLinkReply($response);
    Notification::assertNothingSent();
});

test('a JSON request for an email with no account gets the same reply as a real one', function () {
    Notification::fake();

    $response = $this->postJson('/password/email', ['email' => 'nobody@example.test']);

    $response->assertOk()->assertExactJson(['message' => "If that address has an account, we've sent a reset link."]);
    Notification::assertNothingSent();
});

test('the reset form gives an unknown email the same reply as a wrong token', function (string $email) {
    $login = Login::factory()->create(['email' => 'real@example.test']);

    $response = $this->from('/password/reset/made-up-token')->post('/password/reset', [
        'token' => 'made-up-token',
        'email' => $email,
        'password' => 'a-brand-new-pass',
        'password_confirmation' => 'a-brand-new-pass',
    ]);

    $response->assertRedirect('/password/reset/made-up-token')
        ->assertSessionHasErrors(['email' => 'This password reset token is invalid.'])
        ->assertSessionHasInput('email', $email);
    expect(Hash::check('password', $login->fresh()->password))->toBeTrue();
})->with([
    'an email with no account' => 'nobody@example.test',
    'a real account with a wrong token' => 'real@example.test',
]);
