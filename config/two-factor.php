<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Two-factor sign-in (Security SEC-06)
    |--------------------------------------------------------------------------
    |
    | When enabled, every user must sign in with a code from an authenticator
    | app. Turn it off only for local demos and browser tests: production
    | always requires two-factor, whatever this value says.
    |
    */

    'enabled' => filter_var(env('TWO_FACTOR_REQUIRED', true), FILTER_VALIDATE_BOOLEAN),

    /*
    | Minutes a user has to enter the code after a correct password.
    */

    'challenge_minutes' => 10,

    /*
    | Recovery codes made in each set, and the count below which the dashboard
    | tells the user to make new ones.
    */

    'recovery_code_count' => 8,

    'low_codes_warning' => 3,
];
