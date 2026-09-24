<?php

use App\Models\AcademicYear;
use App\Models\Divisions;
use App\Models\Login;
use App\Models\Period;
use App\Models\Subjects;
use App\Models\Teachers;
use App\Models\TimetableEntry;

beforeEach(function () {
    $this->year = AcademicYear::factory()->create(['is_active' => true]);
    $this->division = Divisions::factory()->create();
    $this->subject = Subjects::factory()->create(['subject_name' => 'Geography']);
    $this->teacher = Teachers::factory()->create(['first_name' => 'Tess', 'last_name' => 'Map']);
    $this->teacher->subjects()->attach($this->subject->id);
    $this->division->teachers()->attach($this->teacher->id, ['class_teacher' => true]);
    $this->period = Period::query()->teaching()->ordered()->first();
    actingAsRole(Login::ROLE_ADMIN);
});

function slotPayload(array $overrides = []): array
{
    return [
        'division_id' => test()->division->id,
        'day' => 1,
        'period_id' => test()->period->id,
        'subject_id' => test()->subject->id,
        'teacher_id' => test()->teacher->id,
        ...$overrides,
    ];
}

test('the manager shows the first division with the bell schedule and breaks', function () {
    $this->get('/admin/timetable')
        ->assertSuccessful()
        ->assertSee($this->division->label)
        ->assertSee('Period 1')
        ->assertSee('08:30 - 09:20')
        ->assertSee('Lunch')
        ->assertSee('Monday')
        ->assertSee('Saturday')
        ->assertSee('Not published yet');
});

test('the manager warns when there is no active academic year', function () {
    $this->year->update(['is_active' => false]);

    $this->get('/admin/timetable')->assertSuccessful()->assertSee('no active academic year');
});

function giveTeacherMoreSubjects(int $count = 2): void
{
    test()->teacher->subjects()->attach(Subjects::factory()->count($count)->create()->pluck('id'));
}

test('generating all divisions saves a full timetable', function () {
    giveTeacherMoreSubjects();

    $this->post('/admin/timetable/generate')->assertRedirect(route('admin.timetable'));

    $slots = count(config('school.days')) * Period::query()->teaching()->count();
    expect(TimetableEntry::where('division_id', $this->division->id)->count())->toBe($slots);

    $this->get('/admin/timetable?division='.$this->division->id)
        ->assertSee('Geography')
        ->assertSee('Tess Map');
});

test('generating one division leaves other divisions untouched', function () {
    $other = Divisions::factory()->create();
    $otherTeacher = Teachers::factory()->create();
    $otherTeacher->subjects()->attach($this->subject->id);
    $other->teachers()->attach($otherTeacher->id, ['class_teacher' => false]);
    $this->post('/admin/timetable/generate');
    $before = TimetableEntry::where('division_id', $other->id)->pluck('id')->sort()->values()->all();

    $this->post('/admin/timetable/generate', ['division_id' => $this->division->id])
        ->assertRedirect(route('admin.timetable', ['division' => $this->division->id]));

    expect(TimetableEntry::where('division_id', $other->id)->pluck('id')->sort()->values()->all())->toBe($before);
});

test('generation problems are reported to the admin', function () {
    Divisions::factory()->create(); // no teachers assigned

    $this->post('/admin/timetable/generate')->assertSessionHas('timetable_issues', fn ($issues) => str_contains($issues[0], 'no active teachers'));
});

test('a slot can be set, changed and cleared', function () {
    $this->put('/admin/timetable/entries', slotPayload())->assertSessionHasNoErrors()->assertSessionHas('success');
    expect(TimetableEntry::count())->toBe(1);

    $newSubject = Subjects::factory()->create();
    $this->teacher->subjects()->attach($newSubject->id);
    $this->put('/admin/timetable/entries', slotPayload(['subject_id' => $newSubject->id]))->assertSessionHasNoErrors();
    expect(TimetableEntry::count())->toBe(1)->and(TimetableEntry::first()->subject_id)->toBe($newSubject->id);

    $this->delete('/admin/timetable/entries', slotPayload())->assertSessionHas('success');
    expect(TimetableEntry::count())->toBe(0);
});

test('a slot rejects a teacher who is not assigned to the division', function () {
    $outsider = Teachers::factory()->create();
    $outsider->subjects()->attach($this->subject->id);

    $this->put('/admin/timetable/entries', slotPayload(['teacher_id' => $outsider->id]))
        ->assertSessionHasErrors(['teacher_id' => "{$outsider->full_name} is not assigned to this division."]);
});

test('a slot rejects a subject the teacher does not teach', function () {
    $this->put('/admin/timetable/entries', slotPayload(['subject_id' => Subjects::factory()->create()->id]))
        ->assertSessionHasErrors('subject_id');
});

test('a slot rejects a teacher who is busy in another division', function () {
    $other = Divisions::factory()->create();
    $other->teachers()->attach($this->teacher->id, ['class_teacher' => false]);
    TimetableEntry::factory()->create([
        'division_id' => $other->id, 'day' => 1, 'period_id' => $this->period->id,
        'subject_id' => $this->subject->id, 'teacher_id' => $this->teacher->id,
    ]);

    $this->put('/admin/timetable/entries', slotPayload())
        ->assertSessionHasErrors(['teacher_id' => "{$this->teacher->full_name} is already teaching {$other->label} in that period."]);
});

test('a slot rejects an inactive teacher, a break period and a day outside the school week', function () {
    $this->put('/admin/timetable/entries', slotPayload(['period_id' => Period::where('is_break', true)->value('id')]))->assertSessionHasErrors('period_id');
    $this->put('/admin/timetable/entries', slotPayload(['day' => 7]))->assertSessionHasErrors('day');

    $this->teacher->login->update(['is_active' => false]);
    $this->put('/admin/timetable/entries', slotPayload())->assertSessionHasErrors('teacher_id');

    expect(TimetableEntry::count())->toBe(0);
});

test('editing requires an active academic year', function () {
    $this->year->update(['is_active' => false]);

    $this->put('/admin/timetable/entries', slotPayload())->assertSessionHasErrors('division_id');
});

test('the teacher view lists the teacher\'s lessons across divisions', function () {
    TimetableEntry::factory()->create(slotPayload());

    $this->get('/admin/timetable?teacher='.$this->teacher->id)
        ->assertSuccessful()
        ->assertSee('Tess Map')
        ->assertSee('Geography')
        ->assertSee($this->division->label);
});

test('a division timetable exports as CSV', function () {
    TimetableEntry::factory()->create(slotPayload());

    $response = $this->get('/admin/timetable/export?division='.$this->division->id)->assertSuccessful();
    $csv = $response->streamedContent();

    expect($response->headers->get('content-disposition'))->toContain('.csv')
        ->and($csv)->toContain('Period,Time,Monday')
        ->and($csv)->toContain('"Geography (Tess Map)"')
        ->and($csv)->toContain('Lunch');
});

test('a teacher timetable exports as CSV', function () {
    TimetableEntry::factory()->create(slotPayload());

    $csv = $this->get('/admin/timetable/export?teacher='.$this->teacher->id)->streamedContent();

    expect($csv)->toContain('Geography ('.$this->division->label.')');
});

test('publishing records the time', function () {
    $this->post('/admin/timetable/publish')->assertSessionHas('success');

    expect($this->year->fresh()->timetable_published_at)->not->toBeNull();
    $this->get('/admin/timetable')->assertSee('Last published');
});

test('the unscheduled panel lists missing lessons and fills up after generating', function () {
    $this->get('/admin/timetable')->assertSee('Geography - '.$this->division->label, false);

    giveTeacherMoreSubjects();
    $this->post('/admin/timetable/generate');

    $this->get('/admin/timetable')->assertSee('Every subject has all its lessons.');
});

test('lessons whose teacher was unassigned are flagged', function () {
    TimetableEntry::factory()->create(slotPayload());
    $this->division->teachers()->detach($this->teacher->id);

    $this->get('/admin/timetable')->assertSeeInOrder(['Needs attention', '1']);
});

test('the database blocks a teacher in two places at once', function () {
    $other = Divisions::factory()->create();
    TimetableEntry::factory()->create(slotPayload());

    expect(fn () => TimetableEntry::factory()->create(slotPayload(['division_id' => $other->id])))
        ->toThrow(Illuminate\Database\QueryException::class);
});
