<?php

/**
 * Security SEC-19: reset-link requests are limited per IP address (5 a minute, 20 an hour), so one source can't
 * flood many addresses with reset emails.
 */

use App\Models\Login;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Notification;

test('five requests in a minute from one address are all answered', function () {
    Notification::fake();
    $login = Login::factory()->create(['email' => 'real@example.test']);
    foreach (range(1, 4) as $i) {
        $this->post('/password/email', ['email' => "nobody{$i}@example.test"]);
    }

    $response = $this->from('/password/reset')->post('/password/email', ['email' => 'real@example.test']);

    $response->assertSessionHas('status', "If that address has an account, we've sent a reset link.")
        ->assertSessionHasNoErrors();
    Notification::assertSentTo($login, ResetPassword::class);
});

test('a sixth request in a minute from one address is refused and sends no email', function () {
    $this->freezeTime();
    Notification::fake();
    Login::factory()->create(['email' => 'real@example.test']);
    foreach (range(1, 5) as $i) {
        $this->post('/password/email', ['email' => "nobody{$i}@example.test"]);
    }

    $response = $this->from('/password/reset')->post('/password/email', ['email' => 'real@example.test']);

    $response->assertRedirect('/password/reset')
        ->assertSessionHasErrors(['email' => 'Too many reset requests from this network. Please try again in 1 minute.'])
        ->assertSessionHasInput('email', 'real@example.test');
    Notification::assertNothingSent();
});

test('a 21st request in an hour from one address is refused', function () {
    $this->freezeTime();
    foreach (range(1, 20) as $i) {
        if ($i > 1 && $i % 5 === 1) {
            $this->travel(61)->seconds();
        }
        $this->post('/password/email', ['email' => "nobody{$i}@example.test"]);
    }
    $this->travel(61)->seconds();

    $response = $this->from('/password/reset')->post('/password/email', ['email' => 'nobody21@example.test']);

    $response->assertSessionHasErrors(['email' => 'Too many reset requests from this network. Please try again in 56 minutes.']);
});

test('another address is still answered while one is over the limit', function () {
    foreach (range(1, 6) as $i) {
        $this->post('/password/email', ['email' => "nobody{$i}@example.test"]);
    }

    $response = $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.2'])
        ->from('/password/reset')
        ->post('/password/email', ['email' => 'nobody7@example.test']);

    $response->assertSessionHas('status', "If that address has an account, we've sent a reset link.")
        ->assertSessionHasNoErrors();
});

test('a JSON request over the limit gets 429 with the same message', function () {
    $this->freezeTime();
    foreach (range(1, 5) as $i) {
        $this->postJson('/password/email', ['email' => "nobody{$i}@example.test"]);
    }

    $response = $this->postJson('/password/email', ['email' => 'nobody6@example.test']);

    $response->assertTooManyRequests()
        ->assertHeader('Retry-After', '60')
        ->assertExactJson(['message' => 'Too many reset requests from this network. Please try again in 1 minute.']);
});
