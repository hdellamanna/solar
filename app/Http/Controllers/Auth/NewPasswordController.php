<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\NewPasswordRequest;
use App\Models\EmailVerificationToken;
use App\Services\Auth\InvalidResetTokenException;
use App\Services\Auth\PasswordResetService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Handles the second half of the password-reset flow (FASE 4D /
 * Auth Phase 2).
 *
 *  - `create()` shows the "set a new password" form, but only when
 *    the token in the URL is still valid — bad / expired / used
 *    tokens bounce the user back to the forgot-password page with
 *    an error flash.
 *  - `store()` consumes the token, rotates the password, and logs
 *    the user in automatically so they do not have to retype the
 *    new credentials on the very next screen.
 */
class NewPasswordController extends Controller
{
    public function __construct(private PasswordResetService $service) {}

    /**
     * GET /reset-password/{token} — show the new-password form if
     * the token is still valid, otherwise redirect to
     * forgot-password with an error flash.
     */
    public function create(Request $request, string $token): Response|RedirectResponse
    {
        if (! $this->tokenLooksValid($token)) {
            return redirect()
                ->route('password.request')
                ->with('error', 'Link inválido ou expirado. Solicite um novo.');
        }

        return Inertia::render('Auth/NewPassword', [
            'token' => $token,
            'email' => $request->string('email')->toString() ?: null,
        ]);
    }

    /**
     * POST /reset-password — consume the token, rotate the password,
     * and log the user in.
     */
    public function store(NewPasswordRequest $request): RedirectResponse
    {
        try {
            $user = $this->service->resetPassword(
                $request->string('token')->toString(),
                $request->string('password')->toString(),
            );
        } catch (InvalidResetTokenException $e) {
            return redirect()
                ->route('password.request')
                ->with('error', $e->getMessage());
        }

        // Auto-login: the user just proved they control the inbox
        // (they used the link) so we do not force them through the
        // login form again with the brand-new password.
        Auth::login($user);
        $request->session()->regenerate();

        return redirect()
            ->route('dashboard')
            ->with('success', 'Senha redefinida com sucesso.');
    }

    /**
     * Cheap validity probe for the GET form. Mirrors the real check
     * in the service so the user does not get a form that is about
     * to fail. We never reveal WHICH condition failed.
     */
    private function tokenLooksValid(string $rawToken): bool
    {
        $hash = EmailVerificationToken::hashToken($rawToken);

        /** @var EmailVerificationToken|null $token */
        $token = EmailVerificationToken::query()
            ->forPurpose(EmailVerificationToken::PURPOSE_PASSWORD_RESET)
            ->where('token_hash', $hash)
            ->first();

        if ($token === null) {
            return false;
        }

        if ($token->consumed_at !== null) {
            return false;
        }

        if (! $token->expires_at || $token->expires_at->isPast()) {
            return false;
        }

        return true;
    }
}
