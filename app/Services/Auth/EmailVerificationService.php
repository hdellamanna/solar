<?php

namespace App\Services\Auth;

use App\Mail\VerifyEmailMail;
use App\Models\EmailVerificationToken;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

/**
 * Orchestrates the email-verification flow for new and re-issuing users.
 *
 * Responsibilities:
 *  - Mint and persist single-use tokens (the raw value is embedded in
 *    the email; only the SHA-256 hash lives in the database).
 *  - Validate a raw token presented via the verification link and
 *    mark the user's `email_verified_at` on success.
 *  - Enforce resend throttling (30s cooldown, 5/hour cap) using the
 *    application cache as the rate-limit store.
 */
class EmailVerificationService
{
    /** Minimum seconds between two consecutive sends to the same user. */
    public const RESEND_COOLDOWN_SECONDS = 30;

    /** Maximum number of sends allowed within the rolling one-hour window. */
    public const RESEND_HOURLY_CAP = 5;

    /**
     * Issue a fresh token, persist it, and email the verification link.
     */
    public function sendVerificationEmail(User $user, ?string $ip = null, ?string $ua = null): void
    {
        ['token' => $raw] = EmailVerificationToken::generateForUser($user, null, $ip, $ua);

        $url = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(EmailVerificationToken::TTL_MINUTES),
            ['token' => $raw],
        );

        Mail::to($user->email)->send(new VerifyEmailMail($user, $url));

        $this->bumpResendCounter($user);
    }

    /**
     * Resolve a raw token into the user it belongs to. The token is
     * marked consumed and the user's `email_verified_at` is stamped.
     *
     * @throws InvalidVerificationTokenException
     */
    public function verify(string $rawToken): User
    {
        $hash = EmailVerificationToken::hashToken($rawToken);

        /** @var EmailVerificationToken|null $token */
        $token = EmailVerificationToken::where('token_hash', $hash)->first();

        if ($token === null) {
            throw new InvalidVerificationTokenException('Token de verificação não encontrado.');
        }

        if ($token->consumed_at !== null) {
            throw new InvalidVerificationTokenException('Este link já foi utilizado.');
        }

        if (! $token->expires_at || $token->expires_at->isPast()) {
            throw new InvalidVerificationTokenException('Este link expirou. Solicite um novo.');
        }

        $user = $token->user;
        if ($user === null) {
            throw new InvalidVerificationTokenException('Usuário associado ao token não existe mais.');
        }

        // Mark consumed first, then verify the user. If two requests race,
        // the second one will fail at the consumed_at check above (after
        // re-fetching) — we accept the small window of overlap.
        $token->consumed_at = now();
        $token->save();

        if ($user->email_verified_at === null) {
            $user->email_verified_at = now();
            $user->save();
        }

        return $user;
    }

    /**
     * Returns true when the user is allowed to receive another verification
     * email right now. Enforces a 30-second cooldown between consecutive
     * sends and a hard cap of 5 sends per rolling hour.
     */
    public function canResend(User $user): bool
    {
        $lastSentAt = Cache::get($this->lastSentKey($user));
        if ($lastSentAt !== null) {
            $elapsed = abs((int) $lastSentAt->diffInSeconds(now()));
            if ($elapsed < self::RESEND_COOLDOWN_SECONDS) {
                return false;
            }
        }

        $windowCount = (int) Cache::get($this->hourlyKey($user), 0);
        if ($windowCount >= self::RESEND_HOURLY_CAP) {
            return false;
        }

        return true;
    }

    /**
     * Seconds the user still has to wait before the next resend is
     * allowed. Returns 0 when resend is allowed right now.
     */
    public function retryAfterSeconds(User $user): int
    {
        $lastSentAt = Cache::get($this->lastSentKey($user));
        if ($lastSentAt === null) {
            return 0;
        }
        $elapsed = abs((int) $lastSentAt->diffInSeconds(now()));
        $remaining = self::RESEND_COOLDOWN_SECONDS - $elapsed;

        return max(0, $remaining);
    }

    /**
     * Increment the rolling hourly counter and stamp the "last sent"
     * marker. Called from {@see sendVerificationEmail()} after the
     * email is actually handed to the mailer.
     */
    private function bumpResendCounter(User $user): void
    {
        $now = now();

        Cache::put($this->lastSentKey($user), $now, now()->addHour());
        Cache::add($this->hourlyKey($user), 0, now()->addHour());
        Cache::increment($this->hourlyKey($user));
    }

    private function lastSentKey(User $user): string
    {
        return "email_verification:last_sent:{$user->id}";
    }

    private function hourlyKey(User $user): string
    {
        return "email_verification:hourly_count:{$user->id}";
    }
}
