<?php

namespace App\Http\Middleware;

use Carbon\Carbon;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * FASE 7 — i18n tri-língue. Resolves the active locale for the
 * current request and applies it to:
 *   - the application (via `app()->setLocale()`)
 *   - Carbon (via `Carbon::setLocale()`), so `diffForHumans()` and
 *     other formatting helpers return strings in the active locale
 *
 * ## Resolution order
 *
 *  1. **Authenticated user.** `auth()->user()->locale` is read
 *     first because it persists across devices, login state, and
 *     cookies — a user who set their language once should not
 *     have to set it again from a new browser.
 *  2. **`X-App-Locale` request header.** Set by the front-end on
 *     locale change (immediately after the user picks a new
 *     language, the next Inertia visit carries this header) so the
 *     very first response after a switch is rendered in the new
 *     language.
 *  3. **`app_locale` cookie.** Set by the front-end on the user's
 *     first visit (before the auth round-trip completes) and read
 *     back on every subsequent visit. This is how guest users keep
 *     their preference across sessions.
 *  4. **`config('app.locale')`.** The default (`pt-BR`).
 *
 * Whichever value wins is validated against
 * `config('app.available_locales')` (a whitelist of `pt-BR`, `es`,
 * `en`). Anything outside the set is silently coerced to the
 * default. This prevents a malicious or stale cookie/header from
 * loading an unknown translation namespace.
 *
 * ## Cookie lifetime
 *
 * The companion `Settings/LocaleController` writes the `app_locale`
 * cookie with a 1-year lifetime. The cookie is intentionally
 * `httpOnly: false` so the front-end can read its current value
 * (via `document.cookie`) and use it as a fallback when the user
 * is not yet authenticated — a guest who picked Spanish should see
 * Spanish on the next visit before any server round-trip.
 *
 * ## Registration
 *
 * Prepended to the `web` middleware group in
 * {@see \bootstrap/app.php}. Runs BEFORE `StartSession` and
 * `HandleInertiaRequests` so the Inertia shared props carry the
 * correct `app.locale` value on the very first render.
 */
class SetLocale
{
    public const COOKIE_NAME = 'app_locale';

    public const HEADER_NAME = 'X-App-Locale';

    public function handle(Request $request, Closure $next): Response
    {
        $resolved = $this->resolve($request);

        // Validate against the whitelist; fall back to the
        // configured default if the candidate is unknown.
        $available = (array) config('app.available_locales', ['pt-BR', 'es', 'en']);
        $default = (string) config('app.locale', 'pt-BR');

        if (! in_array($resolved, $available, true)) {
            $resolved = $default;
        }

        app()->setLocale($resolved);
        Carbon::setLocale($resolved);

        return $next($request);
    }

    /**
     * Pick a candidate locale from the request in the order
     * documented in the class docblock.
     *
     * This middleware runs in the `web` group, BEFORE the
     * `auth` middleware. `$request->user()` is therefore `null`
     * for the lifetime of this middleware — Laravel resolves
     * the user later in the pipeline. To still honour the
     * "user's preference wins" contract, we fall back to reading
     * the session's `login_web_<guard>_<hash>` key (the standard
     * Laravel web auth key shape) and looking the user up
     * directly via `User::find()`. This costs 1 query on a
     * request where the user is actually logged in; the query
     * is index-keyed and Laravel memoizes the user for the rest
     * of the request, so the cost is negligible.
     */
    private function resolve(Request $request): ?string
    {
        // 1. Authenticated user (via the standard auth guard, if
        // it has already resolved the user — true on every
        // request after the auth middleware runs, false for the
        // first middleware in the chain).
        $user = $request->user();
        if ($user !== null && ! empty($user->locale)) {
            return (string) $user->locale;
        }

        // 1b. Session-based user lookup. The session is started
        // earlier in the `web` group, so the auth `login_web_*`
        // key is already available. We do a single `User::find`
        // here to honour the user's locale preference.
        $sessionUserId = $this->sessionUserId($request);
        if ($sessionUserId !== null) {
            $userLocale = \App\Models\User::query()
                ->whereKey($sessionUserId)
                ->value('locale');
            if (is_string($userLocale) && $userLocale !== '') {
                return $userLocale;
            }
        }

        // 2. X-App-Locale header (set by the front-end on locale
        // change so the very next Inertia visit picks up the
        // new locale before the cookie round-trip).
        $header = $request->headers->get(self::HEADER_NAME);
        if (is_string($header) && $header !== '') {
            return $header;
        }

        // 3. app_locale cookie (set by the front-end on the
        // first visit and by the Settings/LocaleController
        // after a successful save).
        $cookie = $request->cookie(self::COOKIE_NAME);
        if (is_string($cookie) && $cookie !== '') {
            return $cookie;
        }

        // 4. Fall through to `config('app.locale')` (set in
        // the middleware's main `handle()` body).
        return null;
    }

    /**
     * Read the `login_web_<guard>_<hash>` key from the session
     * and return the user id, or null if the key is missing /
     * unparseable. The key is set by Laravel's
     * `SessionGuard` on login and is the standard way to check
     * "is the user logged in" from middleware that runs before
     * the auth guard itself.
     */
    private function sessionUserId(Request $request): ?int
    {
        $session = $request->hasSession() ? $request->session() : null;
        if ($session === null) {
            return null;
        }
        $guard = (string) config('auth.defaults.guard', 'web');
        $hash = sha1($guard);
        $key = "login_web_{$guard}_{$hash}";
        $id = $session->get($key);
        return is_numeric($id) ? (int) $id : null;
    }
}
