<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Divisions;
use App\Models\Subjects;
use App\Models\Teachers;

class TimeTableController extends Controller
{
    //

    public function generateTimeTable() {
        $days = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
        $time_slots = [
            '08:30-09:20',
            '09:30-10:20',
            '10:30-11:20',
            '11:30-12:20',
            '01:20-02:10',
            '02:20-03:10',
        ];

        $divisions = Divisions::with('class')->get();
        $subjects = Subjects::all();
        $teachers = Teachers::with('subject')->get();

        if ($divisions->isEmpty()) {
            $divisions = collect([
                (object) ['id' => 1, 'division_name' => 'A', 'class' => (object) ['class_name' => 'Grade 10']],
                (object) ['id' => 2, 'division_name' => 'B', 'class' => (object) ['class_name' => 'Grade 10']],
            ]);
        }

        if ($subjects->isEmpty()) {
            $subjects = collect([
                (object) ['id' => 1, 'subject_name' => 'Math'],
                (object) ['id' => 2, 'subject_name' => 'English'],
                (object) ['id' => 3, 'subject_name' => 'Science'],
                (object) ['id' => 4, 'subject_name' => 'History'],
            ]);
        }

        if ($teachers->isEmpty()) {
            $teachers = collect([
                (object) ['id' => 1, 'first_name' => 'Ms.', 'last_name' => 'Patel', 'subject' => (object) ['id' => 1, 'subject_name' => 'Math']],
                (object) ['id' => 2, 'first_name' => 'Mr.', 'last_name' => 'Lewis', 'subject' => (object) ['id' => 4, 'subject_name' => 'History']],
                (object) ['id' => 3, 'first_name' => 'Dr.', 'last_name' => 'Kim', 'subject' => (object) ['id' => 3, 'subject_name' => 'Science']],
                (object) ['id' => 4, 'first_name' => 'Ms.', 'last_name' => 'Davis', 'subject' => (object) ['id' => 2, 'subject_name' => 'English']],
            ]);
        }

        $final_timetable = [];
        $subject_index = 0;
        $teacher_index = 0;

        foreach ($divisions as $division) {
            $division_key = $division->id;
            $division_name = ($division->class->class_name ?? 'Class') . ' - ' . $division->division_name;

            foreach ($days as $day) {
                foreach ($time_slots as $slot) {
                    $subject = $subjects[$subject_index % $subjects->count()];
                    $teacher = $teachers[$teacher_index % $teachers->count()];

                    if (!empty($teacher->subject) && $teacher->subject->id !== $subject->id) {
                        $teacher = $teachers->firstWhere('subject_id', $subject->id) ?? $teacher;
                    }

                    $final_timetable[$division_key][$day][$slot] = [
                        'division_id' => $division->id,
                        'division_name' => $division_name,
                        'day' => $day,
                        'time' => $slot,
                        'subject' => $subject->subject_name,
                        'teacher' => trim($teacher->first_name . ' ' . $teacher->last_name),
                    ];

                    $subject_index++;
                    $teacher_index++;
                }
            }
        }

        return response()->json([
            'generated_at' => now()->toDateTimeString(),
            'days' => $days,
            'time_slots' => $time_slots,
            'divisions' => $divisions,
            'timetable' => $final_timetable,
        ]);
    }
}
