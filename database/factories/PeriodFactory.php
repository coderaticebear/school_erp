<?php

namespace Database\Factories;

use App\Models\Period;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Period>
 */
class PeriodFactory extends Factory
{
    protected $model = Period::class;

    public function definition(): array
    {
        $start = $this->faker->unique()->numberBetween(7, 17);

        return [
            'label' => 'Period '.$start,
            'starts_at' => sprintf('%02d:00', $start),
            'ends_at' => sprintf('%02d:50', $start),
            'is_break' => false,
        ];
    }

    public function break(): static
    {
        return $this->state(fn () => ['is_break' => true, 'label' => 'Break']);
    }
}
