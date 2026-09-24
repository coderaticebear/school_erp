<?php

use App\Models\AcademicYear;
use App\Models\Login;
use App\Models\StudentClass;

beforeEach(fn () => actingAsRole(Login::ROLE_ADMIN));

test('the academic years page lists years', function () {
    AcademicYear::factory()->create(['year' => '2025-2026']);

    $this->get('/admin/academic-years')->assertSuccessful()->assertSee('2025-2026');
});

test('the first academic year added becomes active, later ones do not', function () {
    $this->post('/admin/academic-years', ['year' => '2025-2026'])->assertSessionHas('success');
    $this->post('/admin/academic-years', ['year' => '2026-2027'])->assertSessionHas('success');

    expect(AcademicYear::where('year', '2025-2026')->first()->is_active)->toBeTrue()
        ->and(AcademicYear::where('year', '2026-2027')->first()->is_active)->toBeFalse();
});

test('academic year format is validated', function (string $year) {
    $this->post('/admin/academic-years', ['year' => $year])->assertSessionHasErrors('year');
    expect(AcademicYear::count())->toBe(0);
})->with(['2025', '2025-2025', '2025-2027', 'abcd-efgh', '<b>2025-2026</b>x', '']);

test('duplicate academic years are rejected', function () {
    AcademicYear::factory()->create(['year' => '2025-2026']);

    $this->post('/admin/academic-years', ['year' => '2025-2026'])->assertSessionHasErrors('year');
});

test('an academic year can be renamed, keeping its own value valid', function () {
    $year = AcademicYear::factory()->create(['year' => '2025-2026']);

    $this->put("/admin/academic-years/{$year->id}", ['year' => '2025-2026'])->assertSessionHasNoErrors();
    $this->put("/admin/academic-years/{$year->id}", ['year' => '2024-2025'])->assertSessionHasNoErrors();

    expect($year->fresh()->year)->toBe('2024-2025');
});

test('activating a year deactivates the others', function () {
    $old = AcademicYear::factory()->create(['year' => '2025-2026', 'is_active' => true]);
    $new = AcademicYear::factory()->create(['year' => '2026-2027', 'is_active' => false]);

    $this->post("/admin/academic-years/{$new->id}/activate")->assertSessionHas('success');

    expect($new->fresh()->is_active)->toBeTrue()
        ->and($old->fresh()->is_active)->toBeFalse()
        ->and(AcademicYear::current()->id)->toBe($new->id);
});

test('the active year and years with enrolments cannot be deleted', function () {
    $active = AcademicYear::factory()->create(['year' => '2025-2026', 'is_active' => true]);
    $used = AcademicYear::factory()->create(['year' => '2024-2025', 'is_active' => false]);
    StudentClass::factory()->create(['academic_year_id' => $used->id]);

    $this->delete("/admin/academic-years/{$active->id}")->assertSessionHas('error');
    $this->delete("/admin/academic-years/{$used->id}")->assertSessionHas('error');

    expect(AcademicYear::count())->toBe(2);
});

test('an unused inactive year can be deleted', function () {
    AcademicYear::factory()->create(['year' => '2025-2026', 'is_active' => true]);
    $unused = AcademicYear::factory()->create(['year' => '2026-2027', 'is_active' => false]);

    $this->delete("/admin/academic-years/{$unused->id}")->assertSessionHas('success');

    expect(AcademicYear::find($unused->id))->toBeNull();
});

test('the database allows only one active academic year', function () {
    AcademicYear::factory()->create(['year' => '2025-2026', 'is_active' => true]);

    expect(fn () => AcademicYear::factory()->create(['year' => '2026-2027', 'is_active' => true]))
        ->toThrow(Illuminate\Database\QueryException::class);
});
