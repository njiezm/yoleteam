<?php

namespace Tests\Feature;

use App\Enums\RaceResultStatus;
use App\Enums\RaceType;
use App\Models\Association;
use App\Models\Boat;
use App\Models\Race;
use App\Models\RaceResult;
use App\Models\RaceStage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class RaceTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_races_index_shows_the_next_race_and_cumulative_points(): void
    {
        Carbon::setTestNow('2026-09-23');
        $user = $this->signInAdmin();
        [$tour] = $this->tourWithResults($user->association);
        $this->race($user->association, ['name' => 'Régate de Sainte-Anne', 'start_date' => '2026-10-04', 'location' => 'Sainte-Anne']);

        $this->get(route('races.index'))
            ->assertOk()
            ->assertSeeInOrder(['Prochaine échéance · J-11', 'Régate de Sainte-Anne', 'Voir la régate'])
            ->assertSee($tour->name)
            ->assertSee('Tour des Yoles')
            ->assertSee('2 étapes')
            ->assertSee('À venir')
            ->assertSee('7<span class="text-sm"> pts</span>', false)
            ->assertSee('Ti-Bwa');
    }

    public function test_race_show_renders_kpis_stages_and_chart_for_the_selected_boat(): void
    {
        $user = $this->signInAdmin();
        [$race, , $cagou] = $this->tourWithResults($user->association);

        $this->get(route('races.show', $race))
            ->assertOk()
            ->assertSee('Points cumulés')
            ->assertSee('1:04:00')
            ->assertSee('Fort-de-France → Schœlcher')
            ->assertSee('Rang par étape')
            ->assertSee('<polyline', false)
            ->assertSee(route('races.stages.results.update', [$race, $race->stages->first()]))
            ->assertSee('Ajouter une étape');

        $this->get(route('races.show', [$race, 'boat' => $cagou->id]))
            ->assertOk()
            ->assertSee('Abandon')
            ->assertSee('Démâtage');
    }

    public function test_race_show_renders_without_stages_or_results(): void
    {
        $user = $this->signInAdmin();
        $race = $this->race($user->association);

        $this->get(route('races.show', $race))
            ->assertOk()
            ->assertSee('Aucune étape enregistrée')
            ->assertSee('Aucun classement saisi');
    }

    public function test_admin_can_create_update_and_delete_a_race(): void
    {
        $user = $this->signInAdmin();

        $this->get(route('races.create'))->assertOk();

        $this->post(route('races.store'), [
            'name' => 'Régate du Robert',
            'type' => RaceType::Regate->value,
            'season' => 2026,
            'start_date' => '2026-10-11',
            'end_date' => '2026-10-11',
            'location' => 'Le Robert',
        ])->assertSessionHasNoErrors();

        $race = Race::where('name', 'Régate du Robert')->firstOrFail();
        $this->assertSame($user->association_id, $race->association_id);

        $this->get(route('races.edit', $race))->assertOk()->assertSee('Régate du Robert');

        $this->put(route('races.update', $race), [
            'name' => 'Régate du Robert 2026',
            'type' => RaceType::Championnat->value,
            'season' => 2026,
            'start_date' => '2026-10-11',
        ])->assertRedirect(route('races.show', $race));

        $this->assertSame(RaceType::Championnat, $race->fresh()->type);

        $stage = $race->stages()->create(['number' => 1, 'name' => 'Manche unique', 'date' => '2026-10-11']);

        $this->delete(route('races.destroy', $race))->assertRedirect(route('races.index'));

        $this->assertModelMissing($race);
        $this->assertModelMissing($stage);
    }

    public function test_race_validation(): void
    {
        $this->signInAdmin();

        $this->post(route('races.store'), [])
            ->assertSessionHasErrors(['name', 'type', 'season', 'start_date']);

        $this->post(route('races.store'), [
            'name' => 'Régate', 'type' => 'regate', 'season' => 2026, 'start_date' => '2026-10-11', 'end_date' => '2026-10-10',
        ])->assertSessionHasErrors(['end_date']);

        $this->assertDatabaseCount('races', 0);
    }

    public function test_admin_can_add_update_and_delete_a_stage(): void
    {
        $user = $this->signInAdmin();
        $race = $this->race($user->association);

        $this->post(route('races.stages.store', $race), [
            'number' => 1, 'name' => 'Étape 1', 'date' => '2026-07-26',
            'start_location' => 'Fort-de-France', 'end_location' => 'Schœlcher', 'distance_nm' => '8.5',
        ])->assertRedirect(route('races.show', $race))->assertSessionHasNoErrors();

        $stage = $race->stages()->sole();
        $this->assertSame('8.50', $stage->distance_nm);

        $this->put(route('races.stages.update', [$race, $stage]), ['number' => 1, 'name' => 'Étape 1 bis', 'date' => '2026-07-27'])
            ->assertSessionHasNoErrors();
        $this->assertSame('Étape 1 bis', $stage->fresh()->name);

        $this->delete(route('races.stages.destroy', [$race, $stage]))->assertRedirect(route('races.show', $race));
        $this->assertModelMissing($stage);
    }

    public function test_stage_number_is_unique_within_a_race(): void
    {
        $user = $this->signInAdmin();
        $race = $this->race($user->association);
        $other = $this->race($user->association, ['name' => 'Autre régate']);
        $race->stages()->create(['number' => 1, 'name' => 'Étape 1', 'date' => '2026-07-26']);
        $second = $race->stages()->create(['number' => 2, 'name' => 'Étape 2', 'date' => '2026-07-27']);

        $this->post(route('races.stages.store', $race), ['number' => 1, 'name' => 'Doublon', 'date' => '2026-07-28'])
            ->assertSessionHasErrorsIn('newStage', ['number' => 'La valeur du champ numéro est déjà utilisée.']);

        $this->put(route('races.stages.update', [$race, $second]), ['number' => 1, 'name' => 'Étape 2', 'date' => '2026-07-27'])
            ->assertSessionHasErrorsIn('stage-'.$second->id, ['number']);

        $this->post(route('races.stages.store', $other), ['number' => 1, 'name' => 'Étape 1', 'date' => '2026-07-28'])
            ->assertSessionHasNoErrors();

        $this->assertSame(2, $race->stages()->count());
    }

    public function test_results_are_upserted_with_time_parsing_and_empty_rows_removed(): void
    {
        $user = $this->signInAdmin();
        $race = $this->race($user->association);
        $stage = $race->stages()->create(['number' => 1, 'name' => 'Étape 1', 'date' => '2026-07-26']);
        $tiBwa = Boat::factory()->for($user->association)->create(['name' => 'Ti-Bwa']);
        $cagou = Boat::factory()->for($user->association)->create(['name' => 'La Cagou']);
        $zetwal = Boat::factory()->for($user->association)->create(['name' => 'Zetwal']);
        $stage->results()->create(['boat_id' => $cagou->id, 'rank' => 9, 'points' => 9]);
        $existing = $stage->results()->create(['boat_id' => $tiBwa->id, 'rank' => 5, 'points' => 5]);

        $this->put(route('races.stages.results.update', [$race, $stage]), [
            'results' => [
                $tiBwa->id => ['rank' => '4', 'time' => '1:04:00', 'points' => '4', 'status' => 'classe', 'notes' => ''],
                $cagou->id => ['rank' => '', 'time' => '', 'points' => '', 'status' => 'classe', 'notes' => ''],
                $zetwal->id => ['rank' => '', 'time' => '', 'points' => '20', 'status' => 'abandon', 'notes' => 'Démâtage'],
            ],
        ])->assertRedirect(route('races.show', $race))->assertSessionHasNoErrors();

        $existing->refresh();
        $this->assertSame(4, $existing->rank);
        $this->assertSame(3840, $existing->elapsed_seconds);
        $this->assertSame('1:04:00', $existing->elapsed_time);

        $this->assertDatabaseMissing('race_results', ['race_stage_id' => $stage->id, 'boat_id' => $cagou->id]);

        $abandon = RaceResult::where('boat_id', $zetwal->id)->sole();
        $this->assertSame(RaceResultStatus::Abandon, $abandon->status);
        $this->assertNull($abandon->rank);
        $this->assertSame('Démâtage', $abandon->notes);
        $this->assertSame(2, $stage->results()->count());
    }

    public function test_results_validation_rejects_badly_formatted_times(): void
    {
        $user = $this->signInAdmin();
        $race = $this->race($user->association);
        $stage = $race->stages()->create(['number' => 1, 'name' => 'Étape 1', 'date' => '2026-07-26']);
        $boat = Boat::factory()->for($user->association)->create();

        $this->put(route('races.stages.results.update', [$race, $stage]), [
            'results' => [$boat->id => ['rank' => '1', 'time' => '64 min', 'status' => 'classe']],
        ])->assertSessionHasErrorsIn('results-'.$stage->id, [
            "results.{$boat->id}.time" => 'Le temps doit être au format H:MM:SS (ex. 1:04:30).',
        ]);

        $this->assertDatabaseCount('race_results', 0);
    }

    public function test_results_for_boats_of_another_association_are_ignored(): void
    {
        $user = $this->signInAdmin();
        $race = $this->race($user->association);
        $stage = $race->stages()->create(['number' => 1, 'name' => 'Étape 1', 'date' => '2026-07-26']);
        $foreignBoat = Boat::factory()->for(Association::factory())->create();

        $this->put(route('races.stages.results.update', [$race, $stage]), [
            'results' => [$foreignBoat->id => ['rank' => '1', 'status' => 'classe']],
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseCount('race_results', 0);
    }

    public function test_patron_can_view_races_but_not_manage_them(): void
    {
        $user = $this->signInPatron();
        [$race] = $this->tourWithResults($user->association);
        $stage = $race->stages->first();

        $this->get(route('races.index'))->assertOk()->assertDontSee(route('races.create'));
        $this->get(route('races.show', $race))->assertOk()->assertDontSee('Ajouter une étape')->assertDontSee(route('races.edit', $race));

        $this->get(route('races.create'))->assertForbidden();
        $this->post(route('races.store'), [])->assertForbidden();
        $this->get(route('races.edit', $race))->assertForbidden();
        $this->put(route('races.update', $race), [])->assertForbidden();
        $this->delete(route('races.destroy', $race))->assertForbidden();
        $this->post(route('races.stages.store', $race), [])->assertForbidden();
        $this->put(route('races.stages.update', [$race, $stage]), [])->assertForbidden();
        $this->delete(route('races.stages.destroy', [$race, $stage]))->assertForbidden();
        $this->put(route('races.stages.results.update', [$race, $stage]), [])->assertForbidden();

        $this->assertModelExists($race);
    }

    public function test_races_of_another_association_are_not_found(): void
    {
        $this->signInAdmin();
        $foreign = $this->race(Association::factory()->create(), ['name' => 'Régate étrangère']);
        $stage = $foreign->stages()->create(['number' => 1, 'name' => 'Étape 1', 'date' => '2026-07-26']);

        $this->get(route('races.index'))->assertOk()->assertDontSee('Régate étrangère');
        $this->get(route('races.show', $foreign))->assertNotFound();
        $this->get(route('races.edit', $foreign))->assertNotFound();
        $this->delete(route('races.destroy', $foreign))->assertNotFound();
        $this->put(route('races.stages.results.update', [$foreign, $stage]), ['results' => []])->assertNotFound();
    }

    public function test_stage_of_another_race_is_not_found(): void
    {
        $user = $this->signInAdmin();
        $race = $this->race($user->association);
        $other = $this->race($user->association, ['name' => 'Autre']);
        $stage = $other->stages()->create(['number' => 1, 'name' => 'Étape 1', 'date' => '2026-07-26']);

        $this->delete(route('races.stages.destroy', [$race, $stage]))->assertNotFound();
        $this->assertModelExists($stage);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function race(Association $association, array $attributes = []): Race
    {
        return Race::create([
            'association_id' => $association->id,
            'name' => 'Tour des Yoles Rondes 2026',
            'type' => RaceType::TourDesYoles,
            'season' => 2026,
            'start_date' => '2026-07-26',
            'end_date' => '2026-08-02',
            'location' => 'Tour de la Martinique',
            ...$attributes,
        ]);
    }

    /**
     * A two-stage race where Ti-Bwa finishes 4th then 3rd and La Cagou 11th then abandons.
     *
     * @return array{0: Race, 1: Boat, 2: Boat}
     */
    private function tourWithResults(Association $association): array
    {
        $race = $this->race($association);
        $tiBwa = Boat::factory()->for($association)->create(['name' => 'Ti-Bwa']);
        $cagou = Boat::factory()->for($association)->create(['name' => 'La Cagou']);

        /** @var RaceStage $first */
        $first = $race->stages()->create(['number' => 1, 'name' => 'Étape 1', 'date' => '2026-07-26', 'start_location' => 'Fort-de-France', 'end_location' => 'Schœlcher', 'distance_nm' => 8.5]);
        $second = $race->stages()->create(['number' => 2, 'name' => 'Étape 2', 'date' => '2026-07-27', 'start_location' => 'Case-Pilote', 'end_location' => 'Les Anses-d’Arlet', 'distance_nm' => 14]);

        $first->results()->create(['boat_id' => $tiBwa->id, 'rank' => 4, 'elapsed_seconds' => 3840, 'points' => 4]);
        $first->results()->create(['boat_id' => $cagou->id, 'rank' => 11, 'elapsed_seconds' => 4102, 'points' => 11]);
        $second->results()->create(['boat_id' => $tiBwa->id, 'rank' => 3, 'elapsed_seconds' => 6120, 'points' => 3]);
        $second->results()->create(['boat_id' => $cagou->id, 'status' => RaceResultStatus::Abandon, 'points' => 20, 'notes' => 'Démâtage au large du Diamant']);

        return [$race->load('stages'), $tiBwa, $cagou];
    }
}
