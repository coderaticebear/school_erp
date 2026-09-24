<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticable;
use Illuminate\Notifications\Notifiable;

class Login extends Authenticable
{
    use HasFactory, Notifiable;

    public const ROLE_ADMIN = 1;

    public const ROLE_TEACHER = 2;

    public const ROLE_STUDENT = 3;

    public const ROLE_PARENT = 4;

    protected $table = 'login';

    protected $primaryKey = 'id';

    protected $fillable = [
        'email',
        'password',
        'role',
        'is_active',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'role' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Display name for the navbar user menu: the person's name, or the email for admins.
     */
    public function getNameAttribute(): string
    {
        $profile = match ($this->role) {
            self::ROLE_TEACHER => $this->teacher,
            self::ROLE_STUDENT => $this->student,
            self::ROLE_PARENT => $this->parent,
            default => null,
        };

        return $profile ? trim("{$profile->first_name} {$profile->last_name}") : (string) $this->email;
    }

    /**
     * The teacher profile of a teacher login, or 403 when the account has none.
     */
    public function teacherProfile(): Teachers
    {
        return $this->teacher ?? abort(403, 'Your account has no teacher profile. Please contact the office.');
    }

    public function studentProfile(): Students
    {
        return $this->student ?? abort(403, 'Your account has no student profile. Please contact the office.');
    }

    public function parentProfile(): Parents
    {
        return $this->parent ?? abort(403, 'Your account has no parent profile. Please contact the office.');
    }

    public function student()
    {
        return $this->hasOne(Students::class, 'login_id');
    }

    public function teacher()
    {
        return $this->hasOne(Teachers::class, 'login_id');
    }

    public function parent()
    {
        return $this->hasOne(Parents::class, 'login_id');
    }
}
