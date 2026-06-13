<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\PasswordResetLinkRequest;
use App\Services\Auth\PasswordResetService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Handles the "forgot password" flow entry points (FASE 4D / Auth
 * Phase 2).
 *
 *  - `create()` renders the email-entry page.
 *  - `store()` accepts the email, hands it to
 *    {@see PasswordResetService::requestReset()}, and always bounces
 *    back with the SAME success flash — the alternative would let an
 *    attacker enumerate registered email addresses by comparing
 *    responses.
 */
class PasswordResetLinkController extends Controller
{
    public function __construct(private PasswordResetService $service) {}

    /**
     * GET /forgot-password — show the email-entry form.
     */
    public function create(): Response
    {
        return Inertia::render('Auth/ForgottenPassword');
    }

    /**
     * POST /forgot-password — accept the email and trigger (or
     * silently no-op) the reset email. The flash message is the same
     * regardless of whether the address matches a real user.
     */
    public function store(PasswordResetLinkRequest $request): RedirectResponse
    {
        $this->service->requestReset(
            $request->string('email')->toString(),
            $request->ip(),
            $request->userAgent(),
        );

        return back()->with(
            'success',
            'Se o email existir em nossa base, enviaremos um link de redefinição em alguns minutos.',
        );
    }
}
