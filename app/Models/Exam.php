<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Exam extends Model
{
    use HasFactory;

    protected $fillable = [
        'academic_year_id',
        'name',
        'starts_on',
        'max_marks',
        'pass_marks',
        'results_published_at',
    ];

    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'max_marks' => 'integer',
            'pass_marks' => 'integer',
            'results_published_at' => 'datetime',
        ];
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class, 'academic_year_id');
    }

    public function marks(): HasMany
    {
        return $this->hasMany(Mark::class);
    }

    public function isPublished(): bool
    {
        return $this->results_published_at !== null;
    }

    /**
     * Letter grade for a score out of this exam's max marks.
     */
    public function gradeFor(?float $marks): string
    {
        if ($marks === null) {
            return '—';
        }

        if ($marks < $this->pass_marks) {
            return 'F';
        }

        $percent = 100 * $marks / $this->max_marks;

        foreach (config('school.grades') as $grade => $minimum) {
            if ($percent >= $minimum) {
                return $grade;
            }
        }

        return 'F';
    }
}
