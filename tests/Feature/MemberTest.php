<?php

namespace Tests\Feature;

use App\Enums\AttendanceStatus;
use App\Enums\BwaPlacement;
use App\Enums\MemberLevel;
use App\Enums\OutingStatus;
use App\Enums\OutingType;
use App\Models\Attendance;
use App\Models\Boat;
use App\Models\CrewAssignment;
use App\Models\CrewPlan;
use App\Models\CrewRole;
use App\Models\Member;
use App\Models\Outing;
use App\Models\User;
use App\Services\BoatLayoutGenerator;
use Database\Seeders\CrewRoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use Tests\TestCase;

class MemberTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CrewRoleSeeder::class);
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get(route('members.index'))->assertRedirect(route('login'));
    }

    public function test_index_lists_members_with_roles_and_season_rate(): void
    {
        $user = $this->signInAdmin();
        $roles = CrewRole::idsByCode();
        $kevin = $this->member($user, ['first_name' => 'Kévin', 'last_name' => 'Rosemain', 'nickname' => 'Kéké', 'weight_kg' => 78, 'height_cm' => 183]);
        $kevin->crewRoles()->attach([$roles[CrewRole::DRESSEUR] => ['is_preferred' => true], $roles[CrewRole::ECOPEUR] => ['is_preferred' => false]]);
        $this->member($user, ['first_name' => 'Rodrigue', 'last_name' => 'Céleste']);

        $this->attend($kevin, now()->subDays(3), AttendanceStatus::Present);
        $this->attend($kevin, now()->subDays(5), AttendanceStatus::Absent);

        $this->get(route('members.index'))
            ->assertOk()
            ->assertSee('Kévin Rosemain')
            ->assertSee('« Kéké »', false)
            ->assertSee('Rodrigue Céleste')
            ->assertSee('Bwa dressé')
            ->assertSee('Écopeur')
            ->assertSee('78</b> kg · 183 cm', false)
            ->assertSee('50%')
            ->assertSee('Bwa dressé · 1')
            ->assertSee('Ajouter un membre');
    }

    public function test_index_search_matches_names_nickname_and_phone_case_insensitively(): void
    {
        $user = $this->signInAdmin();
        $this->member($user, ['first_name' => 'Kévin', 'last_name' => 'Rosemain', 'nickname' => 'Kéké', 'phone' => '0696 11 22 33']);
        $this->member($user, ['first_name' => 'Rodrigue', 'last_name' => 'Céleste', 'nickname' => null, 'phone' => '0696 99 88 77']);

        $this->get(route('members.index', ['q' => 'ROSE']))->assertSee('Kévin Rosemain')->assertDontSee('Rodrigue Céleste');
        $this->get(route('members.index', ['q' => 'kéké']))->assertSee('Kévin Rosemain')->assertDontSee('Rodrigue Céleste');
        $this->get(route('members.index', ['q' => '99 88']))->assertSee('Rodrigue Céleste')->assertDontSee('Kévin Rosemain');
    }

    public function test_index_filters_by_level_status_and_role(): void
    {
        $user = $this->signInAdmin();
        $roles = CrewRole::idsByCode();
        $expert = $this->member($user, ['first_name' => 'Expertin', 'level' => MemberLevel::Expert]);
        $expert->crewRoles()->attach($roles[CrewRole::PATRON], ['is_preferred' => true]);
        $this->member($user, ['first_name' => 'Debutin', 'level' => MemberLevel::Debutant]);
        $this->member($user, ['first_name' => 'Inactivin', 'is_active' => false]);

        $this->get(route('members.index', ['level' => 'expert']))->assertSee('Expertin')->assertDontSee('Debutin');
        $this->get(route('members.index'))->assertDontSee('Inactivin');
        $this->get(route('members.index', ['status' => 'inactive']))->assertSee('Inactivin')->assertDontSee('Expertin');
        $this->get(route('members.index', ['status' => 'all']))->assertSee('Inactivin')->assertSee('Expertin');
        $this->get(route('members.index', ['role' => CrewRole::PATRON]))->assertSee('Expertin')->assertDontSee('Debutin');
    }

    public function test_index_only_lists_members_of_the_users_association(): void
    {
        $this->signInAdmin();
        Member::factory()->create(['first_name' => 'Étrangère']);

        $this->get(route('members.index'))->assertOk()->assertDontSee('Étrangère');
    }

    public function test_show_renders_profile_statistics_and_recent_positions(): void
    {
        $user = $this->signInAdmin();
        $roles = CrewRole::idsByCode();
        $member = $this->member($user, ['first_name' => 'Kévin', 'last_name' => 'Rosemain', 'phone' => '0696 11 22 33', 'email' => 'kevin@example.test', 'notes' => 'Très bon dresseur par vent fort.']);
        $member->crewRoles()->attach($roles[CrewRole::DRESSEUR], ['is_preferred' => true]);

        $this->attend($member, today()->subDays(10), AttendanceStatus::Absent);
        $this->attend($member, today()->subDays(7), AttendanceStatus::Retard);
        $this->attend($member, today()->subDays(3), AttendanceStatus::Present);
        $regatta = $this->attend($member, today()->subDay(), AttendanceStatus::Present, OutingType::Regate, 'Régate du Robert')->outing;

        $boat = Boat::factory()->for($user->association)->create(['name' => 'Ti-Bwa']);
        $configuration = $boat->configurations()->create(['name' => 'Standard', 'bwa_count' => 3, 'is_default' => true]);
        $position = $configuration->positions()->create([
            'crew_role_id' => $roles[CrewRole::DRESSEUR], 'code' => 'dresseur_babord_1', 'label' => 'Bwa dressé bâbord 1',
            'side' => 'babord', 'bwa_index' => 1, 'sort_order' => 1, 'x' => 10, 'y' => 40,
        ]);
        $plan = CrewPlan::create(['outing_id' => $regatta->id, 'boat_id' => $boat->id, 'boat_configuration_id' => $configuration->id]);
        CrewAssignment::create(['crew_plan_id' => $plan->id, 'boat_position_id' => $position->id, 'member_id' => $member->id, 'bwa_placement' => BwaPlacement::Exterieur]);

        $this->get(route('members.show', $member))
            ->assertOk()
            ->assertSee('Kévin Rosemain')
            ->assertSee('Préféré')
            ->assertSee('tel:0696112233', false)
            ->assertSee('mailto:kevin@example.test', false)
            ->assertSee('75%')
            ->assertViewHas('seasonOnSite', 3)
            ->assertViewHas('seasonLate', 1)
            ->assertViewHas('regattas', 1)
            ->assertViewHas('streak', 3)
            ->assertSee('Régate du Robert')
            ->assertSee('Ti-Bwa')
            ->assertSee('Bwa dressé bâbord 1 · extérieur')
            ->assertSee('Très bon dresseur par vent fort.')
            ->assertSee(route('members.edit', $member));
    }

    public function test_show_renders_for_a_member_without_history(): void
    {
        $user = $this->signInAdmin();
        $member = $this->member($user, ['weight_kg' => null, 'height_cm' => null, 'phone' => null, 'email' => null]);

        $this->get(route('members.show', $member))
            ->assertOk()
            ->assertSee('Pas encore d’appel enregistré', false)
            ->assertSee('Aucun poste occupé', false);
    }

    public function test_patron_can_view_but_not_manage_members(): void
    {
        $user = $this->signInPatron();
        $member = $this->member($user, ['first_name' => 'Kévin']);

        $this->get(route('members.index'))->assertOk()->assertDontSee(route('members.create'));
        $this->get(route('members.show', $member))->assertOk()->assertDontSee(route('members.edit', $member));

        $this->get(route('members.create'))->assertForbidden();
        $this->post(route('members.store'), $this->payload())->assertForbidden();
        $this->get(route('members.edit', $member))->assertForbidden();
        $this->put(route('members.update', $member), $this->payload())->assertForbidden();
        $this->delete(route('members.destroy', $member))->assertForbidden();

        $this->assertSame(1, Member::count());
        $this->assertNotSoftDeleted($member);
    }

    public function test_member_of_another_association_is_not_found(): void
    {
        $this->signInAdmin();
        $other = Member::factory()->create();

        $this->get(route('members.show', $other))->assertNotFound();
        $this->get(route('members.edit', $other))->assertNotFound();
        $this->put(route('members.update', $other), $this->payload())->assertNotFound();
        $this->delete(route('members.destroy', $other))->assertNotFound();
        $this->assertNotSoftDeleted($other);
    }

    public function test_create_form_renders_every_crew_role(): void
    {
        $this->signInAdmin();

        $response = $this->get(route('members.create'))->assertOk()->assertSee('Nouveau membre');

        foreach (CrewRole::all() as $role) {
            $response->assertSee('name="roles[]" value="'.$role->id.'"', false);
        }
    }

    public function test_admin_creates_a_member_with_roles_and_preferred_role(): void
    {
        $user = $this->signInAdmin();
        $roles = CrewRole::idsByCode();

        $response = $this->post(route('members.store'), $this->payload([
            'roles' => [$roles[CrewRole::DRESSEUR], $roles[CrewRole::ECOPEUR]],
            'preferred' => [$roles[CrewRole::DRESSEUR], $roles[CrewRole::PATRON]],
        ]));

        $member = Member::sole();
        $response->assertRedirect(route('members.show', $member))->assertSessionHas('status', 'Membre enregistré');

        $this->assertSame($user->association_id, $member->association_id);
        $this->assertSame('Mickaël', $member->first_name);
        $this->assertSame(MemberLevel::Intermediaire, $member->level);
        $this->assertTrue($member->is_active);
        $this->assertSame('74.5', $member->weight_kg);
        $this->assertEquals(
            [$roles[CrewRole::DRESSEUR] => 1, $roles[CrewRole::ECOPEUR] => 0],
            $member->crewRoles()->pluck('is_preferred', 'crew_roles.id')->map(fn ($value) => (int) $value)->all(),
        );
    }

    public function test_admin_updates_a_member_and_resyncs_roles(): void
    {
        $user = $this->signInAdmin();
        $roles = CrewRole::idsByCode();
        $member = $this->member($user);
        $member->crewRoles()->attach([$roles[CrewRole::PATRON] => ['is_preferred' => true], $roles[CrewRole::DRESSEUR] => ['is_preferred' => false]]);

        $this->get(route('members.edit', $member))->assertOk()->assertSee('Supprimer');

        $payload = Arr::except($this->payload([
            'first_name' => 'Jean-Marc',
            'roles' => [$roles[CrewRole::DRESSEUR]],
            'preferred' => [$roles[CrewRole::DRESSEUR]],
        ]), 'is_active');

        $this->put(route('members.update', $member), $payload)->assertRedirect(route('members.show', $member));

        $member->refresh();
        $this->assertSame('Jean-Marc', $member->first_name);
        $this->assertFalse($member->is_active);
        $this->assertEquals(
            [$roles[CrewRole::DRESSEUR] => 1],
            $member->crewRoles()->pluck('is_preferred', 'crew_roles.id')->map(fn ($value) => (int) $value)->all(),
        );
    }

    public function test_store_requires_names_and_level(): void
    {
        $this->signInAdmin();

        $this->from(route('members.create'))
            ->post(route('members.store'), [])
            ->assertRedirect(route('members.create'))
            ->assertSessionHasErrors([
                'first_name' => 'Le champ prénom est obligatoire.',
                'last_name' => 'Le champ nom est obligatoire.',
                'level' => 'Le champ niveau est obligatoire.',
            ]);

        $this->assertSame(0, Member::count());
    }

    public function test_store_validates_gabarit_bounds_and_roles(): void
    {
        $this->signInAdmin();

        $this->post(route('members.store'), $this->payload(['weight_kg' => 20, 'height_cm' => 250, 'email' => 'pas-un-email', 'roles' => [9999]]))
            ->assertSessionHasErrors([
                'weight_kg' => 'Le champ poids doit être compris entre 30 et 150.',
                'height_cm' => 'Le champ taille doit être compris entre 100 et 220.',
                'email',
                'roles.0',
            ]);

        $this->assertSame(0, Member::count());
    }

    public function test_admin_soft_deletes_a_member(): void
    {
        $user = $this->signInAdmin();
        $member = $this->member($user);

        $this->delete(route('members.destroy', $member))
            ->assertRedirect(route('members.index'))
            ->assertSessionHas('status');

        $this->assertSoftDeleted($member);
    }

    public function test_more_page_shows_settings_only_to_admins(): void
    {
        $admin = $this->signInAdmin();

        $this->get(route('more'))
            ->assertOk()
            ->assertSee($admin->name)
            ->assertSee($admin->association->name)
            ->assertSee(route('members.index'))
            ->assertSee(route('statistics.index'))
            ->assertSee(route('settings.edit'))
            ->assertSee(route('logout'));

        $this->signInPatron();

        $this->get(route('more'))
            ->assertOk()
            ->assertSee('Patron')
            ->assertSee(route('boats.index'))
            ->assertDontSee(route('settings.edit'));
    }

    public function test_index_and_show_display_age_and_years_of_yole(): void
    {
        $this->travelTo('2026-06-15 10:00:00');
        $user = $this->signInAdmin();
        $member = $this->member($user, ['first_name' => 'Kévin', 'last_name' => 'Rosemain', 'nickname' => 'Kéké', 'birth_date' => '1990-09-01', 'yole_since_year' => 2014]);
        $this->member($user, ['first_name' => 'Novice', 'birth_date' => '2008-01-10', 'yole_since_year' => 2026]);

        $this->get(route('members.index'))
            ->assertOk()
            ->assertSee('« Kéké » · 35 ans · 12 ans de yole', false)
            ->assertSee('18 ans · Première année de yole', false);

        $this->get(route('members.show', $member))
            ->assertOk()
            ->assertSee('35 ans · 12 ans de yole', false);
    }

    public function test_member_without_birth_date_or_start_year_shows_neither(): void
    {
        $user = $this->signInAdmin();
        $member = $this->member($user, ['birth_date' => null, 'yole_since_year' => null]);

        $this->get(route('members.show', $member))->assertOk()->assertDontSee('de yole');
    }

    public function test_form_has_start_year_and_live_age_hooks_but_no_category(): void
    {
        $user = $this->signInAdmin();
        $member = $this->member($user, ['birth_date' => now()->subYears(30)->subMonth(), 'yole_since_year' => 2010]);

        $this->get(route('members.create'))
            ->assertOk()
            ->assertSee('Pratique la yole depuis (année)')
            ->assertSee('data-member-form', false)
            ->assertSee('data-member-birth-date', false)
            ->assertSee('data-member-age', false)
            ->assertSee('data-offline-form', false)
            ->assertDontSee('name="category"', false)
            ->assertDontSee('Catégorie');

        $this->get(route('members.edit', $member))
            ->assertOk()
            ->assertSee('value="2010"', false)
            ->assertSee('30 ans');
    }

    public function test_admin_saves_the_yole_start_year_and_category_is_ignored(): void
    {
        $this->signInAdmin();

        $this->post(route('members.store'), $this->payload(['yole_since_year' => '2016', 'category' => 'jeune']))->assertSessionHasNoErrors();

        $member = Member::sole();
        $this->assertSame(2016, $member->yole_since_year);
        $this->assertSame(today()->year - 2016, $member->yoleYears());
        $this->assertDatabaseHas('members', ['id' => $member->id, 'category' => null]);
    }

    public function test_yole_start_year_is_bounded(): void
    {
        $this->signInAdmin();

        $this->post(route('members.store'), $this->payload(['yole_since_year' => '1949']))->assertSessionHasErrors('yole_since_year');
        $this->post(route('members.store'), $this->payload(['yole_since_year' => (string) (today()->year + 1)]))->assertSessionHasErrors('yole_since_year');
        $this->post(route('members.store'), $this->payload(['yole_since_year' => 'abc']))->assertSessionHasErrors('yole_since_year');

        $this->assertSame(0, Member::count());
    }

    public function test_index_links_to_exports_with_the_current_filters(): void
    {
        $this->signInPatron();

        $this->get(route('members.index', ['q' => 'Rose', 'level' => 'expert']))
            ->assertOk()
            ->assertSee('Exporter Excel')
            ->assertSee(route('members.export', ['q' => 'Rose', 'level' => 'expert']))
            ->assertSee(route('members.print', ['q' => 'Rose', 'level' => 'expert']));
    }

    public function test_excel_export_is_a_valid_xlsx_with_the_filtered_members(): void
    {
        $this->travelTo('2026-06-15 10:00:00');
        $user = $this->signInAdmin();
        $roles = CrewRole::idsByCode();
        $kevin = $this->member($user, ['first_name' => 'Kévin', 'last_name' => 'Rosemain', 'level' => MemberLevel::Expert, 'birth_date' => '1990-09-01', 'yole_since_year' => 2014, 'weight_kg' => 78.5]);
        $kevin->crewRoles()->attach([$roles[CrewRole::DRESSEUR] => ['is_preferred' => true], $roles[CrewRole::ECOPEUR] => ['is_preferred' => false]]);
        $this->attend($kevin, now()->subDays(3), AttendanceStatus::Present);
        $this->member($user, ['first_name' => 'Debutin', 'level' => MemberLevel::Debutant]);
        Member::factory()->create(['first_name' => 'Étrangère', 'level' => MemberLevel::Expert]);

        $response = $this->get(route('members.export', ['level' => 'expert']))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
            ->assertDownload('membres-2026-06-15.xlsx');

        $sheet = $this->sheetXml($response->streamedContent());

        $this->assertStringContainsString('<t xml:space="preserve">Années de yole</t>', $sheet);
        $this->assertStringContainsString('<t xml:space="preserve">Rosemain</t>', $sheet);
        $this->assertStringContainsString('<t xml:space="preserve">Bwa dressé ★, Écopeur</t>', $sheet);
        $this->assertStringContainsString('<c r="D2"><v>35</v></c><c r="E2"><v>12</v></c><c r="F2"><v>78.5</v></c>', $sheet);
        $this->assertStringContainsString('<c r="M2"><v>100</v></c>', $sheet);
        $this->assertStringNotContainsString('Debutin', $sheet);
        $this->assertStringNotContainsString('Étrangère', $sheet);
    }

    public function test_print_page_lists_the_filtered_members_and_opens_the_print_dialog(): void
    {
        $user = $this->signInPatron();
        $this->member($user, ['first_name' => 'Kévin', 'last_name' => 'Rosemain']);
        $this->member($user, ['first_name' => 'Rodrigue', 'last_name' => 'Céleste']);

        $this->get(route('members.print', ['q' => 'rose']))
            ->assertOk()
            ->assertSee($user->association->name)
            ->assertSee('Liste des membres')
            ->assertSee('Recherche « rose » · Membres actifs', false)
            ->assertSee('Rosemain')
            ->assertDontSee('Céleste')
            ->assertSee('Imprimer / Enregistrer en PDF')
            ->assertSee('window.print()', false);
    }

    public function test_guests_cannot_export_members(): void
    {
        $this->get(route('members.export'))->assertRedirect(route('login'));
        $this->get(route('members.print'))->assertRedirect(route('login'));
    }

    private function sheetXml(string $binary): string
    {
        $path = tempnam(sys_get_temp_dir(), 'test-xlsx');
        file_put_contents($path, $binary);

        $zip = new \ZipArchive;
        $this->assertTrue($zip->open($path));
        $this->assertNotFalse($zip->locateName('[Content_Types].xml'));
        $sheet = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();
        unlink($path);

        $this->assertIsString($sheet);
        $this->assertNotFalse(simplexml_load_string($sheet));

        return $sheet;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function member(User $user, array $attributes = []): Member
    {
        return Member::factory()->for($user->association)->create($attributes);
    }

    private function attend(Member $member, \DateTimeInterface $date, AttendanceStatus $status, OutingType $type = OutingType::Entrainement, string $title = 'Entraînement'): Attendance
    {
        $outing = Outing::factory()->for($member->association)->create([
            'date' => $date,
            'type' => $type,
            'title' => $title,
            'status' => OutingStatus::Terminee,
        ]);

        return Attendance::create(['outing_id' => $outing->id, 'member_id' => $member->id, 'status' => $status]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return [
            'first_name' => 'Mickaël',
            'last_name' => 'Sainte-Rose',
            'nickname' => 'Mika',
            'birth_date' => '2004-03-12',
            'gender' => 'm',
            'phone' => '0696 12 34 56',
            'email' => 'mika@example.test',
            'weight_kg' => '74.5',
            'height_cm' => '178',
            'level' => 'intermediaire',
            'yole_since_year' => '2016',
            'notes' => 'Disponible le mercredi.',
            'is_active' => '1',
            ...$overrides,
        ];
    }

    public function test_deleting_a_member_frees_upcoming_seats_and_keeps_past_ones(): void
    {
        $this->seed(CrewRoleSeeder::class);
        $user = $this->signInAdmin();
        $member = Member::factory()->for($user->association)->create(['first_name' => 'Parti']);
        $boat = Boat::factory()->for($user->association)->create();
        $configuration = $boat->configurations()->create(['name' => '1 voile', 'sail_count' => 1, 'bwa_count' => 9, 'is_default' => true]);
        app(BoatLayoutGenerator::class)->generate($configuration);
        $patron = $configuration->positions()->where('code', 'patron')->value('id');

        $plans = collect([today()->subWeek(), today()])->map(function ($date) use ($user, $boat, $configuration, $patron, $member) {
            $outing = Outing::factory()->for($user->association)->create(['date' => $date]);
            $plan = $outing->crewPlans()->create(['boat_id' => $boat->id, 'boat_configuration_id' => $configuration->id]);
            $plan->assignments()->create(['boat_position_id' => $patron, 'member_id' => $member->id]);

            return $plan;
        });

        $this->delete(route('members.destroy', $member))->assertRedirect(route('members.index'));

        [$past, $upcoming] = $plans;
        $this->assertSame(1, $past->assignments()->count());
        $this->assertSame(0, $upcoming->assignments()->count());

        // Past plan and its outing still render, showing the deleted member.
        $this->get(route('crew-plans.show', [$past->outing_id, $past]))->assertOk()->assertSee('Parti');
        $this->get(route('outings.show', $past->outing_id))->assertOk();
        // The editor leaves the seat free rather than keeping a member who can no longer be saved.
        $this->get(route('crew-plans.edit', [$past->outing_id, $past]))->assertOk()->assertDontSee('&quot;patron&quot;:{&quot;member_id&quot;', false);
    }
}
