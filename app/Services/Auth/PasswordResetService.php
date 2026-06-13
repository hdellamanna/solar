<?php

namespace App\Services\Auth;

use App\Mail\PasswordResetMail;
use App\Models\EmailVerificationToken;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Str;
use Illuminate\Support\Facades\URL;

/**
 * Orchestrates the password-reset flow (FASE 4D / Auth Phase 2).
 *
 * Mirrors {@see EmailVerificationService} in shape but keeps a
 * separate state machine so the two flows can evolve independently.
 * Cross-cutting helpers (hashing, throttling) are duplicated as
 * private methods so neither service has a hard dependency on the
 * other.
 *
 * Responsibilities:
 *  - Mint and persist a single-use password-reset token (the raw
 *    value is embedded in the email; only the SHA-256 hash lives in
 *    the database).
 *  - Enforce the 30-second cooldown / 5-per-hour cap on the
 *    application cache (the `password-reset:*` namespace).
 *  - Resolve a raw token to its user, mark it consumed, and rotate
 *    the password (invalidating remember-me cookies along the way).
 *  - Silently no-op when the requested email does not exist so the
 *    UI cannot be used to enumerate accounts.
 */
class PasswordResetService
{
    /** Token lifetime in minutes (matches the email-verification flow). */
    public const TTL_MINUTES = 60;

    /** Minimum seconds between two consecutive reset emails to the same address. */
    public const RESEND_COOLDOWN_SECONDS = 30;

    /** Maximum number of reset emails allowed within the rolling one-hour window. */
    public const RESEND_HOURLY_CAP = 5;

    /**
     * Issue a fresh token, persist it, and email the reset link.
     *
     * Always returns `true` so the controller can return the same
     * success flash regardless of whether the email matched a user —
     * the alternative (a different message for unknown emails) would
     * leak the existence of accounts.
     */
    public function requestReset(string $email, ?string $ip = null, ?string $ua = null): bool
    {
        $email = mb_strtolower(trim($email));

        if (! $this->canRequestReset($email)) {
            return true;
        }

        $user = User::where('email', $email)->first();

        // No user — silently return success. We still don't bump the
        // counter (there is no cache key for an unknown email), so a
        // subsequent legitimate request from the same address is not
        // penalised.
        if ($user === null) {
            return true;
        }

        ['token' => $raw] = EmailVerificationToken::generateForUser(
            $user,
            null,
            $ip,
            $ua,
            EmailVerificationToken::PURPOSE_PASSWORD_RESET,
        );

        $url = URL::temporarySignedRoute(
            'password.reset',
            now()->addMinutes(self::TTL_MINUTES),
            ['token' => $raw],
        );

        Mail::to($user->email)->send(new PasswordResetMail($user, $url));

        $this->bumpThrottle($email);

        return true;
    }

    /**
     * Resolve a raw token into the user it belongs to, mark the token
     * consumed, and rotate the password. Existing "remember me"
     * cookies become invalid because the remember-token column is
     * regenerated.
     *
     * @throws InvalidResetTokenException
     */
    public function resetPassword(string $rawToken, string $newPassword): User
    {
        $hash = EmailVerificationToken::hashToken($rawToken);

        /** @var EmailVerificationToken|null $token */
        $token = EmailVerificationToken::query()
            ->forPurpose(EmailVerificationToken::PURPOSE_PASSWORD_RESET)
            ->where('token_hash', $hash)
            ->first();

        if ($token === null) {
            throw new InvalidResetTokenException('Token de redefinição não encontrado.');
        }

        if ($token->consumed_at !== null) {
            throw new InvalidResetTokenException('Este link já foi utilizado.');
        }

        if (! $token->expires_at || $token->expires_at->isPast()) {
            throw new InvalidResetTokenException('Este link expirou. Solicite um novo.');
        }

        $user = $token->user;
        if ($user === null) {
            throw new InvalidResetTokenException('Usuário associado ao token não existe mais.');
        }

        // Mark consumed first, then mutate the user. If two requests
        // race, the second one will fail at the consumed_at check
        // above (after re-fetching) — we accept the small overlap.
        $token->consumed_at = now();
        $token->save();

        $user->password = Hash::make($newPassword);
        // Rotating the remember token invalidates any "remember me"
        // cookie an attacker might have captured before the reset.
        $user->setRememberToken(Str::random(60));
        $user->save();

        // Invalidate every other active reset token for this user —
        // a successful reset should kill the entire family of pending
        // links so a leaked inbox link can't be used later.
        EmailVerificationToken::query()
            ->forPurpose(EmailVerificationToken::PURPOSE_PASSWORD_RESET)
            ->where('user_id', $user->id)
            ->whereNull('consumed_at')
            ->where('id', '!=', $token->id)
            ->update(['consumed_at' => now()]);

        return $user;
    }

    /**
     * Returns true when the address is allowed to receive another
     * reset email right now. Enforces a 30-second cooldown between
     * consecutive sends and a hard cap of 5 sends per rolling hour.
     * The check is keyed by the email address (not the user id) so
     * unknown addresses are also throttled — that prevents a probe
     * from driving load on the mailer.
     */
    public function canRequestReset(string $email): bool
    {
        $email = mb_strtolower(trim($email));
        $key = $this->throttleKey($email);

        $lastSentAt = Cache::get($key.':last_sent');
        if ($lastSentAt !== null) {
            $elapsed = abs((int) $lastSentAt->diffInSeconds(now()));
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
     * Stamp the throttle keys after a successful send. Mirrors
     * {@see EmailVerificationService::bumpResendCounter()} but keyed
     * by email address instead of user id.
     */
    private function bumpThrottle(string $email): void
    {
        $key = $this->throttleKey($email);

        Cache::put($key.':last_sent', now(), now()->addHour());
        Cache::add($key.':hourly_count', 0, now()->addHour());
        Cache::increment($key.':hourly_count');
    }

    private function throttleKey(string $email): string
    {
        return 'password-reset:throttle:'.hash('sha256', mb_strtolower(trim($email)));
    }
}
