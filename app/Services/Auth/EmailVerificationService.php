<?php

namespace App\Services\Auth;

use App\Mail\VerifyEmailMail;
use App\Models\EmailVerificationToken;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

/**
 * Orchestrates the email-verification flow for new and re-issuing users.
 *
 * Thin adapter over {@see BearerTokenService} (FASE Polish / v0.10.0).
 * The legacy implementation used to own the mint / consume / throttle
 * dance end-to-end; that logic now lives in the bearer service. The
 * adapter is responsible only for:
 *
 *  - choosing the right `purpose` value;
 *  - building the signed verification URL via
 *    `URL::temporarySignedRoute`;
 *  - handing the email to the mailer;
 *  - bumping the throttle counters after a successful send;
 *  - stamping the user's `email_verified_at` on successful consume;
 *  - surfacing pt-BR error messages that match the existing
 *    controllers' flash text.
 *
 * The throttle keys for the email verification flow are keyed by
 * user id (not by email hash) — see
 * {@see BearerTokenService::canResendForUser()} and
 * {@see BearerTokenService::bumpResendCounterForUser()}. The
 * existing 261 feature tests poke the exact
 * `email_verification:last_sent:{userId}` / `:hourly_count:{userId}`
 * keys to assert the throttle, so the shape is preserved.
 *
 * Public surface is identical to the pre-refactor implementation:
 *
 *  - `sendVerificationEmail(User, ?ip, ?ua): void`
 *  - `verify(string $raw): User`
 *  - `canResend(User): bool`
 *  - `retryAfterSeconds(User): int`
 */
class EmailVerificationService
{
    /** Minimum seconds between two consecutive sends to the same user. */
    public const RESEND_COOLDOWN_SECONDS = BearerTokenService::RESEND_COOLDOWN_SECONDS;

    /** Maximum number of sends allowed within the rolling one-hour window. */
    public const RESEND_HOURLY_CAP = BearerTokenService::RESEND_HOURLY_CAP;

    public function __construct(private BearerTokenService $tokens) {}

    /**
     * Issue a fresh token, persist it, and email the verification link.
     */
    public function sendVerificationEmail(User $user, ?string $ip = null, ?string $ua = null): void
    {
        $minted = $this->tokens->mint(
            $user,
            EmailVerificationToken::PURPOSE_EMAIL_VERIFICATION,
            null,
            $ip,
            $ua,
        );

        $url = URL::temporarySignedRoute(
            'verification.verify',
            $minted['row']->expires_at,
            ['token' => $minted['raw']],
        );

        Mail::to($user->email)->send(new VerifyEmailMail($user, $url));

        $this->tokens->bumpResendCounterForUser($user);
    }

    /**
     * Resolve a raw token into the user it belongs to. The token is
     * marked consumed and the user's `email_verified_at` is stamped.
     *
     * @throws InvalidVerificationTokenException
     */
    public function verify(string $rawToken): User
    {
        try {
            return $this->tokens->consume(
                $rawToken,
                EmailVerificationToken::PURPOSE_EMAIL_VERIFICATION,
                function (EmailVerificationToken $token, User $user): User {
                    if ($user->email_verified_at === null) {
                        $user->email_verified_at = now();
                        $user->save();
                    }

                    return $user;
                },
            );
        } catch (InvalidTokenException $e) {
            throw new InvalidVerificationTokenException($this->translate($e));
        }
    }

    /**
     * Returns true when the user is allowed to receive another
     * verification email right now. Delegates to
     * {@see BearerTokenService::canResendForUser()}.
     */
    public function canResend(User $user): bool
    {
        return $this->tokens->canResendForUser($user);
    }

    /**
     * Seconds the user still has to wait before the next resend is
     * allowed. Returns 0 when resend is allowed right now.
     */
    public function retryAfterSeconds(User $user): int
    {
        return $this->tokens->retryAfterSecondsForUser($user);
    }

    /**
     * Translate the generic English {@see InvalidTokenException}
     * message into the pt-BR string the existing controllers
     * surface to the user. The shape is identical to the
     * pre-refactor implementation so the controller error flashes
     * keep working without modification.
     */
    private function translate(InvalidTokenException $e): string
    {
        return match ($e->getMessage()) {
            'Token not found.' => 'Token de verificação não encontrado.',
            'Token already consumed.' => 'Este link já foi utilizado.',
            'Token expired.' => 'Este link expirou. Solicite um novo.',
            default => 'Este link não pode ser utilizado. Solicite um novo.',
        };
    }
}
