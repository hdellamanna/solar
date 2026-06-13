<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ConfirmTwoFactorEnableRequest;
use App\Models\EmailVerificationToken;
use App\Services\Auth\InvalidTwoFactorTokenException;
use App\Services\Auth\TwoFactorEnrollmentService;
use App\Services\Auth\TwoFactorService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * 2FA enrollment (Auth Phase 3).
 *
 *  - `beginEnable()` (POST, auth): sends the email with the
 *    confirmation link.
 *  - `confirmEnable()` (GET, no auth): validates the token in
 *    the URL, mints a fresh TOTP secret, stashes the encrypted
 *    secret on the token row's `meta` column, and returns a
 *    Vue page with `{secret, qrUri, token}` props so the user
 *    can scan.
 *  - `confirmEnableStore()` (POST, no auth): takes the
 *    `{token, code}`, reads the encrypted secret from the
 *    token's `meta`, verifies the TOTP code against it,
 *    persists the encrypted secret + 10 recovery codes, and
 *    redirects to the dashboard with success.
 *
 * The GET/POST split is the design's way of letting the user
 * scan a QR on their phone and type the resulting code in
 * their browser. The `meta` column is the handoff — without it
 * the GET's secret and the POST's verify would mismatch.
 */
class TwoFactorEnableController extends Controller
{
    public function __construct(
        private TwoFactorEnrollmentService $enrollment,
        private TwoFactorService $twoFactor,
    ) {}

    public function beginEnable(Request $request): RedirectResponse
    {
        $user = $request->user();
        if ($user === null) {
            return redirect()->route('login');
        }

        if ($user->hasTwoFactorEnabled()) {
            return redirect()
                ->route('dashboard')
                ->with('error', 'A verificacao em duas etapas ja esta ativa.');
        }

        $this->enrollment->beginEnable($user, $request->ip(), $request->userAgent());

        return back()->with('success', 'Enviamos um link de confirmacao para o seu email.');
    }

    public function confirmEnable(Request $request, string $token): Response|RedirectResponse
    {
        $tokenModel = $this->lookupToken($token);
        if ($tokenModel === null) {
            return redirect()
                ->route('login')
                ->with('error', 'Link de confirmacao invalido ou expirado.');
        }

        $user = $tokenModel->user;
        if ($user === null) {
            return redirect()
                ->route('login')
                ->with('error', 'Usuario associado ao token nao existe mais.');
        }

        // Reuse the previously-stashed secret if the user
        // refreshed the page; otherwise mint a fresh one and
        // persist it on the token row. The encryption is via
        // `Crypt::encryptString` so the secret is at rest in
        // its app-key-sealed form.
        $plainSecret = $this->twoFactor->generateSecret();
        $encryptedSecret = $this->twoFactor->encryptSecret($plainSecret);
        $tokenModel->meta = ($tokenModel->meta ?? []) + [
            'pending_secret_encrypted' => $encryptedSecret,
        ];
        $tokenModel->save();

        $qrUri = $this->twoFactor->provisioningUri($encryptedSecret, $user->email);

        return Inertia::render('Auth/TwoFactorEnableConfirm', [
            'token' => $token,
            'secret' => $plainSecret,
            'qrUri' => $qrUri,
        ]);
    }

    public function confirmEnableStore(ConfirmTwoFactorEnableRequest $request): RedirectResponse
    {
        try {
            $this->enrollment->confirmEnable(
                $request->string('token')->toString(),
                $request->string('code')->toString(),
                $this->twoFactor,
            );
        } catch (InvalidTwoFactorTokenException $e) {
            return redirect()
                ->route('login')
                ->with('error', $e->getMessage());
        }

        return redirect()
            ->route('dashboard')
            ->with('success', '2FA ativado com sucesso.');
    }

    /**
     * Validity probe for the GET form. Returns the live model so
     * the controller can pull the user's email and stash the
     * pending secret without an extra query, or null when the
     * token is missing / consumed / expired.
     */
    private function lookupToken(string $rawToken): ?EmailVerificationToken
    {
        $hash = EmailVerificationToken::hashToken($rawToken);

        /** @var EmailVerificationToken|null $token */
        $token = EmailVerificationToken::query()
            ->forPurpose(EmailVerificationToken::PURPOSE_TWO_FACTOR_ENROLL)
            ->where('token_hash', $hash)
            ->first();

        if ($token === null || $token->consumed_at !== null) {
            return null;
        }

        if (! $token->expires_at || $token->expires_at->isPast()) {
            return null;
        }

        return $token;
    }
}
