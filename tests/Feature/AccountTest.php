<?php

namespace Tests\Feature;

use App\Models\Association;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class AccountTest extends TestCase
{
    use RefreshDatabase;

    private function user(): User
    {
        return User::factory()->for(Association::factory())->create(['email' => 'patron@exemple.fr']);
    }

    public function test_login_and_logout(): void
    {
        $user = $this->user();

        $this->get(route('login'))->assertOk()->assertSee('Mot de passe oublié');
        $this->post(route('login'), ['email' => 'patron@exemple.fr', 'password' => 'mauvais'])->assertSessionHasErrors('email');
        $this->assertGuest();

        $this->post(route('login'), ['email' => 'patron@exemple.fr', 'password' => 'password'])->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);

        $this->post(route('logout'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_reset_link_is_sent_without_revealing_unknown_addresses(): void
    {
        Notification::fake();
        $user = $this->user();

        $this->get(route('password.request'))->assertOk();
        $this->post(route('password.email'), ['email' => 'patron@exemple.fr'])->assertSessionHas('status');
        $this->post(route('password.email'), ['email' => 'inconnu@exemple.fr'])->assertSessionHas('status')->assertSessionHasNoErrors();

        Notification::assertSentTo($user, ResetPassword::class);
        Notification::assertSentTimes(ResetPassword::class, 1);
    }

    public function test_password_can_be_reset_with_a_valid_token(): void
    {
        $user = $this->user();
        $token = Password::createToken($user);

        $this->get(route('password.reset', ['token' => $token, 'email' => $user->email]))->assertOk()->assertSee($user->email);

        $this->post(route('password.update'), ['token' => 'faux', 'email' => $user->email, 'password' => 'nouveau-mdp', 'password_confirmation' => 'nouveau-mdp'])
            ->assertSessionHasErrors('email');

        $this->post(route('password.update'), ['token' => $token, 'email' => $user->email, 'password' => 'nouveau-mdp', 'password_confirmation' => 'nouveau-mdp'])
            ->assertRedirect(route('login'));

        $this->assertTrue(Hash::check('nouveau-mdp', $user->fresh()->password));
    }

    public function test_profile_update(): void
    {
        $user = $this->signInPatron();
        $other = User::factory()->for($user->association)->create();

        $this->get(route('profile.edit'))->assertOk()->assertSee($user->email);

        $this->put(route('profile.update'), ['name' => 'Mike', 'email' => $other->email])->assertSessionHasErrors('email');
        $this->put(route('profile.update'), ['name' => 'Mike', 'email' => 'mike@exemple.fr', 'phone' => '0696 00 00 00'])->assertRedirect(route('profile.edit'));

        $this->assertSame('mike@exemple.fr', $user->fresh()->email);
    }

    public function test_password_change_requires_the_current_password(): void
    {
        $user = $this->signInPatron();

        $this->put(route('profile.password'), ['current_password' => 'faux', 'password' => 'nouveau-mdp', 'password_confirmation' => 'nouveau-mdp'])
            ->assertSessionHasErrorsIn('password', 'current_password');

        $this->put(route('profile.password'), ['current_password' => 'password', 'password' => 'nouveau-mdp', 'password_confirmation' => 'nouveau-mdp'])
            ->assertRedirect(route('profile.edit'));

        $this->assertTrue(Hash::check('nouveau-mdp', $user->fresh()->password));
    }
}
