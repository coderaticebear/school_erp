<?php

use App\Models\Divisions;
use App\Models\Login;
use App\Models\Subjects;
use App\Models\Teachers;

beforeEach(function () {
    $this->subjects = Subjects::factory()->count(3)->create();
    actingAsRole(Login::ROLE_ADMIN);
});

function teacherPayload(array $overrides = []): array
{
    return [
        'first_name' => 'Grace',
        'last_name' => 'Hopper',
        'address_line_1' => '1 Navy Way',
        'address_line_2' => '',
        'city' => 'Arlington',
        'province' => 'VA',
        'country' => 'USA',
        'postal' => '22201',
        'email' => 'grace@example.test',
        'password' => 'teacher-pass',
        'subject_ids' => [test()->subjects[0]->id, test()->subjects[1]->id],
        ...$overrides,
    ];
}

test('the teacher list shows teachers with subjects, divisions and status', function () {
    $teacher = Teachers::factory()->create(['first_name' => 'Alan', 'last_name' => 'Turing']);
    $teacher->subjects()->attach($this->subjects[0]->id);
    $division = Divisions::factory()->create();
    $teacher->divisions()->attach($division->id, ['class_teacher' => true]);

    $this->get('/teachers')
        ->assertSuccessful()
        ->assertSee('Alan Turing')
        ->assertSee($this->subjects[0]->subject_name)
        ->assertSee($division->division_name)
        ->assertSee('Active');
});

test('the add teacher form loads with subjects', function () {
    $this->get('/admin/teachers/create')->assertSuccessful()->assertSee($this->subjects[2]->subject_name);
});

test('admin can add a teacher with several subjects', function () {
    $this->post('/admin/teachers', teacherPayload())
        ->assertRedirect(route('admin.teachers.index'))
        ->assertSessionHas('success');

    $teacher = Teachers::where('first_name', 'Grace')->firstOrFail();

    expect($teacher->login->role)->toBe(Login::ROLE_TEACHER)
        ->and($teacher->login->is_active)->toBeTrue()
        ->and(Hash::check('teacher-pass', $teacher->login->password))->toBeTrue()
        ->and($teacher->subjects->pluck('id')->sort()->values()->all())
        ->toBe(collect([$this->subjects[0]->id, $this->subjects[1]->id])->sort()->values()->all());
});

test('the new teacher can log in and reach the teacher dashboard', function () {
    $this->post('/admin/teachers', teacherPayload());
    auth()->logout();

    $this->post('/login', ['email' => 'grace@example.test', 'password' => 'teacher-pass'])->assertRedirect('/dashboard');
    $this->get('/teacher/dashboard')->assertSuccessful();
});

test('teacher validation', function (array $overrides, string $field) {
    $this->post('/admin/teachers', teacherPayload($overrides))->assertSessionHasErrors($field);

    expect(Teachers::count())->toBe(0);
})->with([
    'no subjects' => [['subject_ids' => []], 'subject_ids'],
    'unknown subject' => [['subject_ids' => [999999]], 'subject_ids.0'],
    'no password' => [['password' => ''], 'password'],
    'bad email' => [['email' => 'not-an-email'], 'email'],
    'no first name' => [['first_name' => ''], 'first_name'],
]);

test('a teacher email already in use is rejected', function () {
    Login::factory()->create(['email' => 'grace@example.test']);

    $this->post('/admin/teachers', teacherPayload())->assertSessionHasErrors('email');
});

test('the edit form is prefilled', function () {
    $teacher = Teachers::factory()->create(['first_name' => 'Katherine']);
    $teacher->subjects()->attach($this->subjects[1]->id);

    $this->get("/admin/teachers/{$teacher->id}/edit")
        ->assertSuccessful()
        ->assertSee('value="Katherine"', false)
        ->assertSee($teacher->login->email);
});

test('editing a teacher updates profile, email and subjects but keeps the password when blank', function () {
    $teacher = Teachers::factory()->create();
    $teacher->subjects()->attach($this->subjects[0]->id);
    $oldHash = $teacher->login->password;

    $this->put("/admin/teachers/{$teacher->id}", teacherPayload([
        'email' => $teacher->login->email,
        'password' => '',
        'subject_ids' => [$this->subjects[2]->id],
    ]))->assertSessionHasNoErrors()->assertRedirect(route('admin.teachers.index'));

    $teacher->refresh();
    expect($teacher->first_name)->toBe('Grace')
        ->and($teacher->login->password)->toBe($oldHash)
        ->and($teacher->subjects->pluck('id')->all())->toBe([$this->subjects[2]->id]);
});

test('editing a teacher can change the password', function () {
    $teacher = Teachers::factory()->create();

    $this->put("/admin/teachers/{$teacher->id}", teacherPayload(['email' => $teacher->login->email, 'password' => 'brand-new-pass']));

    expect(Hash::check('brand-new-pass', $teacher->fresh()->login->password))->toBeTrue();
});

test('a teacher cannot take another account\'s email', function () {
    $teacher = Teachers::factory()->create();
    $other = Login::factory()->create();

    $this->put("/admin/teachers/{$teacher->id}", teacherPayload(['email' => $other->email]))->assertSessionHasErrors('email');
});

test('a teacher can be deactivated, which blocks login, and reactivated', function () {
    $teacher = Teachers::factory()->create();
    $teacher->login->update(['password' => bcrypt('teacher-pass')]);

    $this->post("/admin/teachers/{$teacher->id}/toggle-active")->assertSessionHas('success');
    expect($teacher->login->fresh()->is_active)->toBeFalse();

    auth()->logout();
    $this->post('/login', ['email' => $teacher->login->email, 'password' => 'teacher-pass'])->assertSessionHasErrors('email');

    actingAsRole(Login::ROLE_ADMIN);
    $this->post("/admin/teachers/{$teacher->id}/toggle-active");
    expect($teacher->login->fresh()->is_active)->toBeTrue();
});

test('the teacher detail endpoint returns subjects and divisions', function () {
    $teacher = Teachers::factory()->create();
    $teacher->subjects()->attach($this->subjects[0]->id);
    $division = Divisions::factory()->create();
    $teacher->divisions()->attach($division->id, ['class_teacher' => true]);

    $this->getJson("/teachers/{$teacher->id}")
        ->assertSuccessful()
        ->assertJsonPath('subjects.0', $this->subjects[0]->subject_name)
        ->assertJsonPath('divisions.0.class_teacher', true);

    $this->getJson('/teachers/999999')->assertNotFound();
});
