<?php

namespace Tests\Feature;

use App\Enums\OutingStatus;
use App\Models\Association;
use App\Models\Boat;
use App\Models\Outing;
use App\Services\BoatLayoutGenerator;
use Database\Seeders\CrewRoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OutingTest extends TestCase
{
    use RefreshDatabase;

    private function boatWithConfigurations(Association $association): Boat
    {
        $boat = Boat::factory()->for($association)->create();
        foreach ([['1 voile', 1, 3, false], ['2 voiles', 2, 4, true]] as [$name, $sails, $bwa, $default]) {
            app(BoatLayoutGenerator::class)->generate($boat->configurations()->create([
                'name' => $name, 'sail_count' => $sails, 'bwa_count' => $bwa, 'is_default' => $default,
            ]));
        }

        return $boat->load('configurations');
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get(route('dashboard'))->assertRedirect(route('login'));
        $this->get(route('outings.index'))->assertRedirect(route('login'));
    }

    public function test_dashboard_and_outing_pages_render(): void
    {
        $this->seed(CrewRoleSeeder::class);
        $user = $this->signInPatron();
        $boat = $this->boatWithConfigurations($user->association);
        $outing = Outing::factory()->for($user->association)->create(['date' => today(), 'title' => 'Entraînement du matin']);
        $outing->crewPlans()->create(['boat_id' => $boat->id, 'boat_configuration_id' => $boat->configurations->first()->id]);

        $this->get(route('dashboard'))->assertOk()->assertSee('Entraînement du matin');
        $this->get(route('outings.index'))->assertOk()->assertSee('Entraînement du matin');
        $this->get(route('outings.index', ['filtre' => 'passees', 'mois' => 'garbage']))->assertOk();
        $this->get(route('outings.create'))->assertOk();
        $this->get(route('outings.show', $outing))->assertOk()->assertSee($boat->name);
        $this->get(route('outings.edit', $outing))->assertOk();
    }

    public function test_creating_an_outing_creates_a_crew_plan_per_selected_boat(): void
    {
        $this->seed(CrewRoleSeeder::class);
        $user = $this->signInPatron();
        $boat = $this->boatWithConfigurations($user->association);
        $oneSail = $boat->configurations->firstWhere('sail_count', 1);

        $response = $this->post(route('outings.store'), [
            'type' => 'entrainement',
            'title' => 'Virements',
            'date' => today()->addDay()->toDateString(),
            'start_time' => '07:00',
            'end_time' => '10:00',
            'boats' => [$boat->id],
            'configurations' => [$boat->id => $oneSail->id],
        ]);

        $outing = Outing::sole();
        $response->assertRedirect(route('outings.show', $outing));
        $this->assertSame(OutingStatus::Planifiee, $outing->status);
        $this->assertSame($user->id, $outing->created_by);
        $this->assertDatabaseHas('crew_plans', ['outing_id' => $outing->id, 'boat_id' => $boat->id, 'boat_configuration_id' => $oneSail->id]);
    }

    public function test_outing_validation(): void
    {
        $this->signInPatron();

        $this->post(route('outings.store'), ['type' => 'nope', 'start_time' => '10:00', 'end_time' => '09:00'])
            ->assertSessionHasErrors(['type', 'title', 'date', 'end_time']);
    }

    public function test_cannot_engage_a_boat_of_another_association(): void
    {
        $this->signInPatron();
        $foreign = Boat::factory()->create();

        $this->post(route('outings.store'), ['type' => 'entrainement', 'title' => 'X', 'date' => today()->toDateString(), 'boats' => [$foreign->id]])
            ->assertSessionHasErrors('boats.0');
    }

    public function test_update_and_delete(): void
    {
        $user = $this->signInPatron();
        $outing = Outing::factory()->for($user->association)->create();

        $this->put(route('outings.update', $outing), [
            'type' => 'regate', 'title' => 'Régate du Robert', 'date' => today()->toDateString(), 'status' => 'annulee',
        ])->assertRedirect(route('outings.show', $outing));
        $this->assertSame(OutingStatus::Annulee, $outing->fresh()->status);

        $this->delete(route('outings.destroy', $outing))->assertRedirect(route('outings.index'));
        $this->assertSoftDeleted($outing);
    }

    public function test_outings_of_another_association_are_not_found(): void
    {
        $this->signInPatron();
        $foreign = Outing::factory()->create();

        $this->get(route('outings.show', $foreign))->assertNotFound();
        $this->put(route('outings.update', $foreign), [])->assertNotFound();
        $this->get('/sorties/abc')->assertNotFound();
    }

    public function test_navigation_conditions_are_saved_and_shown(): void
    {
        $user = $this->signInPatron();
        $outing = Outing::factory()->for($user->association)->create();

        $this->put(route('outings.update', $outing), [
            'type' => 'entrainement', 'title' => 'Vent fort', 'date' => today()->toDateString(), 'status' => 'planifiee',
            'wind_direction' => 67, 'wind_strength' => 18, 'wind_gusts' => 25, 'sea_state' => 'agitee', 'swell_m' => '1.5', 'weather' => 'Grains passagers',
        ])->assertRedirect(route('outings.show', $outing));

        $this->get(route('outings.show', $outing))
            ->assertOk()
            ->assertSee('Vent E-NE 18 nds (rafales 25) · mer agitée · houle 1,5 m · Grains passagers');

        $this->put(route('outings.update', $outing), [
            'type' => 'entrainement', 'title' => 'Vent fort', 'date' => today()->toDateString(), 'status' => 'planifiee', 'sea_state' => 'tempete', 'swell_m' => 40,
        ])->assertSessionHasErrors(['sea_state', 'swell_m']);
    }

    public function test_crew_plan_editor_takes_the_outing_wind_by_default(): void
    {
        $this->seed(CrewRoleSeeder::class);
        $user = $this->signInPatron();
        $boat = $this->boatWithConfigurations($user->association);
        $outing = Outing::factory()->for($user->association)->create(['wind_direction' => 90, 'wind_strength' => 14]);
        $plan = $outing->crewPlans()->create(['boat_id' => $boat->id, 'boat_configuration_id' => $boat->configurations->first()->id]);

        $this->get(route('crew-plans.edit', [$outing, $plan]))
            ->assertOk()
            ->assertSee('&quot;wind_direction&quot;:90,&quot;wind_strength&quot;:14', false);
    }
}
