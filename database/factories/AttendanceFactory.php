<?php

namespace Database\Factories;

use App\Models\AcademicYear;
use App\Models\Attendance;
use App\Models\StudentClass;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Attendance>
 */
class AttendanceFactory extends Factory
{
    protected $model = Attendance::class;

    public function definition(): array
    {
        return [
            'academic_year_id' => fn () => AcademicYear::current()?->id ?? AcademicYear::factory()->create()->id,
            'division_id' => fn (array $attributes) => StudentClass::where('student_id', $attributes['student_id'])->value('class_division_id'),
            'student_id' => fn () => StudentClass::factory()->create()->student_id,
            'date' => now()->toDateString(),
            'status' => Attendance::PRESENT,
        ];
    }
}
