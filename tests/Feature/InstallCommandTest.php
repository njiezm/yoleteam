<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Association;
use App\Models\CrewRole;
use App\Models\Member;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InstallCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_crew_roles_the_association_and_an_admin_without_demo_data(): void
    {
        $this->artisan('yoleteam:installer', [
            '--association' => 'Association des Yoles Rondes de la Baie des Mulets',
            '--ville' => 'Le Vauclin',
            '--nom' => 'Bureau',
            '--email' => 'bureau@exemple.fr',
            '--password' => 'mot-de-passe',
        ])->assertSuccessful();

        $this->assertSame(7, CrewRole::count());
        $admin = User::sole();
        $this->assertSame(UserRole::Admin, $admin->role);
        $this->assertSame('Le Vauclin', $admin->association->city);
        $this->assertSame(0, Member::count());

        $this->post(route('login'), ['email' => 'bureau@exemple.fr', 'password' => 'mot-de-passe'])->assertRedirect(route('dashboard'));
    }

    public function test_it_refuses_a_short_password_or_a_taken_email(): void
    {
        User::factory()->for(Association::factory())->create(['email' => 'pris@exemple.fr']);

        $this->artisan('yoleteam:installer', ['--association' => 'A', '--ville' => 'B', '--nom' => 'C', '--email' => 'pris@exemple.fr', '--password' => 'court'])
            ->assertFailed();

        $this->assertSame(1, User::count());
    }
}
