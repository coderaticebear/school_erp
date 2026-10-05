<?php

/**
 * Security SEC-24: the unused Laravel `users` table is gone. Accounts live in `login`; the password-reset and
 * session tables, created by the same first migration, stay.
 */

use Illuminate\Support\Facades\Schema;

test('the legacy users table is dropped and the reset and session tables remain', function () {
    expect(Schema::hasTable('users'))->toBeFalse()
        ->and(Schema::hasTable('password_reset_tokens'))->toBeTrue()
        ->and(Schema::hasTable('sessions'))->toBeTrue();
});
