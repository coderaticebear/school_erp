<?php

namespace Database\Seeders;

use App\Models\AcademicYear;
use App\Models\Divisions;
use App\Models\Login;
use App\Models\Parents;
use App\Models\StudentClass;
use App\Models\Students;
use App\Models\Subjects;
use App\Models\Teachers;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Demo accounts with known credentials (password: "password") for local testing.
 * Each non-admin account has a matching profile so its portal pages work.
 */
class LoginSeeder extends Seeder
{
    public function run(): void
    {
        $password = Hash::make('password');

        Login::firstOrCreate(
            ['email' => 'admin@example.com'],
            ['password' => $password, 'role' => Login::ROLE_ADMIN, 'is_active' => true],
        );

        if (! Login::where('email', 'teacher@example.com')->exists()) {
            $teacher = Teachers::factory()->create([
                'login_id' => Login::factory()->teacher()->create(['email' => 'teacher@example.com', 'password' => $password])->id,
                'first_name' => 'Demo',
                'last_name' => 'Teacher',
            ]);

            $teacher->subjects()->attach(Subjects::query()->orderBy('id')->limit(2)->pluck('id'));

            // Teach in the first two divisions; class teacher of the first.
            foreach (Divisions::query()->orderBy('id')->limit(2)->get() as $index => $division) {
                if ($index === 0) {
                    $division->teachers()->newPivotStatement()->where('division_id', $division->id)->update(['class_teacher' => false]);
                }

                $division->teachers()->syncWithoutDetaching([$teacher->id => ['class_teacher' => $index === 0]]);
            }
        }

        if (! Login::where('email', 'parent@example.com')->exists()) {
            $parent = Parents::factory()->create([
                'login_id' => Login::factory()->parent()->create(['email' => 'parent@example.com', 'password' => $password])->id,
                'first_name' => 'Demo',
                'last_name' => 'Parent',
            ]);

            $student = Students::factory()->create([
                'login_id' => Login::factory()->student()->create(['email' => 'student@example.com', 'password' => $password])->id,
                'parent_id' => $parent->id,
                'first_name' => 'Demo',
                'last_name' => 'Student',
            ]);

            $academicYear = AcademicYear::current();
            $division = Divisions::query()->orderBy('id')->first();

            if ($academicYear && $division) {
                StudentClass::create([
                    'student_id' => $student->id,
                    'class_division_id' => $division->id,
                    'academic_year_id' => $academicYear->id,
                    'is_active' => true,
                ]);
            }
        }
    }
}
