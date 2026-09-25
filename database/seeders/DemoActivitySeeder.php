<?php

namespace Database\Seeders;

use App\Models\AcademicYear;
use App\Models\Attendance;
use App\Models\Divisions;
use App\Models\Exam;
use App\Models\Login;
use App\Models\Mark;
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

        $this->seedExams($academicYear, $divisions, $generator, $markedBy);
    }

    /**
     * A published exam with full marks and a draft exam with some marks entered.
     *
     * @param  \Illuminate\Support\Collection<int, Divisions>  $divisions
     */
    protected function seedExams(AcademicYear $academicYear, $divisions, TimetableGenerator $generator, ?int $enteredBy): void
    {
        $midterm = Exam::firstOrCreate(
            ['academic_year_id' => $academicYear->id, 'name' => 'Term 1 Midterm'],
            ['starts_on' => Carbon::today()->subWeeks(3), 'max_marks' => 100, 'pass_marks' => 40, 'results_published_at' => now()],
        );

        $finals = Exam::firstOrCreate(
            ['academic_year_id' => $academicYear->id, 'name' => 'Term 1 Finals'],
            ['starts_on' => Carbon::today()->addWeeks(2), 'max_marks' => 100, 'pass_marks' => 40],
        );

        foreach ($divisions as $division) {
            $studentIds = StudentClass::where('class_division_id', $division->id)->where('academic_year_id', $academicYear->id)->pluck('student_id');

            foreach ($generator->teachableSubjects($division) as $index => $subject) {
                foreach ($studentIds as $studentId) {
                    $absent = random_int(1, 50) === 1;

                    Mark::updateOrCreate(
                        ['exam_id' => $midterm->id, 'student_id' => $studentId, 'subject_id' => $subject->id],
                        ['division_id' => $division->id, 'is_absent' => $absent, 'marks' => $absent ? null : random_int(35, 100), 'entered_by' => $enteredBy],
                    );

                    // Finals: only the first two subjects have marks so far.
                    if ($index < 2) {
                        Mark::updateOrCreate(
                            ['exam_id' => $finals->id, 'student_id' => $studentId, 'subject_id' => $subject->id],
                            ['division_id' => $division->id, 'is_absent' => false, 'marks' => random_int(40, 100), 'entered_by' => $enteredBy],
                        );
                    }
                }
            }
        }
    }
}
