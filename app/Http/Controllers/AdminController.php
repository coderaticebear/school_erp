<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Students;
use App\Models\Teachers;
use App\Models\Parents;

class AdminController extends Controller
{
    //

    public function index() {

        $studentCount = Students::count();
        $teacherCount = Teachers::count();


        $data = [
            'studentCount' => $studentCount,
            'teacherCount' =>$teacherCount
        ];
        return view('admin.dashboard')->with('data', $data);
    }

    public function addStudent() {
        $parents = Parents::all();
        $data = $parents->map(function($parent) {
            return [
                'id' => $parent->id,
                'name' => $parent->first_name . " " . $parent->last_name,
            ];
        })->toArray();
        return view('student.add')->with('data', $data);
    }


}
