<?php

namespace Tests\Feature;

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
 * Several boats on one outing: crews composed together, padlock for rowers on several boats, plans side by side.
 */
class MultiBoatCrewTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Outing $outing;

    private CrewPlan $prixe;

    private CrewPlan $zetwal;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CrewRoleSeeder::class);
        $this->user = $this->signInPatron();
        $this->outing = Outing::factory()->for($this->user->association)->create(['date' => today()]);
        $this->prixe = $this->plan('Prixe – Midea');
        $this->zetwal = $this->plan('Zetwal');
    }

    private function plan(string $boatName): CrewPlan
    {
        $boat = Boat::factory()->for($this->user->association)->create(['name' => $boatName]);
        $configuration = $boat->configurations()->create(['name' => '2 voiles', 'sail_count' => 2, 'bwa_count' => 8, 'is_default' => true]);
        app(BoatLayoutGenerator::class)->generate($configuration);

        return $this->outing->crewPlans()->create(['boat_id' => $boat->id, 'boat_configuration_id' => $configuration->id]);
    }

    private function seat(CrewPlan $plan, string $code, Member $member): void
    {
        $plan->assignments()->create(['boat_position_id' => $plan->configuration->positions()->where('code', $code)->value('id'), 'member_id' => $member->id]);
    }

    public function test_editor_page_holds_every_boat_of_the_outing_in_tabs(): void
    {
        $response = $this->get(route('crew-plans.edit', [$this->outing, $this->zetwal]));
        // The requested boat is shown, the other one is in a hidden tab.
        $this->assertMatchesRegularExpression('/data-editor-panel="'.$this->prixe->id.'"\s+hidden/', $response->getContent());
        $this->assertDoesNotMatchRegularExpression('/data-editor-panel="'.$this->zetwal->id.'"\s+hidden/', $response->getContent());
        $response
            ->assertOk()
            ->assertSee('data-plan-tabs', false)
            ->assertSee(route('crew-plans.update', [$this->outing, $this->prixe]), false)
            ->assertSee(route('crew-plans.update', [$this->outing, $this->zetwal]), false)
            ->assertSee('Un coursier = une yole')
            ->assertSee('Voir tous les plans');
    }

    public function test_a_single_boat_has_no_tabs(): void
    {
        $this->zetwal->delete();

        $this->get(route('crew-plans.edit', [$this->outing, $this->prixe]))->assertOk()->assertDontSee('data-plan-tabs', false);
    }

    public function test_padlock_is_saved_on_the_outing(): void
    {
        $this->putJson(route('outings.share-crew.update', $this->outing), ['share_crew' => true])->assertOk()->assertJson(['share_crew' => true]);
        $this->assertTrue($this->outing->fresh()->share_crew);

        $this->put(route('outings.share-crew.update', $this->outing), ['share_crew' => '0'])->assertRedirect();
        $this->assertFalse($this->outing->fresh()->share_crew);

        $this->putJson(route('outings.share-crew.update', $this->outing), ['share_crew' => 'peut-être'])->assertUnprocessable();
        $this->putJson(route('outings.share-crew.update', Outing::factory()->create()), ['share_crew' => true])->assertNotFound();
    }

    public function test_editor_data_tells_whether_rowers_can_be_reused(): void
    {
        $member = Member::factory()->for($this->user->association)->create();
        $this->seat($this->prixe, 'patron', $member);
        $editor = app(CrewPlanEditorData::class);

        $closed = $editor->build($this->outing->fresh(), $this->zetwal->boat, $this->zetwal, $this->user->association_id);
        $this->assertFalse($closed['shareCrew']);
        $this->assertSame(2, $closed['boatCount']);
        $this->assertSame('Prixe – Midea', collect($closed['members'])->firstWhere('id', $member->id)['elsewhere']);

        $this->outing->update(['share_crew' => true]);
        $this->assertTrue($editor->build($this->outing->fresh(), $this->zetwal->boat, $this->zetwal, $this->user->association_id)['shareCrew']);
    }

    public function test_a_rower_can_be_saved_on_two_boats_when_the_padlock_is_open(): void
    {
        $member = Member::factory()->for($this->user->association)->create();
        $this->outing->update(['share_crew' => true]);
        $this->seat($this->prixe, 'patron', $member);

        $this->putJson(route('crew-plans.update', [$this->outing, $this->zetwal]), [
            'boat_configuration_id' => $this->zetwal->boat_configuration_id,
            'assignments' => [['position_id' => $this->zetwal->configuration->positions()->where('code', 'patron')->value('id'), 'member_id' => $member->id]],
        ])->assertOk();

        $this->assertSame(2, $member->crewAssignments()->count());
    }

    public function test_overview_shows_every_plan_and_rowers_on_several_boats(): void
    {
        $member = Member::factory()->for($this->user->association)->create(['first_name' => 'Ludovic']);
        $this->seat($this->prixe, 'patron', $member);
        $this->seat($this->zetwal, 'patron', $member);

        $this->get(route('crew-plans.index', $this->outing))
            ->assertOk()
            ->assertSeeInOrder(['Prixe – Midea', 'Zetwal'])
            ->assertSee('aussi sur Zetwal')
            ->assertSee('aussi sur Prixe – Midea')
            ->assertSee('data-yole=', false);
    }

    public function test_outing_page_offers_to_compose_the_boats_together(): void
    {
        $this->get(route('outings.show', $this->outing))
            ->assertOk()
            ->assertSee('Composer les 2 équipages ensemble')
            ->assertSee(route('crew-plans.index', $this->outing), false)
            ->assertSee(route('outings.share-crew.update', $this->outing), false);
    }

    public function test_padlock_changed_offline_is_replayed(): void
    {
        $this->postJson(route('sync.store'), ['device_id' => 'phone', 'sent_at' => now()->toIso8601String(), 'operations' => [[
            'id' => (string) Str::uuid(), 'entity' => 'form', 'entity_uuid' => (string) Str::uuid(),
            'payload' => ['method' => 'PUT', 'url' => route('outings.share-crew.update', $this->outing, false), 'fields' => ['share_crew' => '1'], 'label' => 'Cadenas'],
            'client_updated_at' => now()->subMinute()->toIso8601String(),
        ]]])->assertOk()->assertJsonPath('results.0.status', 'applied');

        $this->assertTrue($this->outing->fresh()->share_crew);
    }
}
