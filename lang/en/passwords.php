<?php

/**
 * Overrides for Laravel's password-reset messages. Keys not listed here keep Laravel's own wording.
 *
 * 'sent' is the one reply to every reset-link request, whether or not the email has an account, so the
 * forgot-password form doesn't reveal which emails have accounts (Security SEC-11).
 */
return [
    'sent' => "If that address has an account, we've sent a reset link.",
];
