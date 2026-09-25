<?php

namespace Database\Factories;

use App\Models\Login;
use App\Models\Parents;
use Illuminate\Database\Eloquent\Factories\Factory;

class ParentsFactory extends Factory
{
    protected $model = Parents::class;

    public function definition()
    {
        return [
            'login_id' => Login::factory()->parent(),
            'first_name' => $this->faker->firstName,
            'last_name' => $this->faker->lastName,
            'address_line_1' => $this->faker->streetAddress,
            'address_line_2' => $this->faker->secondaryAddress,
            'city' => $this->faker->city,
            'province' => $this->faker->state,
            'country' => $this->faker->country,
            'postal' => $this->faker->postcode,
            'area_code' => (string) $this->faker->numberBetween(200, 999),
            'phone_number' => $this->faker->numerify('#######'),
        ];
    }
}
