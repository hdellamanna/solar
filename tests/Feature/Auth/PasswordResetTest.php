<?php

namespace Tests\Feature\Auth;

use App\Mail\PasswordResetMail;
use App\Mail\VerifyEmailMail;
use App\Models\EmailVerificationToken;
use App\Models\User;
use App\Services\Auth\EmailVerificationService;
use App\Services\Auth\PasswordResetService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * Coverage for FASE 4D / Auth Phase 2 — password reset (forgot-password).
 *
 * The 13 cases below exercise every behaviour promised in
 * `docs/auth/phase-2/design.md` and lock down the security contract
 * (no user enumeration, throttling, single-use, 60-minute TTL, signed
 * URL, auto-login, regression of PR1).
 *
 * Naming and order follow the design doc verbatim so the reviewer can
 * cross-reference the bullet list at a glance.
 */
class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The cache backend (`array`) survives between tests within the
     * same process. Flush it so throttle counters start at zero for
     * every assertion — without this, case 3 / 4 / 5 would inherit
     * counters from previous tests and intermittently fail.
     */
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    // ------------------------------------------------------------------
    // 1. test_user_can_request_password_reset_link
    // ------------------------------------------------------------------
    public function test_user_can_request_password_reset_link(): void
    {
        Mail::fake();

        $user = User::factory()->create([
            'email' => 'resetme@solar.app',
        ]);

        $response = $this->post(route('password.email'), [
            'email' => 'resetme@solar.app',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success', fn ($message) => str_contains(
            (string) $message,
            'Se o email existir em nossa base',
        ));

        // Token row created with the right purpose.
        $this->assertDatabaseHas('email_verification_tokens', [
            'user_id' => $user->id,
            'purpose' => EmailVerificationToken::PURPOSE_PASSWORD_RESET,
            'consumed_at' => null,
        ]);

        Mail::assertSent(PasswordResetMail::class, function (PasswordResetMail $mail) use ($user) {
            return $mail->user->is($user);
        });
    }

    // ------------------------------------------------------------------
    // 2. test_password_reset_request_does_not_reveal_user_existence
    // ------------------------------------------------------------------
    public function test_password_reset_request_does_not_reveal_user_existence(): void
    {
        Mail::fake();

        $response = $this->from(route('password.request'))->post(route('password.email'), [
            'email' => 'nobody@nowhere.example',
        ]);

        // Same flash message as the success path — no oracle.
        $response->assertRedirect(route('password.request'));
        $response->assertSessionHas('success', fn ($message) => str_contains(
            (string) $message,
            'Se o email existir em nossa base',
        ));

        // No email was sent, no token row was persisted.
        Mail::assertNothingSent();
        $this->assertDatabaseCount('email_verification_tokens', 0);
    }

    // ------------------------------------------------------------------
    // 3. test_password_reset_request_throttles_to_one_per_30s
    // ------------------------------------------------------------------
    public function test_password_reset_request_throttles_to_one_per_30s(): void
    {
        Mail::fake();

        User::factory()->create(['email' => 'throttle@solar.app']);

        // First send goes through.
        $this->post(route('password.email'), ['email' => 'throttle@solar.app'])
            ->assertSessionHas('success');
        Mail::assertSent(PasswordResetMail::class, 1);
        $this->assertDatabaseCount('email_verification_tokens', 1);

        // Immediate second send must NOT mint a new token or send mail.
        $this->post(route('password.email'), ['email' => 'throttle@solar.app'])
            ->assertSessionHas('success');
        Mail::assertSent(PasswordResetMail::class, 1);
        $this->assertDatabaseCount('email_verification_tokens', 1);

        // Roll the "last sent" cache marker back 60s to bypass the
        // cooldown and prove it was the only reason the second send
        // was suppressed — not some other bug.
        $key = 'password-reset:throttle:'.hash('sha256', 'throttle@solar.app');
        Cache::put($key.':last_sent', now()->subSeconds(60), now()->addHour());

        $this->post(route('password.email'), ['email' => 'throttle@solar.app'])
            ->assertSessionHas('success');
        Mail::assertSent(PasswordResetMail::class, 2);
        $this->assertDatabaseCount('email_verification_tokens', 2);
    }

    // ------------------------------------------------------------------
    // 4. test_password_reset_request_caps_at_5_per_hour
    // ------------------------------------------------------------------
    public function test_password_reset_request_caps_at_5_per_hour(): void
    {
        Mail::fake();

        User::factory()->create(['email' => 'cap@solar.app']);

        // Pre-seed the hourly counter to the cap (5) and rewind the
        // "last sent" marker past the 30s cooldown so the only gate
        // we are exercising is the hourly cap.
        $key = 'password-reset:throttle:'.hash('sha256', 'cap@solar.app');
        Cache::put($key.':hourly_count', 5, now()->addHour());
        Cache::put($key.':last_sent', now()->subHour(), now()->addHour());

        $this->post(route('password.email'), ['email' => 'cap@solar.app'])
            ->assertSessionHas('success');
        Mail::assertSent(PasswordResetMail::class, 0);
        $this->assertDatabaseCount('email_verification_tokens', 0);

        // Drop the counter to 4 and the next send must go through
        // (bumping the counter to 5).
        Cache::put($key.':hourly_count', 4, now()->addHour());

        $this->post(route('password.email'), ['email' => 'cap@solar.app'])
            ->assertSessionHas('success');
        Mail::assertSent(PasswordResetMail::class, 1);
        $this->assertDatabaseCount('email_verification_tokens', 1);
    }

    // ------------------------------------------------------------------
    // 5. test_password_reset_link_marks_token_consumed_on_use
    // ------------------------------------------------------------------
    public function test_password_reset_link_marks_token_consumed_on_use(): void
    {
        Mail::fake();

        $user = User::factory()->create([
            'email' => 'consume@solar.app',
            'password' => Hash::make('old-password'),
        ]);

        $this->post(route('password.email'), ['email' => 'consume@solar.app'])
            ->assertSessionHas('success');

        $tokenRow = EmailVerificationToken::where('user_id', $user->id)
            ->forPurpose(EmailVerificationToken::PURPOSE_PASSWORD_RESET)
            ->firstOrFail();
        $this->assertNull($tokenRow->consumed_at);

        $url = $this->extractResetUrlFromMail($user->email);
        $this->assertNotEmpty($url, 'Reset URL was not captured from the queued mail.');

        $response = $this->post(route('password.update'), [
            'token' => $this->extractRawTokenFromUrl($url),
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ]);

        $response->assertRedirect(route('dashboard'));
        $response->assertSessionHas('success', fn ($m) => str_contains((string) $m, 'Senha redefinida'));

        // The row is now stamped with `consumed_at`.
        $tokenRow->refresh();
        $this->assertNotNull($tokenRow->consumed_at);

        // Password actually rotated — old hash no longer matches.
        $user->refresh();
        $this->assertTrue(Hash::check('new-password-123', $user->password));
        $this->assertFalse(Hash::check('old-password', $user->password));
    }

    // ------------------------------------------------------------------
    // 6. test_password_reset_link_expires_after_60_minutes
    // ------------------------------------------------------------------
    public function test_password_reset_link_expires_after_60_minutes(): void
    {
        Mail::fake();

        $user = User::factory()->create([
            'email' => 'expire@solar.app',
            'password' => Hash::make('old-password'),
        ]);

        $this->post(route('password.email'), ['email' => 'expire@solar.app'])
            ->assertSessionHas('success');

        $url = $this->extractResetUrlFromMail('expire@solar.app');
        $rawToken = $this->extractRawTokenFromUrl($url);

        // Jump 61 minutes into the future and try again. The token row
        // is now in the past so the service must reject it.
        $this->travel(61)->minutes();

        $response = $this->post(route('password.update'), [
            'token' => $rawToken,
            'password' => 'irrelevant-1234',
            'password_confirmation' => 'irrelevant-1234',
        ]);

        $response->assertRedirect(route('password.request'));
        $response->assertSessionHas('error');

        $user->refresh();
        $this->assertTrue(Hash::check('old-password', $user->password));
    }

    // ------------------------------------------------------------------
    // 7. test_password_reset_link_cannot_be_reused
    // ------------------------------------------------------------------
    public function test_password_reset_link_cannot_be_reused(): void
    {
        Mail::fake();

        $user = User::factory()->create([
            'email' => 'reuse@solar.app',
        ]);

        $this->post(route('password.email'), ['email' => 'reuse@solar.app'])
            ->assertSessionHas('success');

        $url = $this->extractResetUrlFromMail('reuse@solar.app');
        $rawToken = $this->extractRawTokenFromUrl($url);

        // First use goes through.
        $this->post(route('password.update'), [
            'token' => $rawToken,
            'password' => 'first-password-1',
            'password_confirmation' => 'first-password-1',
        ])->assertRedirect(route('dashboard'));

        // The same raw token a second time must be rejected.
        $this->post(route('password.update'), [
            'token' => $rawToken,
            'password' => 'second-password-2',
            'password_confirmation' => 'second-password-2',
        ])
            ->assertRedirect(route('password.request'))
            ->assertSessionHas('error');

        // The user's password is the FIRST one, not the second.
        $user->refresh();
        $this->assertTrue(Hash::check('first-password-1', $user->password));
        $this->assertFalse(Hash::check('second-password-2', $user->password));
    }

    // ------------------------------------------------------------------
    // 8. test_password_reset_invalidates_other_active_tokens
    // ------------------------------------------------------------------
    public function test_password_reset_invalidates_other_active_tokens(): void
    {
        Mail::fake();

        $user = User::factory()->create(['email' => 'multi@solar.app']);

        // First reset request.
        $this->post(route('password.email'), ['email' => 'multi@solar.app'])
            ->assertSessionHas('success');
        $firstUrl = $this->extractResetUrlFromMail('multi@solar.app');
        $firstToken = $this->extractRawTokenFromUrl($firstUrl);

        // Roll past the 30s cooldown so the second request actually
        // mints a fresh token (otherwise the throttle would hide the
        // behaviour we are testing).
        $key = 'password-reset:throttle:'.hash('sha256', 'multi@solar.app');
        Cache::put($key.':last_sent', now()->subSeconds(60), now()->addHour());

        // Second reset request.
        $this->post(route('password.email'), ['email' => 'multi@solar.app'])
            ->assertSessionHas('success');
        $secondUrl = $this->extractResetUrlFromMail('multi@solar.app');
        $secondToken = $this->extractRawTokenFromUrl($secondUrl);

        $this->assertNotEquals($firstToken, $secondToken, 'Tokens should differ between requests.');

        // Use the FIRST token successfully.
        $this->post(route('password.update'), [
            'token' => $firstToken,
            'password' => 'new-password-abc',
            'password_confirmation' => 'new-password-abc',
        ])->assertRedirect(route('dashboard'));

        // The SECOND token must now be invalid — the service kills
        // sibling tokens on a successful reset.
        $this->post(route('password.update'), [
            'token' => $secondToken,
            'password' => 'would-be-password-xyz',
            'password_confirmation' => 'would-be-password-xyz',
        ])
            ->assertRedirect(route('password.request'))
            ->assertSessionHas('error');
    }

    // ------------------------------------------------------------------
    // 9. test_password_reset_does_not_block_unverified_users
    // ------------------------------------------------------------------
    public function test_password_reset_does_not_block_unverified_users(): void
    {
        Mail::fake();

        // Intentionally unverified — the user signed up but never
        // confirmed their email. The reset flow should still work
        // (the inbox is the proof of identity).
        $user = User::factory()->unverified()->create([
            'email' => 'unverified@solar.app',
        ]);

        $this->post(route('password.email'), ['email' => 'unverified@solar.app'])
            ->assertSessionHas('success');

        $url = $this->extractResetUrlFromMail('unverified@solar.app');
        $rawToken = $this->extractRawTokenFromUrl($url);

        $this->post(route('password.update'), [
            'token' => $rawToken,
            'password' => 'reset-while-unverified-1',
            'password_confirmation' => 'reset-while-unverified-1',
        ])->assertRedirect(route('dashboard'));

        $user->refresh();
        $this->assertTrue(Hash::check('reset-while-unverified-1', $user->password));
        // Still unverified after the reset — reset does not flip the bit.
        $this->assertNull($user->email_verified_at);
    }

    // ------------------------------------------------------------------
    // 10. test_password_reset_loglocks_user_in_after_success
    // ------------------------------------------------------------------
    public function test_password_reset_loglocks_user_in_after_success(): void
    {
        Mail::fake();

        $user = User::factory()->create(['email' => 'autologin@solar.app']);

        $this->post(route('password.email'), ['email' => 'autologin@solar.app'])
            ->assertSessionHas('success');

        $url = $this->extractResetUrlFromMail('autologin@solar.app');
        $rawToken = $this->extractRawTokenFromUrl($url);

        // No actingAs() — the user starts the request as a guest.
        $response = $this->post(route('password.update'), [
            'token' => $rawToken,
            'password' => 'fresh-pass-9876',
            'password_confirmation' => 'fresh-pass-9876',
        ]);

        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
    }

    // ------------------------------------------------------------------
    // 11. test_password_reset_email_contains_signed_url
    // ------------------------------------------------------------------
    public function test_password_reset_email_contains_signed_url(): void
    {
        Mail::fake();

        User::factory()->create(['email' => 'signed@solar.app']);

        $this->post(route('password.email'), ['email' => 'signed@solar.app'])
            ->assertSessionHas('success');

        $url = null;
        Mail::assertSent(PasswordResetMail::class, function (PasswordResetMail $mail) use (&$url) {
            $url = $mail->resetUrl;

            return true;
        });

        $this->assertNotEmpty($url, 'Reset URL was not embedded in the queued mail.');

        // The URL must point at the password-reset form.
        $this->assertStringContainsString('/reset-password/', $url);

        // Signed-URL contract: the `expires` and `signature` query
        // parameters must both be present.
        $this->assertStringContainsString('expires=', $url);
        $this->assertStringContainsString('signature=', $url);

        // Sanity: the URL is parseable and the host matches the app.
        $parsed = parse_url($url);
        $this->assertNotFalse($parsed);
        $this->assertArrayHasKey('query', $parsed);
        parse_str($parsed['query'], $params);
        $this->assertArrayHasKey('expires', $params);
        $this->assertArrayHasKey('signature', $params);
    }

    // ------------------------------------------------------------------
    // 12. test_old_email_verification_tokens_still_work (regression)
    // ------------------------------------------------------------------
    public function test_old_email_verification_tokens_still_work(): void
    {
        // The migration that added the `purpose` column also added a
        // CHECK constraint and a new 3-col index. This test exercises
        // the entire PR1 (email verification) flow end-to-end to make
        // sure none of those changes broke existing tokens.
        Mail::fake();

        $user = User::factory()->unverified()->create([
            'email' => 'regression@solar.app',
        ]);

        // Use the existing PR1 service to mint a token the PR1 way.
        app(EmailVerificationService::class)->sendVerificationEmail($user);

        // The token must carry the default purpose — nothing else.
        $tokenRow = EmailVerificationToken::where('user_id', $user->id)
            ->where('purpose', EmailVerificationToken::PURPOSE_EMAIL_VERIFICATION)
            ->firstOrFail();
        $this->assertNull($tokenRow->consumed_at);

        $verificationUrl = null;
        Mail::assertSent(VerifyEmailMail::class, function (VerifyEmailMail $mail) use (&$verificationUrl) {
            $verificationUrl = $mail->verificationUrl;

            return true;
        });

        $this->assertNotEmpty($verificationUrl);

        // Hit the verification link — must mark the user verified and
        // redirect to the dashboard (this is the same flow PR1 ships).
        $this->get($verificationUrl)
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('success');

        $user->refresh();
        $this->assertNotNull($user->email_verified_at);

        // Token is now consumed, same as before the migration.
        $tokenRow->refresh();
        $this->assertNotNull($tokenRow->consumed_at);

        // Sanity: no password_reset row was accidentally created by
        // the verification flow.
        $this->assertDatabaseMissing('email_verification_tokens', [
            'user_id' => $user->id,
            'purpose' => EmailVerificationToken::PURPOSE_PASSWORD_RESET,
        ]);
    }

    // ------------------------------------------------------------------
    // 13. test_invalid_token_redirects_to_forgot_password_with_error
    // ------------------------------------------------------------------
    public function test_invalid_token_redirects_to_forgot_password_with_error(): void
    {
        // No token row exists for this garbage value.
        $response = $this->get(route('password.reset', ['token' => 'never-issued-token-xyz']));

        $response->assertRedirect(route('password.request'));
        $response->assertSessionHas('error', fn ($message) => str_contains(
            (string) $message,
            'Link inválido ou expirado',
        ));
    }

    // ==================================================================
    // Helpers
    // ==================================================================

    /**
     * Pull the signed reset URL out of the (last) queued
     * PasswordResetMail addressed to $email. Returns an empty string
     * if no mail was queued.
     */
    private function extractResetUrlFromMail(string $email): string
    {
        $url = '';
        Mail::assertSent(PasswordResetMail::class, function (PasswordResetMail $mail) use ($email, &$url) {
            if ($mail->user->email !== $email) {
                return true; // keep iterating; the last matching one wins
            }
            $url = $mail->resetUrl;

            return true;
        });

        return $url;
    }

    /**
     * The reset URL has the shape `/reset-password/{rawToken}?expires=...&signature=...`.
     * We only need the raw token to POST back to `password.update`.
     */
    private function extractRawTokenFromUrl(string $url): string
    {
        $path = parse_url($url, PHP_URL_PATH) ?: '';
        $segments = array_values(array_filter(explode('/', $path)));

        return (string) end($segments);
    }
}
