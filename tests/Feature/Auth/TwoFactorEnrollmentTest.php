<?php

namespace Tests\Feature\Auth;

use App\Mail\TwoFactorEnableMail;
use App\Models\EmailVerificationToken;
use App\Models\RecoveryCode;
use App\Models\User;
use App\Models\UserTwoFactor;
use App\Services\Auth\TwoFactorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

/**
 * Coverage for FASE 4D / Auth Phase 3 — 2FA enrollment.
 *
 * The 8 cases below cover the two halves of the enable flow:
 *  - `beginEnable()` (authenticated POST → email + token row)
 *  - `confirmEnableStore()` (signed-link POST with 6-digit TOTP)
 *
 * Plus two regression cases (disable wipes everything) and the
 * signed-URL contract. The `useTwoFactor()` factory state
 * supplies a real TOTP secret so we never duplicate the
 * encryption / secret-mint dance in each test.
 */
class TwoFactorEnrollmentTest extends TestCase
{
    use RefreshDatabase;

    // ------------------------------------------------------------------
    // 1. test_user_can_begin_2fa_enrollment
    // ------------------------------------------------------------------
    public function test_user_can_begin_2fa_enrollment(): void
    {
        Mail::fake();

        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->post(route('two-factor.enable.begin'));

        $response->assertRedirect();
        $response->assertSessionHas('success', fn ($msg) => str_contains(
            (string) $msg,
            'Enviamos um link',
        ));

        // Token row created with the right purpose.
        $this->assertDatabaseHas('email_verification_tokens', [
            'user_id' => $user->id,
            'purpose' => EmailVerificationToken::PURPOSE_TWO_FACTOR_ENROLL,
            'consumed_at' => null,
        ]);

        Mail::assertSent(TwoFactorEnableMail::class, function (TwoFactorEnableMail $mail) use ($user) {
            return $mail->user->is($user);
        });
    }

    // ------------------------------------------------------------------
    // 2. test_enable_enrollment_requires_authenticated_user
    // ------------------------------------------------------------------
    public function test_enable_enrollment_requires_authenticated_user(): void
    {
        Mail::fake();

        // No actingAs — this is an anonymous request.
        $response = $this->post(route('two-factor.enable.begin'));

        // The auth middleware should bounce the anonymous caller
        // to /login (302). We don't assert the exact URL — the
        // important contract is "no token was minted, no email
        // was sent".
        $response->assertStatus(302);

        $this->assertDatabaseCount('email_verification_tokens', 0);
        Mail::assertNothingSent();
    }

    // ------------------------------------------------------------------
    // 3. test_enable_enrollment_email_contains_signed_url
    // ------------------------------------------------------------------
    public function test_enable_enrollment_email_contains_signed_url(): void
    {
        Mail::fake();

        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('two-factor.enable.begin'))
            ->assertSessionHas('success');

        $url = null;
        Mail::assertSent(TwoFactorEnableMail::class, function (TwoFactorEnableMail $mail) use (&$url) {
            $url = $mail->confirmUrl;

            return true;
        });

        $this->assertNotEmpty($url, 'Enable URL was not captured from the queued mail.');
        $this->assertStringContainsString('/two-factor/enable/confirm/', $url);
        $this->assertStringContainsString('expires=', $url);
        $this->assertStringContainsString('signature=', $url);

        // Sanity: parseable and signed by Laravel.
        $parsed = parse_url($url);
        $this->assertNotFalse($parsed);
        parse_str($parsed['query'], $params);
        $this->assertArrayHasKey('expires', $params);
        $this->assertArrayHasKey('signature', $params);
    }

    // ------------------------------------------------------------------
    // 4. test_user_can_confirm_2fa_enrollment_with_valid_totp
    // ------------------------------------------------------------------
    public function test_user_can_confirm_2fa_enrollment_with_valid_totp(): void
    {
        Mail::fake();

        $user = User::factory()->create();

        // Stage 1: mint the token + signed URL.
        $this->actingAs($user)
            ->post(route('two-factor.enable.begin'))
            ->assertSessionHas('success');

        $url = null;
        Mail::assertSent(TwoFactorEnableMail::class, function (TwoFactorEnableMail $mail) use (&$url) {
            $url = $mail->confirmUrl;

            return true;
        });

        $rawToken = $this->extractRawTokenFromUrl($url);

        // Stage 2: GET the confirm page so the controller mints the
        // pending TOTP secret and stashes it on the token row's meta.
        // The GET lives outside the `auth` group (so the user can
        // open the link in a fresh browser), and the `guest`
        // middleware would otherwise redirect an authenticated
        // user to /dashboard. Log out first, then hit the URL.
        auth()->logout();
        $this->get($url)->assertOk();

        $tokenRow = EmailVerificationToken::query()
            ->forPurpose(EmailVerificationToken::PURPOSE_TWO_FACTOR_ENROLL)
            ->where('user_id', $user->id)
            ->firstOrFail();

        $meta = $tokenRow->meta ?? [];
        $this->assertArrayHasKey('pending_secret_encrypted', $meta);
        $encrypted = $meta['pending_secret_encrypted'];

        // Compute the TOTP the authenticator would emit right now
        // against the pending secret. This is exactly what the
        // user would type back from their phone.
        $tf = app(TwoFactorService::class);
        $code = $tf->currentOtp($encrypted);

        // Stage 3: POST the 6-digit code. Also unauthenticated
        // (the route is `web, guest, signed` — the token is the
        // credential).
        $response = $this->post(route('two-factor.enable.store'), [
            'token' => $rawToken,
            'code' => $code,
        ]);

        $response->assertRedirect(route('dashboard'));
        $response->assertSessionHas('success', fn ($msg) => str_contains(
            (string) $msg,
            '2FA ativado',
        ));

        // user_two_factor row exists; 10 recovery codes minted.
        $this->assertDatabaseHas('user_two_factor', [
            'user_id' => $user->id,
        ]);

        $recoveryCount = RecoveryCode::where('user_id', $user->id)->count();
        $this->assertSame(10, $recoveryCount, 'Expected 10 recovery codes minted on enable.');

        // Token row was consumed.
        $tokenRow->refresh();
        $this->assertNotNull($tokenRow->consumed_at);
    }

    // ------------------------------------------------------------------
    // 5. test_enable_confirmation_rejects_invalid_totp_code
    // ------------------------------------------------------------------
    public function test_enable_confirmation_rejects_invalid_totp_code(): void
    {
        Mail::fake();

        $user = User::factory()->create();
        $this->actingAs($user)
            ->post(route('two-factor.enable.begin'))
            ->assertSessionHas('success');

        $url = null;
        Mail::assertSent(TwoFactorEnableMail::class, function (TwoFactorEnableMail $mail) use (&$url) {
            $url = $mail->confirmUrl;

            return true;
        });

        // Render the confirm page so the pending secret exists.
        // Log out first — the GET lives behind `guest` middleware.
        auth()->logout();
        $this->get($url)->assertOk();

        $rawToken = $this->extractRawTokenFromUrl($url);

        // Bogus code. The form request validates `digits:6`, so the
        // controller will redirect to /login with the
        // service-layer exception message ("Codigo 2FA invalido.")
        $response = $this->post(route('two-factor.enable.store'), [
            'token' => $rawToken,
            'code' => '000000',
        ]);

        $response->assertRedirect(route('login'));
        $response->assertSessionHas('error', fn ($msg) => str_contains(
            (string) $msg,
            'inv',
        ));

        // No user_two_factor row was created.
        $this->assertDatabaseMissing('user_two_factor', [
            'user_id' => $user->id,
        ]);

        // No recovery codes were minted.
        $this->assertSame(0, RecoveryCode::where('user_id', $user->id)->count());
    }

    // ------------------------------------------------------------------
    // 6. test_enable_confirmation_rejects_consumed_token
    // ------------------------------------------------------------------
    public function test_enable_confirmation_rejects_consumed_token(): void
    {
        Mail::fake();

        $user = User::factory()->create();
        $this->actingAs($user)
            ->post(route('two-factor.enable.begin'))
            ->assertSessionHas('success');

        $url = null;
        Mail::assertSent(TwoFactorEnableMail::class, function (TwoFactorEnableMail $mail) use (&$url) {
            $url = $mail->confirmUrl;

            return true;
        });
        $rawToken = $this->extractRawTokenFromUrl($url);

        // Log out before GET — the route is behind `guest` middleware.
        auth()->logout();
        $this->get($url)->assertOk();

        $tokenRow = EmailVerificationToken::query()
            ->forPurpose(EmailVerificationToken::PURPOSE_TWO_FACTOR_ENROLL)
            ->where('user_id', $user->id)
            ->firstOrFail();
        $encrypted = $tokenRow->meta['pending_secret_encrypted'];
        $code = app(TwoFactorService::class)->currentOtp($encrypted);

        // First use: succeeds.
        $this->post(route('two-factor.enable.store'), [
            'token' => $rawToken,
            'code' => $code,
        ])->assertRedirect(route('dashboard'));

        // Second use: the token is consumed. The next user to click
        // the same link hits the "already used" branch.
        // The token IS still embedded in the URL but the
        // `signed` middleware still passes (the URL signature
        // has not expired); the controller's lookupToken
        // does the "consumed_at" check.
        $response = $this->get($url);
        $response->assertRedirect(route('login'));
        $response->assertSessionHas('error', fn ($msg) => str_contains(
            (string) $msg,
            'inv',
        ));
    }

    // ------------------------------------------------------------------
    // 7. test_enable_confirmation_rejects_expired_token
    // ------------------------------------------------------------------
    public function test_enable_confirmation_rejects_expired_token(): void
    {
        Mail::fake();

        $user = User::factory()->create();
        $this->actingAs($user)
            ->post(route('two-factor.enable.begin'))
            ->assertSessionHas('success');

        $url = null;
        Mail::assertSent(TwoFactorEnableMail::class, function (TwoFactorEnableMail $mail) use (&$url) {
            $url = $mail->confirmUrl;

            return true;
        });

        // Roll time forward past the 60-minute TTL. The signed URL
        // is no longer in the signed-URL validity window AND the
        // token row's `expires_at` is in the past. The POST hits
        // the controller (no longer inside the `signed` group —
        // the token in the body is the credential). The service
        // layer rejects the token because `expires_at` is past,
        // and the controller bounces to /login with the
        // "expired" flash.
        $this->travel(61)->minutes();
        auth()->logout();

        $rawToken = $this->extractRawTokenFromUrl($url);
        $response = $this->post(route('two-factor.enable.store'), [
            'token' => $rawToken,
            'code' => '000000',
        ]);

        $response->assertRedirect(route('login'));
        $response->assertSessionHas('error', fn ($msg) => str_contains(
            (string) $msg,
            'expir',
        ));

        // No user_two_factor row.
        $this->assertDatabaseMissing('user_two_factor', [
            'user_id' => $user->id,
        ]);
    }

    // ------------------------------------------------------------------
    // 8. test_enable_clears_2fa_on_disable
    // ------------------------------------------------------------------
    public function test_enable_clears_2fa_on_disable(): void
    {
        Mail::fake();

        $user = User::factory()->create([
            'password' => 'enable-pass-1234',
        ]);

        // Full enable flow.
        $this->actingAs($user)
            ->post(route('two-factor.enable.begin'))
            ->assertSessionHas('success');

        $enableUrl = null;
        Mail::assertSent(TwoFactorEnableMail::class, function (TwoFactorEnableMail $mail) use (&$enableUrl) {
            $enableUrl = $mail->confirmUrl;

            return true;
        });

        // Log out before GET — the route is behind `guest` middleware
        // (a user opening the email link is by definition not authed
        // in the same browser).
        auth()->logout();
        $this->get($enableUrl)->assertOk();
        $tokenRow = EmailVerificationToken::query()
            ->forPurpose(EmailVerificationToken::PURPOSE_TWO_FACTOR_ENROLL)
            ->where('user_id', $user->id)
            ->firstOrFail();
        $code = app(TwoFactorService::class)
            ->currentOtp($tokenRow->meta['pending_secret_encrypted']);
        $this->post(route('two-factor.enable.store'), [
            'token' => $this->extractRawTokenFromUrl($enableUrl),
            'code' => $code,
        ])->assertRedirect(route('dashboard'));

        // 2FA on, 10 recovery codes minted.
        $user = $user->fresh();
        $this->assertTrue($user->hasTwoFactorEnabled());
        $this->assertSame(10, RecoveryCode::where('user_id', $user->id)->count());

        // Full disable flow. The user is logged out (from the
        // confirm GET) so re-auth as the same user. The
        // `two_factor` middleware exempts the disable route by
        // name, so we don't need to stamp the session flag here.
        // CRITICAL: re-fetch the user from the DB. The original
        // `$user` reference was loaded before the enable
        // controller created the `user_two_factor` row, so
        // `$user->hasTwoFactorEnabled()` on the in-memory
        // instance would still return false. The controller
        // takes `$user = $request->user()` — which is the same
        // stale instance — and would bounce with "2FA ja esta
        // desativada".
        $user = $user->fresh();
        $this->actingAs($user)
            ->post(route('two-factor.disable.begin'), [
                'password' => 'enable-pass-1234',
            ])
            ->assertSessionHas('success');

        $disableUrl = null;
        Mail::assertSent(\App\Mail\TwoFactorDisableMail::class, function ($mail) use (&$disableUrl) {
            $disableUrl = $mail->confirmUrl;

            return true;
        });
        $rawDisableToken = $this->extractRawTokenFromUrl($disableUrl);

        // Log out before the disable POST — the store route is
        // also `guest, signed` per the design (a click from
        // email lands on a fresh browser).
        auth()->logout();
        $this->post(route('two-factor.disable.store'), [
            'token' => $rawDisableToken,
            'password' => 'enable-pass-1234',
        ])->assertRedirect(route('login'));

        // All 2FA state wiped.
        $this->assertDatabaseMissing('user_two_factor', ['user_id' => $user->id]);
        $this->assertSame(0, RecoveryCode::where('user_id', $user->id)->count());
        $this->assertFalse($user->fresh()->hasTwoFactorEnabled());
    }

    // ==================================================================
    // Helpers
    // ==================================================================

    /**
     * Pull the raw token segment out of a confirm URL with the
     * shape `/two-factor/{enable,disable}/confirm/{rawToken}?…`.
     */
    private function extractRawTokenFromUrl(string $url): string
    {
        $path = parse_url($url, PHP_URL_PATH) ?: '';
        $segments = array_values(array_filter(explode('/', $path)));

        return (string) end($segments);
    }
}
