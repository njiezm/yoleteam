<?php

namespace Tests\Feature;

use App\Enums\AttendanceStatus;
use App\Enums\OutingStatus;
use App\Enums\OutingType;
use App\Models\Boat;
use App\Models\CrewPlan;
use App\Models\Member;
use App\Models\Outing;
use App\Models\User;
use App\Services\BoatLayoutGenerator;
use Database\Seeders\CrewRoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Outing page, step by step: appel → équipages → impressions de navigation → (résultats) → valider la sortie.
 */
class OutingFlowTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Outing $outing;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CrewRoleSeeder::class);
        $this->user = $this->signInPatron();
        $this->outing = Outing::factory()->for($this->user->association)->create([
            'type' => OutingType::Entrainement, 'date' => today(), 'start_time' => '06:00', 'end_time' => null, 'status' => OutingStatus::Planifiee,
        ]);
    }

    private function plan(): CrewPlan
    {
        $boat = Boat::factory()->for($this->user->association)->create(['name' => 'Prixe – Midea']);
        $configuration = $boat->configurations()->create(['name' => '2 voiles', 'sail_count' => 2, 'bwa_count' => 8, 'is_default' => true]);
        app(BoatLayoutGenerator::class)->generate($configuration);

        return $this->outing->crewPlans()->create(['boat_id' => $boat->id, 'boat_configuration_id' => $configuration->id]);
    }

    public function test_outing_page_chains_appel_crews_navigation_and_validation(): void
    {
        Member::factory()->for($this->user->association)->create(['first_name' => 'Ludovic', 'is_active' => true]);
        $this->plan();

        $this->get(route('outings.show', $this->outing))
            ->assertOk()
            ->assertSeeInOrder(['1. Appel', 'Ludovic', 'Appel terminé', '2. Équipages', 'Prixe – Midea', 'Composer l’équipage', '3. Impressions de navigation', 'Valider la sortie'])
            ->assertSee('data-attendance', false)
            ->assertSee(route('attendance.update', $this->outing), false)
            ->assertSee(route('outings.navigation.update', $this->outing->uuid), false)
            ->assertSee(route('outings.complete', $this->outing->uuid), false)
            // No drawing preview any more on the outing page (only in the offline editor template).
            ->assertDontSee('data-yole=', false)
            ->assertDontSee('Déroulé avant la sortie');
    }

    public function test_the_appel_recorded_from_the_outing_page_is_saved(): void
    {
        $member = Member::factory()->for($this->user->association)->create();

        $this->putJson(route('attendance.update', $this->outing), ['statuses' => [$member->id => 'present']])->assertOk();

        $this->assertSame(AttendanceStatus::Present, $this->outing->attendances()->sole()->status);
        $this->get(route('outings.show', $this->outing))->assertOk()->assertSee('1 présent(s)');
    }

    public function test_navigation_impressions_are_saved_with_duration_and_speed(): void
    {
        $this->put(route('outings.navigation.update', $this->outing->uuid), [
            'end_time' => '08:00', 'distance_nm' => '10', 'impressions' => 'Bonne glisse, virements à reprendre',
        ])->assertRedirect(route('outings.show', $this->outing).'#navigation');

        $outing = $this->outing->fresh();
        $this->assertSame('Bonne glisse, virements à reprendre', $outing->impressions);
        $this->assertSame(120, $outing->durationMinutes());
        $this->assertSame(5.0, $outing->averageSpeedKnots());

        $this->get(route('outings.show', $this->outing))->assertOk()->assertSee(['2 h', '10,0 milles', '5,0 nœuds', 'Bonne glisse, virements à reprendre']);
    }

    public function test_navigation_is_validated(): void
    {
        $this->put(route('outings.navigation.update', $this->outing->uuid), ['end_time' => '05:30'])->assertSessionHasErrors('end_time');
        $this->put(route('outings.navigation.update', $this->outing->uuid), ['distance_nm' => '-1'])->assertSessionHasErrors('distance_nm');
        $this->put(route('outings.navigation.update', $this->outing->uuid), ['end_time' => '8h'])->assertSessionHasErrors('end_time');
        $this->put(route('outings.navigation.update', $this->outing->uuid), ['impressions' => str_repeat('a', 5001)])->assertSessionHasErrors('impressions');

        $this->assertNull($this->outing->fresh()->end_time);
    }

    public function test_navigation_goes_back_to_the_page_it_came_from_but_never_to_another_site(): void
    {
        $plan = $this->plan();
        $back = route('crew-plans.show', [$this->outing, $plan], false).'#navigation';

        $this->put(route('outings.navigation.update', $this->outing->uuid), ['impressions' => 'Ok', 'redirect_to' => $back])->assertRedirect($back);
        $this->put(route('outings.navigation.update', $this->outing->uuid), ['impressions' => 'Ok', 'redirect_to' => 'https://evil.test'])
            ->assertRedirect(route('outings.show', $this->outing).'#navigation');
        $this->put(route('outings.navigation.update', $this->outing->uuid), ['impressions' => 'Ok', 'redirect_to' => '//evil.test'])
            ->assertRedirect(route('outings.show', $this->outing).'#navigation');
    }

    public function test_validated_crew_plan_offers_the_navigation_and_the_next_step(): void
    {
        $plan = $this->plan();

        $this->get(route('crew-plans.show', [$this->outing, $plan]))->assertOk()->assertDontSee('Impressions de navigation');

        $plan->assignments()->create([
            'boat_position_id' => $plan->configuration->positions()->where('code', 'patron')->value('id'),
            'member_id' => Member::factory()->for($this->user->association)->create()->id,
        ]);
        $this->post(route('crew-plans.validation.store', [$this->outing, $plan]))->assertRedirect(route('crew-plans.show', [$this->outing, $plan]));

        $this->get(route('crew-plans.show', [$this->outing, $plan]))
            ->assertOk()
            ->assertSee('Impressions de navigation')
            ->assertSee(route('outings.navigation.update', $this->outing->uuid), false)
            ->assertSee('Valider la sortie');
    }

    public function test_validating_the_outing_keeps_everything_editable(): void
    {
        $this->post(route('outings.complete', $this->outing->uuid))->assertRedirect(route('outings.show', $this->outing));
        $this->assertSame(OutingStatus::Terminee, $this->outing->fresh()->status);

        $this->get(route('outings.show', $this->outing))->assertOk()->assertSee('Sortie validée')->assertSee('Rouvrir')->assertSee('1. Appel');

        // Still editable after validation.
        $this->put(route('outings.navigation.update', $this->outing->uuid), ['impressions' => 'Ajout après coup'])->assertRedirect();
        $this->assertSame('Ajout après coup', $this->outing->fresh()->impressions);

        $this->delete(route('outings.reopen', $this->outing->uuid))->assertRedirect(route('outings.show', $this->outing));
        $this->assertSame(OutingStatus::EnCours, $this->outing->fresh()->status);
    }

    public function test_outings_of_another_association_cannot_be_touched(): void
    {
        $other = Outing::factory()->create();

        $this->put(route('outings.navigation.update', $other->uuid), ['impressions' => 'Intrus'])->assertNotFound();
        $this->post(route('outings.complete', $other->uuid))->assertNotFound();
        $this->delete(route('outings.reopen', $other->uuid))->assertNotFound();
        $this->put('/sorties/par-identifiant/pas-un-uuid/navigation', [])->assertNotFound();

        $this->assertNull($other->fresh()->impressions);
    }

    public function test_offline_outing_page_has_navigation_and_validation_steps(): void
    {
        $this->get(route('outings.offline'))
            ->assertOk()
            ->assertSeeInOrder(['1. Appel', '2. Équipages', '3. Impressions de navigation', 'Valider la sortie'])
            ->assertSee('data-navigation-form', false)
            ->assertSee('data-complete-form', false);
    }

    public function test_navigation_and_validation_of_an_outing_created_offline_are_replayed_after_it(): void
    {
        $uuid = (string) Str::uuid();
        $form = fn (string $method, string $url, array $fields, int $minutesAgo) => [
            'id' => (string) Str::uuid(), 'entity' => 'form', 'entity_uuid' => (string) Str::uuid(),
            'payload' => ['method' => $method, 'url' => $url, 'fields' => $fields, 'label' => 'Test'],
            'client_updated_at' => now()->subMinutes($minutesAgo)->toIso8601String(),
        ];

        $this->postJson(route('sync.store'), ['device_id' => 'phone', 'sent_at' => now()->toIso8601String(), 'operations' => [
            $form('POST', '/sorties', ['uuid' => $uuid, 'type' => 'entrainement', 'title' => 'Sans réseau', 'date' => today()->toDateString(), 'start_time' => '06:00'], 30),
            $form('PUT', "/sorties/par-identifiant/$uuid/navigation", ['end_time' => '07:30', 'distance_nm' => '6', 'impressions' => 'Mer calme'], 20),
            $form('POST', "/sorties/par-identifiant/$uuid/validation", [], 10),
        ]])->assertOk()
            ->assertJsonPath('results.0.status', 'applied')
            ->assertJsonPath('results.1.status', 'applied')
            ->assertJsonPath('results.2.status', 'applied');

        $outing = Outing::where('uuid', $uuid)->sole();
        $this->assertSame('Mer calme', $outing->impressions);
        $this->assertSame(90, $outing->durationMinutes());
        $this->assertSame(OutingStatus::Terminee, $outing->status);
    }

    public function test_migration_merges_the_former_before_during_after_notes(): void
    {
        $migration = require database_path('migrations/2026_09_26_144555_merge_outing_impressions.php');
        $migration->down();
        DB::table('outings')->where('id', $this->outing->id)->update(['notes_before' => 'Mer formée', 'notes_during' => null, 'notes_after' => 'Virements à reprendre']);

        $migration->up();

        $this->assertSame("Avant : Mer formée\n\nAprès : Virements à reprendre", $this->outing->fresh()->impressions);
    }

    public function test_race_outing_shows_results_as_step_four(): void
    {
        $this->outing->update(['type' => OutingType::Regate]);

        $this->get(route('outings.show', $this->outing))
            ->assertOk()
            ->assertSeeInOrder(['3. Impressions de navigation', '4. Résultats', 'Valider la sortie']);
    }
}
