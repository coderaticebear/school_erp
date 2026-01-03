<?php

namespace App\Http\Controllers;

use App\Models\Parents;
use App\Models\Students;
use App\Models\Teachers;
use App\Models\AcademicYear;
use App\Models\StudentClass;
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

    public function getCurrentAcademicYear() {
        $academicYear = AcademicYear::where('is_active', true)->first();
        return $academicYear;
    }

    public function viewStudent($id)
    {


        $id = SanitizeInput::run([$id])[0];
        if (! ctype_digit((string) $id)) {
            abort(404, 'Invalid student ID');
        }
        $academicYear = $this->getCurrentAcademicYear();
        $student = Students::with(['parent.login'])->findOrFail($id);
        $parent = $student->parent;
        $classDetails = StudentClass::query()
        ->where('student_id', $student->id)
        ->where('academic_year_id', $academicYear->id)
        ->with(['division.class'])->firstOrFail();
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
            'division_name' => $classDetails->division->division_name,
            'class_name' => $classDetails->division->class->class_name,
            'academic_year' => $academicYear->year,
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

        return view('student.add')->with('data', $data);
    }

    public function getParentByEmail(Request $request)
    {
        $validated = $request->validate([
            'email' => 'required|string|email',
        ]);

        $parent = Parents::join('login', 'login.id', '=', 'parents.login_id')
            ->where('login.email', $validated['email'])
            ->value('parents.id');

        return response()->json([
            'parent_id' => $parent,
        ]);
    }
}
