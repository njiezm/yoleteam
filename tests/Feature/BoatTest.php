<?php

namespace Tests\Feature;

use App\Enums\CrewPlanStatus;
use App\Enums\RaceType;
use App\Models\Association;
use App\Models\Boat;
use App\Models\BoatConfiguration;
use App\Models\CrewPlan;
use App\Models\Member;
use App\Models\Outing;
use App\Models\Race;
use App\Services\BoatLayoutGenerator;
use Database\Seeders\CrewRoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BoatTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CrewRoleSeeder::class);
    }

    public function test_boats_index_renders_boat_cards_with_their_configurations(): void
    {
        $user = $this->signInAdmin();
        $this->boatWithConfigurations($user->association, ['name' => 'Ti-Bwa', 'sponsor' => 'Rhum Clément']);
        $this->boatWithConfigurations($user->association, ['name' => 'Zetwal', 'is_active' => false]);

        $this->get(route('boats.index'))
            ->assertOk()
            ->assertSee('Ti-Bwa')
            ->assertSee('Rhum Clément')
            ->assertSee('Opérationnelle')
            ->assertSee('Indisponible')
            ->assertSee('2 voiles · 8 bwa · défaut')
            ->assertSee('1 voile · 9 bwa')
            ->assertSee('17 postes + fonds')
            ->assertSee(route('boats.create'));
    }

    public function test_boat_show_renders_the_default_configuration_and_its_positions(): void
    {
        $user = $this->signInAdmin();
        $boat = $this->boatWithConfigurations($user->association, ['name' => 'Ti-Bwa']);

        $this->get(route('boats.show', $boat))
            ->assertOk()
            ->assertSee('Ti-Bwa · Configuration')
            ->assertSee('Postes (17 + 4 places de fond au choix)')
            ->assertSee('Bwa dressé 8 (dernier)')
            ->assertSee('Écoute grande voile 2')
            ->assertSee('2ème corde')
            ->assertSee('Pagaie 2')
            ->assertSee(route('boats.configurations.update', [$boat, $boat->configurations()->where('is_default', true)->first()]))
            ->assertSee('Nouvelle configuration');
    }

    public function test_boat_show_selects_a_configuration_from_the_query_string(): void
    {
        $user = $this->signInAdmin();
        $boat = $this->boatWithConfigurations($user->association);
        $single = $boat->configurations()->where('name', '1 voile')->first();

        $this->get(route('boats.show', [$boat, 'configuration' => $single->id]))
            ->assertOk()
            ->assertSee('Postes (14 + 4 places de fond au choix)')
            ->assertSee('Bwa dressé 9 (dernier)')
            ->assertDontSee('Écoute grande voile');
    }

    public function test_admin_can_create_a_boat_with_two_generated_configurations(): void
    {
        $user = $this->signInAdmin();

        $response = $this->post(route('boats.store'), [
            'name' => 'Lanbi',
            'sponsor' => 'Ville du Robert',
            'hull_color' => 'vert',
            'length_m' => '9.40',
            'notes' => 'Coque refaite en 2025',
            'is_active' => '1',
        ]);

        $boat = Boat::where('name', 'Lanbi')->firstOrFail();
        $response->assertRedirect(route('boats.show', $boat))->assertSessionHas('status', 'Yole enregistrée');

        $this->assertSame($user->association_id, $boat->association_id);
        $this->assertTrue($boat->is_active);
        $this->assertSame('vert', $boat->hull_color);

        $configurations = $boat->configurations()->withCount('positions')->orderBy('name')->get();
        $this->assertSame(['1 voile (misaine)', '2 voiles'], $configurations->pluck('name')->all());
        $this->assertSame([false, true], $configurations->pluck('is_default')->all());
        $this->assertSame([18, 21], $configurations->pluck('positions_count')->all());
    }

    public function test_boat_creation_is_validated(): void
    {
        $this->signInAdmin();

        $this->post(route('boats.store'), ['name' => '', 'hull_color' => 'fuchsia'])
            ->assertSessionHasErrors(['name' => 'Le champ nom est obligatoire.', 'hull_color']);

        $this->assertDatabaseCount('boats', 0);
    }

    public function test_admin_can_update_boat_details(): void
    {
        $user = $this->signInAdmin();
        $boat = Boat::factory()->for($user->association)->create(['name' => 'Siwo', 'is_active' => true]);

        $this->put(route('boats.update', $boat), ['name' => 'Siwo II', 'hull_color' => 'orange', 'is_active' => '0'])
            ->assertRedirect(route('boats.show', $boat))
            ->assertSessionHas('status', 'Yole enregistrée');

        $boat->refresh();
        $this->assertSame('Siwo II', $boat->name);
        $this->assertSame('orange', $boat->hull_color);
        $this->assertFalse($boat->is_active);
    }

    public function test_changing_bwa_count_regenerates_positions(): void
    {
        $user = $this->signInAdmin();
        $boat = $this->boatWithConfigurations($user->association);
        $configuration = $boat->configurations()->where('is_default', true)->first();

        $this->put(route('boats.configurations.update', [$boat, $configuration]), [
            'name' => '2 voiles large', 'sail_count' => 2, 'bwa_count' => 10, 'is_default' => '1',
        ])
            ->assertRedirect(route('boats.show', [$boat, 'configuration' => $configuration->id]))
            ->assertSessionHasNoErrors();

        $configuration->refresh();
        $this->assertSame('2 voiles large', $configuration->name);
        $this->assertSame(10, $configuration->bwa_count);
        $this->assertSame(23, $configuration->positions()->count());
        $this->assertSame(10, $configuration->positions()->whereNotNull('bwa_index')->count());
    }

    public function test_changing_counts_is_refused_when_a_crew_plan_uses_the_configuration(): void
    {
        $user = $this->signInAdmin();
        $boat = $this->boatWithConfigurations($user->association);
        $configuration = $boat->configurations()->where('is_default', true)->first();
        $this->crewPlanFor($boat, $configuration);

        $this->put(route('boats.configurations.update', [$boat, $configuration]), [
            'name' => '2 voiles', 'sail_count' => 2, 'bwa_count' => 7,
        ])->assertSessionHasErrorsIn('configuration', [
            'bwa_count' => 'Configuration utilisée par 1 plan(s) d’équipage : pour changer l’équipage, créez plutôt une nouvelle configuration.',
        ]);

        $this->assertSame(8, $configuration->fresh()->bwa_count);
        $this->assertSame(21, $configuration->positions()->count());
    }

    public function test_renaming_a_used_configuration_is_allowed(): void
    {
        $user = $this->signInAdmin();
        $boat = $this->boatWithConfigurations($user->association);
        $configuration = $boat->configurations()->where('is_default', true)->first();
        $this->crewPlanFor($boat, $configuration);
        $positionIds = $configuration->positions()->pluck('id')->all();

        $this->put(route('boats.configurations.update', [$boat, $configuration]), [
            'name' => 'Tour des Yoles', 'sail_count' => 2, 'bwa_count' => 8,
        ])->assertSessionHasNoErrors();

        $this->assertSame('Tour des Yoles', $configuration->fresh()->name);
        $this->assertSame($positionIds, $configuration->positions()->pluck('id')->all());
    }

    public function test_making_a_configuration_default_unsets_the_previous_default(): void
    {
        $user = $this->signInAdmin();
        $boat = $this->boatWithConfigurations($user->association);
        $single = $boat->configurations()->where('name', '1 voile')->first();
        $double = $boat->configurations()->where('name', '2 voiles')->first();

        $this->put(route('boats.configurations.update', [$boat, $single]), [
            'name' => '1 voile', 'sail_count' => 1, 'bwa_count' => 9, 'is_default' => '1',
        ])->assertSessionHasNoErrors();

        $this->assertTrue($single->fresh()->is_default);
        $this->assertFalse($double->fresh()->is_default);
    }

    public function test_admin_can_add_a_default_configuration(): void
    {
        $user = $this->signInAdmin();
        $boat = $this->boatWithConfigurations($user->association);

        $this->post(route('boats.configurations.store', $boat), [
            'name' => 'Petit temps', 'sail_count' => 2, 'bwa_count' => 2, 'is_default' => '1',
        ])->assertSessionHasNoErrors()->assertSessionHas('status', 'Configuration créée');

        $created = $boat->configurations()->where('name', 'Petit temps')->firstOrFail();
        $this->assertTrue($created->is_default);
        $this->assertSame(1, $boat->configurations()->where('is_default', true)->count());
        $this->assertSame(15, $created->positions()->count());
    }

    public function test_configuration_validation_uses_its_own_error_bag(): void
    {
        $user = $this->signInAdmin();
        $boat = $this->boatWithConfigurations($user->association);

        $this->post(route('boats.configurations.store', $boat), ['name' => '', 'sail_count' => 3, 'bwa_count' => 13])
            ->assertSessionHasErrorsIn('newConfiguration', ['name', 'sail_count', 'bwa_count']);
    }

    public function test_deleting_the_default_configuration_promotes_another_one(): void
    {
        $user = $this->signInAdmin();
        $boat = $this->boatWithConfigurations($user->association);
        $double = $boat->configurations()->where('is_default', true)->first();

        $this->delete(route('boats.configurations.destroy', [$boat, $double]))
            ->assertRedirect(route('boats.show', $boat));

        $this->assertModelMissing($double);
        $this->assertDatabaseMissing('boat_positions', ['boat_configuration_id' => $double->id]);
        $this->assertTrue($boat->configurations()->sole()->is_default);
    }

    public function test_the_last_configuration_or_a_used_one_cannot_be_deleted(): void
    {
        $user = $this->signInAdmin();
        $boat = $this->boatWithConfigurations($user->association);
        [$double, $single] = [$boat->configurations()->where('is_default', true)->first(), $boat->configurations()->where('is_default', false)->first()];
        $this->crewPlanFor($boat, $double);

        $this->delete(route('boats.configurations.destroy', [$boat, $double]))
            ->assertSessionHasErrorsIn('configuration', ['delete']);
        $this->assertModelExists($double);

        $single->delete();

        $this->delete(route('boats.configurations.destroy', [$boat, $double]))
            ->assertSessionHasErrorsIn('configuration', ['delete']);
        $this->assertModelExists($double);
    }

    public function test_boat_without_crew_plans_can_be_deleted(): void
    {
        $user = $this->signInAdmin();
        $boat = $this->boatWithConfigurations($user->association);

        $this->delete(route('boats.destroy', $boat))
            ->assertRedirect(route('boats.index'))
            ->assertSessionHas('status', 'Yole supprimée');

        $this->assertModelMissing($boat);
        $this->assertDatabaseCount('boat_positions', 0);
    }

    public function test_boat_with_crew_plans_cannot_be_deleted(): void
    {
        $user = $this->signInAdmin();
        $boat = $this->boatWithConfigurations($user->association);
        $this->crewPlanFor($boat, $boat->configurations()->first());

        $this->delete(route('boats.destroy', $boat))
            ->assertRedirect(route('boats.show', $boat))
            ->assertSessionHasErrors('delete');

        $this->assertModelExists($boat);
    }

    public function test_boat_with_race_results_cannot_be_deleted(): void
    {
        $user = $this->signInAdmin();
        $boat = Boat::factory()->for($user->association)->create();
        $race = Race::create(['association_id' => $user->association_id, 'name' => 'Régate du Robert', 'type' => RaceType::Regate, 'season' => 2026, 'start_date' => '2026-05-01']);
        $stage = $race->stages()->create(['number' => 1, 'name' => 'Manche 1', 'date' => '2026-05-01']);
        $stage->results()->create(['boat_id' => $boat->id, 'rank' => 2, 'points' => 2]);

        $this->delete(route('boats.destroy', $boat))->assertSessionHasErrors('delete');

        $this->assertModelExists($boat);
    }

    public function test_boat_with_history_is_deleted_when_the_history_is_confirmed(): void
    {
        $this->seed(CrewRoleSeeder::class);
        $user = $this->signInAdmin();
        $boat = $this->boatWithConfigurations($user->association, ['name' => 'Vieille yole']);
        $outing = Outing::factory()->for($user->association)->create();
        $plan = $outing->crewPlans()->create(['boat_id' => $boat->id, 'boat_configuration_id' => $boat->configurations->first()->id]);
        $member = Member::factory()->for($user->association)->create();
        $plan->assignments()->create(['boat_position_id' => $boat->configurations->first()->positions()->value('id'), 'member_id' => $member->id]);
        $plan->assignments()->first()->delete();
        $race = Race::create(['association_id' => $user->association_id, 'name' => 'Régate', 'type' => RaceType::Regate, 'season' => 2026, 'start_date' => '2026-05-01']);
        $race->stages()->create(['number' => 1, 'name' => 'Manche 1', 'date' => '2026-05-01'])->results()->create(['boat_id' => $boat->id, 'rank' => 2]);

        $this->get(route('boats.show', $boat))->assertOk()->assertSee('1 plan(s) d’équipage et 1 résultat(s) de régate');

        $this->delete(route('boats.destroy', $boat), ['with_history' => 1])->assertRedirect(route('boats.index'));

        $this->assertModelMissing($boat);
        $this->assertSame(0, CrewPlan::withTrashed()->count());
        $this->assertModelExists($member);
        $this->assertModelExists($outing);
    }

    public function test_patron_can_view_boats_read_only(): void
    {
        $user = $this->signInPatron();
        $boat = $this->boatWithConfigurations($user->association, ['name' => 'Ti-Bwa']);

        $this->get(route('boats.index'))
            ->assertOk()
            ->assertSee('Ti-Bwa')
            ->assertDontSee(route('boats.create'));

        $this->get(route('boats.show', $boat))
            ->assertOk()
            ->assertSee('Postes (17 + 4 places de fond au choix)')
            ->assertDontSee('Enregistrer la yole')
            ->assertDontSee('Supprimer la yole')
            ->assertDontSee('Nouvelle configuration');
    }

    public function test_patron_cannot_manage_boats(): void
    {
        $user = $this->signInPatron();
        $boat = $this->boatWithConfigurations($user->association);
        $configuration = $boat->configurations()->first();
        $payload = ['name' => 'X', 'sail_count' => 1, 'bwa_count' => 3];

        $this->get(route('boats.create'))->assertForbidden();
        $this->post(route('boats.store'), ['name' => 'X'])->assertForbidden();
        $this->put(route('boats.update', $boat), ['name' => 'X'])->assertForbidden();
        $this->delete(route('boats.destroy', $boat))->assertForbidden();
        $this->post(route('boats.configurations.store', $boat), $payload)->assertForbidden();
        $this->put(route('boats.configurations.update', [$boat, $configuration]), $payload)->assertForbidden();
        $this->delete(route('boats.configurations.destroy', [$boat, $configuration]))->assertForbidden();

        $this->assertModelExists($boat);
        $this->assertSame(2, $boat->configurations()->count());
    }

    public function test_boats_of_another_association_are_not_found(): void
    {
        $this->signInAdmin();
        $foreign = $this->boatWithConfigurations(Association::factory()->create(), ['name' => 'Kannari']);
        $configuration = $foreign->configurations()->first();

        $this->get(route('boats.index'))->assertOk()->assertDontSee('Kannari');
        $this->get(route('boats.show', $foreign))->assertNotFound();
        $this->put(route('boats.update', $foreign), ['name' => 'X'])->assertNotFound();
        $this->delete(route('boats.destroy', $foreign))->assertNotFound();
        $this->put(route('boats.configurations.update', [$foreign, $configuration]), ['name' => 'X', 'sail_count' => 1, 'bwa_count' => 3])->assertNotFound();
    }

    public function test_configuration_of_another_boat_is_not_found(): void
    {
        $user = $this->signInAdmin();
        $boat = $this->boatWithConfigurations($user->association);
        $other = $this->boatWithConfigurations($user->association);

        $this->put(route('boats.configurations.update', [$boat, $other->configurations()->first()]), ['name' => 'X', 'sail_count' => 1, 'bwa_count' => 3])
            ->assertNotFound();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function boatWithConfigurations(Association $association, array $attributes = []): Boat
    {
        $boat = Boat::factory()->for($association)->create($attributes);
        $generator = app(BoatLayoutGenerator::class);

        $generator->generate($boat->configurations()->create(['name' => '1 voile', 'sail_count' => 1, 'bwa_count' => 9, 'is_default' => false]));
        $generator->generate($boat->configurations()->create(['name' => '2 voiles', 'sail_count' => 2, 'bwa_count' => 8, 'is_default' => true]));

        return $boat;
    }

    private function crewPlanFor(Boat $boat, BoatConfiguration $configuration): CrewPlan
    {
        $outing = Outing::factory()->for($boat->association)->create();

        return $outing->crewPlans()->create([
            'boat_id' => $boat->id,
            'boat_configuration_id' => $configuration->id,
            'status' => CrewPlanStatus::Brouillon,
        ]);
    }
}
