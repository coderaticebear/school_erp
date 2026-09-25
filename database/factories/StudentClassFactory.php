<?php

namespace Database\Factories;

use App\Models\AcademicYear;
use App\Models\Divisions;
use App\Models\StudentClass;
use App\Models\Students;
use Illuminate\Database\Eloquent\Factories\Factory;

class StudentClassFactory extends Factory
{
    protected $model = StudentClass::class;

    public function definition(): array
    {
        return [
            'student_id' => Students::factory(),
            'class_division_id' => Divisions::factory(),
            'is_active' => true,
            'academic_year_id' => fn () => AcademicYear::current()?->id ?? AcademicYear::factory()->active()->create()->id,
        ];
    }
}
