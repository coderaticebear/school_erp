<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Subjects;

class SubjectController extends Controller
{
    //

    public function getSubject($id = null) {
        if($id) {
            $subject = Subjects::find($id);
            if(!$subject) {
                abort(404,'No subject found');
            }

            $data = [[
                'subject_name' => $subject->subject_name,
            ]];
        } else {
            $subject = Subjects::all();

            $data = $subject->map(function ($item){
                return [
                    'subject_name' =>$item->subject_name,
                ];
            } )->toArray();
        }

        return view('subject.list')->with('data', $data);
    }
}
