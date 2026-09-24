<?php

use App\Models\AcademicYear;
use App\Models\Divisions;
use App\Models\Exam;
use App\Models\Login;
use App\Models\Mark;
use App\Models\StudentClass;
use App\Models\Students;
use App\Models\Subjects;
use App\Models\Teachers;
use App\Services\ExamResults;

beforeEach(function () {
    $this->year = AcademicYear::factory()->create(['is_active' => true]);
    $this->exam = Exam::factory()->create(['max_marks' => 100, 'pass_marks' => 40]);
    $this->division = Divisions::factory()->create();
    $this->subjects = Subjects::factory()->count(2)->create();
    $teacher = Teachers::factory()->create();
    $teacher->subjects()->attach($this->subjects->pluck('id'));
    $this->division->teachers()->attach($teacher->id, ['class_teacher' => true]);
});

function enrol(string $firstName): Students
{
    return StudentClass::factory()->create([
        'class_division_id' => test()->division->id,
        'student_id' => Students::factory()->create(['first_name' => $firstName]),
    ])->student;
}

function score(Students $student, int $subjectIndex, ?float $marks): void
{
    Mark::factory()->create([
        'exam_id' => test()->exam->id,
        'student_id' => $student->id,
        'subject_id' => test()->subjects[$subjectIndex]->id,
        'division_id' => test()->division->id,
        'marks' => $marks,
        'is_absent' => $marks === null,
    ]);
}

test('grades follow the configured scale and the pass mark', function (float $marks, string $grade) {
    expect($this->exam->gradeFor($marks))->toBe($grade);
})->with([
    [100, 'A+'], [90, 'A+'], [89.99, 'A'], [80, 'A'], [70, 'B'], [60, 'C'], [50, 'D'], [49, 'E'], [40, 'E'], [39.5, 'F'], [0, 'F'],
]);

test('grades scale with max marks', function () {
    $exam = Exam::factory()->create(['max_marks' => 50, 'pass_marks' => 20]);

    expect($exam->gradeFor(45))->toBe('A+')->and($exam->gradeFor(19))->toBe('F');
});

test('results compute totals, percent, grade, pass/fail and competition rank', function () {
    $ana = enrol('Ana');
    score($ana, 0, 90);
    score($ana, 1, 80);   // 170 → 85% A, rank 1
    $ben = enrol('Ben');
    score($ben, 0, 60);
    score($ben, 1, 70);   // 130 → 65% C, rank 2
    $cai = enrol('Cai');
    score($cai, 0, 70);
    score($cai, 1, 60);   // 130 → 65% C, rank 2 (tie)
    $dev = enrol('Dev');
    score($dev, 0, 95);
    score($dev, 1, 30);   // 125 → 62.5%, failed a subject → F, rank 4
    $eli = enrol('Eli');
    score($eli, 0, 99);
    score($eli, 1, null); // absent → F
    $fin = enrol('Fin');
    score($fin, 0, 50);                       // missing a subject → Incomplete

    $rows = app(ExamResults::class)->forDivision($this->exam, $this->division)['rows']->keyBy(fn ($row) => $row['student']->first_name);

    expect($rows['Ana'])->toMatchArray(['total' => 170.0, 'max_total' => 200, 'percent' => 85.0, 'grade' => 'A', 'result' => 'Pass', 'rank' => 1])
        ->and($rows['Ben'])->toMatchArray(['percent' => 65.0, 'grade' => 'C', 'result' => 'Pass', 'rank' => 2])
        ->and($rows['Cai']['rank'])->toBe(2)
        ->and($rows['Dev'])->toMatchArray(['percent' => 62.5, 'grade' => 'F', 'result' => 'Fail'])
        ->and($rows['Eli'])->toMatchArray(['total' => 99.0, 'grade' => 'F', 'result' => 'Fail'])
        ->and($rows['Fin'])->toMatchArray(['percent' => null, 'result' => 'Incomplete', 'rank' => null]);

    expect([$rows['Dev']['rank'], $rows['Eli']['rank']])->toEqualCanonicalizing([4, 5]);
});

test('subjects with marks but no current teacher still appear', function () {
    $extra = Subjects::factory()->create(['subject_name' => 'Latin']);
    $student = enrol('Gia');
    Mark::factory()->create(['exam_id' => $this->exam->id, 'student_id' => $student->id, 'subject_id' => $extra->id, 'division_id' => $this->division->id]);

    $subjects = app(ExamResults::class)->forDivision($this->exam, $this->division)['subjects'];

    expect($subjects->pluck('subject_name'))->toContain('Latin')->and($subjects)->toHaveCount(3);
});

test('the admin results page and CSV export show the table', function () {
    actingAsRole(Login::ROLE_ADMIN);
    $ana = enrol('Ana');
    score($ana, 0, 90);
    score($ana, 1, 80);

    $this->get("/admin/exams/{$this->exam->id}/divisions/{$this->division->id}")
        ->assertSuccessful()->assertSee('Ana')->assertSee('85.0')->assertSee('Pass');

    $csv = $this->get("/admin/exams/{$this->exam->id}/divisions/{$this->division->id}?format=csv")->streamedContent();
    expect($csv)->toContain('Student,')->toContain('"Ana ')->toContain(',170,85,A,Pass,1');
});

test('the report card shows the student\'s marks, result and rank', function () {
    actingAsRole(Login::ROLE_ADMIN);
    $ana = enrol('Ana');
    score($ana, 0, 90);
    score($ana, 1, 80);
    $ben = enrol('Ben');
    score($ben, 0, 60);
    score($ben, 1, 70);

    $this->get("/admin/exams/{$this->exam->id}/students/{$ben->id}")
        ->assertSuccessful()
        ->assertSee('Ben')
        ->assertSee('60 / 100')
        ->assertSee('2 of 2')
        ->assertSee('Pass');
});

test('a report card for a student with no class that year says so', function () {
    actingAsRole(Login::ROLE_ADMIN);
    $student = Students::factory()->create();

    $this->get("/admin/exams/{$this->exam->id}/students/{$student->id}")->assertSuccessful()->assertSee('was not enrolled');
});

test('the student profile links to report cards', function () {
    actingAsRole(Login::ROLE_ADMIN);
    $ana = enrol('Ana');

    $this->get("/admin/view/student/{$ana->id}")->assertSee($this->exam->name)->assertSee(route('admin.exams.report-card', [$this->exam, $ana]));
});
