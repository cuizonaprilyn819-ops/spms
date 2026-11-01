<?php

namespace Database\Factories;

use App\Models\Office;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Office>
 */
class OfficeFactory extends Factory
{
    /** A valid recipient for new campus assignments; imported/unconfigured offices stay explicit. */
    public function activeForCampus(): static
    {
        return $this->state(fn (array $attributes) => [
            'campus' => config('hinunangan.campus_id'),
            'is_active' => true,
        ]);
    }

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->company(),
            'code' => fake()->unique()->lexify('OFF-???'),
            'description' => fake()->sentence(),
        ];
    }
}
