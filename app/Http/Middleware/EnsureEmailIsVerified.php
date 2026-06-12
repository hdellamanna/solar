<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gate for routes that require a verified email address.
 *
 * Aliased as `verified` (Laravel's conventional name) in
 * `bootstrap/app.php`. When a logged-in user has not yet confirmed
 * their address, we redirect them to the verification notice with an
 * error flash.
 *
 * The verification flow itself and the logout endpoint are exempt —
 * an unverified user must still be able to verify, resend, or leave.
 */
class EnsureEmailIsVerified
{
    /**
     * Routes (by name) that bypass the verification check even though
     * they live inside the `auth` middleware group.
     *
     * @var list<string>
     */
    private const BYPASS_ROUTE_NAMES = [
        'verification.notice',
        'verification.verify',
        'verification.resend',
        'logout',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // Not authenticated: let the upstream `auth` middleware do its
        // job (redirect to login). This middleware is only meaningful
        // for authenticated users.
        if ($user === null) {
            return $next($request);
        }

        $route = $request->route();
        if ($route !== null && in_array($route->getName(), self::BYPASS_ROUTE_NAMES, true)) {
            return $next($request);
        }

        if (! method_exists($user, 'hasVerifiedEmail') || ! $user->hasVerifiedEmail()) {
            return redirect()
                ->route('verification.notice')
                ->with('error', 'Confirme seu email para acessar essa área.');
        }

        return $next($request);
    }
}
