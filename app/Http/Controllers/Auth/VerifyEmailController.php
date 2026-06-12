<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\EmailVerificationToken;
use App\Services\Auth\EmailVerificationService;
use App\Services\Auth\InvalidVerificationTokenException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Handles the two halves of the email verification flow:
 *  - `notice()` renders the Vue page that says "check your inbox".
 *  - `verify($token)` consumes a token presented via the email link
 *    and either confirms the user (redirecting to the dashboard) or
 *    bounces them back to the notice with an error flash.
 */
class VerifyEmailController extends Controller
{
    public function __construct(private EmailVerificationService $service) {}

    /**
     * GET /email/verify — the "check your inbox" landing page.
     */
    public function notice(Request $request): Response|RedirectResponse
    {
        $user = $request->user();

        if ($user === null) {
            return redirect()->route('login');
        }

        if ($user->hasVerifiedEmail()) {
            return redirect()
                ->route('dashboard')
                ->with('success', 'Seu email já está confirmado.');
        }

        return Inertia::render('Auth/VerifyEmailNotice', [
            'email' => $user->email,
        ]);
    }

    /**
     * GET /email/verify/{token} — clicked from the verification email.
     *
     * The route is registered as a `temporarySignedRoute`, so Laravel
     * already validates the signature and expiry of the URL itself
     * before this method runs.
     */
    public function verify(Request $request, string $token): RedirectResponse
    {
        $user = $request->user();

        // If somehow the user is not logged in (e.g. opened the link
        // in a different browser/incognito), we still try to verify
        // the token; the user model returned by the service carries
        // the verified flag.
        try {
            $verified = $this->service->verify($token);
        } catch (InvalidVerificationTokenException $e) {
            return redirect()
                ->route('verification.notice')
                ->with('error', $e->getMessage());
        }

        // Log the user in if they were not already (verified link
        // opened in a new browser tab).
        if ($user === null) {
            Auth::login($verified);
            $request->session()->regenerate();
        } elseif ($user->id !== $verified->id) {
            // Mismatched session: log the verified user in instead.
            Auth::login($verified);
            $request->session()->regenerate();
        }

        // Clean up any un-consumed tokens for this user that are now
        // stale — keeps the table tidy for users who verify on a
        // click from a re-sent email.
        EmailVerificationToken::query()
            ->where('user_id', $verified->id)
            ->whereNull('consumed_at')
            ->update(['consumed_at' => now()]);

        return redirect()
            ->route('dashboard')
            ->with('success', 'Email confirmado! Bem-vindo.');
    }
}
