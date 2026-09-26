<?php

namespace Database\Factories;

use App\Enums\OutingType;
use App\Enums\RaceOutcome;
use App\Models\Outing;
use App\Models\OutingRace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OutingRace>
 */
class OutingRaceFactory extends Factory
{
    public function definition(): array
    {
        $place = fake()->numberBetween(1, RaceOutcome::MAX_PLACE);

        return [
            'outing_id' => Outing::factory()->state(['type' => OutingType::Regate]),
            'number' => 1,
            'place' => $place,
            'result' => RaceOutcome::Classe,
            'points' => $place,
        ];
    }
}
