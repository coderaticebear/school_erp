<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Period extends Model
{
    use HasFactory;

    protected $fillable = [
        'label',
        'starts_at',
        'ends_at',
        'is_break',
    ];

    protected function casts(): array
    {
        return ['is_break' => 'boolean'];
    }

    public function entries(): HasMany
    {
        return $this->hasMany(TimetableEntry::class);
    }

    /**
     * Periods in the order of the school day.
     */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('starts_at')->orderBy('id');
    }

    public function scopeTeaching(Builder $query): Builder
    {
        return $query->where('is_break', false);
    }

    /**
     * "08:30 - 09:20".
     */
    public function getTimeRangeAttribute(): string
    {
        return substr($this->starts_at, 0, 5).' - '.substr($this->ends_at, 0, 5);
    }
}
