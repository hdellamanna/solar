<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\User;
use App\Services\Auth\EmailVerificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Handles new user registration.
 *
 * As of FASE 4D / Auth Phase 1 the new user is logged in immediately
 * (so they can see the verification notice) but is *not* allowed to
 * reach the dashboard until they confirm the email address. The
 * `verified` middleware handles that gate; this controller just makes
 * sure the verification email is sent on the way in.
 */
class RegisterController extends Controller
{
    public function __construct(private EmailVerificationService $service) {}

    /**
     * Show the registration form.
     */
    public function create(): Response
    {
        return Inertia::render('Auth/Register');
    }

    /**
     * Persist a new user, send the verification email, and bounce them
     * to the verification notice.
     */
    public function store(RegisterRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
            'theme' => 'system',
        ]);

        $this->service->sendVerificationEmail(
            $user,
            $request->ip(),
            $request->userAgent(),
        );

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('verification.notice');
    }
}
