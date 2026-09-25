<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Classes extends Model
{
    use HasFactory;

    protected $table = 'classes';

    protected $primaryKey = 'id';

    protected $fillable = [
        'class_name',
    ];

    public function divisions(): HasMany
    {
        return $this->hasMany(Divisions::class, 'class_id');
    }
}
