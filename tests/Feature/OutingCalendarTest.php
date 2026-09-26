<?php

namespace Tests\Feature;

use App\Enums\OutingStatus;
use App\Models\Outing;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OutingCalendarTest extends TestCase
{
    use RefreshDatabase;

    public function test_week_month_and_year_print_views(): void
    {
        $user = $this->signInPatron();
        Outing::factory()->for($user->association)->create(['title' => 'Entraînement du mercredi', 'date' => '2026-09-16', 'start_time' => '17:00', 'location' => 'Baie des Mulets']);
        Outing::factory()->regate()->for($user->association)->create(['title' => 'Régate du Vauclin', 'date' => '2026-09-26']);
        Outing::factory()->for($user->association)->create(['title' => 'Sortie de mars', 'date' => '2026-03-07']);
        Outing::factory()->for($user->association)->create(['title' => 'Sortie annulée', 'date' => '2026-09-17', 'status' => OutingStatus::Annulee]);
        Outing::factory()->create(['title' => 'Sortie d’une autre association', 'date' => '2026-09-16']);

        $this->get(route('outings.calendar', ['vue' => 'semaine', 'date' => '2026-09-17']))
            ->assertOk()
            ->assertSee('Semaine du 14 septembre au 20 septembre 2026')
            ->assertSee($user->association->name)
            ->assertSee('Imprimer / Enregistrer en PDF')
            ->assertSee('window.print()', false)
            ->assertSee(['Entraînement du mercredi', '17:00', 'Baie des Mulets'])
            ->assertSee(route('outings.calendar', ['vue' => 'semaine', 'date' => '2026-09-10']))
            ->assertDontSee('Régate du Vauclin')
            ->assertDontSee('Sortie annulée')
            ->assertDontSee('Sortie d’une autre association');

        $this->get(route('outings.calendar', ['vue' => 'mois', 'date' => '2026-09-17']))
            ->assertOk()
            ->assertSee('Septembre 2026')
            ->assertSee(['Entraînement du mercredi', 'Régate du Vauclin'])
            ->assertDontSee('Sortie de mars');

        $this->get(route('outings.calendar', ['vue' => 'annee', 'date' => '2026-09-17']))
            ->assertOk()
            ->assertSee('Année 2026')
            ->assertSeeInOrder(['Mars', 'Sortie de mars', 'Septembre', 'Entraînement du mercredi', 'Régate du Vauclin']);
    }

    public function test_invalid_parameters_fall_back_to_the_current_month(): void
    {
        $this->signInPatron();

        $this->get(route('outings.calendar', ['vue' => 'decennie', 'date' => 'hier']))
            ->assertOk()
            ->assertSee(ucfirst(today()->translatedFormat('F Y')));
    }

    public function test_outings_index_links_to_the_print_views(): void
    {
        $this->signInPatron();

        $this->get(route('outings.index', ['mois' => '2026-03']))
            ->assertOk()
            ->assertSee(route('outings.calendar', ['vue' => 'annee', 'date' => '2026-03-01']));
    }
}
