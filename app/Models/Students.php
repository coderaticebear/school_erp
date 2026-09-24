<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Students extends Model
{
    use HasFactory;

    protected $table = 'students';

    protected $primaryKey = 'id';

    protected $fillable = [
        'login_id',
        'parent_id',
        'first_name',
        'last_name',
        'address_line_1',
        'address_line_2',
        'city',
        'province',
        'country',
        'postal',
        'date_of_birth',
        'gender',
        'blood_group',
    ];

    public function login()
    {
        return $this->belongsTo(Login::class, 'login_id');
    }

    public function parent()
    {
        return $this->belongsTo(Parents::class, 'parent_id');
    }

    public function attendance(): HasMany
    {
        return $this->hasMany(Attendance::class, 'student_id');
    }

    /**
     * The student's enrolment for an academic year (defaults to the active year).
     */
    public function enrolmentFor(?AcademicYear $academicYear = null): ?StudentClass
    {
        $academicYear ??= AcademicYear::current();

        return $academicYear
            ? $this->StudentClasses()->where('academic_year_id', $academicYear->id)->with('division.class')->first()
            : null;
    }

    public function StudentClasses()
    {
        return $this->hasMany(StudentClass::class, 'student_id');
    }
}
