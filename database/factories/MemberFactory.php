<?php

namespace Database\Factories;

use App\Enums\Gender;
use App\Enums\MemberCategory;
use App\Enums\MemberLevel;
use App\Models\Association;
use App\Models\Member;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Member>
 */
class MemberFactory extends Factory
{
    public function definition(): array
    {
        $faker = fake('fr_FR');
        $gender = $faker->randomElement([Gender::M, Gender::M, Gender::M, Gender::F]);
        $birth = $faker->dateTimeBetween('-60 years', '-15 years');
        $age = (int) $birth->diff(now())->y;

        return [
            'association_id' => Association::factory(),
            'first_name' => $faker->firstName($gender === Gender::F ? 'female' : 'male'),
            'last_name' => $faker->lastName(),
            'nickname' => null,
            'phone' => '0696 '.$faker->numerify('## ## ##'),
            'email' => $faker->optional()->safeEmail(),
            'birth_date' => $birth,
            'gender' => $gender,
            'weight_kg' => $faker->randomFloat(1, 55, 95),
            'height_cm' => $faker->numberBetween(160, 195),
            'level' => $faker->randomElement(MemberLevel::cases()),
            'category' => match (true) {
                $age < 21 => MemberCategory::Jeune,
                $age >= 45 => MemberCategory::Veteran,
                default => MemberCategory::Senior,
            },
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
