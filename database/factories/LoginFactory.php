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
}
