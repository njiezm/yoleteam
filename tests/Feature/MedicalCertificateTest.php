<?php

namespace Tests\Feature;

use App\Models\Boat;
use App\Models\CrewRole;
use App\Models\Member;
use App\Models\Outing;
use App\Models\User;
use App\Services\BoatLayoutGenerator;
use Database\Seeders\CrewRoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Member sheet: medical certificate (ticked = up to date), weight without the former 150 kg cap, "Corde poitier".
 */
class MedicalCertificateTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = $this->signInAdmin();
    }

    /** @param  array<string, mixed>  $overrides */
    private function payload(array $overrides = []): array
    {
        return ['first_name' => 'Kévin', 'last_name' => 'Rosemain', 'level' => 'intermediaire', 'is_active' => '1', ...$overrides];
    }

    public function test_certificate_is_ticked_or_not_on_the_form(): void
    {
        $this->get(route('members.create'))->assertOk()->assertSee('Certificat médical')->assertSee('name="medical_certificate"', false);

        $this->post(route('members.store'), $this->payload(['medical_certificate' => '1']))->assertSessionHasNoErrors();
        $this->assertTrue(Member::sole()->medical_certificate);

        $member = Member::sole();
        $this->put(route('members.update', $member), $this->payload())->assertSessionHasNoErrors();
        $this->assertFalse($member->fresh()->medical_certificate);
    }

    public function test_members_without_certificate_are_flagged_as_not_up_to_date(): void
    {
        Member::factory()->for($this->user->association)->create(['first_name' => 'Àjour', 'medical_certificate' => true]);
        $late = Member::factory()->for($this->user->association)->create(['first_name' => 'Enretard', 'medical_certificate' => false]);

        $this->get(route('members.index'))
            ->assertOk()
            ->assertSee('1 membre(s) actif(s) sans certificat médical : pas à jour')
            ->assertSee('Certificat médical manquant : pas à jour')
            ->assertSee('Certificat médical à jour');
        $this->get(route('members.index', ['certificate' => 'missing']))->assertOk()->assertSee('Enretard')->assertDontSee('Àjour');
        $this->get(route('members.index', ['certificate' => 'ok']))->assertOk()->assertSee('Àjour')->assertDontSee('Enretard');
        $this->get(route('members.show', $late))->assertOk()->assertSee('Certificat médical manquant');
        $this->get(route('members.print'))->assertOk()->assertSee('certificat manquant');
    }

    public function test_appel_and_crew_editor_show_a_missing_certificate(): void
    {
        $this->seed(CrewRoleSeeder::class);
        Member::factory()->for($this->user->association)->create(['first_name' => 'Enretard', 'medical_certificate' => false]);
        $outing = Outing::factory()->for($this->user->association)->create();
        $boat = Boat::factory()->for($this->user->association)->create();
        $configuration = $boat->configurations()->create(['name' => '2 voiles', 'sail_count' => 2, 'bwa_count' => 8, 'is_default' => true]);
        app(BoatLayoutGenerator::class)->generate($configuration);
        $plan = $outing->crewPlans()->create(['boat_id' => $boat->id, 'boat_configuration_id' => $configuration->id]);

        $this->get(route('attendance.edit', $outing))->assertOk()->assertSee('Certificat médical manquant');
        $this->get(route('crew-plans.edit', [$outing, $plan]))->assertOk()->assertSee('&quot;certificate&quot;:false', false);
    }

    public function test_weight_is_no_longer_capped_at_150_kg(): void
    {
        $this->get(route('members.create'))->assertOk()->assertDontSee('max="150"', false);

        $this->post(route('members.store'), $this->payload(['weight_kg' => '162.5']))->assertSessionHasNoErrors();
        $this->assertSame('162.5', (string) Member::sole()->weight_kg);

        $this->post(route('members.store'), $this->payload(['first_name' => 'Autre', 'weight_kg' => '0']))->assertSessionHasErrors('weight_kg');
    }

    public function test_cordes_are_called_corde_poitier(): void
    {
        $this->seed(CrewRoleSeeder::class);

        $this->assertSame('Corde poitier 1', CrewRole::where('code', CrewRole::PREMIERE_CORDE)->value('label'));
        $this->assertSame('Corde poitier 2', CrewRole::where('code', CrewRole::DEUXIEME_CORDE)->value('label'));

        $boat = Boat::factory()->for($this->user->association)->create();
        $configuration = $boat->configurations()->create(['name' => '2 voiles', 'sail_count' => 2, 'bwa_count' => 8, 'is_default' => true]);
        app(BoatLayoutGenerator::class)->generate($configuration);
        $this->assertEqualsCanonicalizing(['Corde poitier 1', 'Corde poitier 2'], $configuration->positions()->whereIn('code', ['premiere_corde', 'deuxieme_corde'])->pluck('label')->all());

        $this->get(route('members.create'))->assertOk()->assertSee('Corde poitier 1')->assertDontSee('1ère corde');
    }
}
