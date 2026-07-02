<?php

namespace App\Http\Middleware;

use App\Models\AppMeta;
use Closure;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * First-boot setup wizard gate.
 *
 * Scope: only redirects when setup is incomplete AND the route would
 * otherwise require authentication or run a controller action. Public
 * health/static/info endpoints always pass through (so the operator can
 * always curl /up, hit /manifest.json, etc).
 *
 * Decision tree:
 *   1. If `app_meta.setup_completed_at` is set → no-op (return $next).
 *   2. If setup routes themselves (`setup.show/store/skip`) or `/up` →
 *      pass through so the wizard can render.
 *   3. If request is to a path under public prefix (`/about`,
 *      `/tutorial`, `/login`, `/register`, `/forgot-password`,
 *      `/reset-password/*`, `/email/verify/*`, `/two-factor/*`,
 *      `/assets/*`, `/build/*`, `/storage/*`, `/manifest.json`,
 *      `/sw.js`) → pass through (these are public landing / auth /
 *      asset paths; the operator needs to be able to hit them before
 *      setup is done).
 *   4. Otherwise → redirect to /setup.
 *
 * DB safety: if the `app_meta` table doesn't exist yet (fresh install
 * with no migrations), the middleware no-ops. After migrations run, the
 * wizard kicks in automatically.
 *
 * Re-triggering: DELETE FROM app_meta WHERE key = 'setup_completed_at'
 * forces the wizard to re-appear.
 */
class RequireSetup
{
    /**
     * Routes that always pass through (named routes).
     */
    private const PASS_THROUGH_NAMED = [
        'setup.show',
        'setup.store',
        'setup.skip',
        'health',
    ];

    /**
     * Path prefixes that always pass through (the operator needs
     * access to the auth UI, public landing pages, and static assets
     * before the wizard is complete).
     */
    private const PASS_THROUGH_PREFIXES = [
        'login',
        'register',
        'forgot-password',
        'reset-password',
        'email/verify',
        'two-factor',
        'up',
        'assets/',
        'build/',
        'storage/',
        'manifest.json',
        'sw.js',
        'pwa/',
        'about',
        'tutorial',
        'fonts/',
        'favicon.ico',
        'robots.txt',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        // Cheap short-circuit: if setup is already complete, no-op.
        if ($this->isSetupComplete()) {
            return $next($request);
        }

        // Allow setup routes themselves + health check.
        $routeName = $request->route()?->getName();
        if ($routeName !== null && in_array($routeName, self::PASS_THROUGH_NAMED, true)) {
            return $next($request);
        }

        // Allow public path prefixes.
        $path = ltrim($request->path(), '/');
        foreach (self::PASS_THROUGH_PREFIXES as $prefix) {
            if ($path === $prefix || str_starts_with($path, $prefix)) {
                return $next($request);
            }
        }

        // Otherwise, redirect to the wizard.
        return redirect()->route('setup.show');
    }

    /**
     * Returns true when app_meta.setup_completed_at is set to a non-null
     * value. Returns false on any DB error (table missing, connection lost,
     * migration not yet run).
     */
    private function isSetupComplete(): bool
    {
        try {
            return AppMeta::get('setup_completed_at') !== null;
        } catch (QueryException|Throwable $e) {
            return false;
        }
    }
}