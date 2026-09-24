<?php

namespace Database\Factories;

use App\Models\Association;
use App\Models\Boat;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Boat>
 */
class BoatFactory extends Factory
{
    public function definition(): array
    {
        return [
            'association_id' => Association::factory(),
            'name' => fake()->unique()->randomElement(['Ti-Bwa', 'La Cagou', 'Zetwal', 'Siwo', 'Lanbi', 'Kannari', 'Mabouya', 'Zanndoli']),
            'sponsor' => fake('fr_FR')->optional()->company(),
            'hull_color' => fake()->randomElement(['rouge', 'bleu', 'jaune', 'vert', 'blanc', 'noir']),
            'length_m' => fake()->randomFloat(2, 8.5, 10.5),
            'is_active' => true,
        ];
    }
}
