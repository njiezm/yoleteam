<?php

namespace Database\Seeders;

use App\Enums\CrewZone;
use App\Models\CrewRole;
use Illuminate\Database\Seeder;

class CrewRoleSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            ['code' => CrewRole::PATRON, 'label' => 'Patron', 'zone' => CrewZone::Arriere, 'color' => '#F5B700'],
            ['code' => CrewRole::AIDE_PATRON, 'label' => 'Aide-patron', 'zone' => CrewZone::Arriere, 'color' => '#3B82F6'],
            ['code' => CrewRole::PREMIERE_CORDE, 'label' => '1ère corde', 'zone' => CrewZone::Avant, 'color' => '#8B5CF6'],
            ['code' => CrewRole::DEUXIEME_CORDE, 'label' => '2ème corde', 'zone' => CrewZone::Avant, 'color' => '#8B5CF6'],
            ['code' => CrewRole::ECOUTE, 'label' => 'Écoute', 'zone' => CrewZone::Greement, 'color' => '#F97316'],
            ['code' => CrewRole::DRESSEUR, 'label' => 'Dresseur', 'zone' => CrewZone::Bwa, 'color' => '#10B981'],
            ['code' => CrewRole::ECOPEUR, 'label' => 'Écopeur', 'zone' => CrewZone::Coque, 'color' => '#06B6D4'],
        ];

        foreach ($roles as $index => $role) {
            CrewRole::updateOrCreate(
                ['code' => $role['code']],
                [...$role, 'sort_order' => ($index + 1) * 10],
            );
        }
    }
}
