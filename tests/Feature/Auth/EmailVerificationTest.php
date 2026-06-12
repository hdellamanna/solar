<?php

namespace Tests\Feature\Auth;

use App\Mail\VerifyEmailMail;
use App\Models\EmailVerificationToken;
use App\Models\User;
use App\Services\Auth\EmailVerificationService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * Coverage for FASE 4D / Auth Phase 1 — email verification.
 *
 * These 12 tests exercise every behaviour promised in
 * `docs/auth/phase-1/design.md`:
 *
 *  1.  unverified user cannot reach the dashboard
 *  2.  registration sends the verification email and does not log the
 *      user in to the dashboard
 *  3.  clicking the verification link stamps `email_verified_at` and
 *      redirects to the dashboard
 *  4.  an expired token is rejected
 *  5.  a consumed token cannot be reused
 *  6.  an unknown / malformed token bounces the user to the notice
 *  7.  the re-send endpoint enforces a 30 s cooldown
 *  8.  the re-send endpoint caps at 5 sends per hour
 *  9.  the `verified` middleware blocks every protected route until
 *      the email is confirmed
 * 10.  the demo user is pre-verified by the seeder
 * 11.  a verified user can access the dashboard
 * 12.  the email carries a signed URL that embeds the token
 */
class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // The cache backend (`array`) survives between tests within
        // the same process. Flush it so throttle counters start at
        // zero for every assertion.
        Cache::flush();
    }

    // ------------------------------------------------------------------
    // 1. test_user_cannot_login_without_verified_email
    // ------------------------------------------------------------------
    public function test_user_cannot_login_without_verified_email(): void
    {
        Mail::fake();

        $user = User::factory()->unverified()->create([
            'email' => 'novo@solar.app',
            'password' => bcrypt('secret123'),
        ]);

        $this->post(route('login'), [
            'email' => 'novo@solar.app',
            'password' => 'secret123',
        ])
            ->assertRedirect(route('verification.notice'))
            ->assertSessionHas('error');

        $this->assertAuthenticatedAs($user);

        // The login flow should have queued a fresh verification email.
        Mail::assertSent(VerifyEmailMail::class);
    }

    // ------------------------------------------------------------------
    // 2. test_register_sends_verification_email_and_does_not_login
    // ------------------------------------------------------------------
    public function test_register_sends_verification_email_and_does_not_login(): void
    {
        Mail::fake();

        $this->post(route('register'), [
            'name' => 'Carla',
            'email' => 'carla@solar.app',
            'password' => 'senhaforte123',
            'password_confirmation' => 'senhaforte123',
        ])
            ->assertRedirect(route('verification.notice'));

        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', ['email' => 'carla@solar.app']);

        $user = User::where('email', 'carla@solar.app')->firstOrFail();
        $this->assertNull($user->email_verified_at);

        // Token was persisted.
        $this->assertDatabaseCount('email_verification_tokens', 1);
        $this->assertDatabaseHas('email_verification_tokens', [
            'user_id' => $user->id,
            'consumed_at' => null,
        ]);

        Mail::assertSent(VerifyEmailMail::class, function (VerifyEmailMail $mail) use ($user) {
            return $mail->user->is($user)
                && str_contains($mail->verificationUrl, '/email/verify/');
        });
    }

    // ------------------------------------------------------------------
    // 3. test_verification_link_marks_email_verified_and_redirects_to_dashboard
    // ------------------------------------------------------------------
    public function test_verification_link_marks_email_verified_and_redirects_to_dashboard(): void
    {
        Mail::fake();

        $this->post(route('register'), [
            'name' => 'Diego',
            'email' => 'diego@solar.app',
            'password' => 'senhaforte123',
            'password_confirmation' => 'senhaforte123',
        ])->assertRedirect(route('verification.notice'));

        $user = User::where('email', 'diego@solar.app')->firstOrFail();
        $this->assertNull($user->email_verified_at);

        $url = null;
        Mail::assertSent(VerifyEmailMail::class, function (VerifyEmailMail $mail) use (&$url, $user) {
            $this->assertTrue($mail->user->is($user));
            $url = $mail->verificationUrl;

            return true;
        });

        $this->assertNotEmpty($url);

        $this->get($url)
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('success');

        $user->refresh();
        $this->assertNotNull($user->email_verified_at);

        // The token row is now stamped with `consumed_at`.
        $token = EmailVerificationToken::where('user_id', $user->id)->firstOrFail();
        $this->assertNotNull($token->consumed_at);
    }

    // ------------------------------------------------------------------
    // 4. test_verification_link_expires_after_60_minutes
    // ------------------------------------------------------------------
    public function test_verification_link_expires_after_60_minutes(): void
    {
        Mail::fake();

        $user = User::factory()->unverified()->create();
        // Issue a token that expired 1 minute ago — the verification
        // route uses a 60-minute TTL, so anything beyond is invalid.
        $raw = 'expired-raw-token';
        EmailVerificationToken::create([
            'user_id' => $user->id,
            'token_hash' => EmailVerificationToken::hashToken($raw),
            'expires_at' => now()->subMinute(),
            'consumed_at' => null,
        ]);

        // Build a properly-signed URL so the `signed` middleware
        // accepts the request and our service is the one that rejects.
        $url = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['token' => $raw],
        );

        $this->actingAs($user)
            ->get($url)
            ->assertRedirect(route('verification.notice'))
            ->assertSessionHas('error');

        $user->refresh();
        $this->assertNull($user->email_verified_at);
    }

    // ------------------------------------------------------------------
    // 5. test_verification_link_cannot_be_reused
    // ------------------------------------------------------------------
    public function test_verification_link_cannot_be_reused(): void
    {
        Mail::fake();

        $user = User::factory()->unverified()->create();

        // Build a valid, fresh token and consume it via the service so
        // the row is in the "already used" state.
        ['token' => $raw] = EmailVerificationToken::generateForUser($user);
        app(EmailVerificationService::class)->verify($raw);

        $user->refresh();
        $this->assertNotNull($user->email_verified_at);

        $url = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['token' => $raw],
        );

        // Hit the link a second time — must NOT succeed.
        $this->actingAs($user)
            ->get($url)
            ->assertRedirect(route('verification.notice'))
            ->assertSessionHas('error');
    }

    // ------------------------------------------------------------------
    // 6. test_invalid_token_redirects_to_notice_with_error
    // ------------------------------------------------------------------
    public function test_invalid_token_redirects_to_notice_with_error(): void
    {
        $user = User::factory()->unverified()->create();

        $url = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['token' => 'never-issued-token'],
        );

        $this->actingAs($user)
            ->get($url)
            ->assertRedirect(route('verification.notice'))
            ->assertSessionHas('error');

        $user->refresh();
        $this->assertNull($user->email_verified_at);
    }

    // ------------------------------------------------------------------
    // 7. test_resend_button_throttles_to_one_per_30s
    // ------------------------------------------------------------------
    public function test_resend_button_throttles_to_one_per_30s(): void
    {
        Mail::fake();

        $user = User::factory()->unverified()->create();
        $from = route('verification.notice');

        // First send goes through.
        $this->actingAs($user)
            ->post(route('verification.resend'))
            ->assertSessionHas('success');
        Mail::assertSent(VerifyEmailMail::class, 1);

        // Immediate second send must be blocked.
        $this->actingAs($user)
            ->post(route('verification.resend'))
            ->assertSessionHas('error');
        Mail::assertSent(VerifyEmailMail::class, 1);

        // Bypass the cooldown by rewinding the "last sent" marker and
        // try again. This proves the throttle — not some other bug —
        // was the cause of the rejection.
        Cache::put("email_verification:last_sent:{$user->id}", now()->subSeconds(60), now()->addHour());

        $this->actingAs($user)
            ->post(route('verification.resend'))
            ->assertSessionHas('success');
        Mail::assertSent(VerifyEmailMail::class, 2);
    }

    // ------------------------------------------------------------------
    // 8. test_resend_button_caps_at_5_per_hour
    // ------------------------------------------------------------------
    public function test_resend_button_caps_at_5_per_hour(): void
    {
        Mail::fake();

        $user = User::factory()->unverified()->create();

        // Pre-seed the hourly counter to the cap. (Cache::add only
        // writes if the key is missing, so we use put to be explicit
        // about the value.)
        Cache::put("email_verification:hourly_count:{$user->id}", 5, now()->addHour());
        // Make sure we are not still inside the 30 s cooldown.
        Cache::put("email_verification:last_sent:{$user->id}", now()->subHour(), now()->addHour());

        $this->actingAs($user)
            ->post(route('verification.resend'))
            ->assertSessionHas('error');

        // No new mail was sent.
        Mail::assertNothingSent();

        // Reset the counter to 4 (one short of the cap). The next
        // resend should go through and bump it to 5.
        Cache::put("email_verification:hourly_count:{$user->id}", 4, now()->addHour());

        $this->actingAs($user)
            ->post(route('verification.resend'))
            ->assertSessionHas('success');
        Mail::assertSent(VerifyEmailMail::class, 1);
    }

    // ------------------------------------------------------------------
    // 9. test_middleware_blocks_auth_routes_until_verified
    // ------------------------------------------------------------------
    public function test_middleware_blocks_auth_routes_until_verified(): void
    {
        $user = User::factory()->unverified()->create();

        // A non-trivial sample of the protected routes.
        $protectedRoutes = [
            ['GET',  route('dashboard')],
            ['GET',  route('accounts.index')],
            ['GET',  route('transactions.index')],
            ['GET',  route('goals.index')],
            ['GET',  route('budgets.index')],
            ['GET',  route('subscriptions.index')],
            ['GET',  route('investments.index')],
            ['GET',  route('debts.index')],
            ['GET',  route('pix.index')],
            ['GET',  route('reports.index')],
            ['GET',  route('tags.index')],
            ['GET',  route('profile.edit')],
        ];

        foreach ($protectedRoutes as [$method, $url]) {
            $response = $this->actingAs($user)->{$method}($url);
            $response->assertRedirect(route('verification.notice'));
            $this->assertTrue(
                str_contains($response->headers->get('Location') ?? '', route('verification.notice')),
                "Expected {$url} to redirect to the verification notice, got "
                    .($response->headers->get('Location') ?? '(no redirect)'),
            );
        }
    }

    // ------------------------------------------------------------------
    // 10. test_demo_user_is_pre_verified_in_seeder
    // ------------------------------------------------------------------
    public function test_demo_user_is_pre_verified_in_seeder(): void
    {
        $this->seed(DatabaseSeeder::class);

        $demo = User::where('email', 'demo@solar.app')->firstOrFail();
        $this->assertNotNull($demo->email_verified_at, 'Demo user should be pre-verified.');

        $this->post(route('login'), [
            'email' => 'demo@solar.app',
            'password' => 'solar123',
        ])->assertRedirect(route('dashboard'));
    }

    // ------------------------------------------------------------------
    // 11. test_verified_user_can_access_dashboard
    // ------------------------------------------------------------------
    public function test_verified_user_can_access_dashboard(): void
    {
        $user = User::factory()->create(); // factory defaults to email_verified_at=now()

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk();
    }

    // ------------------------------------------------------------------
    // 12. test_email_contains_signed_url_with_token
    // ------------------------------------------------------------------
    public function test_email_contains_signed_url_with_token(): void
    {
        Mail::fake();

        $user = User::factory()->unverified()->create();

        $this->actingAs($user)->post(route('verification.resend'));

        $url = null;
        Mail::assertSent(VerifyEmailMail::class, function (VerifyEmailMail $mail) use (&$url) {
            $url = $mail->verificationUrl;

            return true;
        });

        $this->assertNotEmpty($url, 'Verification URL was not embedded in the email.');

        // URL must be a `temporarySignedRoute` to the verify endpoint.
        // The token lives in the path segment, the signature + expires
        // are appended as a query string by Laravel.
        $this->assertStringContainsString('/email/verify/', $url);
        $this->assertStringContainsString('signature=', $url);
        $this->assertStringContainsString('expires=', $url);

        // Extract the token that was actually issued.
        $token = (string) (preg_match('#/email/verify/([^?]+)#', $url, $m) ? $m[1] : '');
        $this->assertNotEmpty($token, 'Could not extract token from verification URL.');

        // The token must NOT appear in plaintext anywhere in the
        // database — only its SHA-256 hash should be stored.
        foreach (EmailVerificationToken::all() as $row) {
            $this->assertNotEquals($token, $row->token_hash);
        }

        $this->assertDatabaseHas('email_verification_tokens', [
            'token_hash' => EmailVerificationToken::hashToken($token),
            'user_id' => $user->id,
            'consumed_at' => null,
        ]);

        // The URL itself, when stripped of its signature/expires
        // query, must continue to be a valid verify route — the
        // browser hit won't have the signature the first time, so
        // this is mostly a sanity check on the structure.
        $base = explode('?', $url)[0];
        $this->assertStringContainsString('/email/verify/'.$token, $base);
    }
}
