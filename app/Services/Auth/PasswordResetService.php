<?php

namespace App\Services\Auth;

use App\Mail\PasswordResetMail;
use App\Models\EmailVerificationToken;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\URL;

/**
 * Orchestrates the password-reset flow (FASE 4D / Auth Phase 2).
 *
 * Thin adapter over {@see BearerTokenService} (FASE Polish / v0.10.0).
 * The legacy implementation used to own the mint / consume / throttle
 * dance end-to-end; that logic now lives in the bearer service. The
 * adapter is responsible only for:
 *
 *  - choosing the right `purpose` value;
 *  - building the signed reset URL via `URL::temporarySignedRoute`;
 *  - handing the email to the mailer;
 *  - silently no-op'ing when the requested email does not exist
 *    (anti-enumeration);
 *  - hashing the new password, rotating the remember-token, and
 *    invalidating sibling reset tokens on successful consume;
 *  - surfacing pt-BR error messages that match the existing
 *    controllers' flash text.
 *
 * Throttle keys are `password-reset:throttle:{emailHash}:{last_sent,
 * hourly_count}` (the shape the existing 261 tests poke directly).
 *
 * Public surface is identical to the pre-refactor implementation:
 *
 *  - `requestReset(string $email, ?ip, ?ua): bool`
 *  - `resetPassword(string $raw, string $new): User`
 *  - `canRequestReset(string $email): bool`
 */
class PasswordResetService
{
    /** Token lifetime in minutes (matches the email-verification flow). */
    public const TTL_MINUTES = 60;

    /** Minimum seconds between two consecutive reset emails to the same address. */
    public const RESEND_COOLDOWN_SECONDS = BearerTokenService::RESEND_COOLDOWN_SECONDS;

    /** Maximum number of reset emails allowed within the rolling one-hour window. */
    public const RESEND_HOURLY_CAP = BearerTokenService::RESEND_HOURLY_CAP;

    public function __construct(private BearerTokenService $tokens) {}

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

        $minted = $this->tokens->mint(
            $user,
            EmailVerificationToken::PURPOSE_PASSWORD_RESET,
            null,
            $ip,
            $ua,
        );

        $url = URL::temporarySignedRoute(
            'password.reset',
            $minted['row']->expires_at,
            ['token' => $minted['raw']],
        );

        // FASE 7 — i18n: pin the Mailable to the recipient's locale.
        Mail::to($user->email)
            ->locale($user->locale ?? config('app.locale'))
            ->send(new PasswordResetMail($user, $url));

        $this->tokens->bumpResendCounter(
            $this->tokens->resendThrottleKey($email, EmailVerificationToken::PURPOSE_PASSWORD_RESET),
        );

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
        try {
            $user = $this->tokens->consume(
                $rawToken,
                EmailVerificationToken::PURPOSE_PASSWORD_RESET,
                function (EmailVerificationToken $token, User $user) use ($newPassword): User {
                    $user->password = Hash::make($newPassword);
                    // Rotating the remember token invalidates any
                    // "remember me" cookie an attacker might have
                    // captured before the reset.
                    $user->setRememberToken(Str::random(60));
                    $user->save();

                    // Invalidate every other active reset token for
                    // this user — a successful reset should kill the
                    // entire family of pending links so a leaked
                    // inbox link can't be used later.
                    EmailVerificationToken::query()
                        ->forPurpose(EmailVerificationToken::PURPOSE_PASSWORD_RESET)
                        ->where('user_id', $user->id)
                        ->whereNull('consumed_at')
                        ->where('id', '!=', $token->id)
                        ->update(['consumed_at' => now()]);

                    return $user;
                },
            );
        } catch (InvalidTokenException $e) {
            throw new InvalidResetTokenException($this->translate($e));
        }

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
        return $this->tokens->canResend(
            $email,
            EmailVerificationToken::PURPOSE_PASSWORD_RESET,
        );
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
            'Token not found.' => 'Token de redefinição não encontrado.',
            'Token already consumed.' => 'Este link já foi utilizado.',
            'Token expired.' => 'Este link expirou. Solicite um novo.',
            default => 'Este link não pode ser utilizado. Solicite um novo.',
        };
    }
}
