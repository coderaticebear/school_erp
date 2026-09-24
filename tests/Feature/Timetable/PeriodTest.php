<?php

use App\Models\Login;
use App\Models\Period;
use App\Models\Subjects;
use App\Models\TimetableEntry;

beforeEach(fn () => actingAsRole(Login::ROLE_ADMIN));

test('the default bell schedule has six teaching periods and a lunch break', function () {
    expect(Period::query()->teaching()->count())->toBe(6)
        ->and(Period::where('is_break', true)->value('label'))->toBe('Lunch');

    $this->get('/admin/periods')->assertSuccessful()->assertSee('Lunch')->assertSee('08:30 - 09:20');
});

test('a period can be added, edited and deleted', function () {
    $this->post('/admin/periods', ['label' => 'Period 7', 'starts_at' => '15:20', 'ends_at' => '16:10'])->assertSessionHasNoErrors();
    $period = Period::where('label', 'Period 7')->firstOrFail();
    expect($period->is_break)->toBeFalse();

    $this->put("/admin/periods/{$period->id}", ['label' => 'Clubs', 'starts_at' => '15:20', 'ends_at' => '16:30', 'is_break' => '1'])->assertSessionHasNoErrors();
    expect($period->fresh()->label)->toBe('Clubs')->and($period->fresh()->is_break)->toBeTrue();

    $this->delete("/admin/periods/{$period->id}")->assertSessionHas('success');
    expect(Period::find($period->id))->toBeNull();
});

test('periods cannot overlap or end before they start', function () {
    $this->post('/admin/periods', ['label' => 'Overlap', 'starts_at' => '09:00', 'ends_at' => '09:45'])->assertSessionHasErrors('starts_at');
    $this->post('/admin/periods', ['label' => 'Backwards', 'starts_at' => '17:00', 'ends_at' => '16:00'])->assertSessionHasErrors('ends_at');
    $this->post('/admin/periods', ['label' => 'Bad', 'starts_at' => '25:00', 'ends_at' => '26:00'])->assertSessionHasErrors('starts_at');
});

test('a period keeps its own time when edited', function () {
    $period = Period::query()->ordered()->first();

    $this->put("/admin/periods/{$period->id}", ['label' => 'First', 'starts_at' => '08:30', 'ends_at' => '09:20'])->assertSessionHasNoErrors();
});

test('a period with lessons cannot be deleted or turned into a break', function () {
    $entry = TimetableEntry::factory()->create();
    $period = $entry->period;

    $this->delete("/admin/periods/{$period->id}")->assertSessionHas('error');
    $this->put("/admin/periods/{$period->id}", ['label' => 'x', 'starts_at' => substr($period->starts_at, 0, 5), 'ends_at' => substr($period->ends_at, 0, 5), 'is_break' => '1'])
        ->assertSessionHas('error');

    expect($period->fresh()->is_break)->toBeFalse();
});

test('subjects accept an optional weekly period count', function () {
    $this->post('/admin/subjects', ['subject_name' => 'Drama', 'periods_per_week' => '4'])->assertSessionHasNoErrors();
    expect(Subjects::where('subject_name', 'Drama')->value('periods_per_week'))->toBe(4);

    $this->post('/admin/subjects', ['subject_name' => 'Dance', 'periods_per_week' => '0'])->assertSessionHasErrors('periods_per_week');
    $this->post('/admin/subjects', ['subject_name' => 'Music', 'periods_per_week' => ''])->assertSessionHasNoErrors();
    expect(Subjects::where('subject_name', 'Music')->value('periods_per_week'))->toBeNull();
});
