<?php

use App\Models\AcademicYear;
use App\Models\Divisions;
use App\Models\Period;
use App\Models\Subjects;
use App\Models\Teachers;
use App\Models\TimetableEntry;
use App\Services\TimetableGenerator;
use Illuminate\Support\Collection;

/**
 * Build a school: divisions that each get a teacher per subject, with some teachers shared across divisions.
 *
 * @return array{year: AcademicYear, divisions: Collection, subjects: Collection, teachers: Collection}
 */
function buildSchool(int $divisionCount = 3, int $subjectCount = 6): array
{
    $year = AcademicYear::factory()->create(['is_active' => true]);
    $subjects = Subjects::factory()->count($subjectCount)->create();
    $divisions = Divisions::factory()->count($divisionCount)->create();
    $teachers = collect();

    // Two teachers per subject, each teaching that subject in some divisions.
    foreach ($subjects as $subject) {
        $pair = Teachers::factory()->count(2)->create();
        $pair->each(fn ($teacher) => $teacher->subjects()->attach($subject->id));
        $teachers = $teachers->merge($pair);

        foreach ($divisions as $index => $division) {
            $division->teachers()->syncWithoutDetaching([$pair[$index % 2]->id => ['class_teacher' => false]]);
        }
    }

    return ['year' => $year, 'divisions' => Divisions::with('class')->get(), 'subjects' => $subjects, 'teachers' => $teachers];
}

function slotCount(): int
{
    return count(config('school.days')) * Period::query()->teaching()->count();
}

test('a feasible school is fully scheduled with no clashes', function () {
    ['year' => $year, 'divisions' => $divisions] = buildSchool();

    $result = app(TimetableGenerator::class)->generate($year, $divisions, seed: 7);
    $entries = collect($result['entries']);

    expect($result['unscheduled'])->toBe([])
        ->and($result['issues'])->toBe([])
        ->and($entries)->toHaveCount($divisions->count() * slotCount());

    // No teacher in two places, no division double-booked.
    expect($entries->unique(fn ($e) => "{$e['teacher_id']}:{$e['day']}:{$e['period_id']}"))->toHaveCount($entries->count())
        ->and($entries->unique(fn ($e) => "{$e['division_id']}:{$e['day']}:{$e['period_id']}"))->toHaveCount($entries->count());

    // One teacher per subject per division, and at most 2 of a subject per day (3 only when relaxed).
    $entries->groupBy(fn ($e) => "{$e['division_id']}:{$e['subject_id']}")
        ->each(fn ($group) => expect($group->pluck('teacher_id')->unique())->toHaveCount(1));
    expect($entries->groupBy(fn ($e) => "{$e['division_id']}:{$e['day']}:{$e['subject_id']}")->map->count()->max())->toBeLessThanOrEqual(3);
});

test('only break-free periods and configured days are used', function () {
    ['year' => $year, 'divisions' => $divisions] = buildSchool(1, 4);
    config(['school.days' => [1, 2, 3, 4, 5]]);
    $breakIds = Period::query()->where('is_break', true)->pluck('id');

    $entries = collect(app(TimetableGenerator::class)->generate($year, $divisions, seed: 1)['entries']);

    expect($entries->pluck('day')->unique()->sort()->values()->all())->toBe([1, 2, 3, 4, 5])
        ->and($entries->pluck('period_id')->intersect($breakIds))->toBeEmpty();
});

test('each teacher only teaches subjects they teach, in divisions they are assigned to', function () {
    ['year' => $year, 'divisions' => $divisions] = buildSchool();

    $entries = app(TimetableGenerator::class)->generate($year, $divisions, seed: 3)['entries'];

    foreach ($entries as $entry) {
        $teacher = Teachers::find($entry['teacher_id']);
        expect($teacher->subjects->pluck('id'))->toContain($entry['subject_id'])
            ->and($teacher->divisions->pluck('id'))->toContain($entry['division_id']);
    }
});

test('weekly periods per subject are respected and the rest is shared evenly', function () {
    ['year' => $year, 'divisions' => $divisions, 'subjects' => $subjects] = buildSchool(1, 4);
    $subjects[0]->update(['periods_per_week' => 12]);

    $entries = collect(app(TimetableGenerator::class)->generate($year, $divisions, seed: 5)['entries']);
    $counts = $entries->countBy('subject_id');

    $rest = slotCount() - 12;
    expect($counts[$subjects[0]->id])->toBe(12);
    foreach ($subjects->slice(1) as $subject) {
        expect($counts[$subject->id])->toBeIn([intdiv($rest, 3), intdiv($rest, 3) + 1]);
    }
});

test('the same seed gives the same timetable', function () {
    ['year' => $year, 'divisions' => $divisions] = buildSchool();
    $generator = app(TimetableGenerator::class);

    expect($generator->generate($year, $divisions, seed: 99)['entries'])
        ->toBe($generator->generate($year, $divisions, seed: 99)['entries']);
});

test('a division with no teachers is reported and left empty', function () {
    ['year' => $year] = buildSchool(1);
    $empty = Divisions::factory()->create();

    $result = app(TimetableGenerator::class)->generate($year, Divisions::with('class')->get(), seed: 1);

    expect(collect($result['entries'])->where('division_id', $empty->id))->toBeEmpty()
        ->and($result['issues'])->toHaveCount(1)
        ->and($result['issues'][0])->toContain('no active teachers');
});

test('inactive teachers are never scheduled', function () {
    ['year' => $year, 'divisions' => $divisions, 'teachers' => $teachers] = buildSchool(2, 3);
    $inactive = $teachers->first();
    $inactive->login->update(['is_active' => false]);

    $entries = collect(app(TimetableGenerator::class)->generate($year, $divisions, seed: 2)['entries']);

    expect($entries->pluck('teacher_id'))->not->toContain($inactive->id);
});

test('regenerating one division keeps the others and avoids their teachers\' busy slots', function () {
    ['year' => $year, 'divisions' => $divisions] = buildSchool(3);
    $generator = app(TimetableGenerator::class);

    $all = $generator->generate($year, $divisions, seed: 11);
    $generator->save($year, $divisions, $all['entries']);
    $otherEntries = TimetableEntry::where('division_id', '!=', $divisions[0]->id)->orderBy('id')->get(['id', 'subject_id', 'teacher_id', 'day', 'period_id'])->toArray();

    $one = $generator->generate($year, $divisions->take(1), seed: 12);
    $generator->save($year, $divisions->take(1), $one['entries']);

    expect(TimetableEntry::where('division_id', '!=', $divisions[0]->id)->orderBy('id')->get(['id', 'subject_id', 'teacher_id', 'day', 'period_id'])->toArray())->toBe($otherEntries)
        ->and(TimetableEntry::where('division_id', $divisions[0]->id)->count())->toBe(count($one['entries']));

    // Saving would have thrown on the teacher-slot unique index if there were a clash, but check explicitly too.
    $all = TimetableEntry::all();
    expect($all->unique(fn ($e) => "{$e->teacher_id}:{$e->day}:{$e->period_id}"))->toHaveCount($all->count());
});

test('asking for more periods than the week has is reported', function () {
    ['year' => $year, 'divisions' => $divisions, 'subjects' => $subjects] = buildSchool(1, 2);
    $subjects->each->update(['periods_per_week' => slotCount()]);

    $result = app(TimetableGenerator::class)->generate($year, $divisions, seed: 1);

    expect($result['issues'][0])->toContain('only '.slotCount().' exist')
        ->and(array_sum($result['unscheduled'][$divisions[0]->id]))->toBe(slotCount());
});

test('an over-booked teacher leaves lessons unscheduled instead of clashing', function () {
    $year = AcademicYear::factory()->create(['is_active' => true]);
    $subject = Subjects::factory()->create();
    $teacher = Teachers::factory()->create();
    $teacher->subjects()->attach($subject->id);
    $divisions = Divisions::factory()->count(2)->create();
    $divisions->each(fn ($division) => $division->teachers()->attach($teacher->id, ['class_teacher' => false]));

    $result = app(TimetableGenerator::class)->generate($year, Divisions::with('class')->get(), seed: 1);

    // One teacher, one subject, two divisions each wanting every slot: only one slot count fits.
    expect(count($result['entries']))->toBe(slotCount())
        ->and(array_sum(array_map('array_sum', $result['unscheduled'])))->toBe(slotCount());
});

test('saving replaces only the targeted divisions', function () {
    ['year' => $year, 'divisions' => $divisions] = buildSchool(2, 3);
    $generator = app(TimetableGenerator::class);
    $generator->save($year, $divisions, $generator->generate($year, $divisions, seed: 1)['entries']);

    $generator->save($year, $divisions->take(1), []);

    expect(TimetableEntry::where('division_id', $divisions[0]->id)->count())->toBe(0)
        ->and(TimetableEntry::where('division_id', $divisions[1]->id)->count())->toBe(slotCount());
});
