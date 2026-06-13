<?php

namespace App\Services\Auth;

use App\Models\TrustedDevice;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * Trusted-device cookie orchestration (Auth Phase 3).
 *
 * Cookie name: `solar_trusted`. Value: `selector:validator` (both
 * base64url-encoded). The selector is the public lookup key; the
 * validator is the secret proof of possession. We store only the
 * SHA-256 of the validator on the server, so a database leak does
 * not let an attacker forge a working cookie.
 *
 * Cookie attributes: httpOnly (always), Secure (when the request
 * arrived over HTTPS), SameSite=Lax, 90 days. The `Secure` flag is
 * tied to `$request->isSecure()` so local HTTP dev still works.
 */
class TrustedDeviceService
{
    /** Public lookup-key length (bytes). */
    public const SELECTOR_BYTES = 32;

    /** Secret proof-of-possession length (bytes). */
    public const VALIDATOR_BYTES = 64;

    /** Cookie name. */
    public const COOKIE_NAME = 'solar_trusted';

    /** Cookie lifetime in days. */
    public const LIFETIME_DAYS = 90;

    /**
     * Issue a fresh trusted-device row AND set the cookie on the
     * outgoing response. Called from the challenge controller once
     * the user passes TOTP or a recovery code and has the
     * "trust this device" checkbox checked.
     */
    public function issue(User $user, Request $request, ?string $friendlyName = null): TrustedDevice
    {
        $selector = $this->randomBase64Url(self::SELECTOR_BYTES);
        $validator = $this->randomBase64Url(self::VALIDATOR_BYTES);

        $now = now();

        $device = TrustedDevice::create([
            'user_id' => $user->id,
            'selector' => $selector,
            'validator_hash' => hash('sha256', $validator),
            'friendly_name' => $friendlyName !== null ? mb_substr($friendlyName, 0, 100) : null,
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent() !== null ? mb_substr($request->userAgent(), 0, 500) : null,
            'last_seen_at' => $now,
            'expires_at' => $now->copy()->addDays(self::LIFETIME_DAYS),
        ]);

        $this->queueCookie($request, $selector.':'.$validator);

        return $device;
    }

    /**
     * Revoke all trusted devices for the user and clear the cookie
     * on the outgoing response. Returns the number of rows
     * deleted. Safe to call when the user has zero rows (no-op).
     */
    public function revokeAll(User $user): int
    {
        $count = (int) TrustedDevice::where('user_id', $user->id)->delete();

        cookie()->queue(cookie()->forget(self::COOKIE_NAME, '/', null));

        return $count;
    }

    /**
     * Revoke a single trusted device. Used from the settings page
     * (per-row "Revoke" button). Returns true on delete, false
     * when the row did not belong to this user (defense against
     * a guessed id).
     */
    public function revokeOne(int $id, User $user): bool
    {
        $deleted = TrustedDevice::where('id', $id)
            ->where('user_id', $user->id)
            ->delete();

        return $deleted > 0;
    }

    /**
     * Verify the cookie in the incoming request belongs to the
     * user and is not expired. On success, bumps `last_seen_at`
     * on the matching row. The cookie expiry itself is the
     * 90-day rolling window from `expires_at`, so we do not
     * re-stamp the cookie on every request — the database row
     * holds the truth.
     */
    public function verify(Request $request, User $user): bool
    {
        $cookieValue = $request->cookie(self::COOKIE_NAME);
        if (! is_string($cookieValue) || ! str_contains($cookieValue, ':')) {
            return false;
        }

        [$selector, $validator] = explode(':', $cookieValue, 2);
        if ($selector === '' || $validator === '') {
            return false;
        }

        /** @var TrustedDevice|null $device */
        $device = TrustedDevice::where('user_id', $user->id)
            ->where('selector', $selector)
            ->first();

        if ($device === null) {
            return false;
        }

        if (! $device->verify($validator)) {
            return false;
        }

        $device->touchSeen();

        return true;
    }

    /**
     * Delete every expired row. Intended for the daily scheduler;
     * not invoked inline (would just slow down the next login).
     */
    public function cleanup(): int
    {
        return TrustedDevice::where('expires_at', '<', now())->delete();
    }

    /**
     * Build a base64url-encoded random string. `Str::random`
     * returns [A-Za-z0-9] which is not enough entropy for the
     * validator — use `random_bytes` + base64url so we get the
     * full byte range.
     */
    private function randomBase64Url(int $bytes): string
    {
        return rtrim(strtr(base64_encode(random_bytes($bytes)), '+/', '-_'), '=');
    }

    /**
     * Queue a `Set-Cookie` on the response, picked up by Laravel
     * on the way out. The EncryptCookies middleware seals the
     * value before it hits the browser.
     */
    private function queueCookie(Request $request, string $value): void
    {
        cookie()->queue(
            cookie()->make(
                self::COOKIE_NAME,
                $value,
                self::LIFETIME_DAYS * 24 * 60,
                '/',
                null,
                $request->isSecure(),
                true,                                  // httpOnly
                false,                                 // raw
                'lax',
            ),
        );
    }
}
