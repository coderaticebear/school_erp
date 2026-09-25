<?php

namespace Database\Factories;

use App\Models\AcademicYear;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AcademicYear>
 */
class AcademicYearFactory extends Factory
{
    protected $model = AcademicYear::class;

    public function definition(): array
    {
        $start = $this->faker->unique()->numberBetween(1990, 2089);

        return [
            'year' => $start.'-'.($start + 1),
            'is_active' => false,
        ];
    }

    /**
     * Only one academic year may be active at a time.
     */
    public function active(): static
    {
        return $this->state(fn () => ['is_active' => true]);
    }
}
