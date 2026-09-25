<?php

use App\Models\AcademicYear;
use App\Models\Attendance;
use App\Models\Divisions;
use App\Models\Exam;
use App\Models\Login;
use App\Models\Mark;
use App\Models\Period;
use App\Models\StudentClass;
use App\Models\Subjects;
use App\Models\Teachers;
use App\Models\TimetableEntry;
use Illuminate\Support\Carbon;

beforeEach(function () {
    Carbon::setTestNow(Carbon::parse('next monday 09:00'));
    $this->year = AcademicYear::factory()->active()->create();
    $this->login = actingAsRole(Login::ROLE_STUDENT);
    $this->student = $this->login->student;
    $this->division = Divisions::factory()->create();
    StudentClass::factory()->create(['student_id' => $this->student->id, 'class_division_id' => $this->division->id]);
    $this->teacher = Teachers::factory()->create(['first_name' => 'Clara', 'last_name' => 'Barton']);
    $this->subject = Subjects::factory()->create(['subject_name' => 'Chemistry']);
    $this->teacher->subjects()->attach($this->subject->id);
    $this->division->teachers()->attach($this->teacher->id, ['class_teacher' => true]);
});

afterEach(fn () => Carbon::setTestNow());

function mondayLesson(): TimetableEntry
{
    return TimetableEntry::factory()->create([
        'division_id' => test()->division->id, 'day' => 1,
        'period_id' => Period::query()->teaching()->ordered()->value('id'),
        'subject_id' => test()->subject->id, 'teacher_id' => test()->teacher->id,
    ]);
}

function markAttendance(string $date, string $status, ?string $remark = null): void
{
    Attendance::factory()->create([
        'student_id' => test()->student->id, 'division_id' => test()->division->id,
        'date' => $date, 'status' => $status, 'remark' => $remark,
    ]);
}

test('the dashboard shows class, class teacher, attendance and today\'s lessons', function () {
    $this->year->update(['timetable_published_at' => now()]);
    mondayLesson();
    markAttendance('2026-01-05', Attendance::PRESENT);
    markAttendance('2026-01-06', Attendance::ABSENT, 'Flu');
    markAttendance('2026-01-07', Attendance::LATE);
    markAttendance('2026-01-08', Attendance::PRESENT);

    $this->get('/student/dashboard')
        ->assertSuccessful()
        ->assertSee('Welcome, '.$this->student->first_name)
        ->assertSee($this->division->label)
        ->assertSee('Class teacher: Clara Barton')
        ->assertSee('75%')
        ->assertSee('Chemistry')
        ->assertSee('Flu');
});

test('the timetable only appears once published', function () {
    mondayLesson();

    $this->get('/student/timetable')->assertSuccessful()->assertSee('has not been published yet')->assertDontSee('Clara Barton');

    $this->year->update(['timetable_published_at' => now()]);

    $this->get('/student/timetable')->assertSuccessful()->assertSee('Chemistry')->assertSee('Clara Barton');
});

test('a student without a class is told so', function () {
    StudentClass::where('student_id', $this->student->id)->delete();

    $this->get('/student/dashboard')->assertSuccessful()->assertSee('Not enrolled in a class this year');
    $this->get('/student/timetable')->assertSee('is not enrolled in a class this year');
});

test('attendance history shows summary and can be filtered by month', function () {
    markAttendance('2026-01-05', Attendance::ABSENT, 'Dentist');
    markAttendance('2026-02-02', Attendance::EXCUSED, 'Trip');

    $this->get('/student/attendance')->assertSuccessful()->assertSee('Dentist')->assertSee('Trip')->assertSee('January 2026');

    $this->get('/student/attendance?month=2026-02')->assertSee('Trip')->assertDontSee('Dentist');
    $this->get('/student/attendance?month=garbage')->assertSuccessful()->assertSee('Dentist');
});

test('results list only published exams, with the report card', function () {
    $published = Exam::factory()->published()->create(['name' => 'Spring Test']);
    $draft = Exam::factory()->create(['name' => 'Secret Draft']);
    foreach ([$published, $draft] as $exam) {
        Mark::factory()->create(['exam_id' => $exam->id, 'student_id' => $this->student->id, 'subject_id' => $this->subject->id, 'division_id' => $this->division->id, 'marks' => 88]);
    }

    $this->get('/student/results')->assertSuccessful()->assertSee('Spring Test')->assertSee('88.0%')->assertDontSee('Secret Draft');
    $this->get("/student/results/{$published->id}")->assertSuccessful()->assertSee('88 / 100');
    $this->get("/student/results/{$draft->id}")->assertNotFound();
});

test('the dashboard shows the latest published result', function () {
    $exam = Exam::factory()->published()->create(['name' => 'Spring Test']);
    Mark::factory()->create(['exam_id' => $exam->id, 'student_id' => $this->student->id, 'subject_id' => $this->subject->id, 'division_id' => $this->division->id, 'marks' => 72]);

    $this->get('/student/dashboard')->assertSee('72.0%')->assertSeeInOrder(['Spring Test', '72.0%', 'Pass']);
});

test('a student cannot use admin, teacher or parent pages', function () {
    $this->get('/admin/view/student/'.$this->student->id)->assertForbidden();
    $this->get('/teacher/dashboard')->assertForbidden();
    $this->get('/parent/children/'.$this->student->id)->assertForbidden();
    $this->get('/marks')->assertForbidden();
});

test('a student login without a student profile is refused politely', function () {
    $this->actingAs(Login::factory()->student()->create());

    $this->get('/student/dashboard')->assertForbidden()->assertSee('no student profile');
});
