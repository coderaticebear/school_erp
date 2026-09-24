<?php

namespace Database\Seeders;

use App\Models\AcademicYear;
use App\Models\Attendance;
use App\Models\Divisions;
use App\Models\Login;
use App\Models\StudentClass;
use App\Services\TimetableGenerator;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class DemoActivitySeeder extends Seeder
{
    public function run(TimetableGenerator $generator): void
    {
        $academicYear = AcademicYear::current();

        if (! $academicYear) {
            return;
        }

        $divisions = Divisions::with('class')->get();
        $generator->save($academicYear, $divisions, $generator->generate($academicYear, $divisions, seed: 2025)['entries']);
        $academicYear->update(['timetable_published_at' => now()]);

        $markedBy = Login::where('role', Login::ROLE_ADMIN)->value('id');
        $enrolments = StudentClass::where('academic_year_id', $academicYear->id)->get();
        $day = Carbon::today()->subDays(14);

        while ($day->lt(Carbon::today())) {
            if (in_array($day->isoWeekday(), config('school.days'), true)) {
                foreach ($enrolments as $enrolment) {
                    $roll = random_int(1, 100);

                    Attendance::updateOrCreate(
                        ['student_id' => $enrolment->student_id, 'date' => $day->toDateString()],
                        [
                            'academic_year_id' => $academicYear->id,
                            'division_id' => $enrolment->class_division_id,
                            'status' => match (true) {
                                $roll <= 85 => Attendance::PRESENT,
                                $roll <= 91 => Attendance::LATE,
                                $roll <= 97 => Attendance::ABSENT,
                                default => Attendance::EXCUSED,
                            },
                            'marked_by' => $markedBy,
                        ],
                    );
                }
            }

            $day->addDay();
        }
    }
}
