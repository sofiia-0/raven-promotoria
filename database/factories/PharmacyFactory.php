<?php

namespace Database\Factories;

use App\Models\Pharmacy;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Establecimientos ficticios: nunca usar datos del fichero real de Raven.
 *
 * @extends Factory<Pharmacy>
 */
class PharmacyFactory extends Factory
{
    private const PLACES = [
        ['Managua', 'Managua'], ['Ciudad Sandino', 'Managua'], ['Masaya', 'Masaya'],
        ['Granada', 'Granada'], ['León', 'León'], ['Jinotepe', 'Carazo'],
    ];

    public function definition(): array
    {
        [$city, $department] = fake()->randomElement(self::PLACES);

        return [
            'name' => 'Farmacia '.fake()->unique()->lastName().' '.fake()->randomElement(['Centro', 'Norte', 'Sur', 'Express', 'Popular']),
            'client_type' => fake()->randomElement(['FARMACIA INDEPENDIENTE', 'CADENA', 'MAYORISTA']),
            'category' => fake()->randomElement(['A', 'AA', 'AAA']),
            'neighborhood' => 'Barrio '.fake()->lastName(),
            'address' => fake()->randomElement(['De la rotonda', 'Del parque central', 'De la iglesia']).' '.fake()->numberBetween(1, 5).' c. al '.fake()->randomElement(['norte', 'sur', 'este', 'oeste']),
            'city' => $city,
            'department' => $department,
            'frequency' => fake()->numberBetween(1, 4),
            'phone' => '0000-'.fake()->numerify('####'),
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => ['is_active' => false]);
    }
}
