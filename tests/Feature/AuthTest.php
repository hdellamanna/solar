<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_register(): void
    {
        $this->post(route('register'), [
            'name' => 'Maria',
            'email' => 'maria@solar.app',
            'password' => 'senhaforte123',
            'password_confirmation' => 'senhaforte123',
        ])->assertRedirect(route('dashboard'));

        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', ['email' => 'maria@solar.app']);
    }

    public function test_user_can_login(): void
    {
        User::factory()->create([
            'email' => 'login@solar.app',
            'password' => bcrypt('secret123'),
        ]);

        $this->post(route('login'), [
            'email' => 'login@solar.app',
            'password' => 'secret123',
        ])->assertRedirect(route('dashboard'));

        $this->assertAuthenticated();
    }

    public function test_invalid_login_fails(): void
    {
        $this->post(route('login'), [
            'email' => 'nobody@solar.app',
            'password' => 'wrong',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_user_can_logout(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->post(route('logout'))->assertRedirect();
        $this->assertGuest();
    }

    public function test_dashboard_requires_auth(): void
    {
        $this->get(route('dashboard'))->assertRedirect(route('login'));
    }
}
