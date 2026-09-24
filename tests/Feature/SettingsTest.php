<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Association;
use App\Models\User;
use Database\Seeders\CrewRoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_settings_page_lists_association_users_and_crew_roles(): void
    {
        $this->seed(CrewRoleSeeder::class);
        $association = Association::factory()->create(['name' => 'Association Yole Nou', 'city' => 'Le François']);
        $admin = $this->signInAdmin($association);
        User::factory()->for($association)->create(['name' => 'Joël Bellance', 'email' => 'patron@yoleteam.test']);
        User::factory()->for(Association::factory())->create(['name' => 'Autre Club']);

        $this->get(route('settings.edit'))
            ->assertOk()
            ->assertSee('Association Yole Nou')
            ->assertSee('Le François')
            ->assertSee('Joël Bellance')
            ->assertSee('patron@yoleteam.test')
            ->assertSee('Admin / bureau')
            ->assertSee('Patron')
            ->assertSee('ÉCO')
            ->assertSee('Gréement')
            ->assertSee('Ajouter un utilisateur')
            ->assertDontSee('Autre Club')
            ->assertDontSee(route('users.destroy', $admin));
    }

    public function test_admin_can_update_the_association(): void
    {
        $admin = $this->signInAdmin();

        $this->put(route('settings.update'), ['name' => 'Yole Nou', 'city' => 'Le Robert', 'primary_color' => '#e11d48'])
            ->assertRedirect(route('settings.edit'))
            ->assertSessionHas('status', 'Paramètres enregistrés');

        $association = $admin->association->fresh();
        $this->assertSame('Yole Nou', $association->name);
        $this->assertSame('Le Robert', $association->city);
        $this->assertSame('#E11D48', $association->primary_color);
    }

    public function test_association_update_is_validated(): void
    {
        $admin = $this->signInAdmin();
        $name = $admin->association->name;

        $this->put(route('settings.update'), ['name' => '', 'primary_color' => 'red'])
            ->assertSessionHasErrors(['name' => 'Le champ nom est obligatoire.', 'primary_color']);

        $this->assertSame($name, $admin->association->fresh()->name);
    }

    public function test_admin_can_add_a_user_to_the_association(): void
    {
        $admin = $this->signInAdmin();

        $this->post(route('users.store'), [
            'name' => 'Rodrigue Sainte-Rose',
            'email' => 'rodrigue@yoleteam.test',
            'role' => UserRole::Patron->value,
            'password' => 'motdepasse',
        ])->assertRedirect(route('settings.edit'))->assertSessionHas('status', 'Utilisateur ajouté');

        $user = User::where('email', 'rodrigue@yoleteam.test')->sole();
        $this->assertSame($admin->association_id, $user->association_id);
        $this->assertSame(UserRole::Patron, $user->role);
        $this->assertTrue(Hash::check('motdepasse', $user->password));
    }

    public function test_user_creation_is_validated(): void
    {
        $admin = $this->signInAdmin();

        $this->post(route('users.store'), ['name' => '', 'email' => $admin->email, 'role' => 'capitaine', 'password' => 'court'])
            ->assertSessionHasErrorsIn('user', [
                'name',
                'email' => 'La valeur du champ adresse e-mail est déjà utilisée.',
                'role',
                'password',
            ]);

        $this->assertDatabaseCount('users', 1);
    }

    public function test_admin_can_delete_another_user_of_the_association(): void
    {
        $admin = $this->signInAdmin();
        $patron = User::factory()->for($admin->association)->create();

        $this->delete(route('users.destroy', $patron))
            ->assertRedirect(route('settings.edit'))
            ->assertSessionHas('status', 'Utilisateur supprimé');

        $this->assertModelMissing($patron);
    }

    public function test_admin_cannot_delete_their_own_account(): void
    {
        $admin = $this->signInAdmin();

        $this->delete(route('users.destroy', $admin))->assertForbidden();

        $this->assertModelExists($admin);
    }

    public function test_admin_cannot_delete_a_user_of_another_association(): void
    {
        $this->signInAdmin();
        $foreign = User::factory()->admin()->for(Association::factory())->create();

        $this->delete(route('users.destroy', $foreign))->assertNotFound();

        $this->assertModelExists($foreign);
    }

    public function test_patron_cannot_access_settings_or_manage_users(): void
    {
        $patron = $this->signInPatron();
        $other = User::factory()->for($patron->association)->create();

        $this->get(route('settings.edit'))->assertForbidden();
        $this->put(route('settings.update'), ['name' => 'X', 'primary_color' => '#0B2545'])->assertForbidden();
        $this->post(route('users.store'), [])->assertForbidden();
        $this->delete(route('users.destroy', $other))->assertForbidden();

        $this->assertModelExists($other);
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get(route('settings.edit'))->assertRedirect(route('login'));
    }
}
