<?php

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend(Tests\TestCase::class)
    ->use(Illuminate\Foundation\Testing\RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

/**
 * Create a login with the given role (plus its teacher/student/parent profile) and authenticate as it.
 */
function actingAsRole(int $role): App\Models\Login
{
    $login = App\Models\Login::factory()->create(['role' => $role]);

    match ($role) {
        App\Models\Login::ROLE_TEACHER => App\Models\Teachers::factory()->create(['login_id' => $login->id]),
        App\Models\Login::ROLE_STUDENT => App\Models\Students::factory()->create(['login_id' => $login->id]),
        App\Models\Login::ROLE_PARENT => App\Models\Parents::factory()->create(['login_id' => $login->id]),
        default => null,
    };

    test()->actingAs($login);

    return $login;
}
