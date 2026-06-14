<?php

namespace Tests\Feature\Auth;

use App\Models\EmailVerificationToken;
use App\Models\User;
use App\Services\Auth\BearerTokenService;
use App\Services\Auth\InvalidTokenException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Coverage for FASE Polish / v0.10.0 — the {@see BearerTokenService}
 * that consolidates the per-flow token lifecycle (email verification,
 * password reset, 2FA enrollment, 2FA disable) into a single owner.
 *
 * The five cases below exercise every public method on the service
 * via the same surface the three per-purpose adapters use. We do
 * NOT reach into private members or the database schema directly
 * beyond the model's public API — the goal is to lock down the
 * public contract that the adapters depend on.
 */
class BearerTokenServiceTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Flush the rate-limit / throttle cache between tests so
     * the `canResend*` counters start at zero for every case.
     */
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    // ------------------------------------------------------------------
    // 1. test_mint_persists_row_with_correct_purpose_and_meta
    // ------------------------------------------------------------------
    public function test_mint_persists_row_with_correct_purpose_and_meta(): void
    {
        $user = User::factory()->create();
        $service = app(BearerTokenService::class);

        // Optional meta callback: the test verifies the
        // callback's array is merged into the row's `meta` JSON
        // column (the per-purpose adapter uses this to stash
        // purpose-specific data like the pending TOTP secret).
        $metaPayload = ['pending_secret' => 'ABCDEFGH'];

        $result = $service->mint(
            $user,
            EmailVerificationToken::PURPOSE_TWO_FACTOR_ENROLL,
            function (EmailVerificationToken $row, string $raw) use ($metaPayload): array {
                // The callback receives the freshly-persisted
                // row and the raw token; it returns the array
                // to merge into `meta`.
                $this->assertSame(EmailVerificationToken::PURPOSE_TWO_FACTOR_ENROLL, $row->purpose);
                $this->assertNotEmpty($raw);
                $this->assertSame(64, strlen($raw), 'Raw token must be 64 chars');

                return $metaPayload;
            },
            '203.0.113.10',
            'Mozilla/5.0 (Test UA)',
        );

        // Public surface: the caller gets back the raw token
        // and the persisted row.
        $this->assertArrayHasKey('raw', $result);
        $this->assertArrayHasKey('row', $result);
        $this->assertSame(64, strlen($result['raw']));
        $this->assertSame(EmailVerificationToken::PURPOSE_TWO_FACTOR_ENROLL, $result['row']->purpose);

        // The raw token and the persisted SHA-256 hash match.
        $this->assertSame(
            hash('sha256', $result['raw']),
            $result['row']->token_hash,
        );

        // The row is persisted with the right shape.
        $this->assertDatabaseHas('email_verification_tokens', [
            'id' => $result['row']->id,
            'user_id' => $user->id,
            'purpose' => EmailVerificationToken::PURPOSE_TWO_FACTOR_ENROLL,
            'consumed_at' => null,
            'ip_address' => '203.0.113.10',
        ]);

        // The meta callback was applied — the row's `meta`
        // column carries the array the callback returned.
        $fresh = $result['row']->fresh();
        $this->assertSame($metaPayload, $fresh->meta);

        // Expiry defaults to 60 minutes from "now" per the
        // model's `EmailVerificationToken::TTL_MINUTES` constant.
        $this->assertNotNull($fresh->expires_at);
        $this->assertTrue(
            $fresh->expires_at->isFuture(),
            'A freshly-minted token must expire in the future',
        );
    }

    // ------------------------------------------------------------------
    // 2. test_consume_throws_on_bad_token
    // ------------------------------------------------------------------
    public function test_consume_throws_on_bad_token(): void
    {
        $user = User::factory()->create();
        $service = app(BearerTokenService::class);

        // Mint a real token so we can prove the consume path
        // distinguishes "unknown raw" from "real raw".
        $minted = $service->mint(
            $user,
            EmailVerificationToken::PURPOSE_PASSWORD_RESET,
        );

        // Hand the service a totally unrelated raw value.
        // The SHA-256 lookup misses → InvalidTokenException.
        $bogus = str_repeat('z', 64);

        $this->expectException(InvalidTokenException::class);
        $this->expectExceptionMessage('Token not found.');

        $service->consume(
            $bogus,
            EmailVerificationToken::PURPOSE_PASSWORD_RESET,
            fn ($row, $u) => null,
        );
    }

    // ------------------------------------------------------------------
    // 3. test_consume_throws_on_expired_token
    // ------------------------------------------------------------------
    public function test_consume_throws_on_expired_token(): void
    {
        $user = User::factory()->create();
        $service = app(BearerTokenService::class);

        // Mint a token, then travel past its expiry. We use
        // Carbon::setTestNow() (not travel()) so the row's
        // `expires_at` is set at "now", and a one-hour
        // travel-forward pushes us past it. The model's
        // `isPast()` check trips and the service throws.
        Carbon::setTestNow(now());
        $minted = $service->mint(
            $user,
            EmailVerificationToken::PURPOSE_PASSWORD_RESET,
        );

        Carbon::setTestNow(now()->addHours(2));

        try {
            $this->expectException(InvalidTokenException::class);
            $this->expectExceptionMessage('Token expired.');

            $service->consume(
                $minted['raw'],
                EmailVerificationToken::PURPOSE_PASSWORD_RESET,
                fn ($row, $u) => null,
            );
        } finally {
            Carbon::setTestNow(); // reset
        }
    }

    // ------------------------------------------------------------------
    // 4. test_consume_throws_on_consumed_token
    // ------------------------------------------------------------------
    public function test_consume_throws_on_consumed_token(): void
    {
        $user = User::factory()->create();
        $service = app(BearerTokenService::class);

        $minted = $service->mint(
            $user,
            EmailVerificationToken::PURPOSE_PASSWORD_RESET,
        );

        // First consume succeeds and returns the handler's
        // return value. We pass a closure that just hands
        // back the user — that's what every per-purpose
        // adapter does (e.g. the password reset adapter
        // returns the user after hashing the new password).
        $returned = $service->consume(
            $minted['raw'],
            EmailVerificationToken::PURPOSE_PASSWORD_RESET,
            fn ($row, $u) => $u,
        );
        $this->assertTrue($returned->is($user));

        // Replay: the same raw now hits the `consumed_at IS
        // NOT NULL` guard. The service throws.
        $this->expectException(InvalidTokenException::class);
        $this->expectExceptionMessage('Token already consumed.');

        $service->consume(
            $minted['raw'],
            EmailVerificationToken::PURPOSE_PASSWORD_RESET,
            fn ($row, $u) => null,
        );
    }

    // ------------------------------------------------------------------
    // 5. test_can_resend_throttles_at_one_per_30s_and_five_per_hour
    // ------------------------------------------------------------------
    public function test_can_resend_throttles_at_one_per_30s_and_five_per_hour(): void
    {
        $service = app(BearerTokenService::class);
        $email = 'throttle-' . uniqid() . '@solar.app';

        // Cold cache: resend is allowed.
        $this->assertTrue(
            $service->canResend($email, EmailVerificationToken::PURPOSE_PASSWORD_RESET),
            'A fresh address must be allowed to resend on first ask',
        );

        // Stamping a counter immediately blocks the next
        // resend (30-second cooldown).
        $keyPrefix = $service->resendThrottleKey($email, EmailVerificationToken::PURPOSE_PASSWORD_RESET);
        $service->bumpResendCounter($keyPrefix);

        $this->assertFalse(
            $service->canResend($email, EmailVerificationToken::PURPOSE_PASSWORD_RESET),
            'Within the 30s cooldown window, canResend must return false',
        );

        // Travel 31 seconds — the cooldown has elapsed but
        // the hourly cap still counts us as having sent 1
        // message this hour, so the answer is still true
        // (we are below the hourly cap).
        Carbon::setTestNow(now()->addSeconds(31));
        $this->assertTrue(
            $service->canResend($email, EmailVerificationToken::PURPOSE_PASSWORD_RESET),
            'Past the cooldown, canResend must return true (we are below the hourly cap)',
        );

        // Push the hourly counter to the cap. The next
        // call must return false (5/hour reached).
        $hourlyKey = $keyPrefix.':hourly_count';
        for ($i = 2; $i <= 5; $i++) {
            $service->bumpResendCounter($keyPrefix);
            // The 30s cooldown is reset by each bump — push
            // the test clock forward each time.
            Carbon::setTestNow(now()->addSeconds(31));
        }

        $this->assertFalse(
            $service->canResend($email, EmailVerificationToken::PURPOSE_PASSWORD_RESET),
            'Once the hourly cap is hit, canResend must return false',
        );
        $this->assertSame(
            5,
            (int) Cache::get($hourlyKey),
            'The hourly counter must sit exactly at the cap',
        );

        // Reset the test clock and the cache for any
        // subsequent tests in the same process.
        Carbon::setTestNow();
    }
}
