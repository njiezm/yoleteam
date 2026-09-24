<?php

namespace Tests\Feature;

use App\Enums\AttendanceStatus;
use App\Enums\BoatSide;
use App\Enums\CrewPlanStatus;
use App\Models\Association;
use App\Models\Boat;
use App\Models\CrewAssignment;
use App\Models\CrewPlan;
use App\Models\Member;
use App\Models\Outing;
use App\Models\User;
use App\Services\BoatLayoutGenerator;
use Database\Seeders\CrewRoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CrewPlanTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Boat $boat;

    private Outing $outing;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CrewRoleSeeder::class);
        $this->user = $this->signInPatron();
        $this->boat = $this->boatFor($this->user->association);
        $this->outing = Outing::factory()->for($this->user->association)->create(['date' => today()]);
    }

    private function boatFor(Association $association): Boat
    {
        $boat = Boat::factory()->for($association)->create();
        foreach ([['1 voile', 1, 9, false], ['2 voiles', 2, 8, true]] as [$name, $sails, $bwa, $default]) {
            app(BoatLayoutGenerator::class)->generate($boat->configurations()->create([
                'name' => $name, 'sail_count' => $sails, 'bwa_count' => $bwa, 'is_default' => $default,
            ]));
        }

        return $boat->load('configurations.positions');
    }

    private function plan(): CrewPlan
    {
        return $this->outing->crewPlans()->create([
            'boat_id' => $this->boat->id,
            'boat_configuration_id' => $this->boat->configurations->firstWhere('is_default', true)->id,
        ]);
    }

    private function position(CrewPlan $plan, string $code): int
    {
        return $plan->configuration->positions()->where('code', $code)->value('id');
    }

    public function test_engaging_a_boat_creates_a_plan_with_its_default_configuration(): void
    {
        $this->post(route('crew-plans.store', $this->outing), ['boat_id' => $this->boat->id])
            ->assertRedirect();

        $plan = CrewPlan::sole();
        $this->assertTrue($plan->configuration->is_default);
        $this->assertSame(CrewPlanStatus::Brouillon, $plan->status);

        $this->post(route('crew-plans.store', $this->outing), ['boat_id' => $this->boat->id])->assertSessionHasErrors('boat_id');
    }

    public function test_editor_and_plan_pages_render(): void
    {
        $plan = $this->plan();
        $member = Member::factory()->for($this->user->association)->create(['first_name' => 'Stéphane']);
        $this->outing->attendances()->create(['member_id' => $member->id, 'status' => AttendanceStatus::Present]);
        $plan->assignments()->create(['boat_position_id' => $this->position($plan, 'patron'), 'member_id' => $member->id]);

        $this->get(route('crew-plans.edit', [$this->outing, $plan]))->assertOk()->assertSee('Stéphane');
        $this->get(route('crew-plans.show', [$this->outing, $plan]))->assertOk()->assertSee('Stéphane');
        $this->get(route('crew-plans.today'))->assertRedirect(route('crew-plans.edit', [$this->outing, $plan]));
    }

    public function test_saving_assignments_supports_moves_and_swaps(): void
    {
        $plan = $this->plan();
        [$a, $b] = Member::factory()->for($this->user->association)->count(2)->create();
        $patron = $this->position($plan, 'patron');
        $dresseur = $this->position($plan, 'bwa_1');
        $url = route('crew-plans.update', [$this->outing, $plan]);
        $state = fn (array $assignments) => ['boat_configuration_id' => $plan->boat_configuration_id, 'wind_direction' => 90, 'wind_strength' => 15, 'assignments' => $assignments];

        $this->putJson($url, $state([
            ['position_id' => $patron, 'member_id' => $a->id],
            ['position_id' => $dresseur, 'member_id' => $b->id, 'bwa_placement' => 'exterieur'],
        ]))->assertOk()->assertJsonPath('status', 'brouillon');

        // Swap the two members.
        $this->putJson($url, $state([
            ['position_id' => $patron, 'member_id' => $b->id],
            ['position_id' => $dresseur, 'member_id' => $a->id, 'bwa_placement' => 'interieur'],
        ]))->assertOk();

        $this->assertSame($b->id, CrewAssignment::where('boat_position_id', $patron)->value('member_id'));
        $this->assertSame('interieur', CrewAssignment::where('boat_position_id', $dresseur)->first()->bwa_placement->value);
        $this->assertSame(2, $plan->assignments()->count());
        $this->assertSame(90, $plan->fresh()->wind_direction);
    }

    public function test_switching_configuration(): void
    {
        $plan = $this->plan();
        $oneSail = $this->boat->configurations->firstWhere('sail_count', 1);
        $member = Member::factory()->for($this->user->association)->create();
        $patron = $oneSail->positions->firstWhere('code', 'patron');

        $this->putJson(route('crew-plans.update', [$this->outing, $plan]), [
            'boat_configuration_id' => $oneSail->id,
            'assignments' => [['position_id' => $patron->id, 'member_id' => $member->id]],
        ])->assertOk();

        $this->assertSame($oneSail->id, $plan->fresh()->boat_configuration_id);
        $this->assertSame($patron->id, $plan->assignments()->value('boat_position_id'));
    }

    public function test_invalid_states_are_rejected(): void
    {
        $plan = $this->plan();
        $member = Member::factory()->for($this->user->association)->create();
        $foreign = Member::factory()->create();
        $otherConfigPosition = $this->boat->configurations->firstWhere('sail_count', 1)->positions->first()->id;
        $url = route('crew-plans.update', [$this->outing, $plan]);
        $base = ['boat_configuration_id' => $plan->boat_configuration_id];

        $this->putJson($url, $base + ['assignments' => [
            ['position_id' => $this->position($plan, 'patron'), 'member_id' => $member->id],
            ['position_id' => $this->position($plan, 'ecoute_gv_1'), 'member_id' => $member->id],
        ]])->assertJsonValidationErrors('assignments.1.member_id');

        $this->putJson($url, $base + ['assignments' => [['position_id' => $this->position($plan, 'patron'), 'member_id' => $foreign->id]]])
            ->assertJsonValidationErrors('assignments.0.member_id');

        $this->putJson($url, $base + ['assignments' => [['position_id' => $otherConfigPosition, 'member_id' => $member->id]]])
            ->assertJsonValidationErrors('assignments');

        $otherBoatConfig = $this->boatFor($this->user->association)->configurations->first()->id;
        $this->putJson($url, ['boat_configuration_id' => $otherBoatConfig, 'assignments' => []])
            ->assertJsonValidationErrors('boat_configuration_id');

        $this->assertSame(0, $plan->assignments()->count());
    }

    public function test_validation_and_reopening(): void
    {
        $plan = $this->plan();

        $this->post(route('crew-plans.validation.store', [$this->outing, $plan]))->assertRedirect(route('crew-plans.edit', [$this->outing, $plan]));
        $this->assertFalse($plan->fresh()->isValidated());

        $member = Member::factory()->for($this->user->association)->create();
        $plan->assignments()->create(['boat_position_id' => $this->position($plan, 'patron'), 'member_id' => $member->id]);

        $this->post(route('crew-plans.validation.store', [$this->outing, $plan]))->assertRedirect(route('crew-plans.show', [$this->outing, $plan]));
        $plan->refresh();
        $this->assertTrue($plan->isValidated());
        $this->assertSame($this->user->id, $plan->validated_by);

        // Editing a validated plan turns it into a new draft version.
        $this->putJson(route('crew-plans.update', [$this->outing, $plan]), ['boat_configuration_id' => $plan->boat_configuration_id, 'assignments' => []])
            ->assertJsonPath('status', 'brouillon')
            ->assertJsonPath('version', 2);

        $plan->refresh()->validate($this->user);
        $this->delete(route('crew-plans.validation.destroy', [$this->outing, $plan]))->assertRedirect(route('crew-plans.edit', [$this->outing, $plan]));
        $this->assertSame(3, $plan->fresh()->version);
    }

    public function test_plans_are_scoped_to_their_outing_and_association(): void
    {
        $plan = $this->plan();
        $otherOuting = Outing::factory()->for($this->user->association)->create();

        $this->get(route('crew-plans.edit', [$otherOuting, $plan]))->assertNotFound();

        $this->actingAs(User::factory()->for(Association::factory())->create());
        $this->get(route('crew-plans.edit', [$this->outing, $plan]))->assertNotFound();
    }

    public function test_deleting_a_plan_allows_engaging_the_boat_again(): void
    {
        $plan = $this->plan();

        $this->delete(route('crew-plans.destroy', [$this->outing, $plan]))->assertRedirect(route('outings.show', $this->outing));
        $this->assertSoftDeleted($plan);

        $this->post(route('crew-plans.store', $this->outing), ['boat_id' => $this->boat->id])->assertRedirect();
        $this->assertSame(1, CrewPlan::count());
    }

    public function test_windward_side_and_fond_seats_are_saved_per_plan(): void
    {
        $plan = $this->plan();
        [$a, $b] = Member::factory()->for($this->user->association)->count(2)->create();
        $url = route('crew-plans.update', [$this->outing, $plan]);
        $state = fn (int $fonds, array $assignments) => ['boat_configuration_id' => $plan->boat_configuration_id, 'bwa_side' => 'tribord', 'fond_count' => $fonds, 'assignments' => $assignments];

        $this->putJson($url, $state(2, [
            ['position_id' => $this->position($plan, 'fond_2'), 'member_id' => $a->id],
            ['position_id' => $this->position($plan, 'bwa_8'), 'member_id' => $b->id, 'bwa_placement' => 'exterieur'],
        ]))->assertOk();

        $plan->refresh();
        $this->assertSame(BoatSide::Tribord, $plan->bwa_side);
        $this->assertSame(2, $plan->fond_count);

        // A seat on a fond that the plan does not use is refused.
        $this->putJson($url, $state(1, [['position_id' => $this->position($plan, 'fond_2'), 'member_id' => $a->id]]))
            ->assertJsonValidationErrors('assignments');

        $this->get(route('crew-plans.show', [$this->outing, $plan]))
            ->assertOk()
            ->assertSee('Bwa dressés · au vent tribord')
            ->assertSee('Fonds / écopeurs')
            ->assertSee('bwa au vent tribord');
    }

    public function test_default_rigs_follow_the_usual_crew_of_a_yole_ronde(): void
    {
        $counts = fn (int $sails) => $this->boat->configurations->firstWhere('sail_count', $sails)->positions
            ->reject(fn ($position) => str_starts_with($position->code, 'fond_'))
            ->countBy(fn ($position) => $position->crewRole->code)
            ->all();

        $this->boat->load('configurations.positions.crewRole');
        $this->assertEquals(['patron' => 1, 'aide_patron' => 2, 'premiere_corde' => 1, 'deuxieme_corde' => 1, 'ecoute' => 4, 'dresseur' => 8], $counts(2));
    }
}
