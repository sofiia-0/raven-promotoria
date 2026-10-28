<?php

namespace Database\Factories;

use App\Models\VisitReason;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VisitReason>
 */
class VisitReasonFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => 'Motivo '.fake()->unique()->words(3, true),
            'sort_order' => fake()->numberBetween(1, 50),
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => ['is_active' => false]);
    }
}
