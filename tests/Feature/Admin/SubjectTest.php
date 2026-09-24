<?php

use App\Models\Login;
use App\Models\Subjects;
use App\Models\Teachers;

beforeEach(fn () => actingAsRole(Login::ROLE_ADMIN));

test('the subjects page lists subjects and their teachers', function () {
    $subject = Subjects::factory()->create(['subject_name' => 'Chemistry']);
    $subject->teachers()->attach(Teachers::factory()->create(['first_name' => 'Marie', 'last_name' => 'Curie'])->id);

    $this->get('/subjects')->assertSuccessful()->assertSee('Chemistry')->assertSee('Marie Curie');
});

test('a subject can be added and renamed', function () {
    $this->post('/admin/subjects', ['subject_name' => '  Biology  '])->assertSessionHas('success');
    $subject = Subjects::where('subject_name', 'Biology')->firstOrFail();

    $this->put("/admin/subjects/{$subject->id}", ['subject_name' => 'Life Science'])->assertSessionHasNoErrors();

    expect($subject->fresh()->subject_name)->toBe('Life Science');
});

test('subject names must be unique regardless of case', function () {
    Subjects::factory()->create(['subject_name' => 'Biology']);

    $this->post('/admin/subjects', ['subject_name' => 'BIOLOGY'])->assertSessionHasErrors('subject_name');
    $this->post('/admin/subjects', ['subject_name' => ''])->assertSessionHasErrors('subject_name');
});

test('a subject that nobody teaches can be deleted', function () {
    $subject = Subjects::factory()->create();

    $this->delete("/admin/subjects/{$subject->id}")->assertSessionHas('success');

    expect(Subjects::find($subject->id))->toBeNull();
});

test('a subject that is taught cannot be deleted', function () {
    $subject = Subjects::factory()->create();
    $subject->teachers()->attach(Teachers::factory()->create()->id);

    $this->delete("/admin/subjects/{$subject->id}")->assertSessionHas('error');

    expect(Subjects::find($subject->id))->not->toBeNull();
});
