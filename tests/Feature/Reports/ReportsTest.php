<?php

use App\Models\AcademicYear;
use App\Models\Attendance;
use App\Models\Divisions;
use App\Models\Exam;
use App\Models\Login;
use App\Models\Mark;
use App\Models\StudentClass;
use App\Models\Students;
use App\Models\Subjects;
use App\Models\Teachers;
use Illuminate\Support\Carbon;

beforeEach(function () {
    Carbon::setTestNow(Carbon::parse('2026-03-18 10:00'));
    $this->year = AcademicYear::factory()->active()->create();
    $this->division = Divisions::factory()->create();
    $this->other = Divisions::factory()->create();
    $this->good = StudentClass::factory()->create(['class_division_id' => $this->division->id, 'student_id' => Students::factory()->create(['first_name' => 'Goodie'])])->student;
    $this->poor = StudentClass::factory()->create(['class_division_id' => $this->division->id, 'student_id' => Students::factory()->create(['first_name' => 'Poorly'])])->student;
    actingAsRole(Login::ROLE_ADMIN);
});

afterEach(fn () => Carbon::setTestNow());

function attend(Students $student, string $date, string $status): void
{
    Attendance::factory()->create(['student_id' => $student->id, 'division_id' => test()->division->id, 'date' => $date, 'status' => $status]);
}

function seedMarch(): void
{
    foreach (['2026-03-02', '2026-03-03', '2026-03-04', '2026-03-05'] as $date) {
        attend(test()->good, $date, Attendance::PRESENT);
    }
    attend(test()->poor, '2026-03-02', Attendance::PRESENT);
    attend(test()->poor, '2026-03-03', Attendance::ABSENT);
    attend(test()->poor, '2026-03-04', Attendance::ABSENT);
    attend(test()->poor, '2026-03-05', Attendance::LATE);
    attend(test()->poor, '2026-02-10', Attendance::ABSENT); // outside the default range
}

test('the attendance report summarises each division for this month by default', function () {
    seedMarch();

    $this->get('/admin/reports/attendance')
        ->assertSuccessful()
        ->assertSee('Mar 1, 2026 – Mar 18, 2026')
        ->assertSee($this->division->label)
        ->assertSee('75%')          // 6 attended of 8 records
        ->assertSee('Not marked');  // the other division
});

test('the low attendance list flags students under the threshold', function () {
    seedMarch();

    $this->get('/admin/reports/attendance')
        ->assertSee('Poorly')       // 2 of 4 = 50%
        ->assertSee('50%')
        ->assertDontSee('Goodie');
});

test('choosing a division lists every student in it, lowest first', function () {
    seedMarch();

    $this->get('/admin/reports/attendance?division='.$this->division->id)
        ->assertSuccessful()
        ->assertSeeInOrder(['Poorly', '50%', 'Goodie', '100%']);
});

test('the date range filters records and swaps a reversed range', function () {
    seedMarch();

    $this->get('/admin/reports/attendance?division='.$this->division->id.'&from=2026-02-28&to=2026-02-01')
        ->assertSee('Feb 1, 2026 – Feb 28, 2026')
        ->assertSee('Poorly')
        ->assertSee('0%')
        ->assertDontSee('Goodie');

    $this->get('/admin/reports/attendance?from=not-a-date')->assertSuccessful()->assertSee('Mar 1, 2026');
});

test('the attendance report exports students as CSV', function () {
    seedMarch();

    $csv = $this->get('/admin/reports/attendance?division='.$this->division->id.'&format=csv')->streamedContent();

    expect($csv)->toContain('Student,Division,Present,Late,Absent,Excused,Days,Percent')
        ->toContain('Poorly')
        ->toContain(',1,1,2,0,4,50');
});

test('the exam report shows pass rate, average and top student per division and subject', function () {
    $exam = Exam::factory()->published()->create(['name' => 'Spring Exam']);
    $subject = Subjects::factory()->create(['subject_name' => 'Zoology']);
    $teacher = Teachers::factory()->create();
    $teacher->subjects()->attach($subject->id);
    $this->division->teachers()->attach($teacher->id, ['class_teacher' => true]);
    Mark::factory()->create(['exam_id' => $exam->id, 'student_id' => $this->good->id, 'subject_id' => $subject->id, 'division_id' => $this->division->id, 'marks' => 90]);
    Mark::factory()->create(['exam_id' => $exam->id, 'student_id' => $this->poor->id, 'subject_id' => $subject->id, 'division_id' => $this->division->id, 'marks' => 30]);

    $this->get('/admin/reports/exams')
        ->assertSuccessful()
        ->assertSee('Spring Exam')
        ->assertSee('50%')             // 1 of 2 passed
        ->assertSee('60%')             // average of 90% and 30%
        ->assertSee('Goodie')
        ->assertSee('Zoology');

    $csv = $this->get('/admin/reports/exams?exam='.$exam->id.'&format=csv')->streamedContent();
    expect($csv)->toContain('Division,Students,Complete,Passed')->toContain(',2,2,1,50,60,');
});

test('the exam report handles no exams', function () {
    $this->get('/admin/reports/exams')->assertSuccessful()->assertSee('no exams');
});

test('the admin dashboard shows real counts, today\'s attendance and the latest pass rate', function () {
    attend($this->good, '2026-03-18', Attendance::PRESENT);
    attend($this->poor, '2026-03-18', Attendance::ABSENT);
    $this->poor->login->update(['is_active' => false]);

    $this->get('/admin/dashboard')
        ->assertSuccessful()
        ->assertSee('Active students')
        ->assertSeeInOrder(['<h3>1</h3>', 'Active students'], false)
        ->assertSee('50%')
        ->assertSee('1/2 divisions marked')
        ->assertSee('No published results yet')
        ->assertDontSee('95%');
});

test('reports are admin-only', function (int $role) {
    actingAsRole($role);

    $this->get('/admin/reports/attendance')->assertForbidden();
    $this->get('/admin/reports/exams')->assertForbidden();
})->with(['teacher' => Login::ROLE_TEACHER, 'student' => Login::ROLE_STUDENT, 'parent' => Login::ROLE_PARENT]);
