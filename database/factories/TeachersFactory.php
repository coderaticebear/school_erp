<?php

namespace Database\Factories;

use App\Models\Login;
use App\Models\Teachers;
use Illuminate\Database\Eloquent\Factories\Factory;

class TeachersFactory extends Factory
{
    protected $model = Teachers::class;

    public function definition()
    {
        return [
            'login_id' => Login::factory()->teacher(),
            'first_name' => $this->faker->firstName,
            'last_name' => $this->faker->lastName,
            'address_line_1' => $this->faker->streetAddress,
            'address_line_2' => $this->faker->secondaryAddress,
            'city' => $this->faker->city,
            'province' => $this->faker->state,
            'country' => $this->faker->country,
            'postal' => $this->faker->postcode,
        ];
    }
}
