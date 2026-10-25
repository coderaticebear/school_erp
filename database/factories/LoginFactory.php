<?php

namespace Database\Factories;

use App\Models\Login;
use Illuminate\Database\Eloquent\Factories\Factory;

class LoginFactory extends Factory
{
    protected $model = Login::class;

    public function definition()
    {
        return [
            'email' => $this->faker->unique()->safeEmail,
            'password' => bcrypt('password'),
            'role' => $this->faker->numberBetween(1, 4), // 1=Admin, 2=Teacher, 3=Student, 4=Parent
            'is_active' => true,
        ];
    }

    // Optional: helper states for clarity
    public function admin()
    {
        return $this->state(fn () => ['role' => 1]);
    }

    public function teacher()
    {
        return $this->state(fn () => ['role' => 2]);
    }

    public function student()
    {
        return $this->state(fn () => ['role' => 3]);
    }

    public function parent()
    {
        return $this->state(fn () => ['role' => 4]);
    }

    public function inactive()
    {
        return $this->state(fn () => ['is_active' => false]);
    }

    /**
     * A login that has finished two-factor set-up.
     * The default secret is a valid 32-character base32 string.
     */
    public function withTwoFactor(?string $secret = null): static
    {
        return $this->state(fn () => [
            'two_factor_secret' => $secret ?? 'JBSWY3DPEHPK3PXPJBSWY3DPEHPK3PXP',
            'two_factor_confirmed_at' => now(),
            'two_factor_recovery_codes' => [],
        ]);
    }
}
