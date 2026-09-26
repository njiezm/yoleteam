<?php

namespace Database\Factories;

use App\Enums\OutingStatus;
use App\Enums\OutingType;
use App\Models\Association;
use App\Models\Outing;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Outing>
 */
class OutingFactory extends Factory
{
    public function definition(): array
    {
        $date = fake()->dateTimeBetween('-2 weeks', '+2 weeks');

        return [
            'association_id' => Association::factory(),
            'type' => OutingType::Entrainement,
            'title' => 'Entraînement',
            'date' => $date,
            'start_time' => '06:00',
            'end_time' => '08:30',
            'location' => 'Baie du François',
            'status' => $date < now() ? OutingStatus::Terminee : OutingStatus::Planifiee,
        ];
    }

    public function regate(): static
    {
        return $this->state(['type' => OutingType::Regate, 'title' => 'Régate']);
    }

    public function tdy(): static
    {
        return $this->state(['type' => OutingType::Tdy, 'title' => 'Tour des yoles · étape']);
    }
}
