<?php

namespace Tests\Feature;

use App\Enums\OutingStatus;
use App\Enums\RaceOutcome;
use App\Models\Outing;
use App\Models\OutingRace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StatisticsTest extends TestCase
{
    use RefreshDatabase;

    public function test_season_results_of_races_and_tdy_stages(): void
    {
        $user = $this->signInPatron();
        $robert = Outing::factory()->regate()->for($user->association)->create([
            'title' => 'Régate du Robert', 'date' => today()->startOfYear()->addDays(10), 'day_rank' => 4, 'general_rank' => 5,
        ]);
        OutingRace::factory()->for($robert)->create(['number' => 1, 'place' => 3, 'points' => 3]);
        OutingRace::factory()->for($robert)->create(['number' => 2, 'place' => null, 'result' => RaceOutcome::Coule, 'points' => 16]);
        $luce = Outing::factory()->regate()->for($user->association)->create([
            'title' => 'Régate de Sainte-Luce', 'date' => today()->startOfYear()->addDays(20), 'day_rank' => 2, 'general_rank' => 3,
        ]);
        OutingRace::factory()->for($luce)->create(['number' => 1, 'place' => 2, 'points' => 2]);
        Outing::factory()->tdy()->for($user->association)->create([
            'title' => 'Étape Sainte-Anne → Le Vauclin', 'date' => today()->startOfYear()->addDays(30), 'stage_rank' => 6, 'general_rank' => 6,
        ]);
        Outing::factory()->regate()->for($user->association)->create(['title' => 'Régate annulée', 'date' => today()->startOfYear()->addDays(5), 'status' => OutingStatus::Annulee]);
        Outing::factory()->regate()->for($user->association)->create(['title' => 'Régate de l’an dernier', 'date' => today()->subYear()]);
        Outing::factory()->for($user->association)->create(['title' => 'Entraînement du samedi', 'date' => today()->startOfYear()->addDay()]);
        Outing::factory()->regate()->create(['title' => 'Régate d’une autre association', 'date' => today()]);

        $this->get(route('statistics.index'))
            ->assertOk()
            ->assertViewHas('kpis', ['races' => 3, 'raceDaysWithResults' => 2, 'bestDayRank' => 2, 'averageCombi' => 10.5, 'bestGeneralRank' => 3])
            ->assertSeeInOrder(['Régate du Robert', '3ᵉ', 'C', '19', 'Régate de Sainte-Luce'])
            ->assertSee('Étape Sainte-Anne → Le Vauclin')
            ->assertSee(route('outings.show', $robert))
            ->assertDontSee('Régate annulée')
            ->assertDontSee('Régate de l’an dernier')
            ->assertDontSee('Entraînement du samedi')
            ->assertDontSee('Régate d’une autre association');

        $this->get(route('statistics.index', ['type' => 'tdy']))
            ->assertOk()
            ->assertSee('Étape Sainte-Anne → Le Vauclin')
            ->assertDontSee('Régate du Robert');

        $this->get(route('statistics.index', ['saison' => today()->subYear()->year]))
            ->assertOk()
            ->assertSee('Régate de l’an dernier')
            ->assertDontSee('Régate du Robert');
    }

    public function test_empty_season_and_invalid_filters(): void
    {
        $this->signInPatron();

        $this->get(route('statistics.index', ['saison' => 'abc', 'type' => 'entrainement']))
            ->assertOk()
            ->assertViewHas('filters', ['saison' => today()->year, 'type' => ''])
            ->assertSee('Aucun résultat pour cette saison');
    }

    public function test_guests_are_redirected(): void
    {
        $this->get(route('statistics.index'))->assertRedirect(route('login'));
    }
}
