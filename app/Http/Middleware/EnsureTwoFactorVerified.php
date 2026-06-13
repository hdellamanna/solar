<?php

namespace App\Http\Middleware;

use App\Services\Auth\TrustedDeviceService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gate for routes that require a verified 2FA challenge.
 *
 * Aliased as `two_factor` in `bootstrap/app.php`. Sits in the
 * route group between `auth` and `verified`:
 *
 *   ['auth', 'verified', 'two_factor']
 *
 * Behaviour:
 *  - User not authenticated: pass through (upstream `auth`
 *    middleware will redirect).
 *  - 2FA not enabled: pass through (no-op).
 *  - 2FA enabled + already session-verified: pass through.
 *  - 2FA enabled + trusted-device cookie matches: stamp the
 *    session flag, pass through.
 *  - Otherwise: redirect to `two-factor.challenge` with a flash.
 *
 * The verification flow itself and the logout endpoint are
 * exempt — a user mid-challenge must still be able to reach the
 * challenge / enable / disable pages and to log out.
 */
class EnsureTwoFactorVerified
{
    /**
     * Routes (by name) that bypass the 2FA check even though
     * they live inside the `two_factor` middleware group.
     *
     * @var list<string>
     */
    private const BYPASS_ROUTE_NAMES = [
        'two-factor.challenge',
        'two-factor.verify',
        'two-factor.enable.begin',
        'two-factor.enable.confirm',
        'two-factor.disable.begin',
        'two-factor.disable.confirm',
        'trusted-devices.destroy',
        'trusted-devices.destroy-all',
        'logout',
    ];

    public function __construct(private TrustedDeviceService $trustedDevices) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // Not authenticated: let upstream middleware do its job.
        if ($user === null) {
            return $next($request);
        }

        $route = $request->route();
        if ($route !== null && in_array($route->getName(), self::BYPASS_ROUTE_NAMES, true)) {
            return $next($request);
        }

        if (! $user->hasTwoFactorEnabled()) {
            return $next($request);
        }

        if ($user->isTwoFactorVerified()) {
            return $next($request);
        }

        // Try a trusted-device cookie before bouncing — this is
        // the "skip 2FA on this device for 90 days" UX.
        if ($this->trustedDevices->verify($request, $user)) {
            $user->markTwoFactorVerified();

            return $next($request);
        }

        return redirect()
            ->route('two-factor.challenge')
            ->with('error', 'Confirme o código 2FA para continuar.');
    }
}
