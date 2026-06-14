<?php

namespace App\Services\Auth;

use App\Mail\TwoFactorDisableMail;
use App\Mail\TwoFactorEnableMail;
use App\Models\EmailVerificationToken;
use App\Models\RecoveryCode;
use App\Models\TrustedDevice;
use App\Models\User;
use App\Models\UserTwoFactor;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

/**
 * Email-confirmed half of the 2FA flow (Auth Phase 3).
 *
 * Thin adapter over {@see BearerTokenService} (FASE Polish / v0.10.0).
 * The live challenge (TOTP code or recovery code at login) is
 * handled by the challenge controller; this service owns the
 * enable / disable flows, both of which are gated by a 60-min
 * one-time email link (same pattern as the email-verification and
 * password-reset flows). The token rows live in the same
 * `email_verification_tokens` table, discriminated by
 * `purpose = 'two_factor_enroll'` / `'two_factor_disable'`.
 *
 * Reuses the existing {@see EmailVerificationToken::generateForUser()},
 * {@see EmailVerificationToken::hashToken()} and
 * {@see EmailVerificationToken::scopeForPurpose()} helpers — does
 * NOT roll its own hash/lookup logic.
 *
 * The `confirmEnable` and `confirmDisable` methods own the
 * post-consume action (encrypting the TOTP secret, minting the
 * recovery codes, wiping the recovery codes on disable, etc.).
 * Those actions run inside the handler closure passed to
 * {@see BearerTokenService::consume()}, so they execute AFTER
 * the token has been marked consumed — same race-window trade-off
 * the pre-refactor implementation had.
 */
class TwoFactorEnrollmentService
{
    /** Token lifetime in minutes (matches the other two email flows). */
    public const TOKEN_TTL_MINUTES = 60;

    /** Number of recovery codes minted per enrollment. */
    public const RECOVERY_CODE_COUNT = 10;

    /** Length of each recovery code, in characters. */
    public const RECOVERY_CODE_LENGTH = 10;

    public function __construct(private BearerTokenService $tokens) {}

    /**
     * Stage 1 of enable: mint a `two_factor_enroll` token, email
     * the user a link to the confirm page. The link is a
     * `temporarySignedRoute` so the user can open it in a
     * different browser if they like.
     */
    public function beginEnable(User $user, ?string $ip = null, ?string $ua = null): string
    {
        $minted = $this->tokens->mint(
            $user,
            EmailVerificationToken::PURPOSE_TWO_FACTOR_ENROLL,
            null,
            $ip,
            $ua,
        );

        $url = URL::temporarySignedRoute(
            'two-factor.enable.confirm',
            $minted['row']->expires_at,
            ['token' => $minted['raw']],
        );

        Mail::to($user->email)->send(new TwoFactorEnableMail($user, $url));

        return $minted['raw'];
    }

    /**
     * Stage 2 of enable: confirm the user owns the inbox (by
     * presenting the email link) AND that they can produce a
     * valid TOTP code from the secret stashed on the token row
     * by the GET page render. On success, persists the encrypted
     * secret + 10 recovery codes.
     *
     * The GET page mints the secret (so the QR can be rendered)
     * and stashes it on the token's `meta.pending_secret_encrypted`.
     * This POST endpoint reads it back and verifies the user's
     * code against it. The secret is then the one persisted
     * on the `user_two_factor` row.
     *
     * Returns the user (with relations refreshed) for the
     * controller to redirect to the dashboard.
     *
     * @throws InvalidTwoFactorTokenException
     */
    public function confirmEnable(string $rawToken, string $totpCode, TwoFactorService $tf): User
    {
        try {
            $user = $this->tokens->consume(
                $rawToken,
                EmailVerificationToken::PURPOSE_TWO_FACTOR_ENROLL,
                function (EmailVerificationToken $token, User $user) use ($totpCode, $tf): User {
                    $meta = $token->meta ?? [];
                    $encryptedSecret = $meta['pending_secret_encrypted'] ?? null;
                    if (! is_string($encryptedSecret) || $encryptedSecret === '') {
                        // The GET page was never rendered (or the
                        // secret was wiped by a re-issue), so
                        // there is no secret to verify against.
                        throw new InvalidTwoFactorTokenException('Sessao de ativacao expirada. Solicite um novo link.');
                    }

                    $newCounter = null;
                    if (! $tf->verifyCode($encryptedSecret, $totpCode, $newCounter, 0)) {
                        throw new InvalidTwoFactorTokenException('Codigo 2FA invalido. Tente novamente.');
                    }

                    DB::transaction(function () use ($user, $encryptedSecret, $newCounter) {
                        // Replace any prior row (e.g. a partial
                        // enable that left a record) — there is a
                        // unique index on user_id, so we upsert.
                        UserTwoFactor::updateOrCreate(
                            ['user_id' => $user->id],
                            [
                                'secret_encrypted' => $encryptedSecret,
                                'last_counter' => $newCounter ?? 0,
                                'enabled_at' => now(),
                                'confirmed_at' => null,
                            ],
                        );

                        // Wipe any prior recovery codes (fresh
                        // codes for the fresh secret) and mint 10
                        // new ones.
                        RecoveryCode::where('user_id', $user->id)->delete();

                        for ($i = 0; $i < self::RECOVERY_CODE_COUNT; $i++) {
                            RecoveryCode::create([
                                'user_id' => $user->id,
                                'code_hash' => $this->hashRecoveryCode($this->generateRecoveryCode()),
                            ]);
                        }
                    });

                    return $user->fresh(['twoFactor', 'recoveryCodes']);
                },
            );
        } catch (InvalidTokenException $e) {
            throw new InvalidTwoFactorTokenException($this->translate($e));
        }

        return $user;
    }

    /**
     * Stage 1 of disable: requires the user's password as defense
     * in depth (so a stolen cookie alone cannot turn 2FA off),
     * mints a `two_factor_disable` token, and emails the link.
     *
     * Returns the raw token (the controller does not need it —
     * it only redirects — but exposing it keeps the call shape
     * symmetrical with `beginEnable`).
     */
    public function beginDisable(User $user, string $password, ?string $ip = null, ?string $ua = null): string
    {
        if (! Hash::check($password, $user->password)) {
            throw new InvalidTwoFactorTokenException('Senha incorreta.');
        }

        $minted = $this->tokens->mint(
            $user,
            EmailVerificationToken::PURPOSE_TWO_FACTOR_DISABLE,
            null,
            $ip,
            $ua,
        );

        $url = URL::temporarySignedRoute(
            'two-factor.disable.confirm',
            $minted['row']->expires_at,
            ['token' => $minted['raw']],
        );

        Mail::to($user->email)->send(new TwoFactorDisableMail($user, $url));

        return $minted['raw'];
    }

    /**
     * Stage 2 of disable: confirms the user owns the inbox AND
     * re-types the password (defense in depth), then wipes
     * the encrypted secret, the recovery codes, and every
     * trusted device. The caller is expected to log the user
     * out afterwards.
     *
     * @throws InvalidTwoFactorTokenException
     */
    public function confirmDisable(string $rawToken, string $password): User
    {
        try {
            $user = $this->tokens->consume(
                $rawToken,
                EmailVerificationToken::PURPOSE_TWO_FACTOR_DISABLE,
                function (EmailVerificationToken $token, User $user) use ($password): User {
                    if (! Hash::check($password, $user->password)) {
                        // The token has already been consumed, so
                        // the user will need to start the disable
                        // flow over. This is a deliberate
                        // trade-off — we do not want a wrong
                        // password to leave a window for brute
                        // force.
                        throw new InvalidTwoFactorTokenException('Senha incorreta.');
                    }

                    DB::transaction(function () use ($user) {
                        UserTwoFactor::where('user_id', $user->id)->delete();
                        RecoveryCode::where('user_id', $user->id)->delete();
                        TrustedDevice::where('user_id', $user->id)->delete();
                    });

                    return $user->fresh();
                },
            );
        } catch (InvalidTokenException $e) {
            throw new InvalidTwoFactorTokenException($this->translate($e));
        }

        return $user;
    }

    /**
     * SHA-256 hash a recovery code. The plain code is never
     * persisted; we only store the hex digest. Same pattern as
     * {@see EmailVerificationToken::hashToken()}.
     */
    public function hashRecoveryCode(string $plain): string
    {
        return hash('sha256', $plain);
    }

    /**
     * Generate a random recovery code in the
     * `AAAA-AAAA-AA` shape (4 chars, dash, 4 chars, dash,
     * 2 chars — total 10 alphanumeric). Uppercase + digits,
     * unambiguous character set (no 0/O/1/I).
     */
    private function generateRecoveryCode(): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $bytes = random_bytes(self::RECOVERY_CODE_LENGTH);
        $out = '';
        for ($i = 0; $i < self::RECOVERY_CODE_LENGTH; $i++) {
            $out .= $alphabet[ord($bytes[$i]) % strlen($alphabet)];
        }

        return substr($out, 0, 4).'-'.substr($out, 4, 4).'-'.substr($out, 8, 2);
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
            'Token not found.' => 'Link inválido ou expirado.',
            'Token already consumed.' => 'Este link já foi utilizado.',
            'Token expired.' => 'Este link expirou. Solicite um novo.',
            default => 'Este link não pode ser utilizado. Solicite um novo.',
        };
    }
}
