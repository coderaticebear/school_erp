<?php

namespace Database\Seeders;

use App\Models\AcademicYear;
use App\Models\Classes;
use App\Models\Divisions;
use App\Models\Login;
use App\Models\Parents;
use App\Models\StudentClass;
use App\Models\Students;
use App\Models\Subjects;
use App\Models\Teachers;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run()
    {
        // Admin logins
        Login::factory()->count(2)->admin()->create();

        // Academic Year (ONLY ONE)
        $academicYear = AcademicYear::firstOrCreate(
            ['year' => '2025-2026'],
            ['is_active' => true]
        );

        // Subjects
        Subjects::factory()->count(5)->create();

        // Classes & Divisions
        $classes = Classes::factory()->count(3)->create();
        foreach ($classes as $class) {
            Divisions::factory()->count(2)->create([
                'class_id' => $class->id,
            ]);
        }

        // Teachers
        Teachers::factory()->count(10)->create();

        // Parents
        $parents = Parents::factory()->count(5)->create();

        // Students
        $students = Students::factory()->count(20)->recycle($parents)->create();

        // Student Classes (THIS WAS MISSING)
        $divisions = Divisions::all();

        foreach ($students as $student) {
            StudentClass::create([
                'student_id' => $student->id,
                'class_division_id' => $divisions->random()->id,
                'academic_year_id' => $academicYear->id,
                'is_active' => true,
            ]);
        }

        // Demo accounts with known passwords
        $this->call(LoginSeeder::class);
    }
}
