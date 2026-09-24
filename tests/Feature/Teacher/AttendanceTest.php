<?php

use App\Models\AcademicYear;
use App\Models\Attendance;
use App\Models\Divisions;
use App\Models\Login;
use App\Models\StudentClass;
use Illuminate\Support\Carbon;

beforeEach(function () {
    Carbon::setTestNow(Carbon::parse('next wednesday 10:00'));
    $this->year = AcademicYear::factory()->create(['is_active' => true]);
    $this->login = actingAsRole(Login::ROLE_TEACHER);
    $this->division = Divisions::factory()->create();
    $this->division->teachers()->attach($this->login->teacher->id, ['class_teacher' => false]);
    $this->students = StudentClass::factory()->count(3)->create(['class_division_id' => $this->division->id])->map->student;
});

afterEach(fn () => Carbon::setTestNow());

function attendancePayload(array $statuses = [], array $overrides = []): array
{
    $rows = [];
    foreach (test()->students as $index => $student) {
        $rows[$student->id] = ['status' => $statuses[$index] ?? Attendance::PRESENT, 'remark' => ''];
    }

    return ['division_id' => test()->division->id, 'date' => today()->toDateString(), 'attendance' => $rows, ...$overrides];
}

test('the attendance sheet lists enrolled students defaulting to present', function () {
    $response = $this->get('/teacher/attendance')->assertSuccessful();

    foreach ($this->students as $student) {
        $response->assertSee($student->first_name);
    }
    $response->assertSee('Not marked yet');
});

test('a teacher can mark attendance for their division', function () {
    $this->post('/teacher/attendance', attendancePayload([Attendance::PRESENT, Attendance::ABSENT, Attendance::LATE]))
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('teacher.attendance', ['division' => $this->division->id, 'date' => today()->toDateString()]));

    expect(Attendance::count())->toBe(3)
        ->and(Attendance::where('student_id', $this->students[1]->id)->value('status'))->toBe(Attendance::ABSENT)
        ->and(Attendance::first()->marked_by)->toBe($this->login->id)
        ->and(Attendance::first()->academic_year_id)->toBe($this->year->id);
});

test('saving again updates the same day instead of duplicating', function () {
    $this->post('/teacher/attendance', attendancePayload());
    $this->post('/teacher/attendance', attendancePayload([Attendance::EXCUSED]))->assertSessionHasNoErrors();

    expect(Attendance::count())->toBe(3)
        ->and(Attendance::where('student_id', $this->students[0]->id)->value('status'))->toBe(Attendance::EXCUSED);

    $this->get('/teacher/attendance')->assertSee('Already marked');
});

test('remarks are saved and sanitized', function () {
    $payload = attendancePayload();
    $payload['attendance'][$this->students[0]->id]['remark'] = '  <b>Doctor</b>   visit ';

    $this->post('/teacher/attendance', $payload);

    expect(Attendance::where('student_id', $this->students[0]->id)->value('remark'))->toBe('Doctor visit');
});

test('past dates can be marked but future dates cannot', function () {
    $lastMonday = today()->previous('monday')->toDateString();

    $this->post('/teacher/attendance', attendancePayload(overrides: ['date' => $lastMonday]))->assertSessionHasNoErrors();
    $this->post('/teacher/attendance', attendancePayload(overrides: ['date' => today()->addDay()->toDateString()]))->assertSessionHasErrors('date');
});

test('attendance cannot be marked on a non-school day', function () {
    config(['school.days' => [1, 2, 3, 4, 5]]);
    $sunday = today()->previous('sunday')->toDateString();

    $this->post('/teacher/attendance', attendancePayload(overrides: ['date' => $sunday]))
        ->assertSessionHasErrors(['date' => 'That date is not a school day.']);

    $this->get('/teacher/attendance?date='.$sunday)->assertSee('is not a school day');
});

test('invalid statuses and students from other divisions are rejected', function () {
    $this->post('/teacher/attendance', attendancePayload(['maybe']))->assertSessionHasErrors('attendance.'.$this->students[0]->id.'.status');

    $outsider = StudentClass::factory()->create()->student;
    $payload = attendancePayload();
    $payload['attendance'][$outsider->id] = ['status' => Attendance::PRESENT];

    $this->post('/teacher/attendance', $payload)->assertSessionHasErrors('attendance');
    expect(Attendance::count())->toBe(0);
});

test('a teacher cannot mark attendance for a division they do not teach', function () {
    $other = Divisions::factory()->create();

    $this->post('/teacher/attendance', attendancePayload(overrides: ['division_id' => $other->id]))->assertForbidden();
    $this->get('/teacher/attendance?division='.$other->id)->assertSuccessful()->assertDontSee($other->label);
});

test('an unknown division is a validation error, not a crash', function () {
    $this->post('/teacher/attendance', attendancePayload(overrides: ['division_id' => 999999]))->assertSessionHasErrors('division_id');
});

test('a future date in the sheet URL falls back to today', function () {
    $this->get('/teacher/attendance?date='.today()->addWeek()->toDateString())
        ->assertSuccessful()
        ->assertSee('value="'.today()->toDateString().'"', false);
});

test('recently marked days are listed with counts', function () {
    $this->post('/teacher/attendance', attendancePayload([Attendance::ABSENT]));

    $this->get('/teacher/attendance')->assertSee(today()->format('D, M j'))->assertSee('2/3');
});

test('the database allows one record per student per day', function () {
    Attendance::factory()->create(['student_id' => $this->students[0]->id, 'division_id' => $this->division->id]);

    expect(fn () => Attendance::factory()->create(['student_id' => $this->students[0]->id, 'division_id' => $this->division->id]))
        ->toThrow(Illuminate\Database\QueryException::class);
});
