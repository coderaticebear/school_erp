<?php

namespace App\Http\Controllers;

use App\Models\Teachers;

class TeacherController extends Controller
{
    //

    public function index()
    {
        return view('teacher.dashboard');
    }

    public function getTeacher($id = null)
    {
        if ($id) {
            $teacher = Teachers::with('subject')->find($id);
            if (! $teacher) {
                abort(404, 'Teacher Not found');
            }
            $data = [[
                'id' => $teacher->id,
                'fname' => $teacher->first_name,
                'lname' => $teacher->last_name,
                'subject' => $teacher->subject->subject_name ?? 'No subject assigned',
            ]];
        } else {
            $teacher = Teachers::with('subject')->get();

            $data = $teacher->map(function ($item) {
                return [
                    'id' => $item->id,
                    'fname' => $item->first_name,
                    'lname' => $item->last_name,
                    'subject' => $item->subject->subject_name ?? 'No subject assigned',
                ];
            })->toArray();
        }

        return view('teacher.list')->with('data', $data);
    }

    public function viewTeacher($id)
    {
        $teacher = Teachers::with('subject')->find($id);
        if (! $teacher) {
            abort(404, 'Teacher Not found');
        }
        return response()->json([
            'id' => $teacher->id,
            'fname' => $teacher->first_name,
            'lname' => $teacher->last_name,
            'subject' => $teacher->subject->subject_name ?? 'No subject assigned',
        ]);
    }
}
