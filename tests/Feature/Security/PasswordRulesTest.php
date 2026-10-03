<?php

/**
 * Security SEC-14: one password rule wherever a password is set: at least 12 characters, and not found in a known
 * data breach. The breach service (Have I Been Pwned) is faked in tests/TestCase.php.
 */

use App\Models\Login;
use Illuminate\Contracts\Validation\UncompromisedVerifier;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Password as PasswordBroker;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\NotPwnedVerifier;
use Illuminate\Validation\Rules\Password;

test('a password under 12 characters is refused', function () {
    $validator = Validator::make(['password' => 'elevenchars'], ['password' => Password::defaults()]);

    expect($validator->errors()->get('password'))->toBe(['The password field must be at least 12 characters.']);
});

test('a 12-character password is accepted once the breach service has checked it', function () {
    $validator = Validator::make(['password' => 'twelve-chars'], ['password' => Password::defaults()]);

    expect($validator->passes())->toBeTrue();
    Http::assertSent(fn (Request $request) => str_starts_with($request->url(), 'https://api.pwnedpasswords.com/range/'));
});

test('a password found in a data breach is refused', function () {
    $this->breachedPasswords = ['correct-horse-battery'];

    $validator = Validator::make(['password' => 'correct-horse-battery'], ['password' => Password::defaults()]);

    expect($validator->errors()->get('password'))
        ->toBe(['The given password has appeared in a data leak. Please choose a different password.']);
});

test('the breach check gives up after 5 seconds', function () {
    expect(app(UncompromisedVerifier::class))->toEqual(new NotPwnedVerifier(app(HttpFactory::class), 5));
});

test('the reset form refuses a password under 12 characters', function () {
    $login = Login::factory()->create();
    $token = PasswordBroker::createToken($login);

    $response = $this->from("/password/reset/{$token}")->post('/password/reset', [
        'token' => $token,
        'email' => $login->email,
        'password' => 'elevenchars',
        'password_confirmation' => 'elevenchars',
    ]);

    $response->assertSessionHasErrors(['password' => 'The password field must be at least 12 characters.']);
    expect(Hash::check('password', $login->fresh()->password))->toBeTrue();
});
