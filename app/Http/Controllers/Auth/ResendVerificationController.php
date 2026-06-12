<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ResendVerificationRequest;
use App\Services\Auth\EmailVerificationService;
use Illuminate\Http\RedirectResponse;

/**
 * Endpoint behind `POST /email/verify/resend`.
 *
 * Relies on the `auth` + (route-side) `verified` exemptions defined
 * in `routes/web.php`. The actual rate limiting lives in
 * {@see EmailVerificationService::canResend()}.
 */
class ResendVerificationController extends Controller
{
    public function __construct(private EmailVerificationService $service) {}

    public function store(ResendVerificationRequest $request): RedirectResponse
    {
        $user = $request->user();

        if ($user === null) {
            // Should be impossible (the FormRequest authorizes only
            // authenticated users), but keep the guard for paranoia.
            return redirect()->route('login');
        }

        if ($user->hasVerifiedEmail()) {
            return redirect()
                ->route('dashboard')
                ->with('success', 'Seu email já está confirmado.');
        }

        if (! $this->service->canResend($user)) {
            $wait = $this->service->retryAfterSeconds($user);
            $msg = $wait > 0
                ? "Aguarde {$wait}s antes de reenviar."
                : 'Limite de reenvios por hora atingido. Tente novamente mais tarde.';

            return back()->with('error', $msg);
        }

        $this->service->sendVerificationEmail(
            $user,
            $request->ip(),
            $request->userAgent(),
        );

        return back()->with('success', 'Email reenviado! Confira sua caixa de entrada.');
    }
}
