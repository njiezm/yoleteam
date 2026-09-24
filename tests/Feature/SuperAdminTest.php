<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Association;
use App\Models\Boat;
use App\Models\Member;
use App\Models\Outing;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SuperAdminTest extends TestCase
{
    use RefreshDatabase;

    private function signInSuperAdmin(?Association $association = null): User
    {
        $user = User::factory()->superAdmin()->for($association ?? Association::factory())->create();
        $this->actingAs($user);

        return $user;
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: bool}>
     */
    public static function superAdminRoutes(): array
    {
        return [
            'dashboard' => ['get', 'super-admin.dashboard', false],
            'associations index' => ['get', 'super-admin.associations.index', false],
            'associations create' => ['get', 'super-admin.associations.create', false],
            'associations store' => ['post', 'super-admin.associations.store', false],
            'associations edit' => ['get', 'super-admin.associations.edit', true],
            'associations update' => ['put', 'super-admin.associations.update', true],
            'associations destroy' => ['delete', 'super-admin.associations.destroy', true],
            'associations switch' => ['post', 'super-admin.associations.switch', true],
            'users index' => ['get', 'super-admin.users.index', false],
            'users create' => ['get', 'super-admin.users.create', false],
            'users store' => ['post', 'super-admin.users.store', false],
            'users edit' => ['get', 'super-admin.users.edit', true],
            'users update' => ['put', 'super-admin.users.update', true],
            'users toggle' => ['post', 'super-admin.users.toggle', true],
            'users reset link' => ['post', 'super-admin.users.reset-link', true],
            'users destroy' => ['delete', 'super-admin.users.destroy', true],
        ];
    }

    private function superAdminUrl(string $route, bool $needsModel, Association $association, User $user): string
    {
        if (! $needsModel) {
            return route($route);
        }

        return route($route, str_starts_with($route, 'super-admin.users.') ? $user : $association);
    }

    #[DataProvider('superAdminRoutes')]
    public function test_association_admins_and_patrons_cannot_reach_the_panel(string $method, string $route, bool $needsModel): void
    {
        $association = Association::factory()->create();
        $target = User::factory()->for($association)->create();

        foreach ([User::factory()->admin(), User::factory()] as $factory) {
            $this->actingAs($factory->for($association)->create());

            $this->{$method}($this->superAdminUrl($route, $needsModel, $association, $target))->assertForbidden();
        }

        $this->assertModelExists($association);
        $this->assertModelExists($target);
        $this->assertNull($target->fresh()->disabled_at);
    }

    #[DataProvider('superAdminRoutes')]
    public function test_guests_are_redirected_to_login(string $method, string $route, bool $needsModel): void
    {
        $association = Association::factory()->create();
        $target = User::factory()->for($association)->create();

        $this->{$method}($this->superAdminUrl($route, $needsModel, $association, $target))->assertRedirect(route('login'));
    }

    public function test_super_admin_gate_and_manage_gate(): void
    {
        $superAdmin = User::factory()->superAdmin()->make();
        $admin = User::factory()->admin()->make();
        $patron = User::factory()->make();

        $this->assertTrue($superAdmin->can('super-admin'));
        $this->assertTrue($superAdmin->can('manage'));
        $this->assertFalse($admin->can('super-admin'));
        $this->assertTrue($admin->can('manage'));
        $this->assertFalse($patron->can('super-admin'));
        $this->assertFalse($patron->can('manage'));
    }

    public function test_navigation_shows_the_panel_link_to_super_admins_only(): void
    {
        $this->signInAdmin();
        $this->get(route('more'))->assertOk()->assertDontSee(route('super-admin.dashboard'));

        $this->signInSuperAdmin();
        $this->get(route('more'))->assertOk()->assertSee(route('super-admin.dashboard'));
    }

    public function test_dashboard_shows_platform_kpis_associations_and_latest_logins(): void
    {
        $yoleNou = Association::factory()->create(['name' => 'Yole Nou', 'city' => 'Le François']);
        $superAdmin = $this->signInSuperAdmin($yoleNou);
        $other = Association::factory()->create(['name' => 'Club <b>Robert</b>']);
        Member::factory()->count(3)->for($yoleNou)->create();
        Member::factory()->for($other)->create();
        Boat::factory()->for($yoleNou)->create();
        Outing::factory()->for($yoleNou)->create(['date' => today()->subDays(3)]);
        Outing::factory()->for($yoleNou)->create(['date' => today()->subDays(60)]);
        User::factory()->for($other)->create(['name' => 'Joël Bellance', 'last_login_at' => now()->subHour()]);
        User::factory()->for($other)->disabled()->create();

        $this->get(route('super-admin.dashboard'))
            ->assertOk()
            ->assertViewHas('associationCount', 2)
            ->assertViewHas('activeUsers', 2)
            ->assertViewHas('disabledUsers', 1)
            ->assertViewHas('memberCount', 4)
            ->assertViewHas('recentOutings', 1)
            ->assertSee('Yole Nou')
            ->assertSee('Le François')
            ->assertSee('Club &lt;b&gt;Robert&lt;/b&gt;', false)
            ->assertDontSee('Club <b>Robert</b>', false)
            ->assertSee('Joël Bellance')
            ->assertDontSee($superAdmin->email);
    }

    public function test_super_admin_can_create_an_association_with_its_first_admin(): void
    {
        $this->signInSuperAdmin();

        $this->get(route('super-admin.associations.create'))->assertOk()->assertSee('Premier compte bureau');

        $this->post(route('super-admin.associations.store'), [
            'name' => 'Yole Nou Robert',
            'city' => 'Le Robert',
            'primary_color' => '#e11d48',
            'admin_name' => 'Bureau Robert',
            'admin_email' => 'bureau@robert.test',
            'admin_password' => 'motdepasse',
        ])->assertRedirect(route('super-admin.associations.index'))->assertSessionHas('status', 'Association « Yole Nou Robert » créée');

        $association = Association::where('name', 'Yole Nou Robert')->sole();
        $this->assertSame('yole-nou-robert', $association->slug);
        $this->assertSame('#E11D48', $association->primary_color);
        $this->assertSame('Le Robert', $association->city);

        $admin = User::where('email', 'bureau@robert.test')->sole();
        $this->assertSame($association->id, $admin->association_id);
        $this->assertSame(UserRole::Admin, $admin->role);
        $this->assertTrue(Hash::check('motdepasse', $admin->password));

        $this->get(route('super-admin.associations.index'))->assertOk()->assertSee('Yole Nou Robert')->assertSee(route('super-admin.associations.switch', $association));
    }

    public function test_association_slug_is_made_unique_when_generated_from_the_name(): void
    {
        $this->signInSuperAdmin();
        Association::factory()->create(['name' => 'Yole Nou', 'slug' => 'yole-nou']);

        $this->post(route('super-admin.associations.store'), ['name' => 'Yole Nou', 'primary_color' => '#0B2545'])
            ->assertRedirect(route('super-admin.associations.index'));

        $this->assertTrue(Association::where('slug', 'yole-nou-2')->exists());
        $this->assertDatabaseCount('users', 1);
    }

    public function test_association_creation_is_validated(): void
    {
        $this->signInSuperAdmin();
        Association::factory()->create(['slug' => 'pris']);
        $count = Association::count();

        $this->post(route('super-admin.associations.store'), [
            'name' => '',
            'slug' => 'pris',
            'primary_color' => 'rouge',
            'admin_email' => 'bureau@robert.test',
        ])->assertSessionHasErrors([
            'name' => 'Le champ nom est obligatoire.',
            'slug',
            'primary_color',
            'admin_name',
            'admin_password',
        ]);

        $this->assertSame($count, Association::count());
    }

    public function test_super_admin_can_update_an_association(): void
    {
        $this->signInSuperAdmin();
        $association = Association::factory()->create(['slug' => 'ancien']);

        $this->get(route('super-admin.associations.edit', $association))->assertOk()->assertSee($association->name);

        $this->put(route('super-admin.associations.update', $association), [
            'name' => 'Yole Nou',
            'slug' => 'yole-nou',
            'city' => 'Sainte-Anne',
            'primary_color' => '#16a34a',
        ])->assertRedirect(route('super-admin.associations.index'))->assertSessionHas('status', 'Association enregistrée');

        $association->refresh();
        $this->assertSame('Yole Nou', $association->name);
        $this->assertSame('yole-nou', $association->slug);
        $this->assertSame('Sainte-Anne', $association->city);
        $this->assertSame('#16A34A', $association->primary_color);
    }

    public function test_only_an_empty_association_can_be_deleted(): void
    {
        $this->signInSuperAdmin();
        $empty = Association::factory()->create();
        $withMembers = Association::factory()->create();
        Member::factory()->for($withMembers)->create();

        $this->from(route('super-admin.associations.edit', $withMembers))
            ->delete(route('super-admin.associations.destroy', $withMembers))
            ->assertRedirect(route('super-admin.associations.edit', $withMembers))
            ->assertSessionHasErrors('association');
        $this->assertModelExists($withMembers);

        $this->delete(route('super-admin.associations.destroy', $empty))
            ->assertRedirect(route('super-admin.associations.index'))
            ->assertSessionHas('status', 'Association supprimée');
        $this->assertModelMissing($empty);
    }

    public function test_super_admin_can_switch_into_another_association(): void
    {
        $superAdmin = $this->signInSuperAdmin();
        $target = Association::factory()->create(['name' => 'Yole Nou']);
        Member::factory()->for($target)->create(['first_name' => 'Rodrigue', 'last_name' => 'Sainte-Rose']);

        $this->post(route('super-admin.associations.switch', $target))
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('status', 'Vous êtes maintenant dans : Yole Nou');

        $this->assertSame($target->id, $superAdmin->fresh()->association_id);
        $this->get(route('members.index'))->assertOk()->assertSee('Rodrigue');
        $this->get(route('settings.edit'))->assertOk()->assertSee('Yole Nou');
    }

    public function test_user_index_filters_by_search_association_role_and_status(): void
    {
        $this->signInSuperAdmin();
        $yoleNou = Association::factory()->create();
        User::factory()->admin()->for($yoleNou)->create(['name' => 'Alice Bureau', 'email' => 'alice@test.fr']);
        User::factory()->for($yoleNou)->disabled()->create(['name' => 'Bruno Patron']);
        User::factory()->for(Association::factory())->create(['name' => 'Chloé Ailleurs']);

        $this->get(route('super-admin.users.index', ['q' => 'ALICE@']))
            ->assertOk()->assertSee('Alice Bureau')->assertDontSee('Bruno Patron')->assertDontSee('Chloé Ailleurs');

        $this->get(route('super-admin.users.index', ['association' => $yoleNou->id]))
            ->assertSee('Alice Bureau')->assertSee('Bruno Patron')->assertDontSee('Chloé Ailleurs');

        $this->get(route('super-admin.users.index', ['role' => 'admin']))
            ->assertSee('Alice Bureau')->assertDontSee('Bruno Patron')->assertDontSee('Chloé Ailleurs');

        $this->get(route('super-admin.users.index', ['status' => 'disabled']))
            ->assertSee('Bruno Patron')->assertDontSee('Alice Bureau')->assertDontSee('Chloé Ailleurs');

        $this->get(route('super-admin.users.index', ['status' => 'active', 'role' => 'patron']))
            ->assertSee('Chloé Ailleurs')->assertDontSee('Bruno Patron')->assertDontSee('Alice Bureau');
    }

    public function test_super_admin_can_create_a_user_with_any_role_and_association(): void
    {
        $this->signInSuperAdmin();
        $association = Association::factory()->create();

        $this->get(route('super-admin.users.create'))->assertOk();

        $this->post(route('super-admin.users.store'), [
            'name' => 'Nouvelle Opératrice',
            'email' => 'ops@yoleteam.test',
            'role' => UserRole::SuperAdmin->value,
            'association_id' => $association->id,
            'password' => 'motdepasse',
        ])->assertRedirect(route('super-admin.users.index'))->assertSessionHas('status', 'Compte de Nouvelle Opératrice créé');

        $user = User::where('email', 'ops@yoleteam.test')->sole();
        $this->assertSame(UserRole::SuperAdmin, $user->role);
        $this->assertSame($association->id, $user->association_id);
        $this->assertTrue(Hash::check('motdepasse', $user->password));
    }

    public function test_user_creation_is_validated(): void
    {
        $superAdmin = $this->signInSuperAdmin();

        $this->post(route('super-admin.users.store'), [
            'name' => '',
            'email' => $superAdmin->email,
            'role' => 'capitaine',
            'association_id' => 999,
            'password' => 'court',
        ])->assertSessionHasErrors([
            'name',
            'email' => 'La valeur du champ adresse e-mail est déjà utilisée.',
            'role',
            'association_id',
            'password',
        ]);

        $this->assertDatabaseCount('users', 1);
    }

    public function test_super_admin_can_update_a_user_and_keep_or_change_the_password(): void
    {
        $this->signInSuperAdmin();
        $user = User::factory()->for(Association::factory())->create();
        $target = Association::factory()->create();

        $this->get(route('super-admin.users.edit', $user))->assertOk()->assertSee($user->email);

        $payload = ['name' => 'Mike', 'email' => 'mike@test.fr', 'phone' => '0696 00 00 00', 'role' => 'admin', 'association_id' => $target->id, 'password' => ''];
        $this->put(route('super-admin.users.update', $user), $payload)
            ->assertRedirect(route('super-admin.users.index'))
            ->assertSessionHas('status', 'Compte enregistré');

        $user->refresh();
        $this->assertSame('mike@test.fr', $user->email);
        $this->assertSame('0696 00 00 00', $user->phone);
        $this->assertSame(UserRole::Admin, $user->role);
        $this->assertSame($target->id, $user->association_id);
        $this->assertTrue(Hash::check('password', $user->password));

        $this->put(route('super-admin.users.update', $user), [...$payload, 'password' => 'nouveau-mdp'])->assertSessionHasNoErrors();
        $this->assertTrue(Hash::check('nouveau-mdp', $user->fresh()->password));
    }

    public function test_super_admin_cannot_remove_their_own_super_admin_role(): void
    {
        $superAdmin = $this->signInSuperAdmin();

        $this->put(route('super-admin.users.update', $superAdmin), [
            'name' => $superAdmin->name,
            'email' => $superAdmin->email,
            'role' => 'admin',
            'association_id' => $superAdmin->association_id,
        ])->assertSessionHasErrors(['role' => 'Vous ne pouvez pas retirer votre propre rôle de super admin.']);

        $this->assertSame(UserRole::SuperAdmin, $superAdmin->fresh()->role);
    }

    public function test_super_admin_can_disable_and_enable_a_user(): void
    {
        $this->signInSuperAdmin();
        $user = User::factory()->for(Association::factory())->create(['name' => 'Joël']);

        $this->post(route('super-admin.users.toggle', $user))->assertSessionHas('status', 'Compte de Joël désactivé');
        $this->assertNotNull($user->fresh()->disabled_at);

        $this->post(route('super-admin.users.toggle', $user))->assertSessionHas('status', 'Compte de Joël réactivé');
        $this->assertNull($user->fresh()->disabled_at);
    }

    public function test_super_admin_cannot_disable_or_delete_their_own_account(): void
    {
        $superAdmin = $this->signInSuperAdmin();

        $this->post(route('super-admin.users.toggle', $superAdmin))->assertForbidden();
        $this->delete(route('super-admin.users.destroy', $superAdmin))->assertForbidden();

        $this->assertModelExists($superAdmin);
        $this->assertNull($superAdmin->fresh()->disabled_at);
    }

    public function test_super_admin_can_delete_a_user(): void
    {
        $this->signInSuperAdmin();
        $user = User::factory()->for(Association::factory())->create();

        $this->delete(route('super-admin.users.destroy', $user))
            ->assertRedirect(route('super-admin.users.index'))
            ->assertSessionHas('status', 'Compte supprimé');

        $this->assertModelMissing($user);
    }

    public function test_super_admin_can_send_a_password_reset_link(): void
    {
        Notification::fake();
        $this->signInSuperAdmin();
        $user = User::factory()->for(Association::factory())->create(['email' => 'patron@test.fr']);

        $this->post(route('super-admin.users.reset-link', $user))
            ->assertSessionHas('status', 'Lien de réinitialisation envoyé à patron@test.fr');

        Notification::assertSentTo($user, ResetPassword::class);
        Notification::assertSentTimes(ResetPassword::class, 1);
    }

    public function test_disabled_user_cannot_log_in(): void
    {
        $user = User::factory()->for(Association::factory())->disabled()->create(['email' => 'off@test.fr']);

        $this->post(route('login'), ['email' => 'off@test.fr', 'password' => 'password'])
            ->assertSessionHasErrors(['email' => 'Ce compte est désactivé.']);

        $this->assertGuest();
        $this->assertNull($user->fresh()->last_login_at);
    }

    public function test_wrong_password_on_a_disabled_account_does_not_reveal_it(): void
    {
        User::factory()->for(Association::factory())->disabled()->create(['email' => 'off@test.fr']);

        $this->post(route('login'), ['email' => 'off@test.fr', 'password' => 'mauvais'])
            ->assertSessionHasErrors(['email' => 'Ces identifiants ne correspondent à aucun compte.']);
    }

    public function test_successful_login_records_the_last_login_time(): void
    {
        $this->freezeSecond();
        $user = User::factory()->for(Association::factory())->create(['email' => 'on@test.fr']);

        $this->post(route('login'), ['email' => 'on@test.fr', 'password' => 'password'])->assertRedirect(route('dashboard'));

        $this->assertTrue(now()->equalTo($user->fresh()->last_login_at));
    }

    public function test_disabled_user_is_logged_out_on_the_next_request(): void
    {
        $user = $this->signInPatron();
        $user->forceFill(['disabled_at' => now()])->save();

        $this->get(route('dashboard'))
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors(['email' => 'Ce compte est désactivé.']);

        $this->assertGuest();
    }

    public function test_disabled_user_gets_a_json_401_on_json_requests(): void
    {
        $user = $this->signInPatron();
        $user->forceFill(['disabled_at' => now()])->save();

        $this->getJson(route('sync.token'))
            ->assertUnauthorized()
            ->assertJson(['message' => 'Ce compte est désactivé.']);

        $this->assertGuest();
    }

    public function test_guest_pages_still_work_with_the_active_account_middleware(): void
    {
        $this->get(route('login'))->assertOk();
    }

    public function test_association_admin_cannot_create_a_super_admin_from_settings(): void
    {
        $this->signInAdmin();

        $this->post(route('users.store'), [
            'name' => 'Pirate',
            'email' => 'pirate@test.fr',
            'role' => UserRole::SuperAdmin->value,
            'password' => 'motdepasse',
        ])->assertSessionHasErrorsIn('user', ['role']);

        $this->assertDatabaseMissing('users', ['email' => 'pirate@test.fr']);
    }

    public function test_association_admin_cannot_delete_a_super_admin(): void
    {
        $admin = $this->signInAdmin();
        $superAdmin = User::factory()->superAdmin()->for($admin->association)->create();

        $this->get(route('settings.edit'))->assertOk()->assertSee('Super admin')->assertDontSee(route('users.destroy', $superAdmin));
        $this->delete(route('users.destroy', $superAdmin))->assertForbidden();

        $this->assertModelExists($superAdmin);
    }

    public function test_promote_command_makes_an_existing_user_super_admin(): void
    {
        $user = User::factory()->admin()->for(Association::factory())->create(['email' => 'bureau@test.fr']);

        $this->artisan('yoleteam:super-admin', ['email' => 'bureau@test.fr'])->assertSuccessful();
        $this->assertSame(UserRole::SuperAdmin, $user->fresh()->role);

        $this->artisan('yoleteam:super-admin', ['email' => 'inconnu@test.fr'])->assertFailed();
    }

    public function test_installer_can_create_the_first_account_as_super_admin(): void
    {
        $this->artisan('yoleteam:installer', [
            '--association' => 'Yole Nou',
            '--ville' => 'Le Vauclin',
            '--nom' => 'Opérateur',
            '--email' => 'ops@exemple.fr',
            '--password' => 'mot-de-passe',
            '--super-admin' => true,
        ])->assertSuccessful();

        $this->assertSame(UserRole::SuperAdmin, User::sole()->role);
    }
}
