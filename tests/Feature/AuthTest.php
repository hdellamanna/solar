<?php

namespace Tests\Feature;

use App\Mail\VerifyEmailMail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_register_and_is_sent_verification_email(): void
    {
        Mail::fake();

        $this->post(route('register'), [
            'name' => 'Maria',
            'email' => 'maria@solar.app',
            'password' => 'senhaforte123',
            'password_confirmation' => 'senhaforte123',
        ])
            // Registration no longer logs the user in to the dashboard
            // — it bounces them to the verification notice (FASE 4D /
            // Auth Phase 1).
            ->assertRedirect(route('verification.notice'));

        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', ['email' => 'maria@solar.app']);
    }

    public function test_user_can_login(): void
    {
        $user = User::factory()->create([
            'email' => 'login@solar.app',
            'password' => bcrypt('secret123'),
            'email_verified_at' => now(),
        ]);

        $this->post(route('login'), [
            'email' => 'login@solar.app',
            'password' => 'secret123',
        ])->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);
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

    public function test_user_can_register_then_verify_email_and_reach_dashboard(): void
    {
        Mail::fake();

        $this->post(route('register'), [
            'name' => 'Joao',
            'email' => 'joao@solar.app',
            'password' => 'senhaforte123',
            'password_confirmation' => 'senhaforte123',
        ])->assertRedirect(route('verification.notice'));

        $user = User::where('email', 'joao@solar.app')->firstOrFail();
        $this->assertNotNull($user);
        $this->assertNull($user->email_verified_at);

        // Pull the verification URL from the queued mail. We reach
        // into the captured message to keep this test self-contained.
        Mail::assertSent(VerifyEmailMail::class, function ($mail) use (&$url, $user) {
            $this->assertTrue($mail->user->is($user));
            $url = $mail->verificationUrl;

            return true;
        });

        $this->assertNotEmpty($url);

        // Hitting the verification link logs the user in (if not
        // already) and redirects to the dashboard.
        $this->get($url)
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('success');

        $user->refresh();
        $this->assertNotNull($user->email_verified_at);
    }

    public function test_dashboard_blocks_unverified_user(): void
    {
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertRedirect(route('verification.notice'))
            ->assertSessionHas('error');
    }
}
