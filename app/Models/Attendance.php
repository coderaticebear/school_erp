<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Attendance extends Model
{
    use HasFactory;

    public const PRESENT = 'present';

    public const ABSENT = 'absent';

    public const LATE = 'late';

    public const EXCUSED = 'excused';

    /**
     * Status => label, in display order.
     */
    public const STATUSES = [
        self::PRESENT => 'Present',
        self::ABSENT => 'Absent',
        self::LATE => 'Late',
        self::EXCUSED => 'Excused',
    ];

    /**
     * Statuses that count as attending for percentages.
     */
    public const ATTENDED = [self::PRESENT, self::LATE];

    protected $table = 'attendance';

    protected $fillable = [
        'academic_year_id',
        'division_id',
        'student_id',
        'date',
        'status',
        'remark',
        'marked_by',
    ];

    protected function casts(): array
    {
        return ['date' => 'date'];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Students::class, 'student_id');
    }

    public function division(): BelongsTo
    {
        return $this->belongsTo(Divisions::class, 'division_id');
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class, 'academic_year_id');
    }

    public function markedBy(): BelongsTo
    {
        return $this->belongsTo(Login::class, 'marked_by');
    }

    public static function badgeClass(string $status): string
    {
        return match ($status) {
            self::PRESENT => 'badge-success',
            self::ABSENT => 'badge-danger',
            self::LATE => 'badge-warning',
            default => 'badge-info',
        };
    }
}
