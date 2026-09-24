<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\Divisions;
use App\Models\Login;
use App\Models\Parents;
use App\Models\StudentClass;
use App\Models\Students;
use App\Models\Teachers;
use App\Pipelines\SanitizeInput;
use Illuminate\Http\Request;

class AdminController extends Controller
{
    //

    public function index()
    {

        $studentCount = Students::count();
        $teacherCount = Teachers::count();

        $data = [
            'studentCount' => $studentCount,
            'teacherCount' => $teacherCount,
        ];

        return view('admin.dashboard')->with('data', $data);
    }

    public function viewStudent($id)
    {
        $id = SanitizeInput::run([$id])[0];

        if (! ctype_digit((string) $id)) {
            abort(404, 'Invalid student ID');
        }

        $academicYear = AcademicYear::current();
        $student = Students::with(['parent.login'])->findOrFail($id);
        $parent = $student->parent;
        $classDetails = $academicYear
            ? StudentClass::query()
                ->where('student_id', $student->id)
                ->where('academic_year_id', $academicYear->id)
                ->with(['division.class'])
                ->first()
            : null;

        $data = [
            'student_id' => $student->id,
            'roll_number' => $student->roll_number ?? 'N/A',
            'status' => $student->status,
            'student_name' => "{$student->first_name} {$student->last_name}",
            'parent_name' => $parent
                ? "{$parent->first_name} {$parent->last_name}"
                : 'N/A',
            'parent_email' => optional($parent?->login)->email ?? 'N/A',
            'address_line_1' => $parent->address_line_1 ?? 'N/A',
            'address_line_2' => $parent->address_line_2 ?? 'N/A',
            'city' => $parent->city ?? 'N/A',
            'province' => $parent->province ?? 'N/A',
            'country' => $parent->country ?? 'N/A',
            'postal' => $parent->postal ?? 'N/A',
            'date_of_birth' => date('j F Y', strtotime($student->date_of_birth)) ?? 'N/A',
            'gender' => $student->gender ?? 'N/A',
            'blood_group' => $student->blood_group ?? 'N/A',
            'division_name' => $classDetails?->division?->division_name ?? 'Not assigned',
            'class_name' => $classDetails?->division?->class?->class_name ?? 'Not assigned',
            'academic_year' => $academicYear?->year ?? 'N/A',
        ];

        return view('student.profile', compact('data'));
    }

    public function addStudent()
    {
        $parents = Parents::all();
        $data = $parents->map(function ($parent) {
            return [
                'id' => $parent->id,
                'name' => $parent->first_name.' '.$parent->last_name,
            ];
        })->toArray();

        $divisions = Divisions::with('class')
            ->get()
            ->sortBy(fn (Divisions $division) => [$division->class->class_name ?? '', $division->division_name])
            ->values();

        return view('student.add')->with(['data' => $data, 'divisions' => $divisions]);
    }

    public function getParentByEmail(Request $request)
    {
        $validated = $request->validate([
            'email' => 'required|string|email',
        ]);

        $parent = Parents::query()
            ->whereHas('login', fn ($query) => $query
                ->whereRaw('lower(email) = ?', [strtolower($validated['email'])])
                ->where('role', Login::ROLE_PARENT))
            ->value('id');

        return response()->json([
            'parent_id' => $parent,
        ]);
    }

    public function assignClassTeacher($id, $classDivisionId) {}

    public function timeTableManager()
    {
        /**
         * Lets for a moment design a cell in timetable. what data is it associated with.
         * It should have a class/division id to identify which room it belongs to
         * A cell must have a subject
         * A cell must have a teacher
         * A cell must have a time
         * A cell must have a day.
         *
         * All this constitutes a cell in time table.
         *
         * The next question is onwership of these data for each cell.
         * What conditions does it needs to meet to be assigned with the data.
         */
        return view('admin.timetable');
    }
}
/**
 * To assign a class teacher, we need two tables.
 * Teacher-division - to track each teachers teaching on each division with a flg to mark if its class teacher or not
 * Also table to track which subjects each teacher teaches!
 */
