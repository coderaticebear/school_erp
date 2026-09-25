<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AcademicYear extends Model
{
    //
    use HasFactory;

    protected $table = 'academic_year';

    protected $primaryKey = 'id';

    protected $fillable = [
        'year',
        'is_active',
        'timetable_published_at',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'timetable_published_at' => 'datetime'];
    }

    /**
     * The academic year currently marked as active, if any.
     */
    public static function current(): ?self
    {
        return static::query()->where('is_active', true)->first();
    }

    public function studentClass()
    {
        return $this->hasMany(StudentClass::class, 'academic_year_id');
    }
}
