<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TimetableEntry extends Model
{
    use HasFactory;

    protected $fillable = [
        'academic_year_id',
        'division_id',
        'day',
        'period_id',
        'subject_id',
        'teacher_id',
    ];

    protected function casts(): array
    {
        return ['day' => 'integer'];
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class, 'academic_year_id');
    }

    public function division(): BelongsTo
    {
        return $this->belongsTo(Divisions::class, 'division_id');
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(Period::class);
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subjects::class, 'subject_id');
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teachers::class, 'teacher_id');
    }
}
