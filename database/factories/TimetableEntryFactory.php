<?php

namespace Database\Factories;

use App\Models\AcademicYear;
use App\Models\Divisions;
use App\Models\Period;
use App\Models\Subjects;
use App\Models\Teachers;
use App\Models\TimetableEntry;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TimetableEntry>
 */
class TimetableEntryFactory extends Factory
{
    protected $model = TimetableEntry::class;

    public function definition(): array
    {
        return [
            'academic_year_id' => fn () => AcademicYear::current()?->id ?? AcademicYear::factory()->active()->create()->id,
            'division_id' => Divisions::factory(),
            'day' => 1,
            'period_id' => fn () => Period::query()->teaching()->ordered()->value('id') ?? Period::factory()->create()->id,
            'subject_id' => Subjects::factory(),
            'teacher_id' => Teachers::factory(),
        ];
    }
}
