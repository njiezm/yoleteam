<?php

namespace Tests\Feature;

use App\Enums\RaceOutcome;
use App\Models\Outing;
use App\Models\OutingRace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OutingResultTest extends TestCase
{
    use RefreshDatabase;

    public function test_race_results_follow_the_points_rules_and_add_up_to_the_combi(): void
    {
        $user = $this->signInPatron();
        $outing = Outing::factory()->regate()->for($user->association)->create();

        $this->put(route('outings.results.update', $outing), [
            'races' => [
                ['place' => '3', 'result' => 'classe'],
                ['place' => '', 'result' => 'coule'],
                ['place' => '', 'result' => 'avarie'],
                ['place' => '', 'result' => 'disqualifie', 'points' => '12'],
                ['place' => '', 'result' => 'classe'],
            ],
            'day_rank' => '4',
            'general_rank' => '7',
            'stage_rank' => '2',
        ])->assertRedirect(route('outings.show', $outing).'#resultats');

        $outing->refresh()->load('races');
        $this->assertSame([1, 2, 3, 4], $outing->races->pluck('number')->all());
        $this->assertSame([3, RaceOutcome::PENALTY_POINTS, RaceOutcome::PENALTY_POINTS, 12], $outing->races->pluck('points')->all());
        $this->assertSame([RaceOutcome::Classe, RaceOutcome::Coule, RaceOutcome::Avarie, RaceOutcome::Disqualifie], $outing->races->pluck('result')->all());
        $this->assertSame(47, $outing->combiPoints());
        $this->assertSame(4, $outing->day_rank);
        $this->assertSame(7, $outing->general_rank);
        $this->assertNull($outing->stage_rank);

        $this->get(route('outings.show', $outing))
            ->assertOk()
            ->assertSee('Résultats')
            ->assertSee('data-combi>47', false)
            ->assertSee('Place de la journée')
            ->assertDontSee('Classement de l’étape');
    }

    public function test_saving_replaces_every_race_and_drops_removed_rows(): void
    {
        $user = $this->signInPatron();
        $outing = Outing::factory()->regate()->for($user->association)->create();
        OutingRace::factory()->for($outing)->count(3)->sequence(fn ($sequence) => ['number' => $sequence->index + 1])->create();

        $this->put(route('outings.results.update', $outing), [
            'races' => [
                ['place' => '5', 'result' => 'classe', 'remove' => '1'],
                ['place' => '2', 'result' => 'classe'],
            ],
        ])->assertRedirect();

        $this->assertSame([[1, 2, 2]], OutingRace::query()->get()->map(fn (OutingRace $race) => [$race->number, $race->place, $race->points])->all());

        $this->put(route('outings.results.update', $outing), ['races' => []])->assertRedirect();
        $this->assertSame(0, OutingRace::count());
    }

    public function test_results_validation(): void
    {
        $user = $this->signInPatron();
        $outing = Outing::factory()->regate()->for($user->association)->create();

        $this->put(route('outings.results.update', $outing), [
            'races' => [
                ['place' => '21', 'result' => 'classe'],
                ['place' => '', 'result' => 'chavire'],
                ['place' => '', 'result' => 'disqualifie'],
            ],
            'day_rank' => '0',
            'general_rank' => '21',
        ])->assertSessionHasErrors(['races.0.place', 'races.1.result', 'races.2.points', 'day_rank', 'general_rank']);

        $this->assertSame(0, OutingRace::count());

        $this->from(route('outings.show', $outing))
            ->followingRedirects()
            ->put(route('outings.results.update', $outing), ['races' => [['result' => 'disqualifie']]])
            ->assertOk()
            ->assertSee('Indiquez les points de la disqualification.');
    }

    public function test_tdy_outings_only_keep_the_stage_and_general_rankings(): void
    {
        $user = $this->signInPatron();
        $outing = Outing::factory()->tdy()->for($user->association)->create();

        $this->put(route('outings.results.update', $outing), [
            'races' => [['place' => '3', 'result' => 'classe']],
            'day_rank' => '4',
            'stage_rank' => '6',
            'general_rank' => '5',
        ])->assertRedirect();

        $outing->refresh();
        $this->assertSame(6, $outing->stage_rank);
        $this->assertSame(5, $outing->general_rank);
        $this->assertNull($outing->day_rank);
        $this->assertSame(0, OutingRace::count());

        $this->get(route('outings.show', $outing))
            ->assertOk()
            ->assertSee('Classement de l’étape')
            ->assertDontSee('Place de la journée')
            ->assertDontSee('Ajouter une course');
    }

    public function test_training_outings_have_no_results(): void
    {
        $user = $this->signInPatron();
        $outing = Outing::factory()->for($user->association)->create();

        $this->get(route('outings.show', $outing))->assertOk()->assertDontSee('Enregistrer les résultats');
        $this->put(route('outings.results.update', $outing), ['general_rank' => '2'])->assertSessionHasErrors('races');
        $this->assertNull($outing->fresh()->general_rank);
    }

    public function test_admins_can_record_results_too(): void
    {
        $user = $this->signInAdmin();
        $outing = Outing::factory()->tdy()->for($user->association)->create();

        $this->put(route('outings.results.update', $outing), ['stage_rank' => '1'])->assertRedirect();

        $this->assertSame(1, $outing->fresh()->stage_rank);
    }

    public function test_guests_and_other_associations_cannot_record_results(): void
    {
        $foreign = Outing::factory()->regate()->create();

        $this->put(route('outings.results.update', $foreign), ['day_rank' => '1'])->assertRedirect(route('login'));

        $this->signInPatron();
        $this->put(route('outings.results.update', $foreign), ['day_rank' => '1'])->assertNotFound();
        $this->assertNull($foreign->fresh()->day_rank);
    }
}
