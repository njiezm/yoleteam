<?php

namespace Tests\Feature;

use App\Enums\BoatSide;
use App\Enums\OutingType;
use App\Models\Boat;
use App\Models\CrewPlan;
use App\Models\Member;
use App\Models\Outing;
use App\Models\User;
use App\Services\BoatLayoutGenerator;
use App\Services\CrewPlanEditorData;
use Database\Seeders\CrewRoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Championship days: 2, 3 or more races (manches), with a crew that can change from one race to the next.
 */
class RaceCrewTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Outing $outing;

    private Boat $boat;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CrewRoleSeeder::class);
        $this->user = $this->signInPatron();
        $this->outing = Outing::factory()->for($this->user->association)->create(['type' => OutingType::Regate, 'date' => today()]);
        $this->boat = $this->boat('Prixe – Midea');
    }

    private function boat(string $name): Boat
    {
        $boat = Boat::factory()->for($this->user->association)->create(['name' => $name]);
        foreach ([['1 voile', 1, 9, false], ['2 voiles', 2, 8, true]] as [$config, $sails, $bwa, $default]) {
            app(BoatLayoutGenerator::class)->generate($boat->configurations()->create([
                'name' => $config, 'sail_count' => $sails, 'bwa_count' => $bwa, 'is_default' => $default,
            ]));
        }

        return $boat->load('configurations.positions');
    }

    private function seat(CrewPlan $plan, string $code, ?Member $member = null): Member
    {
        $member ??= Member::factory()->for($this->user->association)->create();
        $plan->assignments()->create([
            'boat_position_id' => $plan->configuration->positions()->where('code', $code)->value('id'),
            'member_id' => $member->id,
        ]);

        return $member;
    }

    private function firstRace(): CrewPlan
    {
        $this->post(route('crew-plans.store', $this->outing), ['boat_id' => $this->boat->id])->assertRedirect();

        return CrewPlan::where('outing_id', $this->outing->id)->sole();
    }

    public function test_next_race_starts_from_the_crew_of_the_previous_one(): void
    {
        $race1 = $this->firstRace();
        $race1->update(['bwa_side' => BoatSide::Tribord, 'fond_count' => 2, 'boat_configuration_id' => $this->boat->configurations->firstWhere('sail_count', 1)->id]);
        $race1->load('configuration');
        $patron = $this->seat($race1, 'patron');
        $bwa = $this->seat($race1, 'bwa_1');

        $this->post(route('crew-plans.store', $this->outing), ['boat_id' => $this->boat->id, 'race_number' => 2, 'copy_from' => $race1->id])
            ->assertRedirect()
            ->assertSessionHas('status', 'Équipage de la manche 1 repris : ajustez-le pour la manche 2');

        $race2 = CrewPlan::where('race_number', 2)->sole();
        $this->assertSame($race1->boat_configuration_id, $race2->boat_configuration_id);
        $this->assertSame(BoatSide::Tribord, $race2->bwa_side);
        $this->assertSame(2, $race2->fond_count);
        $this->assertEqualsCanonicalizing([$patron->id, $bwa->id], $race2->assignments()->pluck('member_id')->all());
        $this->assertSame('Prixe – Midea · Manche 2', $race2->name());

        // Changing race 2 leaves race 1 as it was.
        $race2->assignments()->where('member_id', $bwa->id)->delete();
        $this->assertSame(2, $race1->assignments()->count());
    }

    public function test_outing_page_groups_plans_by_race_and_offers_the_next_one(): void
    {
        $race1 = $this->firstRace();
        $this->post(route('crew-plans.store', $this->outing), ['boat_id' => $this->boat->id, 'race_number' => 2, 'copy_from' => $race1->id]);

        $this->get(route('outings.show', $this->outing))
            ->assertOk()
            ->assertSeeInOrder(['Manche 1', 'Prixe – Midea', 'Manche 2', 'Prixe – Midea', 'Préparer la manche 3'])
            ->assertSee('Un équipage par manche', false)
            // Offline template of the next race, with the crew of the last one.
            ->assertSee('data-template="'.$this->boat->id.':3"', false)
            ->assertSee('"race_number":3', false);
    }

    public function test_submitting_the_next_race_twice_creates_it_once(): void
    {
        $race1 = $this->firstRace();
        $payload = ['boat_id' => $this->boat->id, 'race_number' => 2, 'copy_from' => $race1->id];

        $this->post(route('crew-plans.store', $this->outing), $payload);
        $race2 = CrewPlan::where('race_number', 2)->sole();
        $this->post(route('crew-plans.store', $this->outing), $payload)->assertRedirect(route('crew-plans.edit', [$this->outing, $race2]));

        $this->assertSame(2, CrewPlan::count());
    }

    public function test_training_outings_keep_one_plan_per_boat(): void
    {
        $this->outing->update(['type' => OutingType::Entrainement]);
        $plan = $this->firstRace();

        $this->post(route('crew-plans.store', $this->outing), ['boat_id' => $this->boat->id, 'race_number' => 2, 'copy_from' => $plan->id])
            ->assertRedirect(route('crew-plans.edit', [$this->outing, $plan]));

        $this->assertSame(1, CrewPlan::count());
        $this->assertSame('Prixe – Midea', $plan->fresh()->name());
        $this->get(route('outings.show', $this->outing))->assertOk()->assertDontSee('Préparer la manche')->assertDontSee('Manche 1');
    }

    public function test_a_rower_can_change_boat_between_two_races_but_not_within_one(): void
    {
        $other = $this->boat('Zetwal');
        $race1 = $this->firstRace();
        $rower = $this->seat($race1, 'bwa_1');

        $editor = app(CrewPlanEditorData::class);
        $outing = $this->outing->fresh();
        $sameRace = collect($editor->build($outing, $other, null, $this->user->association_id, 1)['members'])->firstWhere('id', $rower->id);
        $nextRace = collect($editor->build($outing, $other, null, $this->user->association_id, 2)['members'])->firstWhere('id', $rower->id);

        $this->assertSame('Prixe – Midea', $sameRace['elsewhere']);
        $this->assertNull($nextRace['elsewhere']);
    }

    public function test_a_deleted_member_is_not_carried_to_the_next_race(): void
    {
        $race1 = $this->firstRace();
        $gone = $this->seat($race1, 'patron');
        $this->seat($race1, 'bwa_1');
        $gone->delete();

        $this->post(route('crew-plans.store', $this->outing), ['boat_id' => $this->boat->id, 'race_number' => 2, 'copy_from' => $race1->id]);

        $this->assertSame(1, CrewPlan::where('race_number', 2)->sole()->assignments()->count());
    }

    public function test_copy_from_a_plan_of_another_outing_is_ignored(): void
    {
        $foreign = Outing::factory()->for($this->user->association)->create(['type' => OutingType::Regate]);
        $foreignPlan = $foreign->crewPlans()->create(['boat_id' => $this->boat->id, 'boat_configuration_id' => $this->boat->configurations->first()->id]);
        $foreignPlan->load('configuration');
        $this->seat($foreignPlan, 'patron');
        $this->firstRace();

        $this->post(route('crew-plans.store', $this->outing), ['boat_id' => $this->boat->id, 'race_number' => 2, 'copy_from' => $foreignPlan->id]);

        $this->assertSame(0, CrewPlan::where('outing_id', $this->outing->id)->where('race_number', 2)->sole()->assignments()->count());
    }

    public function test_race_number_is_bounded(): void
    {
        $this->post(route('crew-plans.store', $this->outing), ['boat_id' => $this->boat->id, 'race_number' => 0])->assertSessionHasErrors('race_number');
        $this->post(route('crew-plans.store', $this->outing), ['boat_id' => $this->boat->id, 'race_number' => 21])->assertSessionHasErrors('race_number');
        $this->assertSame(0, CrewPlan::count());
    }

    public function test_next_race_prepared_offline_is_created_at_sync(): void
    {
        $race1 = $this->firstRace();
        $patron = $this->seat($race1, 'patron');
        $planUuid = (string) Str::uuid();

        $this->postJson(route('sync.store'), ['device_id' => 'phone', 'sent_at' => now()->toIso8601String(), 'operations' => [[
            'id' => (string) Str::uuid(),
            'entity' => 'crew_plan',
            'entity_uuid' => $planUuid,
            'payload' => [
                'boat_configuration_id' => $race1->boat_configuration_id, 'bwa_side' => 'babord', 'fond_count' => 1,
                'assignments' => [['position_id' => $race1->configuration->positions()->where('code', 'patron')->value('id'), 'member_id' => $patron->id]],
                'create' => ['outing_uuid' => $this->outing->uuid, 'boat_id' => $this->boat->id, 'race_number' => 2],
            ],
            'client_updated_at' => now()->subMinute()->toIso8601String(),
        ]]])->assertOk()->assertJsonPath('results.0.status', 'applied');

        $race2 = CrewPlan::where('uuid', $planUuid)->sole();
        $this->assertSame(2, $race2->race_number);
        $this->assertSame($patron->id, $race2->assignments()->value('member_id'));
        $this->assertSame(1, $race1->fresh()->assignments()->count());
    }

    public function test_editor_switcher_and_dashboard_label_the_races(): void
    {
        $race1 = $this->firstRace();
        $this->post(route('crew-plans.store', $this->outing), ['boat_id' => $this->boat->id, 'race_number' => 2, 'copy_from' => $race1->id]);

        // The editor groups the boats of one race only: race 2 is composed on its own page.
        $this->get(route('crew-plans.edit', [$this->outing, $race1]))->assertOk()->assertSee('Prixe – Midea · Manche 1')->assertDontSee('data-plan-tabs', false);
        $this->get(route('crew-plans.index', $this->outing))->assertOk()->assertSeeInOrder(['Manche 1', 'Prixe – Midea', 'Manche 2', 'Prixe – Midea']);
        $this->get(route('crew-plans.show', [$this->outing, $race1]))->assertOk()->assertSee('Prixe – Midea · Manche 1');
    }
}
