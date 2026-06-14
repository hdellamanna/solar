<?php

namespace App\Services\Auth;

use App\Models\EmailVerificationToken;
use App\Models\User;
use Closure;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * Single owner of the bearer-token lifecycle.
 *
 * The four single-use email-token flows (email verification,
 * password reset, 2FA enrollment, 2FA disable) used to each roll
 * their own mint/consume/throttle code in three separate
 * services. This class is the shared base; the per-purpose
 * services are now thin adapters that pick a `purpose` value,
 * a `meta` payload, and a post-consume handler closure.
 *
 * Persistence layer is unchanged: every flow stores a SHA-256
 * hash of a 64-char random string in the same
 * `email_verification_tokens` table, discriminated by the
 * `purpose` column. No new token tables, no new hash logic.
 *
 * Public surface:
 *
 *  - `mint()` returns the raw token and the persisted row.
 *  - `consume()` looks up a token, marks it consumed, and runs
 *    the caller's handler with `(row, user)`.
 *  - `canResend()` enforces a 1-per-30s / 5-per-hour throttle.
 *  - `resendThrottleKey()` returns the email-scoped key prefix
 *    so callers can seed or inspect counters in tests.
 *
 * Cache key shapes are inherited from the original per-flow
 * services and MUST stay stable — the existing 261 feature
 * tests poke these keys directly to assert the throttle
 * behaviour:
 *
 *  - email_verification : `email_verification:last_sent:{userId}`
 *                         `email_verification:hourly_count:{userId}`
 *                         (handled by canResendForUser / etc.)
 *  - password_reset     : `password-reset:throttle:{emailHash}:last_sent`
 *                         `password-reset:throttle:{emailHash}:hourly_count`
 *  - 2FA flows          : `bearer-token:throttle:{purpose}:{emailHash}:last_sent`
 *                         `bearer-token:throttle:{purpose}:{emailHash}:hourly_count`
 *
 * The email verification keys are baked in (`:last_sent` and
 * `:hourly_count` are part of the key, with the user id last).
 * The password reset / 2FA keys are prefix-shaped — the
 * adapter appends `:last_sent` or `:hourly_count`.
 */
class BearerTokenService
{
    /** Minimum seconds between two consecutive sends to the same address. */
    public const RESEND_COOLDOWN_SECONDS = 30;

    /** Maximum number of sends allowed within the rolling one-hour window. */
    public const RESEND_HOURLY_CAP = 5;

    /**
     * Mint a fresh single-use token, persist the hash, and run
     * the optional metadata callback so the caller can stash
     * purpose-specific state on the row's `meta` column.
     *
     * The raw value is the 64-char string from
     * {@see EmailVerificationToken::generateForUser()} — that
     * helper also handles the SHA-256 hashing and the
     * `expires_at` stamp, so we don't reimplement either here.
     *
     * @param  Closure(EmailVerificationToken, string): array  $metaCallback
     *         Receives the freshly persisted row and the raw
     *         token, returns the array to merge into `meta`.
     *         Pass null when no extra metadata is needed.
     * @return array{raw: string, row: EmailVerificationToken}
     */
    public function mint(
        User $user,
        string $purpose,
        ?Closure $metaCallback = null,
        ?string $ip = null,
        ?string $ua = null,
    ): array {
        $payload = EmailVerificationToken::generateForUser(
            $user,
            null,
            $ip,
            $ua,
            $purpose,
        );

        $row = $payload['model'];
        $raw = $payload['token'];

        if ($metaCallback !== null) {
            $extra = $metaCallback($row, $raw);
            if (is_array($extra) && $extra !== []) {
                $row->meta = array_merge($row->meta ?? [], $extra);
                $row->save();
            }
        }

        return ['raw' => $raw, 'row' => $row];
    }

    /**
     * Look up a raw token for the given purpose, mark it
     * consumed, and hand the row + resolved user to the caller's
     * handler. The handler's return value is bubbled up.
     *
     * Throws {@see InvalidTokenException} on missing /
     * consumed / expired tokens. Adapters wrap that exception
     * in their own purpose-specific subclass so the HTTP layer
     * keeps its existing error-routing contract.
     *
     * @template T
     * @param  Closure(EmailVerificationToken, User): T  $handler
     * @return T
     */
    public function consume(string $rawToken, string $purpose, Closure $handler): mixed
    {
        $hash = EmailVerificationToken::hashToken($rawToken);

        /** @var EmailVerificationToken|null $token */
        $token = EmailVerificationToken::query()
            ->forPurpose($purpose)
            ->where('token_hash', $hash)
            ->first();

        if ($token === null) {
            throw new InvalidTokenException('Token not found.');
        }

        if ($token->consumed_at !== null) {
            throw new InvalidTokenException('Token already consumed.');
        }

        if (! $token->expires_at || $token->expires_at->isPast()) {
            throw new InvalidTokenException('Token expired.');
        }

        $user = $token->user;
        if ($user === null) {
            throw new InvalidTokenException('User associated with the token no longer exists.');
        }

        // Mark consumed first, then call the handler. If two
        // requests race, the second one fails the consumed_at
        // check above on re-fetch — we accept the small overlap
        // (same trade-off the per-flow services had).
        $token->consumed_at = Carbon::now();
        $token->save();

        return $handler($token, $user);
    }

    /**
     * Returns true when the address is allowed to receive
     * another token-bearing email right now. Enforces a
     * 30-second cooldown between consecutive sends and a hard
     * cap of 5 sends per rolling hour.
     *
     * The email verification flow is keyed by user id (not by
     * email) so the controller and tests can target a
     * specific user — call {@see canResendForUser()} for that
     * flow. This method handles the email-hash-keyed flows
     * (password reset, 2FA).
     */
    public function canResend(string $email, string $purpose): bool
    {
        $key = $this->resendThrottleKey($email, $purpose);

        $lastSentAt = Cache::get($key.':last_sent');
        if ($lastSentAt !== null) {
            // Carbon 3's diffInSeconds is signed; abs() so we
            // don't care which side of the comparison is later.
            $elapsed = abs((int) $lastSentAt->diffInSeconds(Carbon::now()));
            if ($elapsed < self::RESEND_COOLDOWN_SECONDS) {
                return false;
            }
        }

        $windowCount = (int) Cache::get($key.':hourly_count', 0);
        if ($windowCount >= self::RESEND_HOURLY_CAP) {
            return false;
        }

        return true;
    }

    /**
     * Email-verification-specific throttle check (keyed by
     * user id, not by email hash). The legacy implementation
     * keys were `email_verification:last_sent:{userId}` and
     * `email_verification:hourly_count:{userId}` and the
     * existing test suite pokes those exact keys directly to
     * assert the throttle, so this method preserves the
     * shape.
     */
    public function canResendForUser(User $user): bool
    {
        $lastSentKey = "email_verification:last_sent:{$user->id}";
        $hourlyKey = "email_verification:hourly_count:{$user->id}";

        $lastSentAt = Cache::get($lastSentKey);
        if ($lastSentAt !== null) {
            $elapsed = abs((int) $lastSentAt->diffInSeconds(Carbon::now()));
            if ($elapsed < self::RESEND_COOLDOWN_SECONDS) {
                return false;
            }
        }

        $windowCount = (int) Cache::get($hourlyKey, 0);
        if ($windowCount >= self::RESEND_HOURLY_CAP) {
            return false;
        }

        return true;
    }

    /**
     * Email-verification-specific counter stamp (keyed by
     * user id, not by email hash). See {@see canResendForUser()}.
     */
    public function bumpResendCounterForUser(User $user): void
    {
        $lastSentKey = "email_verification:last_sent:{$user->id}";
        $hourlyKey = "email_verification:hourly_count:{$user->id}";
        $now = Carbon::now();

        Cache::put($lastSentKey, $now, $now->copy()->addHour());
        Cache::add($hourlyKey, 0, $now->copy()->addHour());
        Cache::increment($hourlyKey);
    }

    /**
     * Email-verification-specific retry-after (keyed by user
     * id). Returns the number of seconds the caller still has
     * to wait before the next resend is allowed (0 = can
     * resend right now). Mirrors the legacy
     * {@see EmailVerificationService::retryAfterSeconds()}
     * contract.
     */
    public function retryAfterSecondsForUser(User $user): int
    {
        $lastSentKey = "email_verification:last_sent:{$user->id}";

        $lastSentAt = Cache::get($lastSentKey);
        if ($lastSentAt === null) {
            return 0;
        }

        $elapsed = abs((int) $lastSentAt->diffInSeconds(Carbon::now()));
        $remaining = self::RESEND_COOLDOWN_SECONDS - $elapsed;

        return max(0, $remaining);
    }

    /**
     * Build the cache key prefix for a (purpose, email) pair.
     * The caller appends `:last_sent` or `:hourly_count` to
     * read or write the specific counter.
     *
     * Returns the same shape the legacy per-flow services used
     * for the email-hash-keyed flows (password reset, 2FA),
     * so the existing 261 tests' cache pokes keep working
     * without modification.
     *
     * The email verification flow is NOT routed through this
     * method — the legacy keys are keyed by user id
     * (`email_verification:last_sent:{userId}`), not by
     * email hash. The email verification adapter uses
     * {@see canResendForUser()} / {@see bumpResendCounterForUser()}
     * / {@see retryAfterSecondsForUser()} for that flow.
     */
    public function resendThrottleKey(string $email, string $purpose): string
    {
        $email = mb_strtolower(trim($email));
        $emailHash = hash('sha256', $email);

        if ($purpose === EmailVerificationToken::PURPOSE_PASSWORD_RESET) {
            return 'password-reset:throttle:'.$emailHash;
        }

        // 2FA flows and any future purpose use a generic
        // email-hash key. No legacy tests touch these.
        return 'bearer-token:throttle:'.$purpose.':'.$emailHash;
    }

    /**
     * Stamp the throttle counters after a successful send.
     * Public so adapters can call it without re-implementing
     * the increment dance. Uses the key prefix the caller
     * built (see {@see resendThrottleKey()}).
     */
    public function bumpResendCounter(string $keyPrefix): void
    {
        $now = Carbon::now();

        Cache::put($keyPrefix.':last_sent', $now, $now->copy()->addHour());
        Cache::add($keyPrefix.':hourly_count', 0, $now->copy()->addHour());
        Cache::increment($keyPrefix.':hourly_count');
    }
}
