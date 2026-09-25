<?php

use App\Models\AcademicYear;
use App\Models\Divisions;
use App\Models\Exam;
use App\Models\Login;
use App\Models\Mark;
use App\Models\StudentClass;
use App\Models\Subjects;

beforeEach(function () {
    $this->year = AcademicYear::factory()->create(['is_active' => true]);
    $this->exam = Exam::factory()->create(['max_marks' => 100, 'pass_marks' => 40]);
    $this->login = actingAsRole(Login::ROLE_TEACHER);
    $this->subject = Subjects::factory()->create(['subject_name' => 'Algebra']);
    $this->login->teacher->subjects()->attach($this->subject->id);
    $this->division = Divisions::factory()->create();
    $this->division->teachers()->attach($this->login->teacher->id, ['class_teacher' => false]);
    $this->students = StudentClass::factory()->count(3)->create(['class_division_id' => $this->division->id])->map->student;
    $this->url = "/marks/{$this->exam->id}/{$this->division->id}/{$this->subject->id}";
});

function marksPayload(array $rows): array
{
    $marks = [];
    foreach (test()->students as $index => $student) {
        $marks[$student->id] = $rows[$index] ?? ['value' => ''];
    }

    return ['marks' => $marks];
}

test('a teacher sees only their own division and subject sheets', function () {
    Divisions::factory()->create(); // not theirs
    $this->division->teachers()->attach(\App\Models\Teachers::factory()->create()->id, ['class_teacher' => false]);

    $this->get('/marks')->assertSuccessful()->assertSee($this->division->label)->assertSee('Algebra')->assertSee('0/3');
});

test('a teacher can enter, update and clear marks', function () {
    $this->post($this->url, marksPayload([['value' => '78.5', 'remark' => 'Good'], ['value' => '', 'absent' => '1'], ['value' => '']]))
        ->assertSessionHasNoErrors()
        ->assertRedirect($this->url);

    expect(Mark::count())->toBe(2)
        ->and(Mark::where('student_id', $this->students[0]->id)->value('marks'))->toBe(78.5)
        ->and(Mark::where('student_id', $this->students[1]->id)->first()->is_absent)->toBeTrue()
        ->and(Mark::first()->entered_by)->toBe($this->login->id)
        ->and(Mark::first()->division_id)->toBe($this->division->id);

    $this->post($this->url, marksPayload([['value' => ''], ['value' => '55']]));

    expect(Mark::count())->toBe(1)
        ->and(Mark::where('student_id', $this->students[1]->id)->value('marks'))->toBe(55.0);

    $this->get('/marks')->assertSee('1/3');
});

test('the sheet shows existing marks and grades', function () {
    Mark::factory()->create(['exam_id' => $this->exam->id, 'student_id' => $this->students[0]->id, 'subject_id' => $this->subject->id, 'marks' => 91]);

    $this->get($this->url)->assertSuccessful()->assertSee('value="91"', false)->assertSee('A+');
});

test('marks are validated', function (array $row, string $message) {
    $this->post($this->url, marksPayload([$row]))->assertSessionHasErrors(['marks.'.$this->students[0]->id.'.value' => $message]);

    expect(Mark::count())->toBe(0);
})->with([
    'above max' => [['value' => '101'], 'Marks cannot be more than 100.'],
    'negative' => [['value' => '-1'], 'Marks cannot be negative.'],
    'not a number' => [['value' => 'abc'], 'Marks must be a number.'],
    'absent with marks' => [['value' => '50', 'absent' => '1'], 'A student marked absent cannot also have marks.'],
]);

test('students outside the division are rejected', function () {
    $payload = marksPayload([]);
    $payload['marks'][StudentClass::factory()->create()->student_id] = ['value' => '50'];

    $this->post($this->url, $payload)->assertSessionHasErrors('marks');
    expect(Mark::count())->toBe(0);
});

test('marks are locked once results are published', function () {
    $this->exam->update(['results_published_at' => now()]);

    $this->post($this->url, marksPayload([['value' => '60']]))->assertSessionHasErrors('marks');
    $this->get($this->url)->assertSuccessful()->assertDontSee('Save Marks');
    expect(Mark::count())->toBe(0);
});

test('a teacher cannot open or save sheets for another division or a subject they do not teach', function () {
    $otherDivision = Divisions::factory()->create();
    $otherSubject = Subjects::factory()->create();

    $this->get("/marks/{$this->exam->id}/{$otherDivision->id}/{$this->subject->id}")->assertForbidden();
    $this->get("/marks/{$this->exam->id}/{$this->division->id}/{$otherSubject->id}")->assertForbidden();
    $this->post("/marks/{$this->exam->id}/{$otherDivision->id}/{$this->subject->id}", marksPayload([['value' => '50']]))->assertForbidden();
});

test('an admin can enter marks for any division and subject', function () {
    actingAsRole(Login::ROLE_ADMIN);
    $otherSubject = Subjects::factory()->create();

    $this->post("/marks/{$this->exam->id}/{$this->division->id}/{$otherSubject->id}", marksPayload([['value' => '70']]))->assertSessionHasNoErrors();

    expect(Mark::where('subject_id', $otherSubject->id)->count())->toBe(1);
});

test('students and parents cannot reach marks entry', function (int $role) {
    actingAsRole($role);

    $this->get('/marks')->assertForbidden();
    $this->post($this->url, marksPayload([['value' => '99']]))->assertForbidden();
})->with(['student' => Login::ROLE_STUDENT, 'parent' => Login::ROLE_PARENT]);

test('the database rejects a mark that is both absent and scored', function () {
    expect(fn () => Mark::factory()->create(['is_absent' => true, 'marks' => 50]))->toThrow(Illuminate\Database\QueryException::class);
});
