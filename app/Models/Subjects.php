<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Subjects extends Model
{
    use HasFactory;

    protected $table = 'subjects';

    protected $primaryKey = 'id';

    protected $fillable = [
        'subject_name',
        'periods_per_week',
    ];

    protected function casts(): array
    {
        return ['periods_per_week' => 'integer'];
    }

    public function teachers(): BelongsToMany
    {
        return $this->belongsToMany(Teachers::class, 'subject_teacher', 'subject_id', 'teacher_id')
            ->withTimestamps();
    }

    public function timetableEntries(): HasMany
    {
        return $this->hasMany(TimetableEntry::class, 'subject_id');
    }
}
