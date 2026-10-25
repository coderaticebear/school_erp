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

    /**
     * The two-factor fields are hidden and not fillable: only the two-factor code may set them.
     */
    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_confirmed_at',
        'two_factor_recovery_codes',
        'two_factor_last_timestep',
    ];

    protected function casts(): array
    {
        return [
            'role' => 'integer',
            'is_active' => 'boolean',
            'two_factor_secret' => 'encrypted',
            'two_factor_recovery_codes' => 'encrypted:array',
            'two_factor_confirmed_at' => 'datetime',
            'two_factor_last_timestep' => 'integer',
        ];
    }

    /**
     * The name of the role's own dashboard route. Null for an unknown role.
     */
    public function dashboardRouteName(): ?string
    {
        return match ($this->role) {
            self::ROLE_ADMIN => 'admin.dashboard',
            self::ROLE_TEACHER => 'teacher.dashboard',
            self::ROLE_STUDENT => 'student.dashboard',
            self::ROLE_PARENT => 'parent.dashboard',
            default => null,
        };
    }

    /**
     * True when the user has finished two-factor set-up.
     */
    public function hasTwoFactorEnabled(): bool
    {
        return $this->two_factor_confirmed_at !== null && $this->two_factor_secret !== null;
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
