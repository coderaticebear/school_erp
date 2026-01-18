<?php

namespace Database\Factories;

use App\Models\Students;
use App\Models\Login;
use App\Models\Parents;
use Illuminate\Database\Eloquent\Factories\Factory;

class StudentsFactory extends Factory
{
    protected $model = Students::class;

    public function definition()
    {
        return [
            'login_id' => Login::factory()->student(),
            'parent_id' => Parents::factory(),
            'first_name' => $this->faker->firstName,
            'last_name' => $this->faker->lastName,
            'address_line_1' => $this->faker->streetAddress,
            'address_line_2' => $this->faker->secondaryAddress,
            'city' => $this->faker->city,
            'province' => $this->faker->state,
            'country' => $this->faker->country,
            'postal' => $this->faker->postcode,
            'date_of_birth' => $this->faker->date,
            'gender'  => $this->faker->randomElement(['male', 'female']),
            'blood_group' => $this->faker->randomElement(['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-']),
        ];
    }
}
