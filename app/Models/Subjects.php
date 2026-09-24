<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Subjects extends Model
{
    use HasFactory;

    protected $table = 'subjects';

    protected $primaryKey = 'id';

    protected $fillable = [
        'subject_name',
    ];

    public function teachers(): BelongsToMany
    {
        return $this->belongsToMany(Teachers::class, 'subject_teacher', 'subject_id', 'teacher_id')
            ->withTimestamps();
    }
}
