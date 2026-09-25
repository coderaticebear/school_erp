<?php

namespace Database\Factories;

use App\Models\AcademicYear;
use App\Models\Exam;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Exam>
 */
class ExamFactory extends Factory
{
    protected $model = Exam::class;

    public function definition(): array
    {
        return [
            'academic_year_id' => fn () => AcademicYear::current()?->id ?? AcademicYear::factory()->active()->create()->id,
            'name' => 'Exam '.$this->faker->unique()->numberBetween(1, 100000),
            'starts_on' => now()->subWeek()->toDateString(),
            'max_marks' => 100,
            'pass_marks' => 40,
        ];
    }

    public function published(): static
    {
        return $this->state(fn () => ['results_published_at' => now()]);
    }
}
