<?php

use App\Models\Login;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Notification;

test('the forgot password page loads', function () {
    $this->get('/password/reset')->assertSuccessful();
});

test('a user can request a reset link and set a new password', function () {
    Notification::fake();
    $login = Login::factory()->create(['email' => 'forgetful@example.test']);

    $this->post('/password/email', ['email' => 'forgetful@example.test'])->assertSessionHasNoErrors();

    Notification::assertSentTo($login, ResetPassword::class, function (ResetPassword $notification) use ($login) {
        $this->post('/password/reset', [
            'token' => $notification->token,
            'email' => $login->email,
            'password' => 'a-brand-new-pass',
            'password_confirmation' => 'a-brand-new-pass',
        ])->assertSessionHasNoErrors();

        return true;
    });

    expect(Hash::check('a-brand-new-pass', $login->fresh()->password))->toBeTrue();
});
