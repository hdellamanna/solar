<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Services\Auth\EmailVerificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Handles user authentication.
 *
 * If the user authenticates successfully but their email is not yet
 * verified, we send a fresh verification email and bounce them to
 * the notice page. Verified users go straight to the dashboard.
 */
class LoginController extends Controller
{
    public function __construct(private EmailVerificationService $service) {}

    /**
     * Show the login form.
     */
    public function create(): Response
    {
        return Inertia::render('Auth/Login');
    }

    /**
     * Attempt to authenticate the user.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $credentials = $request->only('email', 'password');
        $remember = (bool) $request->boolean('remember');

        if (! Auth::attempt($credentials, $remember)) {
            return back()
                ->withErrors(['email' => 'Credenciais inválidas.'])
                ->onlyInput('email');
        }

        $request->session()->regenerate();

        $user = $request->user();

        if ($user !== null && ! $user->hasVerifiedEmail()) {
            $this->service->sendVerificationEmail(
                $user,
                $request->ip(),
                $request->userAgent(),
            );

            return redirect()
                ->route('verification.notice')
                ->with('error', 'Verifique seu email para acessar sua conta.');
        }

        return redirect()->intended(route('dashboard'));
    }
}
