<?php

namespace App\Http\Controllers;

use App\Models\Parents;
use App\Models\Students;
use App\Models\Teachers;
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
        return view('student.profile');
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
