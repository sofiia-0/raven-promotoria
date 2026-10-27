<?php

namespace Database\Factories;

use App\Models\Pharmacy;
use App\Models\PharmacyAssignment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PharmacyAssignment>
 */
class PharmacyAssignmentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'pharmacy_id' => Pharmacy::factory(),
            'promoter_id' => User::factory()->promoter(),
            'assigned_at' => now()->subMonths(2),
            'ended_at' => null,
        ];
    }

    public function ended(): static
    {
        return $this->state(fn (array $attributes) => ['ended_at' => now()->subDay()]);
    }
}
