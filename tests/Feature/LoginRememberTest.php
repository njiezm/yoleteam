<?php

namespace Tests\Feature;

use App\Models\Association;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Password;
use Symfony\Component\HttpFoundation\Cookie;
use Tests\TestCase;

class LoginRememberTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_page_offers_the_password_eye_and_the_400_days_remember_option(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('data-password-field', false)
            ->assertSee('data-password-toggle', false)
            ->assertSee('aria-controls="password"', false)
            ->assertSee('Afficher le mot de passe')
            ->assertSee('Rester connecté (400 jours)');
    }

    public function test_reset_and_profile_password_fields_have_the_eye_toggle(): void
    {
        $user = User::factory()->for(Association::factory())->create();

        $reset = $this->get(route('password.reset', ['token' => Password::createToken($user), 'email' => $user->email]))->assertOk();
        $this->assertSame(2, substr_count($reset->getContent(), 'data-password-toggle'));

        $this->actingAs($user);
        $profile = $this->get(route('profile.edit'))->assertOk()->assertSee('aria-controls="current_password"', false);
        $this->assertSame(3, substr_count($profile->getContent(), 'data-password-toggle'));
    }

    public function test_remember_me_sets_a_400_days_cookie(): void
    {
        $this->freezeSecond();
        User::factory()->for(Association::factory())->create(['email' => 'patron@exemple.fr']);

        $response = $this->post(route('login'), ['email' => 'patron@exemple.fr', 'password' => 'password', 'remember' => '1'])
            ->assertRedirect(route('dashboard'));

        $recaller = Auth::guard('web')->getRecallerName();
        $cookie = collect($response->headers->getCookies())->first(fn (Cookie $cookie) => $cookie->getName() === $recaller);

        $this->assertNotNull($cookie);
        $this->assertSame(now()->addMinutes(576000)->getTimestamp(), $cookie->getExpiresTime());
    }

    public function test_login_without_remember_sets_no_remember_cookie(): void
    {
        User::factory()->for(Association::factory())->create(['email' => 'patron@exemple.fr']);

        $this->post(route('login'), ['email' => 'patron@exemple.fr', 'password' => 'password'])
            ->assertRedirect(route('dashboard'))
            ->assertCookieMissing(Auth::guard('web')->getRecallerName());
    }
}
