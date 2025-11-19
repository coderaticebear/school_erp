<?php

namespace App\Http\Controllers;

use App\Models\Students;

class StudentController extends Controller
{
    //

    public function index()
    {
        return view('student.dashboard');
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
