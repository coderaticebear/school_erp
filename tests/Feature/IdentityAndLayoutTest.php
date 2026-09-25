<?php

/**
 * Design review Step D: school identity, subject colours and phone layouts.
 */

use App\Models\AcademicYear;
use App\Models\Divisions;
use App\Models\Login;
use App\Models\Period;
use App\Models\StudentClass;
use App\Models\Subjects;
use App\Models\Teachers;
use App\Models\TimetableEntry;
use App\Services\SchoolIdentity;
use App\Services\TimetableGrid;
use Illuminate\Support\Carbon;

afterEach(fn () => Carbon::setTestNow());

test('crest initials come from the school name, skipping minor words', function (string $name, ?string $override, string $initials) {
    config(['school.name' => $name, 'school.initials' => $override]);

    expect(SchoolIdentity::initials())->toBe($initials);
})->with([
    'two words' => ['Riverside Academy', null, 'RA'],
    'minor words skipped' => ['School of the Arts', null, 'SA'],
    'one word' => ['Greenfield', null, 'G'],
    'override wins' => ['St. Mary\'s Convent School', 'SM', 'SM'],
    'punctuation ignored' => ['St. Mary\'s Convent', null, 'SM'],
]);

test('the school name and crest appear in the sidebar and on sign-in', function () {
    config(['school.name' => 'Riverside Academy', 'school.initials' => null]);

    $this->get('/login')
        ->assertSee('<span class="school-wordmark">Riverside Academy</span>', false)
        ->assertSee('>RA</text>', false)
        ->assertDontSee('AdminLTELogo');

    AcademicYear::factory()->active()->create();
    actingAsRole(Login::ROLE_ADMIN);

    $this->get('/admin/dashboard')
        ->assertSee('Riverside Academy</span>', false)
        ->assertSee('class="main-sidebar sidebar-light-primary', false);
});

test('a subject keeps the same colour everywhere', function () {
    $subject = Subjects::factory()->create();

    expect(TimetableGrid::colorFor($subject->id))->toBe(TimetableGrid::colorFor($subject->id))
        ->and(str_replace('bg-soft-', '', TimetableGrid::colorFor($subject->id)))->toBe(str_replace('dot-', '', TimetableGrid::dotFor($subject->id)));

    actingAsRole(Login::ROLE_ADMIN);
    $this->get('/subjects')->assertSee('subject-chip '.TimetableGrid::colorFor($subject->id), false);
});

test('the student timetable has a phone day list that opens on today', function () {
    Carbon::setTestNow(Carbon::parse('next wednesday 09:00'));
    $year = AcademicYear::factory()->active()->create(['timetable_published_at' => now()]);
    $login = actingAsRole(Login::ROLE_STUDENT);
    $division = Divisions::factory()->create();
    StudentClass::factory()->create(['student_id' => $login->student->id, 'class_division_id' => $division->id]);
    $teacher = Teachers::factory()->create(['first_name' => 'Wendy', 'last_name' => 'Wednesday']);
    $subject = Subjects::factory()->create(['subject_name' => 'Geology']);
    TimetableEntry::factory()->create(['division_id' => $division->id, 'day' => 3, 'period_id' => Period::query()->teaching()->ordered()->value('id'), 'subject_id' => $subject->id, 'teacher_id' => $teacher->id]);

    $html = $this->get('/student/timetable')->assertSuccessful()->getContent();

    expect($html)->toContain('data-timetable-days')
        ->and($html)->toMatch('/aria-selected="true"[^>]*>\s*Wed\s*<span class="timetable-day-today">Today<\/span>/')
        ->and($html)->toContain('Wednesday · Today')
        ->and($html)->toContain('Period 1 · Wendy Wednesday')
        ->and($html)->toContain('class="d-none d-md-block timetable-grid-wrap"');
});

test('the attendance sheet uses responsive rows with a remark toggle and live totals', function () {
    Carbon::setTestNow(Carbon::parse('next monday 10:00'));
    AcademicYear::factory()->active()->create();
    $login = actingAsRole(Login::ROLE_TEACHER);
    $division = Divisions::factory()->create();
    $division->teachers()->attach($login->teacher->id, ['class_teacher' => true]);
    StudentClass::factory()->count(2)->create(['class_division_id' => $division->id]);

    $this->get('/teacher/attendance')
        ->assertSee('class="attendance-row"', false)
        ->assertSee('+ Add remark')
        ->assertSee('id="attendance-summary"', false)
        ->assertDontSee('<table class="table table-hover mb-0">', false);
});
