<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Teachers extends Model
{
    use HasFactory;

    protected $table = 'teachers';

    protected $primaryKey = 'id';

    protected $fillable = [
        'login_id',
        'first_name',
        'last_name',
        'address_line_1',
        'address_line_2',
        'city',
        'province',
        'country',
        'postal',
    ];

    public function login(): BelongsTo
    {
        return $this->belongsTo(Login::class, 'login_id');
    }

    public function subjects(): BelongsToMany
    {
        return $this->belongsToMany(Subjects::class, 'subject_teacher', 'teacher_id', 'subject_id')
            ->withTimestamps();
    }

    /**
     * Divisions this teacher teaches in; `pivot->class_teacher` marks the class teacher.
     */
    public function divisions(): BelongsToMany
    {
        return $this->belongsToMany(Divisions::class, 'teacher_division', 'teacher_id', 'division_id')
            ->withPivot('class_teacher')
            ->withTimestamps();
    }

    public function timetableEntries(): HasMany
    {
        return $this->hasMany(TimetableEntry::class, 'teacher_id');
    }

    public function getFullNameAttribute(): string
    {
        return trim("{$this->first_name} {$this->last_name}");
    }
}
