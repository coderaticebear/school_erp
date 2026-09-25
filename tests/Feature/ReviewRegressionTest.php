<?php

/**
 * Regression tests for issues found in the code review of feature/erp-completion.
 */

use App\Models\AcademicYear;
use App\Models\Attendance;
use App\Models\Divisions;
use App\Models\Exam;
use App\Models\Login;
use App\Models\Mark;
use App\Models\StudentClass;
use App\Models\Subjects;
use App\Models\TimetableEntry;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;

afterEach(fn () => Carbon::setTestNow());

test('attendance month labels are right at the end of a month and bad months are ignored', function () {
    Carbon::setTestNow(Carbon::parse('2026-03-31 10:00'));
    AcademicYear::factory()->active()->create();
    $login = actingAsRole(Login::ROLE_STUDENT);
    $enrolment = StudentClass::factory()->create(['student_id' => $login->student->id]);
    Attendance::factory()->create(['student_id' => $login->student->id, 'division_id' => $enrolment->class_division_id, 'date' => '2026-02-10', 'remark' => 'Feb record']);

    $this->get('/student/attendance')->assertSee('February 2026')->assertDontSee('March 2026');
    $this->get('/student/attendance?month=2026-13')->assertSuccessful()->assertSee('Feb record');
});

test('a deactivated user is signed out of an existing session', function () {
    $login = actingAsRole(Login::ROLE_TEACHER);
    $this->get('/teacher/dashboard')->assertSuccessful();

    $login->update(['is_active' => false]);

    $this->get('/teacher/dashboard')->assertRedirect(route('login'))->assertSessionHasErrors('email');
    $this->assertGuest();
});

test('a deactivated user cannot get in through the dashboard redirect', function () {
    $login = actingAsRole(Login::ROLE_PARENT);
    $login->update(['is_active' => false]);

    $this->get('/dashboard')->assertRedirect(route('login'));
    $this->assertGuest();
});

test('a password reset lands on the dashboard, not a missing /home page', function () {
    Notification::fake();
    $login = Login::factory()->admin()->create();
    $this->post('/password/email', ['email' => $login->email]);

    Notification::assertSentTo($login, ResetPassword::class, function (ResetPassword $notification) use ($login) {
        $this->post('/password/reset', [
            'token' => $notification->token, 'email' => $login->email,
            'password' => 'new-password-1', 'password_confirmation' => 'new-password-1',
        ])->assertRedirect('/dashboard');

        return true;
    });
});

test('a division with attendance or marks history cannot be deleted even without current students', function (string $history) {
    actingAsRole(Login::ROLE_ADMIN);
    $enrolment = StudentClass::factory()->create();
    $division = $enrolment->division;

    $history === 'attendance'
        ? Attendance::factory()->create(['student_id' => $enrolment->student_id, 'division_id' => $division->id])
        : Mark::factory()->create(['student_id' => $enrolment->student_id, 'division_id' => $division->id]);

    // Student moves to another division; the old one has only history left.
    $enrolment->update(['class_division_id' => Divisions::factory()->create()->id]);

    $this->delete("/admin/divisions/{$division->id}")->assertSessionHas('error');
    $this->delete("/admin/classes/{$division->class_id}")->assertSessionHas('error');

    expect(Divisions::find($division->id))->not->toBeNull();
})->with(['attendance', 'marks']);

test('a subject used in the timetable or with marks cannot be deleted', function (string $usage) {
    actingAsRole(Login::ROLE_ADMIN);
    $subject = Subjects::factory()->create();

    $usage === 'timetable'
        ? TimetableEntry::factory()->create(['subject_id' => $subject->id])
        : Mark::factory()->create(['subject_id' => $subject->id]);

    $this->delete("/admin/subjects/{$subject->id}")->assertSessionHas('error');

    expect(Subjects::find($subject->id))->not->toBeNull();
})->with(['timetable', 'marks']);

test('an academic year with exams, attendance or a timetable cannot be deleted', function (string $usage) {
    actingAsRole(Login::ROLE_ADMIN);
    $active = AcademicYear::factory()->active()->create();
    $old = AcademicYear::factory()->create();

    match ($usage) {
        'exam' => Exam::factory()->create(['academic_year_id' => $old->id]),
        'attendance' => (function () use ($old) {
            $enrolment = StudentClass::factory()->create();
            Attendance::factory()->create(['academic_year_id' => $old->id, 'student_id' => $enrolment->student_id, 'division_id' => $enrolment->class_division_id]);
        })(),
        'timetable' => TimetableEntry::factory()->create(['academic_year_id' => $old->id]),
    };

    $this->delete("/admin/academic-years/{$old->id}")->assertSessionHas('error');

    expect(AcademicYear::find($old->id))->not->toBeNull();
})->with(['exam', 'attendance', 'timetable']);

test('a full mark on a 1000-mark exam can be saved', function () {
    AcademicYear::factory()->active()->create();
    actingAsRole(Login::ROLE_ADMIN);
    $exam = Exam::factory()->create(['max_marks' => 1000, 'pass_marks' => 400]);
    $enrolment = StudentClass::factory()->create();
    $subject = Subjects::factory()->create();

    $this->post("/marks/{$exam->id}/{$enrolment->class_division_id}/{$subject->id}", [
        'marks' => [$enrolment->student_id => ['value' => '1000']],
    ])->assertSessionHasNoErrors();

    expect(Mark::first()->marks)->toBe(1000.0);
});
