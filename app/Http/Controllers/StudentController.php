<?php

namespace App\Http\Controllers;

use App\Models\Students;
use App\Models\Login;
use App\Models\StudentClass;
 use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;

class StudentController extends Controller
{
    //

    public function index()
    {
        return view('student.dashboard');
    }

    public function addStudent(Request $request)
    {

        // --- 1. VALIDATION ---
        $validated = $request->validate([
            'first_name' => 'required|max:255|string',
            'last_name' => 'required|max:255|string',
            'parent_id' => 'required|integer',

            'address_line_1' => 'required|max:255|string',
            'address_line_2' => 'nullable|max:255|string',
            'city' => 'required|max:255|string',
            'province' => 'required|max:255|string',
            'country' => 'required|max:255|string',
            'postal' => 'required|max:255|string',

            'email' => 'required|email|max:255|unique:login,email',
            'password' => 'required|max:255',
        ]);

        DB::beginTransaction();

        try {

            // --- 2. CREATE LOGIN USER ---
            $login = Login::create([
                'email' => $validated['email'],
                'password' => bcrypt($validated['password']),
                'role' => 3, // student = 3
                'is_active' => true
            ]);

            // --- 3. CREATE STUDENT RECORD ---
            $student = Students::create([
                'login_id' => $login->id,
                'parent_id' => $validated['parent_id'],
                'first_name' => $validated['first_name'],
                'last_name' => $validated['last_name'],

                'address_line_1' => $validated['address_line_1'],
                'address_line_2' => $validated['address_line_2'] ?? null,
                'city' => $validated['city'],
                'province' => $validated['province'],
                'country' => $validated['country'],
                'postal' => $validated['postal'],
            ]);

            DB::commit();

            return redirect()->back()->with('success', 'Student added successfully!');

        } catch (\Exception $e) {

            DB::rollBack();

            return redirect()->back()->with('error', 'Failed to add student: '.$e->getMessage());
        }
    }

    public function assignClass($student_id, $division_id) {
        if($student_id != NUll && $division_id != NULL) {
            $current_class = StudentClass::create([
                'student_id' => $student_id,
                'class_division_id' => $division_id,
                'is_active' => true,
            ]);
            return $current_class->id;
        }
        return false;

    }

    public function getStudent($id = null)
    {
        if ($id) {
            $student = Students::with('parent')
                ->find($id);

            if (! $student) {
                abort(404, 'Student not found');
            }

            $data = [[
                'sfname' => $student->first_name,
                'slname' => $student->last_name,
                'pfname' => $student->parent->first_name ?? '',
                'plname' => $student->parent->last_name ?? '',
            ]];
        } else {
            $students = Students::with('parent')->get();

            $data = $students->map(function ($student) {
                return [
                    'sfname' => $student->first_name,
                    'slname' => $student->last_name,
                    'pfname' => $student->parent->first_name ?? '',
                    'plname' => $student->parent->last_name ?? '',
                ];
            })->toArray();
        }

        return view('student.list')->with('data', $data);
    }
}
