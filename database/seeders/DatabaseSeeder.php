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
    /**
     * @var list<string>
     */
    protected array $subjectNames = [
        'Mathematics', 'English', 'Science', 'Social Studies',
        'Computer Science', 'Physical Education', 'Art', 'French',
    ];

    public function run(): void
    {
        // Admin logins
        Login::factory()->count(2)->admin()->create();

        // Academic Year (ONLY ONE active)
        $academicYear = AcademicYear::firstOrCreate(
            ['year' => '2025-2026'],
            ['is_active' => true]
        );

        // Subjects
        $subjects = collect($this->subjectNames)
            ->map(fn (string $name) => Subjects::firstOrCreate(['subject_name' => $name]));

        // Classes & Divisions
        $classes = collect(['Grade 5', 'Grade 6', 'Grade 7'])
            ->map(fn (string $name) => Classes::firstOrCreate(['class_name' => $name]));

        foreach ($classes as $class) {
            foreach (['A', 'B'] as $divisionName) {
                Divisions::firstOrCreate(['class_id' => $class->id, 'division_name' => $divisionName]);
            }
        }

        // Teachers: every subject gets at least one teacher, some teach two subjects
        $teachers = Teachers::factory()->count(10)->create();

        foreach ($teachers as $index => $teacher) {
            $teacher->subjects()->attach($subjects[$index % $subjects->count()]->id);

            if ($index % 3 === 0) {
                $teacher->subjects()->syncWithoutDetaching([$subjects->random()->id]);
            }
        }

        $teachers->load('subjects');

        // Each division gets one teacher per subject, choosing the least-loaded teacher
        // so nobody is booked for more lessons than the week has (keeps the demo timetable complete).
        $divisions = Divisions::all();
        $load = [];

        foreach ($divisions as $division) {
            $assigned = [];

            foreach ($subjects as $subject) {
                $teacher = $teachers
                    ->filter(fn (Teachers $candidate) => $candidate->subjects->contains('id', $subject->id))
                    ->sortBy(fn (Teachers $candidate) => $load[$candidate->id] ?? 0)
                    ->first();

                $load[$teacher->id] = ($load[$teacher->id] ?? 0) + 1;
                $assigned[$teacher->id] = true;
            }

            foreach (array_keys($assigned) as $position => $teacherId) {
                $division->teachers()->attach($teacherId, ['class_teacher' => $position === 0]);
            }
        }

        // Parents
        $parents = Parents::factory()->count(5)->create();

        // Students
        $students = Students::factory()->count(20)->recycle($parents)->create();

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

        // A published timetable and two weeks of attendance, so every portal has data.
        $this->call(DemoActivitySeeder::class);
    }
}
