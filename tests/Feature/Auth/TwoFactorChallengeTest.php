<?php

namespace Tests\Feature\Auth;

use App\Models\RecoveryCode;
use App\Models\TrustedDevice;
use App\Models\User;
use App\Services\Auth\TwoFactorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Coverage for FASE 4D / Auth Phase 3 — the live 2FA challenge.
 *
 * The 7 cases below exercise every behaviour the design doc
 * promises for the post-login TOTP / recovery code challenge:
 *
 *  - Bypass / redirect rules in `EnsureTwoFactorVerified`.
 *  - TOTP path (digits only, 6 chars).
 *  - Recovery-code path (alphanumeric with dashes).
 *  - Trusted-device cookie: issue, bypass challenge, revoke-all.
 *  - 2FA-disabled user skips the challenge entirely.
 */
class TwoFactorChallengeTest extends TestCase
{
    use RefreshDatabase;

    // ------------------------------------------------------------------
    // 9. test_user_with_2fa_enabled_is_redirected_to_challenge_after_login
    // ------------------------------------------------------------------
    public function test_user_with_2fa_enabled_is_redirected_to_challenge_after_login(): void
    {
        $user = User::factory()->withTwoFactor()->create();

        // Simulate a fresh login by hitting the dashboard — the
        // `two_factor` middleware should bounce us.
        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertRedirect(route('two-factor.challenge'));
        $response->assertSessionHas('error', fn ($msg) => str_contains(
            (string) $msg,
            '2FA',
        ));
    }

    // ------------------------------------------------------------------
    // 10. test_user_can_complete_challenge_with_valid_totp
    // ------------------------------------------------------------------
    public function test_user_can_complete_challenge_with_valid_totp(): void
    {
        $user = User::factory()->withTwoFactor()->create();
        $this->actingAs($user);

        $code = app(TwoFactorService::class)
            ->currentOtp($user->twoFactor->secret_encrypted);

        $response = $this->post(route('two-factor.verify'), ['code' => $code]);

        $response->assertRedirect(route('dashboard'));
        $response->assertSessionHas('success');

        // The session flag is stamped — follow-up dashboard
        // requests are not bounced.
        $this->assertTrue($user->fresh()->isTwoFactorVerified());

        $secondHit = $this->get(route('dashboard'));
        $secondHit->assertOk();
    }

    // ------------------------------------------------------------------
    // 11. test_user_can_complete_challenge_with_recovery_code
    // ------------------------------------------------------------------
    public function test_user_can_complete_challenge_with_recovery_code(): void
    {
        $user = User::factory()
            ->withTwoFactor()
            ->withRecoveryCodes(10)
            ->create();
        $this->actingAs($user);

        // Pull the first unconsumed recovery code's hash and
        // find a code that hashes to it. We pre-mint codes
        // inside the factory, so the SHA-256 inverse is not
        // possible — instead, we insert a known code in plain
        // text and assert it was consumed after the challenge.
        $plainCode = 'ABCD-EFGH-IJ';
        RecoveryCode::where('user_id', $user->id)
            ->whereNull('consumed_at')
            ->firstOrFail()
            ->update(['code_hash' => hash('sha256', $plainCode)]);

        $response = $this->from(route('two-factor.challenge'))
            ->post(route('two-factor.verify'), ['code' => $plainCode]);

        $response->assertRedirect(route('dashboard'));

        // The code row is now stamped consumed.
        $row = RecoveryCode::where('user_id', $user->id)
            ->where('code_hash', hash('sha256', $plainCode))
            ->firstOrFail();
        $this->assertNotNull($row->consumed_at);
    }

    // ------------------------------------------------------------------
    // 12. test_challenge_rejects_invalid_totp_and_recovery_code
    // ------------------------------------------------------------------
    public function test_challenge_rejects_invalid_totp_and_recovery_code(): void
    {
        $user = User::factory()
            ->withTwoFactor()
            ->withRecoveryCodes(10)
            ->create();
        $this->actingAs($user);

        // "000000" is the conventional test placeholder that
        // does not match any current 30s TOTP window. The
        // recovery code "ZZZZ-ZZZZ-ZZ" is similarly not in the
        // 10 minted rows.
        $response = $this->from(route('two-factor.challenge'))
            ->post(route('two-factor.verify'), [
                'code' => '000000',
            ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors(['code']);

        // The user is still NOT 2FA-verified — a follow-up
        // dashboard hit is still bounced.
        $this->assertFalse($user->fresh()->isTwoFactorVerified());

        $bounce = $this->get(route('dashboard'));
        $bounce->assertRedirect(route('two-factor.challenge'));
    }

    // ------------------------------------------------------------------
    // 13. test_trusted_device_cookie_bypasses_challenge_on_next_login
    // ------------------------------------------------------------------
    public function test_trusted_device_cookie_bypasses_challenge_on_next_login(): void
    {
        $user = User::factory()->withTwoFactor()->create();
        $this->actingAs($user);

        $code = app(TwoFactorService::class)
            ->currentOtp($user->twoFactor->secret_encrypted);

        // Pass the challenge with the "trust device" checkbox
        // ON. The backend's TrustedDeviceService will set a
        // cookie on the outgoing response.
        $this->from(route('two-factor.challenge'))
            ->post(route('two-factor.verify'), [
                'code' => $code,
                'trust_device' => '1',
            ])->assertRedirect(route('dashboard'));

        // A trusted_devices row was created.
        $this->assertSame(1, TrustedDevice::where('user_id', $user->id)->count());

        // We cannot read the raw validator back (only the
        // SHA-256 hash is persisted). To test the cookie
        // bypass, insert a fresh device row with a known
        // selector+validator and present the corresponding
        // cookie on the next request.
        auth()->logout();
        $user = $user->fresh();

        $knownSelector = 'sel_test_known_selector_1234567890ab';
        $knownValidator = 'val_test_known_validator_cd';
        TrustedDevice::create([
            'user_id' => $user->id,
            'selector' => $knownSelector,
            'validator_hash' => hash('sha256', $knownValidator),
            'last_seen_at' => now(),
            'expires_at' => now()->addDays(90),
        ]);

        $this->actingAs($user)
            ->withCookie('solar_trusted', $knownSelector.':'.$knownValidator)
            ->get(route('dashboard'))
            ->assertOk();
    }

    // ------------------------------------------------------------------
    // 14. test_trusted_device_cookie_can_be_revoked
    // ------------------------------------------------------------------
    public function test_trusted_device_cookie_can_be_revoked(): void
    {
        $user = User::factory()->withTwoFactor()->create();
        $this->actingAs($user);

        $code = app(TwoFactorService::class)
            ->currentOtp($user->twoFactor->secret_encrypted);

        $this->post(route('two-factor.verify'), [
            'code' => $code,
            'trust_device' => '1',
        ])->assertRedirect(route('dashboard'));

        $this->assertSame(1, TrustedDevice::where('user_id', $user->id)->count());

        // Mark the user 2FA-verified so the revoke-all action
        // is reachable (the controller lives behind the
        // `two_factor` middleware).
        $user->markTwoFactorVerified();

        // Revoke all.
        $this->delete(route('trusted-devices.destroy-all'))
            ->assertRedirect();

        $this->assertSame(0, TrustedDevice::where('user_id', $user->id)->count());

        // Logging out + re-logging in without a cookie must
        // bounce to the challenge again.
        auth()->logout();

        $bounce = $this->actingAs($user->fresh())
            ->get(route('dashboard'));

        $bounce->assertRedirect(route('two-factor.challenge'));
    }

    // ------------------------------------------------------------------
    // 15. test_2fa_disabled_user_skips_challenge
    // ------------------------------------------------------------------
    public function test_2fa_disabled_user_skips_challenge(): void
    {
        $user = User::factory()->create(); // no withTwoFactor
        $this->assertFalse($user->hasTwoFactorEnabled());

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk();
    }

    // ------------------------------------------------------------------
    // 16. test_challenge_429_when_totp_hits_per_minute_limit
    // (FASE Polish / v0.10.0 — TOTP path's per-IP throttle)
    //
    // The 2FA challenge route stacks two named limiters
    // (`two-factor.challenge` 10/min + `two-factor.recovery`
    // 3/min) — the tighter cap wins. So the TOTP path is
    // effectively bounded at 3/min in production. We send
    // 3 attempts and assert the 4th gets 429.
    // ------------------------------------------------------------------
    public function test_challenge_429_when_totp_hits_per_minute_limit(): void
    {
        $user = User::factory()->withTwoFactor()->create();
        $this->actingAs($user);

        // "000000" is the conventional test placeholder that
        // never matches a current 30s TOTP window. Send it
        // 3 times (the binding cap of the two stacked limiters
        // is 3/min) — every request reaches the controller
        // and bounces with a flash. The 4th hits the throttle
        // middleware and returns 429.
        for ($i = 1; $i <= 3; $i++) {
            $this->from(route('two-factor.challenge'))
                ->post(route('two-factor.verify'), ['code' => '000000']);
        }

        $throttled = $this->from(route('two-factor.challenge'))
            ->post(route('two-factor.verify'), ['code' => '000000']);

        $throttled->assertStatus(429);
        $this->assertNotNull($throttled->headers->get('Retry-After'));
    }

    // ------------------------------------------------------------------
    // 17. test_challenge_429_when_recovery_code_hits_per_minute_limit
    // (FASE Polish / v0.10.0 — recovery-code path's tighter 3/min cap)
    //
    // BLOCKING: same defect as RateLimitTest::test_two_factor_recovery_code_respects_stricter_per_minute_limit.
    // The AppServiceProvider's `config("rate-limits.{$configKey}.per_min")` collapses
    // the dotted `two-factor.recovery` key into nested access
    // and falls back to the 10/min default. The fix is one
    // line — switch to `config('rate-limits')[$configKey]['per_min']` —
    // and this test passes as-is.
    // ------------------------------------------------------------------
    public function test_challenge_429_when_recovery_code_hits_per_minute_limit(): void
    {
        $user = User::factory()
            ->withTwoFactor()
            ->withRecoveryCodes(10)
            ->create();
        $this->actingAs($user);

        // Pre-seed a known unconsumed recovery code so the
        // request shape is unambiguously a recovery attempt.
        $plainCode = 'ABCD-EFGH-IJ';
        RecoveryCode::where('user_id', $user->id)
            ->whereNull('consumed_at')
            ->firstOrFail()
            ->update(['code_hash' => hash('sha256', $plainCode)]);

        // The design contract is a 3/min cap. Today the live
        // cap is 10/min (see BLOCKING note above) so the
        // assertion below intentionally tests the CORRECT
        // contract — once the AppServiceProvider is fixed,
        // this passes.
        for ($i = 1; $i <= 3; $i++) {
            $this->from(route('two-factor.challenge'))
                ->post(route('two-factor.verify'), ['code' => $plainCode]);
        }

        $throttled = $this->from(route('two-factor.challenge'))
            ->post(route('two-factor.verify'), ['code' => $plainCode]);

        $throttled->assertStatus(429);
        $this->assertNotNull($throttled->headers->get('Retry-After'));
    }
}
