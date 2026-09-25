<?php

use App\Models\AcademicYear;
use App\Models\Attendance;
use App\Models\Divisions;
use App\Models\Login;
use App\Models\Period;
use App\Models\StudentClass;
use App\Models\Subjects;
use App\Models\TimetableEntry;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->year = AcademicYear::factory()->create(['is_active' => true]);
    $this->login = actingAsRole(Login::ROLE_TEACHER);
    $this->teacher = $this->login->teacher;
    $this->subject = Subjects::factory()->create(['subject_name' => 'Physics']);
    $this->teacher->subjects()->attach($this->subject->id);
    $this->division = Divisions::factory()->create();
    $this->division->teachers()->attach($this->teacher->id, ['class_teacher' => true]);
});

afterEach(fn () => Carbon::setTestNow());

function lessonToday(): TimetableEntry
{
    Carbon::setTestNow(Carbon::parse('next monday 09:00'));

    return TimetableEntry::factory()->create([
        'division_id' => test()->division->id,
        'day' => 1,
        'period_id' => Period::query()->teaching()->ordered()->value('id'),
        'subject_id' => test()->subject->id,
        'teacher_id' => test()->teacher->id,
    ]);
}

test('the dashboard greets the teacher and lists their divisions', function () {
    $this->get('/teacher/dashboard')
        ->assertSuccessful()
        ->assertSee($this->teacher->full_name)
        ->assertSee($this->division->label)
        ->assertSee('Class Teacher')
        ->assertSee('Mark Attendance');
});

test('the dashboard shows today\'s lessons only once the timetable is published', function () {
    lessonToday();

    $this->get('/teacher/dashboard')->assertSee('has not been published yet')->assertDontSee('08:30 - 09:20');

    $this->year->update(['timetable_published_at' => now()]);

    $this->get('/teacher/dashboard')->assertSee('08:30 - 09:20')->assertSee('Physics');
});

test('the dashboard shows when attendance is already marked', function () {
    Carbon::setTestNow(Carbon::parse('next monday 09:00'));
    $enrolment = StudentClass::factory()->create(['class_division_id' => $this->division->id]);
    Attendance::factory()->create(['student_id' => $enrolment->student_id, 'division_id' => $this->division->id, 'date' => today()]);

    $this->get('/teacher/dashboard')->assertSee('Marked')->assertSee('1/1');
});

test('my timetable is hidden until published, then shows the teacher\'s week', function () {
    lessonToday();

    $this->get('/teacher/timetable')->assertSuccessful()->assertSee('has not been published yet');

    $this->year->update(['timetable_published_at' => now()]);

    $this->get('/teacher/timetable')
        ->assertSuccessful()
        ->assertSee('Physics')
        ->assertSee($this->division->label);
});

test('my classes lists assigned divisions with student counts and scheduled subjects', function () {
    StudentClass::factory()->count(2)->create(['class_division_id' => $this->division->id]);
    lessonToday();
    $notMine = Divisions::factory()->create();

    $this->get('/teacher/classes')
        ->assertSuccessful()
        ->assertSee($this->division->label)
        ->assertSee('2 students')
        ->assertSee('Physics')
        ->assertDontSee($notMine->label);
});

test('the roster lists enrolled students with parent contact and attendance rate', function () {
    $enrolment = StudentClass::factory()->create(['class_division_id' => $this->division->id]);
    $student = $enrolment->student;
    Attendance::factory()->create(['student_id' => $student->id, 'division_id' => $this->division->id, 'date' => '2026-01-05', 'status' => Attendance::PRESENT]);
    Attendance::factory()->create(['student_id' => $student->id, 'division_id' => $this->division->id, 'date' => '2026-01-06', 'status' => Attendance::ABSENT]);

    $this->get("/teacher/classes/{$this->division->id}")
        ->assertSuccessful()
        ->assertSee($student->first_name)
        ->assertSee($student->parent->login->email)
        ->assertSee('50%');
});

test('a teacher cannot see the roster of a division they do not teach', function () {
    $other = Divisions::factory()->create();

    $this->get("/teacher/classes/{$other->id}")->assertForbidden();
});

test('a teacher login without a teacher profile is refused politely', function () {
    $this->actingAs(Login::factory()->teacher()->create());

    $this->get('/teacher/dashboard')->assertForbidden()->assertSee('no teacher profile');
});

test('other roles cannot use the teacher portal', function (int $role) {
    actingAsRole($role);

    $this->get('/teacher/timetable')->assertForbidden();
    $this->get('/teacher/attendance')->assertForbidden();
    $this->post('/teacher/attendance', [])->assertForbidden();
})->with([
    'admin' => Login::ROLE_ADMIN,
    'student' => Login::ROLE_STUDENT,
    'parent' => Login::ROLE_PARENT,
]);
