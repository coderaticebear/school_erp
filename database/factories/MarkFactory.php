<?php

namespace Database\Factories;

use App\Models\Exam;
use App\Models\Mark;
use App\Models\StudentClass;
use App\Models\Subjects;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Mark>
 */
class MarkFactory extends Factory
{
    protected $model = Mark::class;

    public function definition(): array
    {
        return [
            'exam_id' => Exam::factory(),
            'student_id' => fn () => StudentClass::factory()->create()->student_id,
            'subject_id' => Subjects::factory(),
            'division_id' => fn (array $attributes) => StudentClass::where('student_id', $attributes['student_id'])->value('class_division_id'),
            'marks' => $this->faker->numberBetween(30, 100),
            'is_absent' => false,
        ];
    }
}
