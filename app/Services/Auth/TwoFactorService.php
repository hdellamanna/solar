<?php

namespace App\Services\Auth;

use Illuminate\Support\Facades\Crypt;
use PragmaRX\Google2FA\Google2FA;

/**
 * TOTP core (Auth Phase 3).
 *
 * Thin wrapper around `pragmarx/google2fa-laravel` so the
 * (a) encryption-at-rest story and (b) the ±1 window / replay-
 * protection logic live in one place. The controller / service
 * layer never talks to the library directly.
 *
 * Encrypted-at-rest note: we use `Crypt::encryptString` (reversible,
 * keyed with the app key) so the live challenge can re-derive the
 * secret to verify codes. SHA-256 is one-way and would prevent
 * verification. The `last_counter` field on the model stops a
 * captured OTP from being replayed.
 */
class TwoFactorService
{
    /** Length (in chars) of the base32 TOTP secret. */
    public const SECRET_LENGTH = 32;

    /** TOTP time step in seconds (RFC 6238 default). */
    public const PERIOD = 30;

    /** Replay window — accept the current step and the previous one. */
    public const WINDOW = 1;

    public function __construct(private Google2FA $google2fa) {}

    /**
     * Generate a fresh, cryptographically random base32 TOTP
     * secret. Returned as plain text so the caller can hand it
     * to the QR-code view; storage happens later via
     * {@see encryptSecret()}.
     */
    public function generateSecret(): string
    {
        return $this->google2fa->generateSecretKey(self::SECRET_LENGTH);
    }

    /**
     * Current 6-digit OTP for the given encrypted secret. Useful
     * for tests and the "show me what to type right now" hint
     * on the challenge page (debug builds only).
     */
    public function currentOtp(string $encryptedSecret): string
    {
        return $this->google2fa->getCurrentOtp($this->decryptSecret($encryptedSecret));
    }

    /**
     * Verify a 6-digit code against the encrypted secret.
     *
     * Pass the user's `last_counter` in via `$lastCounter` — the
     * library will refuse any OTP that does not belong to a
     * strictly newer 30s step, so the caller does not have to do
     * its own replay check.
     *
     * On success, `$newCounter` is set to the time step the
     * user just verified; the caller persists it on the model.
     *
     * @return bool true on a fresh, unused, valid code
     */
    public function verifyCode(
        string $encryptedSecret,
        string $code,
        ?int &$newCounter = null,
        int $lastCounter = 0,
    ): bool {
        $secret = $this->decryptSecret($encryptedSecret);

        $result = $this->google2fa->verifyKeyNewer(
            $secret,
            $code,
            $lastCounter,
            self::WINDOW,
        );

        if ($result === false || $result === 0) {
            $newCounter = null;

            return false;
        }

        $newCounter = (int) $result;

        return true;
    }

    /**
     * Build the `otpauth://totp/...` URL an authenticator app
     * expects when scanning a QR code.
     */
    public function provisioningUri(string $encryptedSecret, string $email, string $issuer = 'Solar Money'): string
    {
        return $this->google2fa->getQRCodeUrl(
            $issuer,
            $email,
            $this->decryptSecret($encryptedSecret),
        );
    }

    public function encryptSecret(string $plain): string
    {
        return Crypt::encryptString($plain);
    }

    public function decryptSecret(string $encrypted): string
    {
        return Crypt::decryptString($encrypted);
    }
}
