<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\BeginTwoFactorDisableRequest;
use App\Http\Requests\Auth\ConfirmTwoFactorDisableRequest;
use App\Models\EmailVerificationToken;
use App\Services\Auth\InvalidTwoFactorTokenException;
use App\Services\Auth\TwoFactorEnrollmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

/**
 * 2FA disable (Auth Phase 3).
 *
 *  - `beginDisable()` (POST, auth): re-prompts the password,
 *    sends the email with the disable link.
 *  - `confirmDisable()` (GET, no auth): validates the token in
 *    the URL, returns a Vue page asking the user to re-type
 *    the password.
 *  - `confirmDisableStore()` (POST, no auth): takes
 *    `{token, password}`, wipes the encrypted secret + recovery
 *    codes + trusted devices, logs the user out, redirects to
 *    `/login` with success flash.
 */
class TwoFactorDisableController extends Controller
{
    public function __construct(private TwoFactorEnrollmentService $enrollment) {}

    public function beginDisable(BeginTwoFactorDisableRequest $request): RedirectResponse
    {
        $user = $request->user();
        if ($user === null) {
            return redirect()->route('login');
        }

        if (! $user->hasTwoFactorEnabled()) {
            return redirect()
                ->route('dashboard')
                ->with('error', 'A verificacao em duas etapas ja esta desativada.');
        }

        try {
            $this->enrollment->beginDisable(
                $user,
                $request->string('password')->toString(),
                $request->ip(),
                $request->userAgent(),
            );
        } catch (InvalidTwoFactorTokenException $e) {
            return back()
                ->withErrors(['password' => $e->getMessage()])
                ->withInput();
        }

        return back()->with('success', 'Enviamos um link de confirmacao para o seu email.');
    }

    public function confirmDisable(Request $request, string $token): Response|RedirectResponse
    {
        if (! $this->tokenLooksValid($token)) {
            return redirect()
                ->route('login')
                ->with('error', 'Link de confirmacao invalido ou expirado.');
        }

        return Inertia::render('Auth/TwoFactorDisableConfirm', [
            'token' => $token,
        ]);
    }

    public function confirmDisableStore(
        ConfirmTwoFactorDisableRequest $request,
    ): RedirectResponse {
        try {
            $user = $this->enrollment->confirmDisable(
                $request->string('token')->toString(),
                $request->string('password')->toString(),
            );
        } catch (InvalidTwoFactorTokenException $e) {
            return redirect()
                ->route('login')
                ->with('error', $e->getMessage());
        }

        // Per the design: "wipes ... user logged out, redirect to
        // /login". We also clear the session-side verified flag
        // and forget the trusted-device cookie on the outgoing
        // response.
        $user->clearTwoFactorVerified();
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()
            ->route('login')
            ->with('success', '2FA desativado. Faca login novamente.');
    }

    private function tokenLooksValid(string $rawToken): bool
    {
        $hash = EmailVerificationToken::hashToken($rawToken);

        /** @var EmailVerificationToken|null $token */
        $token = EmailVerificationToken::query()
            ->forPurpose(EmailVerificationToken::PURPOSE_TWO_FACTOR_DISABLE)
            ->where('token_hash', $hash)
            ->first();

        if ($token === null || $token->consumed_at !== null) {
            return false;
        }

        if (! $token->expires_at || $token->expires_at->isPast()) {
            return false;
        }

        return true;
    }
}
