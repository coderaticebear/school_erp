<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Login;
use App\Models\Students;
use App\Models\Teachers;
use App\Services\TwoFactorAuth;
use Illuminate\Http\RedirectResponse;

/**
 * An admin resets the two-factor sign-in of a teacher, student or parent who lost their device (SEC-06, FR-2FA-12).
 * The routes need a recent admin password (`password.confirm`). An admin account is never reset here: only the
 * `two-factor:reset` command can reset an admin.
 */
class TwoFactorResetController extends Controller
{
    public function __construct(private TwoFactorAuth $twoFactor) {}

    /**
     * Reset the student's own two-factor sign-in.
     */
    public function student(Students $student): RedirectResponse
    {
        $student->load('login');

        return $this->reset($student->login, Login::ROLE_STUDENT, "{$student->first_name} {$student->last_name}");
    }

    /**
     * Reset the two-factor sign-in of the student's parent, from the Edit Student page.
     */
    public function parent(Students $student): RedirectResponse
    {
        $student->load('parent.login');
        $parent = $student->parent;

        abort_if($parent === null, 404);

        return $this->reset($parent->login, Login::ROLE_PARENT, "{$parent->first_name} {$parent->last_name}");
    }

    /**
     * Reset the teacher's two-factor sign-in.
     */
    public function teacher(Teachers $teacher): RedirectResponse
    {
        $teacher->load('login');

        return $this->reset($teacher->login, Login::ROLE_TEACHER, $teacher->full_name);
    }

    /**
     * Reset the login when it has the expected role. An admin login (or any other role) gets 403.
     */
    private function reset(?Login $login, int $expectedRole, string $name): RedirectResponse
    {
        abort_if($login === null, 404);
        abort_if($login->role !== $expectedRole, 403);

        $this->twoFactor->reset($login);

        return back()->with('success', "Two-factor sign-in reset for {$name}. They are signed out and set it up again at their next sign-in.");
    }
}
