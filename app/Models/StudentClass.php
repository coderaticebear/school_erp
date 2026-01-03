<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StudentClass extends Model
{
    //
    protected $table = 'student_classes';

    protected $fillable = [
        'student_id',
        'class_division_id',
        'is_active',
    ];


     public function student()
    {
        return $this->belongsTo(Students::class, 'student_id');
    }

    public function division()
    {
        return $this->belongsTo(Divisions::class, 'class_division_id');
    }
    public function academicYear()
    {
        return $this->belongsTo(academicYear::class, 'academic_year_id');
    }
}
