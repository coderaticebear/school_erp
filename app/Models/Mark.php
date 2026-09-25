<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Mark extends Model
{
    use HasFactory;

    protected $fillable = [
        'exam_id',
        'student_id',
        'subject_id',
        'division_id',
        'marks',
        'is_absent',
        'remark',
        'entered_by',
    ];

    protected function casts(): array
    {
        return [
            'marks' => 'float',
            'is_absent' => 'boolean',
        ];
    }

    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Students::class, 'student_id');
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subjects::class, 'subject_id');
    }

    public function division(): BelongsTo
    {
        return $this->belongsTo(Divisions::class, 'division_id');
    }
}
