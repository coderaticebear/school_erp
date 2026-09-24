<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Divisions extends Model
{
    use HasFactory;

    protected $table = 'divisions';

    protected $primaryKey = 'id';

    protected $fillable = [
        'division_name',
        'class_id',
    ];

    public function class(): BelongsTo
    {
        return $this->belongsTo(Classes::class, 'class_id');
    }

    public function studentClasses(): HasMany
    {
        return $this->hasMany(StudentClass::class, 'class_division_id');
    }

    public function teachers(): BelongsToMany
    {
        return $this->belongsToMany(Teachers::class, 'teacher_division', 'division_id', 'teacher_id')
            ->withPivot('class_teacher')
            ->withTimestamps();
    }

    public function timetableEntries(): HasMany
    {
        return $this->hasMany(TimetableEntry::class, 'division_id');
    }

    /**
     * "Grade 5 - A" style label.
     */
    public function getLabelAttribute(): string
    {
        return trim(($this->class->class_name ?? '').' - '.$this->division_name, ' -');
    }
}
