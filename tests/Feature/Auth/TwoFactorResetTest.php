<?php

use App\Models\Login;
use App\Models\Students;
use App\Models\Teachers;
use App\Services\TwoFactorAuth;
use Illuminate\Support\Facades\DB;

/**
 * An admin resets the two-factor sign-in of a teacher, student or parent who lost the device (SEC-06, T7).
 */

/**
 * Make an account of the given kind with two-factor on, recovery codes, a Remember Me token and two stored sessions.
 *
 * @return array{login: Login, reset: string, edit: string}
 */
function twoFactorResetTarget(string $who): array
{
    if ($who === 'teacher') {
        $teacher = Teachers::factory()->create();
        $login = $teacher->login;
        $reset = route('admin.teachers.two-factor.reset', $teacher);
        $edit = route('admin.teachers.edit', $teacher);
    } else {
        $student = Students::factory()->create();
        $login = $who === 'parent' ? $student->parent->login : $student->login;
        $reset = $who === 'parent'
            ? route('admin.students.parent-two-factor.reset', $student)
            : route('admin.students.two-factor.reset', $student);
        $edit = route('admin.students.edit', $student);
    }

    $login->forceFill([
        'two_factor_secret' => TWO_FACTOR_TEST_SECRET,
        'two_factor_confirmed_at' => now(),
        'two_factor_last_timestep' => 123,
        'remember_token' => 'old-remember-token',
    ])->save();
    app(TwoFactorAuth::class)->generateRecoveryCodes($login);

    storeSessionFor($login->id, 'session-a-'.$login->id);
    storeSessionFor($login->id, 'session-b-'.$login->id);

    return ['login' => $login, 'reset' => $reset, 'edit' => $edit];
}

/**
 * A row in the sessions table, as the database session driver stores it.
 */
function storeSessionFor(int $loginId, string $sessionId): void
{
    DB::table('sessions')->insert([
        'id' => $sessionId,
        'user_id' => $loginId,
        'ip_address' => '127.0.0.1',
        'user_agent' => 'Pest',
        'payload' => 'payload',
        'last_activity' => now()->timestamp,
    ]);
}

$targets = ['teacher' => ['teacher'], 'student' => ['student'], 'parent' => ['parent']];

test('an admin resets the two-factor sign-in of an account', function (string $who) {
    $admin = actingAsConfirmedAdmin();
    storeSessionFor($admin->id, 'admin-session');
    $target = twoFactorResetTarget($who);

    $this->from($target['edit'])->post($target['reset'])
        ->assertRedirect($target['edit'])
        ->assertSessionHas('success', fn (string $message) => str_contains($message, 'Two-factor sign-in reset for'));

    $login = $target['login']->fresh();
    expect($login->two_factor_secret)->toBeNull()
        ->and($login->two_factor_confirmed_at)->toBeNull()
        ->and($login->two_factor_recovery_codes)->toBeNull()
        ->and($login->two_factor_last_timestep)->toBeNull()
        ->and($login->hasTwoFactorEnabled())->toBeFalse()
        ->and($login->remember_token)->not->toBeNull()->not->toBe('old-remember-token')
        ->and(DB::table('sessions')->where('user_id', $login->id)->count())->toBe(0)
        ->and(DB::table('sessions')->where('user_id', $admin->id)->count())->toBe(1);
})->with($targets);

test('after a reset the user must set up two-factor again', function () {
    actingAsConfirmedAdmin();
    $target = twoFactorResetTarget('teacher');
    $this->post($target['reset']);

    config(['two-factor.enabled' => true]);
    $this->actingAs($target['login']->fresh())
        ->get(route('teacher.dashboard'))
        ->assertRedirect(route('two-factor.setup'));
});

test('a reset without a recent admin password goes to the password page and changes nothing', function (string $who) {
    actingAsRole(Login::ROLE_ADMIN);
    $target = twoFactorResetTarget($who);

    $this->from($target['edit'])->post($target['reset'])->assertRedirect(route('password.confirm'));

    expect($target['login']->fresh()->hasTwoFactorEnabled())->toBeTrue()
        ->and(DB::table('sessions')->where('user_id', $target['login']->id)->count())->toBe(2);
})->with($targets);

test('an admin account is never reset from an admin page', function (string $who) {
    actingAsConfirmedAdmin();
    $target = twoFactorResetTarget($who);
    $target['login']->forceFill(['role' => Login::ROLE_ADMIN])->save();

    $this->post($target['reset'])->assertForbidden();

    $login = $target['login']->fresh();
    expect($login->hasTwoFactorEnabled())->toBeTrue()
        ->and($login->remember_token)->toBe('old-remember-token')
        ->and(DB::table('sessions')->where('user_id', $login->id)->count())->toBe(2);
})->with($targets);

test('only an admin can reset two-factor', function (int $role) {
    $target = twoFactorResetTarget('teacher');
    actingAsRole($role);

    $this->withSession(['auth.password_confirmed_at' => time()])
        ->post($target['reset'])
        ->assertForbidden();

    expect($target['login']->fresh()->hasTwoFactorEnabled())->toBeTrue();
})->with([
    'teacher' => [Login::ROLE_TEACHER],
    'student' => [Login::ROLE_STUDENT],
    'parent' => [Login::ROLE_PARENT],
]);

test('the edit page shows a confirmed reset button when two-factor is on', function (string $who, string $title) {
    actingAsRole(Login::ROLE_ADMIN);
    $target = twoFactorResetTarget($who);

    $this->get($target['edit'])
        ->assertOk()
        ->assertSee('<h2 class="card-title">'.$title.'</h2>', false)
        ->assertSee('action="'.$target['reset'].'"', false)
        ->assertSee('data-confirm-title="Reset two-factor for', false)
        ->assertSee('Reset Two-Factor');
})->with([
    'teacher' => ['teacher', 'Two-Factor Sign-In'],
    'student' => ['student', 'Two-Factor Sign-In'],
    'parent' => ['parent', 'Parent Two-Factor Sign-In'],
]);

test('the edit page shows no reset button when two-factor is not set up', function () {
    actingAsRole(Login::ROLE_ADMIN);
    $teacher = Teachers::factory()->create();

    $this->get(route('admin.teachers.edit', $teacher))
        ->assertOk()
        ->assertSee('Two-factor sign-in is not set up.')
        ->assertDontSee('action="'.route('admin.teachers.two-factor.reset', $teacher).'"', false);
});
