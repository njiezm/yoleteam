<?php

namespace Database\Factories;

use App\Models\Association;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Association>
 */
class AssociationFactory extends Factory
{
    public function definition(): array
    {
        $name = 'Association '.fake('fr_FR')->unique()->lastName();

        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'city' => fake()->randomElement(['Le François', 'Le Robert', 'Sainte-Anne', 'Le Vauclin', 'Le Marin', 'Fort-de-France', 'Schoelcher']),
            'primary_color' => fake()->hexColor(),
            'settings' => null,
        ];
    }
}
